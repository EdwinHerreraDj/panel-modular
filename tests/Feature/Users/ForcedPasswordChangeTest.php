<?php

declare(strict_types=1);

use App\Enums\AuthEvent;
use App\Models\User;
use Database\Factories\UserFactory;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('retiene en el cambio de contraseña a quien lo tiene pendiente', function (): void {
    $user = User::factory()->editor()->mustChangePassword()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('password.change'));

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertRedirect(route('password.change'));
});

it('deja ver la pantalla de cambio y cerrar sesión durante el bloqueo', function (): void {
    $user = User::factory()->editor()->mustChangePassword()->create();

    $this->actingAs($user)
        ->get(route('password.change'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Auth/ChangePassword')
            ->where('forced', true));

    $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));
});

it('libera el acceso al cambiar la contraseña', function (): void {
    $user = User::factory()->editor()->mustChangePassword()->create();

    $this->actingAs($user)
        ->put(route('password.change.update'), [
            'current_password' => UserFactory::PASSWORD,
            'password' => 'Contrasena.Elegida.2026#',
            'password_confirmation' => 'Contrasena.Elegida.2026#',
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->must_change_password)->toBeFalse()
        ->and(Hash::check('Contrasena.Elegida.2026#', $user->password))->toBeTrue()
        ->and($user->password_changed_at)->not->toBeNull();

    $this->assertDatabaseHas('auth_logs', [
        'user_id' => $user->id,
        'event' => AuthEvent::PasswordChanged->value,
    ]);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

it('rechaza el cambio si la contraseña actual no es correcta', function (): void {
    $user = User::factory()->editor()->mustChangePassword()->create();

    $this->actingAs($user)
        ->from(route('password.change'))
        ->put(route('password.change.update'), [
            'current_password' => 'no-es-la-mia',
            'password' => 'Contrasena.Elegida.2026#',
            'password_confirmation' => 'Contrasena.Elegida.2026#',
        ])
        ->assertSessionHasErrors(['current_password' => 'La contraseña actual no es correcta.']);

    expect($user->fresh()->must_change_password)->toBeTrue();
});

it('no permite repetir la misma contraseña', function (): void {
    $user = User::factory()->editor()->mustChangePassword()->create();

    $this->actingAs($user)
        ->from(route('password.change'))
        ->put(route('password.change.update'), [
            'current_password' => UserFactory::PASSWORD,
            'password' => UserFactory::PASSWORD,
            'password_confirmation' => UserFactory::PASSWORD,
        ])
        ->assertSessionHasErrors(['password' => 'La contraseña nueva debe ser distinta de la actual.']);

    expect($user->fresh()->must_change_password)->toBeTrue();
});

it('aplica la política de contraseñas al cambiarla', function (): void {
    $user = User::factory()->editor()->mustChangePassword()->create();

    $this->actingAs($user)
        ->from(route('password.change'))
        ->put(route('password.change.update'), [
            'current_password' => UserFactory::PASSWORD,
            'password' => 'corta',
            'password_confirmation' => 'corta',
        ])
        ->assertSessionHasErrors('password');
});

it('cierra las demás sesiones al cambiar la contraseña', function (): void {
    $user = User::factory()->editor()->mustChangePassword()->create();

    DB::table('sessions')->insert([
        'id' => 'otra-sesion',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($user)->put(route('password.change.update'), [
        'current_password' => UserFactory::PASSWORD,
        'password' => 'Contrasena.Elegida.2026#',
        'password_confirmation' => 'Contrasena.Elegida.2026#',
    ]);

    $this->assertDatabaseMissing('sessions', ['id' => 'otra-sesion']);
});

it('no molesta a quien no tiene el cambio pendiente', function (): void {
    $user = User::factory()->editor()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    $this->actingAs($user)
        ->get(route('password.change'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('forced', false));
});

it('permite a cualquiera actualizar sus propios datos pero no su rol', function (): void {
    $user = User::factory()->author()->create();

    $this->actingAs($user)
        ->put(route('profile.update'), [
            'name' => 'Mi nombre nuevo',
            'email' => 'nuevo@ejemplo.test',
            // Se envía a propósito: el Form Request no lo contempla y se ignora.
            'role' => 'superadministrador',
            'status' => 'suspended',
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->name)->toBe('Mi nombre nuevo')
        ->and($user->email)->toBe('nuevo@ejemplo.test')
        ->and($user->roleName()?->value)->toBe('autor')
        ->and($user->status->value)->toBe('active');
});

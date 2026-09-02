<?php

declare(strict_types=1);

use App\Enums\ActivityEvent;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Database\Factories\UserFactory;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->superAdmin = User::factory()->superAdmin()->create(['name' => 'Super Admin']);
});

it('lista los usuarios al superadministrador', function (): void {
    User::factory()->count(3)->editor()->create();

    $this->actingAs($this->superAdmin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Users/Index')
            ->has('users.data', 4));
});

it('filtra los usuarios por rol', function (): void {
    User::factory()->editor()->create();
    User::factory()->author()->create();

    $this->actingAs($this->superAdmin)
        ->get(route('users.index', ['extra' => RoleName::Author->value]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('users.data', 1));
});

it('busca usuarios por nombre y correo', function (): void {
    User::factory()->editor()->create(['name' => 'Marta Ruiz', 'email' => 'marta@ejemplo.test']);
    User::factory()->author()->create(['name' => 'Pedro Gil', 'email' => 'pedro@ejemplo.test']);

    $this->actingAs($this->superAdmin)
        ->get(route('users.index', ['search' => 'marta']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Marta Ruiz'));
});

it('ignora una columna de ordenación no permitida', function (): void {
    $this->actingAs($this->superAdmin)
        ->get(route('users.index', ['sort' => 'password']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('filters.sort', 'name'));
});

it('crea un usuario con su rol y obliga a cambiar la contraseña', function (): void {
    $this->actingAs($this->superAdmin)
        ->post(route('users.store'), [
            'name' => 'Marta Ruiz',
            'email' => 'Marta@Ejemplo.test',
            'role' => RoleName::Editor->value,
            'password' => 'Contrasena.Inicial.2026#',
            'password_confirmation' => 'Contrasena.Inicial.2026#',
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'marta@ejemplo.test')->firstOrFail();

    expect($user->must_change_password)->toBeTrue()
        ->and($user->status)->toBe(UserStatus::Active)
        ->and($user->roleName())->toBe(RoleName::Editor);

    $this->assertDatabaseHas('activity_logs', [
        'subject_id' => $user->id,
        'event' => ActivityEvent::Created->value,
        'user_id' => $this->superAdmin->id,
    ]);
});

it('exige los campos obligatorios en español al crear un usuario', function (): void {
    $this->actingAs($this->superAdmin)
        ->from(route('users.create'))
        ->post(route('users.store'), [])
        ->assertSessionHasErrors([
            'name' => 'El campo nombre es obligatorio.',
            'email' => 'Introduce tu correo electrónico.',
            'role' => 'Selecciona un rol.',
        ]);
});

it('impide repetir el correo electrónico', function (): void {
    User::factory()->editor()->create(['email' => 'marta@ejemplo.test']);

    $this->actingAs($this->superAdmin)
        ->from(route('users.create'))
        ->post(route('users.store'), [
            'name' => 'Otra Marta',
            'email' => 'marta@ejemplo.test',
            'role' => RoleName::Author->value,
            'password' => 'Contrasena.Inicial.2026#',
            'password_confirmation' => 'Contrasena.Inicial.2026#',
        ])
        ->assertSessionHasErrors(['email' => 'Ya existe una cuenta con ese correo electrónico.']);
});

it('aplica la política de contraseñas al crear un usuario', function (): void {
    $this->actingAs($this->superAdmin)
        ->from(route('users.create'))
        ->post(route('users.store'), [
            'name' => 'Marta Ruiz',
            'email' => 'marta@ejemplo.test',
            'role' => RoleName::Author->value,
            'password' => 'corta',
            'password_confirmation' => 'corta',
        ])
        ->assertSessionHasErrors('password');

    expect(session('errors')->first('password'))->toContain('contraseña');
});

it('actualiza los datos y el rol de un usuario', function (): void {
    $user = User::factory()->author()->create();

    $this->actingAs($this->superAdmin)
        ->put(route('users.update', $user), [
            'name' => 'Nombre nuevo',
            'email' => $user->email,
            'role' => RoleName::Editor->value,
            'status' => UserStatus::Active->value,
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->name)->toBe('Nombre nuevo')
        ->and($user->roleName())->toBe(RoleName::Editor);
});

it('suspende a un usuario y le impide entrar', function (): void {
    $user = User::factory()->editor()->create(['email' => 'marta@ejemplo.test']);

    $this->actingAs($this->superAdmin)
        ->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'role' => RoleName::Editor->value,
            'status' => UserStatus::Suspended->value,
        ])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->status)->toBe(UserStatus::Suspended);

    auth()->logout();

    $this->post(route('login'), [
        'email' => 'marta@ejemplo.test',
        'password' => UserFactory::PASSWORD,
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('fija una contraseña nueva, cierra las sesiones y obliga a cambiarla', function (): void {
    $user = User::factory()->editor()->create();

    DB::table('sessions')->insert([
        'id' => 'sesion-abierta',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($this->superAdmin)
        ->put(route('users.password', $user), [
            'password' => 'Contrasena.Nueva.2026#',
            'password_confirmation' => 'Contrasena.Nueva.2026#',
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect(Hash::check('Contrasena.Nueva.2026#', $user->password))->toBeTrue()
        ->and($user->must_change_password)->toBeTrue();

    $this->assertDatabaseMissing('sessions', ['id' => 'sesion-abierta']);
});

it('envía un usuario a la papelera y lo restaura', function (): void {
    $user = User::factory()->editor()->create();

    $this->actingAs($this->superAdmin)
        ->delete(route('users.destroy', $user))
        ->assertRedirect(route('users.index'));

    expect($user->fresh()->trashed())->toBeTrue();

    $this->actingAs($this->superAdmin)
        ->post(route('users.restore', $user))
        ->assertRedirect(route('users.index'));

    expect($user->fresh()->trashed())->toBeFalse();
});

<?php

declare(strict_types=1);

use App\Enums\AuthEvent;
use App\Models\AuthLog;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    RateLimiter::clear('login:'.sha1('ana@ejemplo.test|127.0.0.1'));
});

it('muestra el formulario de acceso a los invitados', function (): void {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Auth/Login')
            ->where('canResetPassword', true));
});

it('redirige al panel a los usuarios ya autenticados', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('login'))
        ->assertRedirect('/panel');
});

it('permite iniciar sesión con credenciales correctas', function (): void {
    $user = User::factory()->create(['email' => 'ana@ejemplo.test']);

    $this->post(route('login'), [
        'email' => 'ana@ejemplo.test',
        'password' => UserFactory::PASSWORD,
    ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($user);

    expect($user->fresh()->last_login_at)->not->toBeNull();

    $this->assertDatabaseHas('auth_logs', [
        'user_id' => $user->id,
        'event' => AuthEvent::Login->value,
    ]);
});

it('acepta el correo con mayúsculas y espacios sobrantes', function (): void {
    $user = User::factory()->create(['email' => 'ana@ejemplo.test']);

    $this->post(route('login'), [
        'email' => '  ANA@Ejemplo.Test  ',
        'password' => UserFactory::PASSWORD,
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('rechaza una contraseña incorrecta con un mensaje en español', function (): void {
    User::factory()->create(['email' => 'ana@ejemplo.test']);

    $this->from(route('login'))
        ->post(route('login'), [
            'email' => 'ana@ejemplo.test',
            'password' => 'contrasena-incorrecta',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors([
            'email' => 'El correo electrónico o la contraseña no son correctos.',
        ]);

    $this->assertGuest();

    $this->assertDatabaseHas('auth_logs', [
        'email' => 'ana@ejemplo.test',
        'event' => AuthEvent::LoginFailed->value,
    ]);
});

it('devuelve el mismo mensaje cuando el correo no existe', function (): void {
    $this->from(route('login'))
        ->post(route('login'), [
            'email' => 'nadie@ejemplo.test',
            'password' => 'cualquier-cosa',
        ])
        ->assertSessionHasErrors([
            'email' => 'El correo electrónico o la contraseña no son correctos.',
        ]);

    $this->assertGuest();
});

it('exige el correo y la contraseña con mensajes en español', function (): void {
    $this->from(route('login'))
        ->post(route('login'), [])
        ->assertSessionHasErrors([
            'email' => 'Introduce tu correo electrónico.',
            'password' => 'Introduce tu contraseña.',
        ]);
});

it('valida el formato del correo en español', function (): void {
    $this->from(route('login'))
        ->post(route('login'), [
            'email' => 'esto-no-es-un-correo',
            'password' => 'lo-que-sea',
        ])
        ->assertSessionHasErrors([
            'email' => 'Introduce un correo electrónico válido.',
        ]);
});

it('impide el acceso a una cuenta suspendida', function (): void {
    User::factory()->suspended()->create(['email' => 'ana@ejemplo.test']);

    $this->from(route('login'))
        ->post(route('login'), [
            'email' => 'ana@ejemplo.test',
            'password' => UserFactory::PASSWORD,
        ])
        ->assertSessionHasErrors('email');

    $this->assertGuest();

    expect(session('errors')->first('email'))->toContain('suspendida');

    $this->assertDatabaseHas('auth_logs', [
        'email' => 'ana@ejemplo.test',
        'event' => AuthEvent::LoginBlocked->value,
    ]);
});

it('impide el acceso a una cuenta invitada todavía sin activar', function (): void {
    User::factory()->invited()->create(['email' => 'ana@ejemplo.test']);

    $this->from(route('login'))
        ->post(route('login'), [
            'email' => 'ana@ejemplo.test',
            'password' => UserFactory::PASSWORD,
        ])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('bloquea el acceso tras cinco intentos fallidos', function (): void {
    User::factory()->create(['email' => 'ana@ejemplo.test']);

    foreach (range(1, 5) as $ignored) {
        $this->post(route('login'), [
            'email' => 'ana@ejemplo.test',
            'password' => 'contrasena-incorrecta',
        ]);
    }

    $this->from(route('login'))
        ->post(route('login'), [
            'email' => 'ana@ejemplo.test',
            'password' => UserFactory::PASSWORD,
        ])
        ->assertSessionHasErrors('email');

    $this->assertGuest();

    expect(session('errors')->first('email'))->toContain('Demasiados intentos');

    $this->assertDatabaseHas('auth_logs', [
        'email' => 'ana@ejemplo.test',
        'event' => AuthEvent::LoginBlocked->value,
    ]);
});

it('regenera el identificador de sesión al iniciar sesión', function (): void {
    User::factory()->create(['email' => 'ana@ejemplo.test']);

    $this->get(route('login'));
    $previousId = session()->getId();

    $this->post(route('login'), [
        'email' => 'ana@ejemplo.test',
        'password' => UserFactory::PASSWORD,
    ]);

    expect(session()->getId())->not->toBe($previousId);
});

it('cierra la sesión y lo deja registrado', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();

    $this->assertDatabaseHas('auth_logs', [
        'user_id' => $user->id,
        'event' => AuthEvent::Logout->value,
    ]);
});

it('no registra nunca la contraseña en el registro de auditoría', function (): void {
    User::factory()->create(['email' => 'ana@ejemplo.test']);

    $this->post(route('login'), [
        'email' => 'ana@ejemplo.test',
        'password' => 'super-secreta-1234',
    ]);

    $logs = AuthLog::all()->toJson();

    expect($logs)->not->toContain('super-secreta-1234');
});

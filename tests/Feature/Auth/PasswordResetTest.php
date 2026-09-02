<?php

declare(strict_types=1);

use App\Enums\AuthEvent;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    RateLimiter::clear('password-reset:'.sha1('ana@ejemplo.test|127.0.0.1'));
});

it('muestra el formulario de recuperación', function (): void {
    $this->get(route('password.request'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/ForgotPassword'));
});

it('envía el enlace de restablecimiento a una cuenta activa', function (): void {
    Notification::fake();

    $user = User::factory()->create(['email' => 'ana@ejemplo.test']);

    $this->post(route('password.email'), ['email' => 'ana@ejemplo.test'])
        ->assertSessionHas('status')
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($user, ResetPassword::class);

    $this->assertDatabaseHas('auth_logs', [
        'email' => 'ana@ejemplo.test',
        'event' => AuthEvent::PasswordResetRequested->value,
    ]);
});

it('responde igual aunque el correo no exista, para no revelar cuentas', function (): void {
    Notification::fake();

    $this->post(route('password.email'), ['email' => 'nadie@ejemplo.test'])
        ->assertSessionHas('status')
        ->assertSessionHasNoErrors();

    Notification::assertNothingSent();
});

it('no envía el enlace a una cuenta suspendida', function (): void {
    Notification::fake();

    $user = User::factory()->suspended()->create(['email' => 'ana@ejemplo.test']);

    $this->post(route('password.email'), ['email' => 'ana@ejemplo.test'])
        ->assertSessionHas('status');

    Notification::assertNotSentTo($user, ResetPassword::class);
});

it('limita las solicitudes de restablecimiento', function (): void {
    Notification::fake();

    User::factory()->create(['email' => 'ana@ejemplo.test']);

    foreach (range(1, 3) as $ignored) {
        $this->post(route('password.email'), ['email' => 'ana@ejemplo.test']);
    }

    $this->from(route('password.request'))
        ->post(route('password.email'), ['email' => 'ana@ejemplo.test'])
        ->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toContain('Demasiados intentos');
});

it('muestra el formulario de nueva contraseña con el token', function (): void {
    $this->get(route('password.reset', ['token' => 'token-de-prueba']).'?email=ana@ejemplo.test')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Auth/ResetPassword')
            ->where('token', 'token-de-prueba')
            ->where('email', 'ana@ejemplo.test'));
});

it('restablece la contraseña con un token válido', function (): void {
    Notification::fake();

    $user = User::factory()->create(['email' => 'ana@ejemplo.test']);

    $this->post(route('password.email'), ['email' => 'ana@ejemplo.test']);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
        $response = $this->post(route('password.store'), [
            'token' => $notification->token,
            'email' => 'ana@ejemplo.test',
            'password' => 'Contrasena.Nueva.2026#',
            'password_confirmation' => 'Contrasena.Nueva.2026#',
        ]);

        $response->assertRedirect(route('login'))->assertSessionHasNoErrors();

        expect(Hash::check('Contrasena.Nueva.2026#', $user->fresh()->password))->toBeTrue();

        $this->assertDatabaseHas('auth_logs', [
            'user_id' => $user->id,
            'event' => AuthEvent::PasswordReset->value,
        ]);

        return true;
    });
});

it('rechaza un token no válido con un mensaje en español', function (): void {
    User::factory()->create(['email' => 'ana@ejemplo.test']);

    $this->from(route('password.reset', ['token' => 'token-falso']))
        ->post(route('password.store'), [
            'token' => 'token-falso',
            'email' => 'ana@ejemplo.test',
            'password' => 'Contrasena.Nueva.2026#',
            'password_confirmation' => 'Contrasena.Nueva.2026#',
        ])
        ->assertSessionHasErrors([
            'email' => 'El enlace de restablecimiento no es válido o ha caducado. Solicita uno nuevo.',
        ]);
});

it('aplica la política de contraseñas en español', function (): void {
    $this->from(route('password.reset', ['token' => 'x']))
        ->post(route('password.store'), [
            'token' => 'x',
            'email' => 'ana@ejemplo.test',
            'password' => 'corta',
            'password_confirmation' => 'corta',
        ])
        ->assertSessionHasErrors('password');

    expect(session('errors')->first('password'))->toContain('contraseña');
});

it('exige que las dos contraseñas coincidan', function (): void {
    $this->from(route('password.reset', ['token' => 'x']))
        ->post(route('password.store'), [
            'token' => 'x',
            'email' => 'ana@ejemplo.test',
            'password' => 'Contrasena.Nueva.2026#',
            'password_confirmation' => 'Otra.Contrasena.2026#',
        ])
        ->assertSessionHasErrors([
            'password' => 'Las contraseñas no coinciden.',
        ]);
});

it('cierra las demás sesiones al restablecer la contraseña', function (): void {
    Notification::fake();

    $user = User::factory()->create(['email' => 'ana@ejemplo.test']);

    DB::table('sessions')->insert([
        'id' => 'sesion-antigua',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);

    $this->post(route('password.email'), ['email' => 'ana@ejemplo.test']);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification): bool {
        $this->post(route('password.store'), [
            'token' => $notification->token,
            'email' => 'ana@ejemplo.test',
            'password' => 'Contrasena.Nueva.2026#',
            'password_confirmation' => 'Contrasena.Nueva.2026#',
        ]);

        return true;
    });

    $this->assertDatabaseMissing('sessions', ['id' => 'sesion-antigua']);
});

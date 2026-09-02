<?php

declare(strict_types=1);

use App\Enums\AuthEvent;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('redirige al acceso a quien no ha iniciado sesión', function (): void {
    $this->get('/panel')->assertRedirect(route('login'));
});

it('redirige la raíz al panel', function (): void {
    $this->get('/')->assertRedirect('/panel');
});

it('muestra el panel a un usuario activo', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/panel')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard')
            ->where('auth.user.email', $user->email));
});

it('expulsa a un usuario suspendido durante su sesión', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/panel')->assertOk();

    $user->update(['status' => 'suspended']);

    $this->actingAs($user)
        ->get('/panel')
        ->assertRedirect(route('login'));

    $this->assertDatabaseHas('auth_logs', [
        'user_id' => $user->id,
        'event' => AuthEvent::LoginBlocked->value,
    ]);
});

it('no expone datos sensibles del usuario al navegador', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/panel')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('auth.user.password')
            ->missing('auth.user.remember_token')
            ->missing('auth.user.two_factor_secret'));
});

it('envía las cabeceras de seguridad', function (): void {
    $this->get(route('login'))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('protege el área privada con el middleware CSRF', function (): void {
    // Laravel omite la verificación CSRF mientras corren las pruebas, así que
    // se comprueba que el middleware sigue registrado en el grupo `web`.
    $middleware = app(Kernel::class)->getMiddlewareGroups()['web'];

    expect($middleware)->toContain(PreventRequestForgery::class);
});

it('convierte la sesión caducada en un aviso en español', function (): void {
    Route::middleware('web')->get('/prueba-419', function (): never {
        throw new HttpException(419, 'CSRF token mismatch.');
    });

    $this->from(route('login'))
        ->get('/prueba-419')
        ->assertRedirect(route('login'))
        ->assertSessionHas('error', 'Tu sesión ha caducado. Vuelve a intentarlo.');
});

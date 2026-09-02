<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\AuthEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordResetLinkRequest;
use App\Models\AuthLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    /**
     * Envía el enlace de restablecimiento.
     *
     * La respuesta es siempre la misma exista o no la cuenta: de lo contrario
     * el formulario permitiría averiguar qué correos están registrados.
     */
    public function store(PasswordResetLinkRequest $request): RedirectResponse
    {
        $email = (string) $request->string('email');

        $this->ensureIsNotRateLimited($request->ip(), $email);

        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if ($user !== null && $user->canAuthenticate()) {
            Password::sendResetLink(['email' => $email]);
        }

        AuthLog::record(AuthEvent::PasswordResetRequested, $request, $user, $email, [
            'delivered' => $user !== null && $user->canAuthenticate(),
        ]);

        return back()->with(
            'status',
            'Si existe una cuenta con ese correo electrónico, recibirás un enlace para restablecer tu contraseña en unos minutos.',
        );
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(?string $ip, string $email): void
    {
        $key = 'password-reset:'.sha1(Str::lower($email).'|'.$ip);

        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', [
                    'seconds' => $seconds = RateLimiter::availableIn($key),
                    'minutes' => (int) ceil($seconds / 60),
                ]),
            ]);
        }

        RateLimiter::hit($key, 300);
    }
}

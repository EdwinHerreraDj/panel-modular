<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\AuthEvent;
use App\Models\AuthLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Intento de inicio de sesión en el área privada.
 *
 * Concentra las tres comprobaciones que no deben faltar nunca: limitación de
 * intentos, verificación de credenciales y estado de la cuenta; y deja
 * constancia de cada resultado en el registro de auditoría.
 */
final class AttemptLogin
{
    /**
     * Hash de descarte usado cuando el correo no existe, para que el tiempo de
     * respuesta sea equivalente al de un correo válido y no se pueda deducir
     * qué cuentas existen.
     */
    private const DUMMY_HASH = '$2y$12$M8Kj0m0z9Xz0mM6L1V3qEeS3Fh0nH8qU5w9rM7Kf3T0bK4c2Y0hJa';

    /**
     * @throws ValidationException
     */
    public function handle(Request $request, string $email, string $password, bool $remember): User
    {
        $this->ensureIsNotRateLimited($request, $email);

        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->getAuthPassword())) {
            if ($user === null) {
                // Normaliza el tiempo de respuesta frente a la enumeración de usuarios.
                Hash::check($password, self::DUMMY_HASH);
            }

            $this->registerFailure($request, $email, $user);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        if (! $user->canAuthenticate()) {
            RateLimiter::hit($this->throttleKey($request, $email), $this->decaySeconds());

            AuthLog::record(AuthEvent::LoginBlocked, $request, $user, $email, [
                'status' => $user->status->value,
            ]);

            throw ValidationException::withMessages([
                'email' => $user->status->blockedReason() ?? __('auth.failed'),
            ]);
        }

        Auth::guard('web')->login($user, $remember);

        // Fija una sesión nueva: evita la fijación de sesión previa al acceso.
        $request->session()->regenerate();

        $user->recordSuccessfulLogin($request->ip());

        RateLimiter::clear($this->throttleKey($request, $email));

        AuthLog::record(AuthEvent::Login, $request, $user, $email, [
            'remember' => $remember,
        ]);

        return $user;
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(Request $request, string $email): void
    {
        $key = $this->throttleKey($request, $email);

        if (! RateLimiter::tooManyAttempts($key, $this->maxAttempts())) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        AuthLog::record(AuthEvent::LoginBlocked, $request, email: $email, context: [
            'reason' => 'rate_limited',
            'available_in' => $seconds,
        ]);

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]),
        ]);
    }

    private function registerFailure(Request $request, string $email, ?User $user): void
    {
        RateLimiter::hit($this->throttleKey($request, $email), $this->decaySeconds());

        AuthLog::record(AuthEvent::LoginFailed, $request, $user, $email, [
            'reason' => $user === null ? 'unknown_email' : 'invalid_password',
        ]);
    }

    /**
     * Clave de limitación por combinación de correo + IP.
     */
    private function throttleKey(Request $request, string $email): string
    {
        return 'login:'.sha1(Str::lower($email).'|'.$request->ip());
    }

    private function maxAttempts(): int
    {
        return (int) config('security.login.max_attempts', 5);
    }

    private function decaySeconds(): int
    {
        return (int) config('security.login.decay_seconds', 60);
    }
}

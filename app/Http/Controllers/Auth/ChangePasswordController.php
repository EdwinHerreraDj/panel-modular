<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\AuthEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Models\AuthLog;
use App\Support\PasswordPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cambio obligatorio de la contraseña inicial.
 */
class ChangePasswordController extends Controller
{
    public function create(Request $request): Response
    {
        $user = $request->user();

        abort_if($user === null, 403);

        return Inertia::render('Auth/ChangePassword', [
            'forced' => $user->must_change_password,
            'passwordPolicy' => PasswordPolicy::forDisplay(),
            'passwordChangedAt' => $user->password_changed_at?->toIso8601String(),
        ]);
    }

    public function update(ChangePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null, 403);

        DB::transaction(function () use ($request, $user): void {
            $user->forceFill([
                'password' => (string) $request->string('password'),
                'must_change_password' => false,
                'password_changed_at' => now(),
                'remember_token' => Str::random(60),
            ])->save();

            // Cualquier otra sesión abierta con la contraseña anterior deja de valer.
            DB::table('sessions')
                ->where('user_id', $user->getKey())
                ->where('id', '!=', $request->session()->getId())
                ->delete();

            AuthLog::record(AuthEvent::PasswordChanged, $request, $user);
        });

        // La sesión actual se mantiene con las credenciales nuevas.
        $request->session()->regenerate();

        return redirect()
            ->route('dashboard')
            ->with('success', 'Tu contraseña se ha actualizado correctamente.');
    }
}

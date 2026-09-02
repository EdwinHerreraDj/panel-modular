<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\AuthEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\NewPasswordRequest;
use App\Models\AuthLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => (string) $request->string('email'),
        ]);
    }

    public function store(NewPasswordRequest $request): RedirectResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use ($request): void {
                DB::transaction(function () use ($user, $password, $request): void {
                    $user->forceFill([
                        'password' => $password,
                        'remember_token' => Str::random(60),
                        'password_changed_at' => now(),
                    ])->save();

                    // Cierra cualquier otra sesión abierta con la contraseña anterior.
                    DB::table('sessions')->where('user_id', $user->getKey())->delete();

                    AuthLog::record(AuthEvent::PasswordReset, $request, $user);
                });

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages([
                'email' => __($status),
            ]);
        }

        return redirect()
            ->route('login')
            ->with('success', 'Tu contraseña se ha restablecido. Ya puedes iniciar sesión.');
    }
}

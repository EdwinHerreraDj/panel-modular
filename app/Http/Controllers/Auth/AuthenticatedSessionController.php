<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AttemptLogin;
use App\Enums\AuthEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\AuthLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => true,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(LoginRequest $request, AttemptLogin $attemptLogin): RedirectResponse
    {
        $attemptLogin->handle(
            $request,
            (string) $request->string('email'),
            (string) $request->string('password'),
            $request->boolean('remember'),
        );

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user !== null) {
            AuthLog::record(AuthEvent::Logout, $request, $user);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Has cerrado la sesión correctamente.');
    }
}

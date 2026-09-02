<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\AuthEvent;
use App\Models\AuthLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Expulsa de inmediato a los usuarios cuya cuenta deja de estar activa
 * mientras tienen una sesión abierta (suspensión o borrado lógico).
 */
class EnsureUserIsActive
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->canAuthenticate()) {
            AuthLog::record(AuthEvent::LoginBlocked, $request, $user, context: [
                'reason' => 'session_revoked',
                'status' => $user->status->value,
            ]);

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('error', $user->status->blockedReason());
        }

        return $next($request);
    }
}

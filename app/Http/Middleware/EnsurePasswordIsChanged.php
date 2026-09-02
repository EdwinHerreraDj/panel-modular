<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Retiene al usuario en la pantalla de cambio de contraseña hasta que sustituya
 * la contraseña inicial que le fijó el superadministrador.
 *
 * Mientras esa contraseña siga en vigor la conoce otra persona, así que la
 * cuenta no debe poder hacer nada más.
 */
class EnsurePasswordIsChanged
{
    /**
     * Rutas accesibles durante el bloqueo: la propia pantalla de cambio y la
     * salida de la sesión.
     *
     * @var list<string>
     */
    private const ALLOWED = [
        'password.change',
        'password.change.update',
        'logout',
    ];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->must_change_password) {
            return $next($request);
        }

        if ($request->routeIs(self::ALLOWED)) {
            return $next($request);
        }

        return redirect()->route('password.change');
    }
}

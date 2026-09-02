<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CORS de la API pública.
 *
 * No se usa un comodín: se responde con el origen concreto solo si está en la
 * lista. Así el navegador de un tercero no puede leer la API desde otro
 * dominio aunque conozca la URL.
 *
 * Se registra como middleware global, no dentro del grupo de la API: la
 * comprobación previa del navegador (`OPTIONS`) no coincide con ninguna ruta,
 * así que el router responde antes de llegar a los middleware de grupo. Por eso
 * el propio Laravel hace lo mismo con el suyo.
 */
class HandleApiCors
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isApiRequest($request)) {
            return $next($request);
        }

        $origin = $request->headers->get('Origin');
        $allowed = $origin !== null && $this->isAllowed($origin);

        // El navegador pregunta antes de enviar la petición real.
        if ($request->getMethod() === 'OPTIONS') {
            $response = response()->noContent(204);
        } else {
            $response = $next($request);
        }

        if ($allowed) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
            $response->headers->set(
                'Access-Control-Allow-Headers',
                config('api.header').', Authorization, Content-Type, Accept',
            );
            $response->headers->set('Access-Control-Max-Age', '86400');
        }

        // Sin esto, una caché intermedia podría servir a un dominio la
        // respuesta que se generó para otro.
        $response->headers->set('Vary', 'Origin');

        return $response;
    }

    /**
     * Solo se toca la API pública: el área privada no se sirve a otros
     * dominios bajo ningún concepto.
     */
    private function isApiRequest(Request $request): bool
    {
        return $request->is(trim((string) config('api.prefix'), '/').'/*');
    }

    /**
     * Se acepta el origen si está en la configuración general o en cualquier
     * clave activa.
     */
    private function isAllowed(string $origin): bool
    {
        $origin = rtrim($origin, '/');

        /** @var list<string> $global */
        $global = config('api.allowed_origins', []);

        if (in_array($origin, $global, true)) {
            return true;
        }

        return ApiKey::active()
            ->whereNotNull('allowed_origins')
            ->get()
            ->contains(fn (ApiKey $key): bool => in_array($origin, $key->allowed_origins ?? [], true));
    }
}

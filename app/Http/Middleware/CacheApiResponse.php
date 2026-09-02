<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiCache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guarda las respuestas de la API para no repetir el trabajo.
 *
 * Se guarda **solo el cuerpo**, nunca la respuesta entera con sus cabeceras: la
 * de CORS depende del dominio que pregunta, y servir a una web la cabecera que
 * se calculó para otra sería un agujero.
 *
 * Va después de la comprobación de la clave, no antes: así una clave revocada
 * deja de funcionar en el acto y el contador de uso sigue siendo real aunque la
 * respuesta salga de la caché.
 */
class CacheApiResponse
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! ApiCache::isEnabled() || ! $request->isMethod('GET')) {
            return $next($request);
        }

        $name = $request->route()?->getName() ?? $request->path();

        /** @var array<string, mixed> $parameters */
        $parameters = array_merge($request->query(), $request->route()?->parameters() ?? []);

        $cached = ApiCache::read($name, $parameters);

        if ($cached !== null) {
            return $this->json($cached, true);
        }

        $response = $next($request);

        // Solo se guarda lo correcto: cachear un error lo perpetuaría más allá
        // de la causa que lo provocó.
        if ($response->getStatusCode() === 200) {
            $content = $response->getContent();

            if (is_string($content) && $content !== '') {
                ApiCache::write($name, $parameters, $content);
            }
        }

        $response->headers->set('X-Cache', 'MISS');

        return $response;
    }

    private function json(string $content, bool $hit): Response
    {
        return new Response($content, 200, [
            'Content-Type' => 'application/json',
            'X-Cache' => $hit ? 'HIT' : 'MISS',
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\AuthenticateApiKey;
use App\Models\ApiKey;
use App\Models\Setting;
use App\Modules\ModuleRegistry;
use App\Modules\Values\PublicCount;
use App\Support\ApiCache;
use App\Support\ApiCatalog;
use App\Support\ApiDocumentation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Conexión con la web pública.
 *
 * Reúne en una pantalla todo lo necesario para conectar la web que enseña los
 * artículos: si la API responde, qué devuelve exactamente ahora mismo, con qué
 * claves se entra y desde qué dominios.
 */
class ApiConnectionController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Api/Index', [
            'connection' => [
                'base_url' => ApiCatalog::url(),
                'header' => config('api.header'),
                'version' => config('api.version'),
                'requires_key' => (bool) config('api.require_key'),
                'rate_limit' => (int) config('api.rate_limit'),
                'cache_seconds' => ApiCache::seconds(),
                'cache_enabled' => ApiCache::isEnabled(),
                'address_is_local' => ApiDocumentation::addressIsLocal(),
            ],
            'endpoints' => ApiCatalog::endpoints(),
            'globalOrigins' => config('api.allowed_origins', []),
            'keys' => ApiKey::query()
                ->with('creator:id,name')
                ->latest()
                ->get()
                ->map(fn (ApiKey $key): array => [
                    'id' => $key->id,
                    'name' => $key->name,
                    'prefix' => $key->prefix,
                    'allowed_origins' => $key->allowed_origins ?? [],
                    'rate_limit' => $key->rate_limit,
                    'requests_count' => $key->requests_count,
                    'last_used_at' => $key->last_used_at?->toIso8601String(),
                    'last_used_ip' => $key->last_used_ip,
                    'expires_at' => $key->expires_at?->toIso8601String(),
                    'revoked_at' => $key->revoked_at?->toIso8601String(),
                    'is_usable' => $key->isUsable(),
                    'created_by' => $key->creator?->name,
                    'created_at' => $key->created_at?->toIso8601String(),
                ])
                ->all(),
            // Se enseña una sola vez, justo después de crearla.
            'newKey' => $request->session()->get('new_api_key'),
            // Lo publicado por cada módulo activo, con las mismas claves que
            // devuelve el endpoint de estado.
            'content' => array_map(
                fn (PublicCount $count): array => [
                    'key' => $count->key,
                    'label' => $count->label,
                    'count' => $count->count,
                ],
                app(ModuleRegistry::class)->publicCounts(),
            ),
        ]);
    }

    /**
     * Vacía la caché de la API a mano.
     *
     * Se invalida sola con cada cambio, así que casi nunca hace falta. Está para
     * el caso en que algo se haya quedado atascado y no apetezca esperar: es más
     * rápido pulsar un botón que explicar por teléfono qué es una caché.
     */
    public function flushCache(): RedirectResponse
    {
        ApiCache::flush();

        return back()->with('success', 'Caché vaciada. La próxima petición volverá a leer los datos.');
    }

    /**
     * Documentación para entregársela a quien haga la web.
     *
     * Se genera al vuelo con los datos de esta instalación, así que nunca puede
     * quedar desfasada respecto a la API que describe.
     */
    public function documentation(Request $request): SymfonyResponse
    {
        $name = Str::slug(Setting::current()->site_name ?: 'blog');

        if ($request->query('formato') === 'openapi') {
            return response()
                ->json(ApiDocumentation::openapi(), 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                ->withHeaders([
                    'Content-Disposition' => 'attachment; filename="api-'.$name.'.openapi.json"',
                ]);
        }

        return response(ApiDocumentation::markdown(), 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="api-'.$name.'.md"',
        ]);
    }

    /**
     * Ejecuta un endpoint y devuelve lo que responde, tal cual.
     *
     * La petición se despacha dentro del propio servidor en lugar de salir a
     * la red: en un alojamiento compartido una llamada a uno mismo suele estar
     * capada, y aquí lo que importa es ver la respuesta real de la API con los
     * datos reales, no medir la red.
     */
    public function run(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:200'],
            'query' => ['nullable', 'array'],
            'query.*' => ['nullable', 'string', 'max:200'],
        ], [], [
            'path' => 'dirección',
        ]);

        $path = trim((string) $validated['path'], '/');

        if (! ApiCatalog::allows($path)) {
            return response()->json([
                'error' => 'Esa dirección no forma parte de la API pública.',
            ], 422);
        }

        /** @var array<string, string> $query */
        $query = array_filter(
            $validated['query'] ?? [],
            fn (mixed $value): bool => is_string($value) && trim($value) !== '',
        );

        $url = ApiCatalog::url($path);

        $sub = Request::create($url, 'GET', $query);
        $sub->headers->set('Accept', 'application/json');
        // Marca interna: deja pasar la comprobación de clave sin exponer
        // ninguna, porque quien está mirando ya se ha identificado en el panel.
        $sub->attributes->set(AuthenticateApiKey::TRUSTED, true);

        $original = app('request');
        $startedAt = microtime(true);

        try {
            // El controlador de la API recibe su Request del contenedor, no del
            // despachador: sin sustituirlo aquí leería los parámetros de la
            // petición del panel y los filtros no se aplicarían.
            app()->instance('request', $sub);

            $response = RouteFacade::dispatch($sub);
        } finally {
            // Y se devuelve la original, o el resto de la respuesta al panel se
            // generaría con la petición simulada.
            app()->instance('request', $original);
        }

        $body = $response->getContent();
        $milliseconds = (int) round((microtime(true) - $startedAt) * 1000);

        return response()->json([
            'status' => $response->getStatusCode(),
            'ok' => $response->getStatusCode() < 400,
            'duration_ms' => $milliseconds,
            'size_bytes' => $body === false ? 0 : strlen($body),
            'url' => $url.($query === [] ? '' : '?'.http_build_query($query)),
            // Saber si la respuesta salió de la caché explica por qué un cambio
            // recién hecho todavía no se ve.
            'cache' => $response->headers->get('X-Cache'),
            // Se devuelve ya decodificado para poder pintarlo con formato.
            'body' => $body === false ? null : json_decode($body, true),
            'raw' => $body === false ? '' : $body,
            'ran_at' => now()->toIso8601String(),
        ]);
    }
}

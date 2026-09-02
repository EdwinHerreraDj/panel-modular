<?php

use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\CacheApiResponse;
use App\Http\Middleware\HandleApiCors;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RunDeferredMaintenance;
use App\Http\Middleware\SecurityHeaders;
use App\Modules\ModuleRegistry;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')
                ->group(__DIR__.'/../routes/auth.php');

            // API pública. El prefijo es configurable para poder servirla
            // desde otra ruta si el alojamiento lo exige.
            Route::middleware('api')
                ->prefix((string) config('api.prefix'))
                ->group(function (): void {
                    require __DIR__.'/../routes/api.php';

                    // Cada módulo activo aporta sus endpoints al mismo grupo,
                    // así que heredan el prefijo, la clave, el límite y la
                    // caché sin tener que repetirlos.
                    foreach (app(ModuleRegistry::class)->routeFiles('api') as $moduleRoutes) {
                        require $moduleRoutes;
                    }
                });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global y el primero de todos: la comprobación previa del navegador
        // (OPTIONS) no coincide con ninguna ruta, así que nunca llegaría a un
        // middleware de grupo. El propio middleware se limita a la API.
        $middleware->prepend(HandleApiCors::class);

        $middleware->alias([
            'api.key' => AuthenticateApiKey::class,
            'api.cache' => CacheApiResponse::class,
        ]);

        // Lo que necesita cualquier endpoint de la API que sirva contenido.
        // Está aquí y no repetido en cada archivo de rutas para que un módulo
        // no pueda publicar por descuido un endpoint sin clave ni límite.
        //
        // El orden importa: el límite va después de la clave para poder contar
        // por clave y no por IP, y la caché la última, así una clave revocada
        // deja de servir aunque la respuesta estuviera guardada.
        $middleware->group('api.public', [
            'api.key',
            'throttle:api',
            'api.cache',
        ]);

        $middleware->web(append: [
            SecurityHeaders::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            // Publica lo programado y poda los registros viejos si el
            // alojamiento no tiene cron. Actúa después de responder, así que no
            // retrasa ninguna página.
            RunDeferredMaintenance::class,
        ]);

        // Los invitados que intentan entrar al área privada van al formulario
        // de acceso, nunca a una ruta inexistente.
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo('/panel');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sesión caducada: se devuelve al formulario con un aviso comprensible
        // en lugar de la página de error 419 por defecto.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            // La API contesta siempre en JSON, aunque el error lo lance el
            // propio framework y aunque el cliente no lo pida en las cabeceras.
            if ($request->is(trim((string) config('api.prefix'), '/').'/*')) {
                $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

                return response()->json([
                    'error' => [
                        'codigo' => match ($status) {
                            404 => 'no_encontrado',
                            429 => 'demasiadas_peticiones',
                            default => 'error_servidor',
                        },
                        'mensaje' => match ($status) {
                            404 => 'La dirección solicitada no existe en esta API.',
                            429 => 'Has superado el límite de peticiones. Espera un momento.',
                            default => 'La API no ha podido atender la petición.',
                        },
                    ],
                ], $status);
            }

            if (! $request->expectsJson() && $e instanceof HttpExceptionInterface && $e->getStatusCode() === 419) {
                return back()->with('error', 'Tu sesión ha caducado. Vuelve a intentarlo.');
            }

            return $response;
        });
    })->create();

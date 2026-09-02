<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\PruneLogs;
use App\Modules\ModuleRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Red de seguridad para las tareas periódicas en alojamientos sin cron.
 *
 * Lo correcto es que el planificador haga este trabajo (ver routes/console.php
 * y el proveedor de cada módulo), pero en un hosting compartido no siempre se
 * puede configurar. Aquí se hacen dos cosas, cada una con su propio cerrojo:
 *
 * - lo que declare cada módulo activo: publicar lo que tenga la hora cumplida,
 *   caducar ofertas, lo que sea;
 * - podar los registros de auditoría antiguos, como mucho una vez al día.
 *
 * Cada tarea empieza por una comprobación que debe ser barata —un `exists`
 * sobre un índice—, así que en la inmensa mayoría de las visitas no cuestan
 * prácticamente nada.
 *
 * Todo se ejecuta después de responder, para no retrasar la página.
 */
class RunDeferredMaintenance
{
    private const PRUNE_KEY = 'logs.prune-check';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->runModuleTasks();
        $this->pruneLogs();
    }

    /**
     * Las tareas que declara cada módulo activo.
     *
     * El núcleo no sabe qué hacen: solo que hay que preguntar si toca y, si
     * toca, ejecutarlas sin que puedan romper la petición.
     */
    private function runModuleTasks(): void
    {
        foreach (app(ModuleRegistry::class)->deferredTasks() as $task) {
            // `add` solo escribe si la clave no existe: hace de cerrojo entre
            // peticiones simultáneas y limita la comprobación a su intervalo.
            if (! Cache::add('deferred:'.$task->key, true, now()->addMinutes($task->everyMinutes))) {
                continue;
            }

            $this->safely(function () use ($task): void {
                if (($task->hasWork)()) {
                    ($task->run)();
                }
            });
        }
    }

    /**
     * Poda de los registros antiguos, una vez al día como mucho.
     *
     * Va aquí por lo mismo que la publicación: si el alojamiento no tiene cron,
     * nadie lo haría nunca y las tablas crecerían sin freno.
     */
    private function pruneLogs(): void
    {
        if (! config('security.logs.prune_on_request', true)) {
            return;
        }

        if (! Cache::add(self::PRUNE_KEY, true, now()->addDay())) {
            return;
        }

        $this->safely(function (): void {
            $prune = app(PruneLogs::class);

            if ($prune->hasStale()) {
                $prune->handle();
            }
        });
    }

    /**
     * Ejecuta algo sin dejar que rompa la petición: esto es una red de
     * seguridad, no la vía principal.
     *
     * @param  Closure(): void  $work
     */
    private function safely(Closure $work): void
    {
        try {
            $work();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}

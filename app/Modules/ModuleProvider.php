<?php

declare(strict_types=1);

namespace App\Modules;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;

/**
 * Punto de partida para el proveedor de servicios de un módulo.
 *
 * Hace el trabajo repetitivo a partir de dónde está el archivo, de modo que el
 * proveedor de un módulo normal se queda en declarar su instancia y, como
 * mucho, sus policies y sus comandos.
 *
 * Solo se registra si el módulo está activo: de eso se encarga el proveedor
 * del núcleo, que es quien lee la configuración.
 */
abstract class ModuleProvider extends ServiceProvider
{
    /**
     * La instancia que implementa el contrato.
     */
    abstract public function module(): Module;

    /**
     * Policies del módulo.
     *
     * Hay que declararlas: el descubrimiento automático de Laravel busca
     * `App\Policies\XPolicy` para `App\Models\X`, y fuera de `App\` no
     * encuentra nada. Sin esto la autorización del módulo no fallaría con un
     * error, sino en silencio.
     *
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return [];
    }

    /**
     * Comandos de consola del módulo.
     *
     * @return list<class-string>
     */
    protected function consoleCommands(): array
    {
        return [];
    }

    /**
     * Tareas del módulo en el planificador, para cuando el alojamiento tiene
     * cron. Sin cron, la red de seguridad son sus `deferredTasks()`.
     */
    protected function schedule(Schedule $schedule): void {}

    public function boot(): void
    {
        // Solo cuando algo resuelve el planificador, que es en consola: en una
        // petición normal no se construye y esto no cuesta nada.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $this->schedule($schedule);
        });

        $migrations = $this->modulePath('Database/Migrations');

        if (is_dir($migrations)) {
            $this->loadMigrationsFrom($migrations);
        }

        foreach ($this->policies() as $model => $policy) {
            Gate::policy($model, $policy);
        }

        if ($this->app->runningInConsole() && $this->consoleCommands() !== []) {
            $this->commands($this->consoleCommands());
        }
    }

    /**
     * Ruta dentro de la carpeta del módulo, deducida de dónde vive esta clase.
     */
    protected function modulePath(string $relative = ''): string
    {
        $directory = dirname((string) (new ReflectionClass($this))->getFileName());

        return $relative === '' ? $directory : $directory.'/'.ltrim($relative, '/');
    }
}

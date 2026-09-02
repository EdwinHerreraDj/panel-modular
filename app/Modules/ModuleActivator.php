<?php

declare(strict_types=1);

namespace App\Modules;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Activa y desactiva módulos escribiendo en el `.env`.
 *
 * Los módulos activos siguen viviendo en una sola línea del `.env`, que es lo
 * que se decidió al principio: se leen al arrancar, se cachean con
 * `config:cache` y no cuestan una consulta. Esta pantalla no cambia esa
 * decisión, solo evita tener que entrar por SSH para editar esa línea.
 *
 * Al tocarla hay que refrescar las cachés de configuración y de rutas: las dos
 * congelan lo que había, y sin refrescarlas un módulo recién activado no
 * tendría ni ajustes ni rutas.
 */
final class ModuleActivator
{
    private const VARIABLE = 'MODULES_ENABLED';

    public function __construct(private readonly ModuleRegistry $registry) {}

    public function envPath(): string
    {
        return app()->environmentFilePath();
    }

    /**
     * ¿Se puede cambiar desde aquí?
     *
     * Algunos alojamientos dejan el `.env` en solo lectura. En ese caso la
     * pantalla lo dice y explica cómo hacerlo a mano, en lugar de fallar al
     * pulsar el botón.
     */
    public function isWritable(): bool
    {
        return is_file($this->envPath()) && is_writable($this->envPath());
    }

    /**
     * Cambia la lista de módulos activos y deja el entorno consistente.
     *
     * @param  list<string>  $keys
     * @return list<string> Lo que se ha hecho, para contárselo a quien lo pulsó
     */
    public function apply(array $keys): array
    {
        $keys = array_values(array_unique(array_filter($keys)));

        foreach ($keys as $key) {
            if (! in_array($key, $this->registry->available(), true)) {
                throw new RuntimeException("El módulo «{$key}» no existe en modules/.");
            }
        }

        // El orden importa. Primero se prepara todo lo que el módulo necesita
        // —sus tablas y sus permisos— y solo al final se enciende en el `.env`.
        //
        // Así, si algo falla al migrar, el `.env` se queda como estaba y el
        // módulo sigue apagado en lugar de quedar encendido y a medias. Y en
        // desarrollo, donde `artisan serve` reinicia el servidor en cuanto ve
        // cambiar el `.env`, el trabajo ya está hecho aunque se pierda la
        // respuesta.
        $done = $this->prepare($keys);

        $this->writeEnabled($keys);

        return [...$done, ...$this->refreshCaches()];
    }

    /**
     * Reescribe la línea del `.env` sin tocar nada más.
     *
     * Público para poder probarlo contra un archivo de usar y tirar: es la
     * parte con lógica, y una prueba no puede reescribir el `.env` de quien
     * está desarrollando.
     *
     * @param  list<string>  $keys
     */
    public function writeEnabled(array $keys): void
    {
        $path = $this->envPath();

        if (! $this->isWritable()) {
            throw new RuntimeException('El archivo .env no se puede escribir.');
        }

        $contents = (string) file_get_contents($path);
        $line = self::VARIABLE.'='.implode(',', $keys);

        $replaced = preg_replace('/^'.self::VARIABLE.'=.*$/m', $line, $contents, 1, $count);

        $contents = $count > 0 && is_string($replaced)
            ? $replaced
            : rtrim($contents, "\r\n")."\n".$line."\n";

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException('No se ha podido escribir el archivo .env.');
        }
    }

    /**
     * Aplica lo que necesitan los módulos que van a quedar activos.
     *
     * Lo que hay que entender aquí: **los proveedores de esta petición ya
     * arrancaron con la lista antigua**. Escribir el `.env` no los revive, así
     * que ni las migraciones del módulo recién activado están registradas ni el
     * registro sabe de él. Por eso se actualiza la configuración en memoria, se
     * tira la instancia del registro y las migraciones se aplican indicando la
     * carpeta de cada módulo a mano.
     *
     * Las rutas y las pantallas del módulo sí esperan a la petición siguiente,
     * que ya arranca con la lista nueva. Como después se redirige, no se nota.
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function prepare(array $keys): array
    {
        // La configuración en memoria y el registro, al día: los proveedores de
        // esta petición arrancaron con la lista antigua y no saben del módulo
        // nuevo.
        config(['modules.enabled' => $keys]);
        app()->forgetInstance(ModuleRegistry::class);

        $done = ['Migraciones aplicadas ('.implode(', ', $this->migrate($keys)).')'];

        Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder',
            '--force' => true,
        ]);

        $done[] = 'Permisos sembrados';

        return $done;
    }

    /**
     * Tira las cachés que congelan la lista de módulos.
     *
     * Si estaban puestas se vuelven a poner: dejar una instalación de
     * producción sin cachear sería empeorarla.
     *
     * @return list<string>
     */
    private function refreshCaches(): array
    {
        $configWasCached = app()->configurationIsCached();
        $routesWereCached = app()->routesAreCached();

        Artisan::call('config:clear');
        Artisan::call('route:clear');

        $done = ['Configuración y rutas releídas'];

        if ($configWasCached) {
            Artisan::call('config:cache');
            $done[] = 'Configuración vuelta a cachear';
        }

        if ($routesWereCached) {
            Artisan::call('route:cache');
            $done[] = 'Rutas vueltas a cachear';
        }

        return $done;
    }

    /**
     * Aplica las migraciones del núcleo y las de cada módulo activo.
     *
     * Las de los módulos van por su carpeta y no por el registro del proveedor,
     * que en esta petición todavía no las conoce. Laravel salta las que ya
     * corrieron, así que repetirlo no cuesta nada.
     *
     * @param  list<string>  $keys
     * @return list<string> Módulos cuyas migraciones se han mirado
     */
    private function migrate(array $keys): array
    {
        Artisan::call('migrate', ['--force' => true]);

        $applied = ['núcleo'];

        foreach ($keys as $key) {
            $path = base_path('modules/'.Str::studly($key).'/Database/Migrations');

            if (! is_dir($path)) {
                continue;
            }

            Artisan::call('migrate', [
                '--force' => true,
                '--path' => $path,
                '--realpath' => true,
            ]);

            $applied[] = $key;
        }

        return $applied;
    }
}

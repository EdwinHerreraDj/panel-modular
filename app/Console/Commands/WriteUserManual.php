<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\UserManual;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Escribe el manual del cliente en el repositorio.
 *
 * Igual que la documentación de la API: se genera de la misma fuente que la
 * pantalla de ayuda, así que no puede quedarse desfasado respecto a ella.
 */
class WriteUserManual extends Command
{
    protected $signature = 'panel:manual {--ruta=docs/MANUAL.md : Dónde escribir el archivo}';

    protected $description = 'Genera el manual del panel para entregárselo al cliente';

    public function handle(): int
    {
        $path = base_path((string) $this->option('ruta'));

        File::ensureDirectoryExists(dirname($path));
        File::put($path, UserManual::markdown());

        $this->components->info('Manual escrito en '.str_replace(base_path().DIRECTORY_SEPARATOR, '', $path));

        return self::SUCCESS;
    }
}

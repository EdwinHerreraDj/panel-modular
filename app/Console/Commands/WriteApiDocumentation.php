<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\ApiDocumentation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Escribe la documentación de la API en el repositorio.
 *
 * Sirve para que el archivo de `docs/` no se quede desfasado: en lugar de
 * mantenerlo a mano, se regenera desde el mismo catálogo que usa el panel.
 */
class WriteApiDocumentation extends Command
{
    protected $signature = 'api:documentar
                            {--ruta=docs/API-BLOG.md : Dónde escribir el archivo}
                            {--openapi : Genera también la especificación OpenAPI}';

    protected $description = 'Genera la documentación de la API pública para entregársela a otra persona';

    public function handle(): int
    {
        $path = base_path((string) $this->option('ruta'));

        File::ensureDirectoryExists(dirname($path));
        File::put($path, ApiDocumentation::markdown());

        $this->components->info('Documentación escrita en '.$this->relative($path));

        if ($this->option('openapi')) {
            $openapi = preg_replace('/\.md$/', '.openapi.json', $path) ?? $path.'.json';

            File::put($openapi, (string) json_encode(
                ApiDocumentation::openapi(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));

            $this->components->info('OpenAPI escrito en '.$this->relative($openapi));
        }

        $this->newLine();
        $this->line('  Recuerda: la clave de acceso se entrega aparte, nunca dentro del documento.');

        return self::SUCCESS;
    }

    private function relative(string $path): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }
}

<?php

declare(strict_types=1);

namespace App\Support;

use App\Modules\ModuleRegistry;
use App\Modules\Values\ApiEndpoint;

/**
 * Catálogo de endpoints de la API pública.
 *
 * Una sola lista que sirve para tres cosas: documentar la API en el panel,
 * poblar el desplegable del probador y decidir qué se puede ejecutar desde
 * ahí. Al ser la misma fuente, la documentación no se queda desfasada.
 */
class ApiCatalog
{
    /**
     * Todos los endpoints: los del núcleo y los de los módulos activos.
     *
     * Un módulo aporta los suyos y aparecen solos en los tres sitios que se
     * alimentan de aquí, sin duplicar ni una línea.
     *
     * @return list<ApiEndpoint>
     */
    public static function all(): array
    {
        $endpoints = [
            ...CoreApiEndpoints::all(),
            ...app(ModuleRegistry::class)->apiEndpoints(),
        ];

        usort($endpoints, fn (ApiEndpoint $a, ApiEndpoint $b): int => $a->position <=> $b->position);

        return $endpoints;
    }

    /**
     * Forma de array, que es la que consumen el panel y el probador.
     *
     * @return list<array{
     *     key: string,
     *     path: string,
     *     title: string,
     *     description: string,
     *     requires_key: bool,
     *     parameters: list<array{name: string, description: string, example: string}>
     * }>
     */
    public static function endpoints(): array
    {
        return array_map(fn (ApiEndpoint $endpoint): array => $endpoint->toArray(), self::all());
    }

    /**
     * ¿Se puede ejecutar esta ruta desde el probador del panel?
     *
     * Solo se aceptan rutas del catálogo: el probador no es un cliente HTTP
     * general con el que alcanzar cualquier dirección del servidor.
     */
    public static function allows(string $path): bool
    {
        $path = trim($path, '/');

        foreach (self::endpoints() as $endpoint) {
            $pattern = '#^'.str_replace('\{slug\}', '[^/]+', preg_quote($endpoint['path'], '#')).'$#';

            if (preg_match($pattern, $path) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * URL absoluta de un endpoint.
     */
    public static function url(string $path = ''): string
    {
        return rtrim(config('app.url'), '/').'/'.trim((string) config('api.prefix'), '/')
            .($path === '' ? '' : '/'.trim($path, '/'));
    }
}

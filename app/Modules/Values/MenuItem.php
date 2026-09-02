<?php

declare(strict_types=1);

namespace App\Modules\Values;

/**
 * Una entrada del menú lateral.
 *
 * El icono viaja como clave, no como componente: el componente los resuelve
 * contra su propio mapa, de modo que se siguen compilando a SVG en línea y no
 * hay ni una petición a una CDN en tiempo de ejecución.
 */
final readonly class MenuItem
{
    /**
     * @param  string  $route  Nombre de la ruta, no la URL
     * @param  string  $activePattern  Patrón que marca la entrada como activa
     * @param  string|null  $permission  Permiso necesario para verla
     * @param  string|null  $group  Sin indicar, la entrada va al grupo del propio módulo
     * @param  int  $position  Orden dentro del grupo; el núcleo usa múltiplos de 10
     */
    public function __construct(
        public string $label,
        public string $route,
        public string $icon,
        public string $activePattern,
        public ?string $permission = null,
        public ?string $group = null,
        public int $position = 100,
    ) {}
}

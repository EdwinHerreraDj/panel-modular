<?php

declare(strict_types=1);

namespace App\Modules\Values;

/**
 * Un bloque propio del módulo en el panel de inicio.
 *
 * Las tarjetas y los avisos son formas genéricas y sirven para casi todo. Lo
 * que no encaja en ellas —una lista de últimos artículos, un desglose por
 * estado— lo pinta un componente del propio módulo, que el panel resuelve por
 * su nombre igual que resuelve una página.
 *
 * Es la alternativa a inventar un motor de bloques configurables: el módulo
 * escribe React normal y el núcleo solo decide dónde va.
 */
final readonly class DashboardWidget
{
    public const MAIN = 'main';

    public const SIDE = 'side';

    /**
     * @param  string  $component  Nombre con el prefijo del módulo: `Blog::RecentArticles`
     * @param  array<string, mixed>  $props  Lo que necesita para pintarse
     * @param  string  $area  `main` (columna ancha) o `side` (columna estrecha)
     */
    public function __construct(
        public string $component,
        public array $props = [],
        public string $area = self::MAIN,
        public int $position = 100,
    ) {}
}

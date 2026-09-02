<?php

declare(strict_types=1);

namespace App\Modules\Values;

/**
 * Una tarjeta del panel de inicio.
 *
 * Las construye el módulo ya resueltas para la persona que mira, porque cada
 * rol ve lo suyo: un autor sus artículos, un editor los del blog entero.
 */
final readonly class DashboardCard
{
    /**
     * @param  string  $tone  Tono de la plantilla: success, warning, danger, info, neutral
     * @param  string|null  $route  Nombre de ruta a la que lleva la tarjeta
     * @param  array<string, mixed>  $query  Parámetros de esa ruta
     */
    public function __construct(
        public string $key,
        public string $label,
        public int $count,
        public string $icon = 'article',
        public string $tone = 'neutral',
        public ?string $route = null,
        public array $query = [],
        public ?string $hint = null,
        public int $position = 100,
    ) {}
}

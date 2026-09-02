<?php

declare(strict_types=1);

namespace App\Modules\Values;

/**
 * El botón de acción principal del panel de inicio.
 *
 * «Escribir un artículo». Cada módulo aporta el suyo si tiene una acción que
 * sea claramente la primera que alguien quiere hacer al entrar.
 */
final readonly class DashboardAction
{
    /**
     * @param  string  $icon  Clave del icono; el panel la resuelve contra su mapa
     */
    public function __construct(
        public string $label,
        public string $href,
        public string $icon = 'pen',
        public int $position = 100,
    ) {}
}

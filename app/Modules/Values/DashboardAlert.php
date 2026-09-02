<?php

declare(strict_types=1);

namespace App\Modules\Values;

/**
 * Un aviso accionable del panel de inicio.
 *
 * «Hay 3 artículos esperando revisión», con el enlace para ir a revisarlos. El
 * módulo decide si procede enseñarlo: solo tiene sentido para quien puede hacer
 * algo al respecto, así que se construyen ya resueltos para cada persona.
 */
final readonly class DashboardAlert
{
    /**
     * @param  string  $tone  Tono de la plantilla: warning, info, danger…
     * @param  string|null  $actionLabel  Texto del enlace, si lo hay
     * @param  string|null  $actionHref  A dónde lleva
     */
    public function __construct(
        public string $key,
        public string $text,
        public string $tone = 'info',
        public ?string $actionLabel = null,
        public ?string $actionHref = null,
        public int $position = 100,
    ) {}
}

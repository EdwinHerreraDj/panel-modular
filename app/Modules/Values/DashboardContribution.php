<?php

declare(strict_types=1);

namespace App\Modules\Values;

/**
 * Lo que un módulo pone en el panel de inicio.
 *
 * Va todo junto en un solo objeto en lugar de en cuatro métodos del contrato:
 * las cuatro cosas se calculan con las mismas consultas y para la misma
 * persona, así que separarlas obligaría a repetirlas.
 */
final readonly class DashboardContribution
{
    /**
     * @param  list<DashboardCard>  $cards  Cifras de un vistazo
     * @param  list<DashboardAlert>  $alerts  Avisos accionables
     * @param  list<DashboardWidget>  $widgets  Bloques propios del módulo
     * @param  list<DashboardAction>  $actions  Botones de acción principal
     */
    public function __construct(
        public array $cards = [],
        public array $alerts = [],
        public array $widgets = [],
        public array $actions = [],
    ) {}

    public static function empty(): self
    {
        return new self;
    }
}

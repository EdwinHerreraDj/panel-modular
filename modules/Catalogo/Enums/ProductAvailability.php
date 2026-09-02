<?php

declare(strict_types=1);

namespace Modules\Catalogo\Enums;

/**
 * Si hay producto o no.
 *
 * Tres etiquetas y ningún número. Quien mantiene el catálogo sabe si le queda
 * poco de algo; lo que no va a hacer es llevar la cuenta exacta en el panel.
 */
enum ProductAvailability: string
{
    case Available = 'available';
    case Low = 'low';
    case OutOfStock = 'out_of_stock';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Disponible',
            self::Low => 'Pocas unidades',
            self::OutOfStock => 'Sin stock',
        };
    }

    /**
     * Tono de la plantilla, para el distintivo del listado.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Low => 'warning',
            self::OutOfStock => 'danger',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}

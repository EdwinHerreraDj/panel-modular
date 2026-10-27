<?php

declare(strict_types=1);

namespace Modules\Tienda\Enums;

/**
 * Cómo está de existencias un artículo o una variante.
 *
 * Se calcula, no se guarda: sale del número de unidades y del umbral. Los
 * valores son los mismos que usa el catálogo para que una web que consuma los
 * dos módulos no tenga que traducir.
 */
enum StockStatus: string
{
    case Available = 'available';
    case Low = 'low';
    case OutOfStock = 'out_of_stock';

    public static function for(int $units, int $threshold): self
    {
        return match (true) {
            $units <= 0 => self::OutOfStock,
            $units <= $threshold => self::Low,
            default => self::Available,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Disponible',
            self::Low => 'Quedan pocas unidades',
            self::OutOfStock => 'Agotado',
        };
    }

    /**
     * Tono de la plantilla, para el distintivo de los listados.
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

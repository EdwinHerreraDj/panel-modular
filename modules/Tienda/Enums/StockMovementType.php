<?php

declare(strict_types=1);

namespace Modules\Tienda\Enums;

/**
 * Por qué cambia el stock.
 *
 * El recuento es distinto de los demás: no se dice cuánto entra o sale, sino
 * cuántas unidades hay de verdad en la estantería. El movimiento guarda la
 * diferencia, que es lo que explica el descuadre.
 */
enum StockMovementType: string
{
    case Entry = 'entry';
    case Sale = 'sale';
    case Return = 'return';
    case Loss = 'loss';
    case Count = 'count';

    public function label(): string
    {
        return match ($this) {
            self::Entry => 'Entrada de mercancía',
            self::Sale => 'Venta',
            self::Return => 'Devolución',
            self::Loss => 'Merma o rotura',
            self::Count => 'Recuento',
        };
    }

    /**
     * +1 si suma, -1 si resta, 0 si fija el número directamente.
     */
    public function direction(): int
    {
        return match ($this) {
            self::Entry, self::Return => 1,
            self::Sale, self::Loss => -1,
            self::Count => 0,
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

<?php

declare(strict_types=1);

namespace Modules\Tienda\Enums;

/**
 * A qué alcanza una promoción.
 *
 * Una categoría incluye sus subcategorías: «Ropa al 20 %» que no rebajara las
 * camisetas porque están en «Ropa › Camisetas» sería una sorpresa para todos.
 */
enum PromotionScope: string
{
    case All = 'all';
    case Categories = 'categories';
    case Products = 'products';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Toda la tienda',
            self::Categories => 'Categorías',
            self::Products => 'Artículos concretos',
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

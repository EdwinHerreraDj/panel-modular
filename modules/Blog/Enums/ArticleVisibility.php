<?php

declare(strict_types=1);

namespace Modules\Blog\Enums;

/**
 * Visibilidad de un artículo ya publicado.
 */
enum ArticleVisibility: string
{
    /** Aparece en los listados y en la API pública. */
    case Public = 'public';

    /** Solo accesible con el enlace directo: no se lista ni sale en búsquedas. */
    case Unlisted = 'unlisted';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Pública',
            self::Unlisted => 'Solo con enlace',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Public => 'Aparece en el blog, en los listados y en las búsquedas.',
            self::Unlisted => 'Accesible solo para quien tenga el enlace. No se lista en ningún sitio.',
        };
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
                'description' => $case->description(),
            ],
            self::cases(),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipos de evento del registro de auditoría de contenido.
 */
enum ActivityEvent: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Creación',
            self::Updated => 'Modificación',
            self::Deleted => 'Envío a la papelera',
            self::Restored => 'Restauración',
        };
    }
}

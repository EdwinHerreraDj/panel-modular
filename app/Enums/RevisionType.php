<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Origen de una versión del historial.
 */
enum RevisionType: string
{
    /** Guardada al pulsar Guardar. */
    case Manual = 'manual';

    /** Copia automática mientras se escribe. Solo se conserva la última. */
    case Autosave = 'autosave';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Guardado',
            self::Autosave => 'Copia automática',
        };
    }
}

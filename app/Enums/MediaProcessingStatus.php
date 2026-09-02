<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado del procesamiento de una imagen de la biblioteca.
 */
enum MediaProcessingStatus: string
{
    /** Subida, pendiente de generar sus variantes. */
    case Pending = 'pending';

    /** Variantes generadas. */
    case Done = 'done';

    /** El procesamiento falló; el original sigue siendo usable. */
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Procesando',
            self::Done => 'Lista',
            self::Failed => 'Error al procesar',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Done => 'success',
            self::Failed => 'danger',
        };
    }
}

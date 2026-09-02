<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Rechaza las imágenes que no cabrían en memoria al procesarlas.
 *
 * El peso del archivo no dice nada del coste de abrirlo: un JPEG de 2 MB puede
 * ocupar 100 MB descomprimido. Sin esta comprobación, en un hosting con poca
 * memoria la subida moriría con un error fatal y una pantalla en blanco, en vez
 * de con un mensaje que se entienda.
 */
class ImageFitsInMemory implements ValidationRule
{
    /**
     * Bytes por píxel que consume una imagen en color verdadero, con margen
     * para la copia que se crea al redimensionar.
     */
    private const BYTES_PER_PIXEL = 4;

    private const OVERHEAD_FACTOR = 2.4;

    /**
     * Parte de la memoria disponible que se considera utilizable: el resto lo
     * necesita la propia aplicación.
     */
    private const USABLE_FRACTION = 0.7;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            return;
        }

        $size = @getimagesize($value->getRealPath());

        if ($size === false) {
            return; // De esto ya se encarga la regla `image`.
        }

        [$width, $height] = $size;

        $needed = (int) ($width * $height * self::BYTES_PER_PIXEL * self::OVERHEAD_FACTOR);
        $available = (int) (self::memoryLimit() * self::USABLE_FRACTION);

        // Sin límite de memoria no hay nada que comprobar.
        if ($available <= 0 || $needed <= $available) {
            return;
        }

        $fail(sprintf(
            'La imagen es demasiado grande para procesarla en este servidor (%d×%d px). '
            .'Redúcela antes de subirla: para un blog rara vez hace falta más de 2000 px de ancho.',
            $width,
            $height,
        ));
    }

    /**
     * Memoria disponible para procesar imágenes, en bytes. Devuelve -1 cuando
     * no hay límite.
     *
     * Por defecto se toma el `memory_limit` de PHP, pero se puede fijar a mano
     * con MEDIA_MAX_PROCESSING_MEMORY: útil cuando el alojamiento declara un
     * límite que en la práctica no puede sostener.
     */
    private static function memoryLimit(): int
    {
        $configured = config('media.max_processing_memory');

        if ($configured !== null && $configured !== '') {
            return self::toBytes((string) $configured);
        }

        return self::toBytes((string) ini_get('memory_limit'));
    }

    private static function toBytes(string $value): int
    {
        $limit = trim($value);

        if ($limit === '' || $limit === '-1') {
            return -1;
        }

        $unit = strtolower(substr($limit, -1));
        $value = (int) $limit;

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }
}

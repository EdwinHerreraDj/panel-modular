<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\ApiCache;

/**
 * Vacía la caché de la API cuando el modelo cambia.
 *
 * Se engancha a todo lo que la API sirve. Sin esto, publicar un artículo no se
 * vería en la web hasta que caducase la caché, y el cliente pensaría —con
 * razón— que el panel no funciona.
 */
trait InvalidatesApiCache
{
    public static function bootInvalidatesApiCache(): void
    {
        // `saved` cubre altas y modificaciones; los otros dos, la papelera.
        static::saved(fn () => ApiCache::flush());
        static::deleted(fn () => ApiCache::flush());

        if (method_exists(static::class, 'restored')) {
            static::restored(fn () => ApiCache::flush());
        }
    }
}

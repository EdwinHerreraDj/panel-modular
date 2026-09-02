<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Caché de las respuestas de la API pública.
 *
 * El almacén de caché en un alojamiento compartido es la base de datos o el
 * sistema de archivos, y ninguno de los dos admite etiquetas. Así que en lugar
 * de borrar claves una a una se usa un **número de versión**: forma parte de
 * cada clave, y subirlo deja obsoleto todo lo guardado de golpe. Las entradas
 * viejas caducan solas.
 *
 * Es más barato que recorrer y borrar, y sobre todo es atómico: no existe el
 * momento intermedio en el que media caché está limpia y la otra media no.
 */
class ApiCache
{
    private const VERSION_KEY = 'api.cache.version';

    /**
     * Prefijo de las claves. Sirve además para reconocerlas en la tabla `cache`.
     */
    private const PREFIX = 'api.response';

    /**
     * Respuesta guardada, si la hay.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function read(string $name, array $parameters): ?string
    {
        if (! self::isEnabled()) {
            return null;
        }

        $cached = Cache::get(self::key($name, $parameters));

        return is_string($cached) ? $cached : null;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function write(string $name, array $parameters, string $payload): void
    {
        if (! self::isEnabled()) {
            return;
        }

        Cache::put(self::key($name, $parameters), $payload, self::seconds());
    }

    /**
     * Invalida toda la caché de la API.
     *
     * Se llama al publicar o modificar cualquier cosa que la API sirva. Es
     * deliberadamente contundente: distinguir qué respuestas concretas se ven
     * afectadas por un cambio es fácil de equivocar, y el coste de recalcular
     * un listado es bajo comparado con servir algo desactualizado.
     */
    public static function flush(): void
    {
        // Se lee y se escribe en lugar de usar `increment`: sobre una clave que
        // todavía no existe, `increment` la deja en 1, que es justo el valor por
        // defecto, y la primera invalidación de todas no invalidaría nada.
        //
        // Si dos peticiones vacían a la vez pueden escribir el mismo número,
        // pero el resultado sigue siendo el correcto: la versión ha cambiado.
        Cache::forever(self::VERSION_KEY, self::version() + 1);
    }

    public static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    public static function seconds(): int
    {
        return (int) config('api.cache_seconds', 0);
    }

    public static function isEnabled(): bool
    {
        return self::seconds() > 0;
    }

    /**
     * Clave estable para una petición.
     *
     * Los parámetros se ordenan para que `?a=1&b=2` y `?b=2&a=1` compartan
     * entrada, y se acorta con un hash porque algunos almacenes limitan la
     * longitud de la clave.
     *
     * @param  array<string, mixed>  $parameters
     */
    private static function key(string $name, array $parameters): string
    {
        ksort($parameters);

        return self::PREFIX.'.v'.self::version().'.'.$name.'.'
            .substr(hash('xxh128', (string) json_encode($parameters)), 0, 16);
    }
}

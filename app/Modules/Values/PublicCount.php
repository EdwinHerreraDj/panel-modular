<?php

declare(strict_types=1);

namespace App\Modules\Values;

/**
 * Cuánto contenido publicado sirve un módulo ahora mismo.
 *
 * Tiene dos consumidores con necesidades distintas: el endpoint de estado, que
 * quiere una clave en español para el JSON, y la pantalla de conexión, que
 * quiere una etiqueta legible. Declarar las dos cosas juntas evita que la
 * interfaz tenga que adivinar una a partir de la otra.
 */
final readonly class PublicCount
{
    /**
     * @param  string  $key  Clave del JSON de la API, en español y sin acentos
     * @param  string  $label  Cómo se enseña en el panel
     */
    public function __construct(
        public string $key,
        public string $label,
        public int $count,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Modules\Values;

/**
 * Un endpoint de la API pública aportado por un módulo.
 *
 * El ejemplo de respuesta viaja aquí dentro, y no en una lista aparte del
 * núcleo, porque de este mismo objeto salen las tres cosas: el probador del
 * panel, la documentación descargable y el OpenAPI. Si el ejemplo viviera
 * fuera, un módulo aparecería en el probador pero sin documentar.
 */
final readonly class ApiEndpoint
{
    /**
     * @param  string  $key  Identificador corto, único en toda la API
     * @param  string  $path  Ruta sin el prefijo, con `{slug}` si lleva parámetro
     * @param  list<array{name: string, description: string, example: string}>  $parameters
     * @param  array<string, mixed>  $example  Respuesta de ejemplo, escrita a mano
     * @param  list<string>  $notes  Avisos que se añaden bajo la tabla de parámetros
     * @param  int  $position  Orden en la documentación; el núcleo deja libre de 30 a 60
     */
    public function __construct(
        public string $key,
        public string $path,
        public string $title,
        public string $description,
        public bool $requiresKey = true,
        public array $parameters = [],
        public array $example = [],
        public array $notes = [],
        public int $position = 100,
    ) {}

    /**
     * Forma de array, que es como la consumen el catálogo y el panel.
     *
     * @return array{
     *     key: string,
     *     path: string,
     *     title: string,
     *     description: string,
     *     requires_key: bool,
     *     parameters: list<array{name: string, description: string, example: string}>
     * }
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'path' => $this->path,
            'title' => $this->title,
            'description' => $this->description,
            'requires_key' => $this->requiresKey,
            'parameters' => $this->parameters,
        ];
    }
}

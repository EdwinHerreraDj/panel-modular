<?php

declare(strict_types=1);

namespace App\Modules\Values;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Un tipo de contenido: un modelo que el resto de la plataforma tiene que
 * saber nombrar, enlazar y guardar en una columna polimórfica.
 *
 * Una sola declaración alimenta cinco sitios: el filtro «Sobre qué» de la
 * auditoría, el enlace al sujeto de un registro, la etiqueta de dónde se usa
 * un archivo de la biblioteca, el destino de una redirección y el mapa de
 * alias morph.
 *
 * El alias es lo que se guarda en la base de datos, **nunca la clase**. Va en
 * inglés porque es código, no interfaz, y es corto y estable: mover el modelo
 * de espacio de nombres deja de tocar un solo dato.
 */
final readonly class ContentType
{
    /**
     * @param  class-string<Model>  $model
     * @param  string  $alias  Lo que se guarda en las columnas `*_type`
     * @param  (Closure(int|string): string)|null  $editUrl  Pantalla propia, si la tiene
     * @param  (Closure(Model): string)|null  $describe  Etiqueta al listar dónde se usa un archivo
     */
    public function __construct(
        public string $model,
        public string $alias,
        public string $singular,
        public string $plural,
        public ?Closure $editUrl = null,
        public ?Closure $describe = null,
    ) {}

    /**
     * Enlace de edición, cuando el tipo tiene pantalla propia.
     */
    public function urlFor(int|string $id): ?string
    {
        return $this->editUrl === null ? null : ($this->editUrl)($id);
    }

    /**
     * Nombre legible de un registro concreto.
     *
     * Por defecto se pregunta por el atributo en lugar de comprobar la clase,
     * así que un módulo nuevo se describe solo con que su modelo tenga `title`
     * o `name`. Solo hace falta declarar `describe` cuando eso no basta.
     */
    public function describeOne(Model $record): string
    {
        if ($this->describe !== null) {
            return ($this->describe)($record);
        }

        $name = $record->getAttribute('title') ?? $record->getAttribute('name');

        return is_string($name) && $name !== ''
            ? $this->singular.': '.$name
            : $this->singular;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Blog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;
use Modules\Blog\Enums\ArticleStatus;
use Modules\Blog\Enums\ArticleVisibility;
use Modules\Blog\Models\Article;

/**
 * Alta y edición de artículos.
 *
 * El slug no se pide nunca: se deriva del título.
 */
class ArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $article = $this->route('article');

        return $article instanceof Article
            ? ($this->user()?->can('update', $article) ?? false)
            : ($this->user()?->can('create', Article::class) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            // El contenido se sanitiza en el servidor antes de guardarlo: aquí
            // solo se comprueba el tamaño.
            'content' => ['nullable', 'string', 'max:'.config('sanitizer.max_length', 500_000)],

            'status' => ['required', new Enum(ArticleStatus::class)],
            'visibility' => ['required', new Enum(ArticleVisibility::class)],
            'published_at' => ['nullable', 'date'],
            'scheduled_for' => ['nullable', 'date', 'after:now'],
            'position' => ['required', 'integer', 'min:0', 'max:9999'],

            'category_ids' => ['nullable', 'array', 'max:10'],
            'category_ids.*' => ['integer', Rule::exists('categories', 'id')->withoutTrashed()],
            'tag_ids' => ['nullable', 'array', 'max:20'],
            'tag_ids.*' => ['integer', Rule::exists('tags', 'id')->withoutTrashed()],

            'related_ids' => ['nullable', 'array', 'max:'.config('articles.max_related', 6)],
            'related_ids.*' => [
                'integer',
                Rule::exists('articles', 'id')->withoutTrashed(),
                // Un artículo no puede recomendarse a sí mismo.
                Rule::notIn(array_filter([$this->route('article')?->getKey()])),
            ],

            // La imagen destacada se elige de la biblioteca.
            'featured_media_id' => ['nullable', 'integer', Rule::exists('media', 'id')->withoutTrashed()],
            'featured_image_alt' => ['nullable', 'string', 'max:255'],

            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'canonical_url' => ['nullable', 'string', 'max:255', 'url:https,http'],
            'og_title' => ['nullable', 'string', 'max:100'],
            'og_description' => ['nullable', 'string', 'max:200'],
            'is_indexable' => ['required', 'boolean'],
        ];
    }

    /**
     * Comprobaciones que dependen del estado actual y de los permisos.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();
                $target = ArticleStatus::tryFrom((string) $this->input('status'));

                if ($user === null || $target === null) {
                    return;
                }

                /** @var Article $article */
                $article = $this->route('article') ?? new Article(['status' => ArticleStatus::Draft]);
                $current = $article->status ?? ArticleStatus::Draft;

                // La transición debe estar contemplada en el flujo editorial.
                if (! $current->canTransitionTo($target)) {
                    $validator->errors()->add('status', sprintf(
                        'No se puede pasar de «%s» a «%s».',
                        $current->label(),
                        $target->label(),
                    ));

                    return;
                }

                // Y quien la pide debe tener el permiso correspondiente.
                $permission = $target->requiredPermission();

                if ($permission !== null && $user->cannot($permission->value)) {
                    $validator->errors()->add('status', sprintf(
                        'No tienes permiso para dejar el artículo en «%s».',
                        $target->label(),
                    ));
                }

                // Programar exige una fecha futura.
                if ($target === ArticleStatus::Scheduled && $this->input('scheduled_for') === null) {
                    $validator->errors()->add(
                        'scheduled_for',
                        'Indica la fecha y la hora en las que debe publicarse.',
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scheduled_for.after' => 'La fecha de publicación programada debe ser futura.',
            'canonical_url.url' => 'Introduce una URL completa, por ejemplo: https://www.miempresa.es/articulo',
            'category_ids.*.exists' => 'Alguna de las categorías seleccionadas ya no existe.',
            'tag_ids.*.exists' => 'Alguna de las etiquetas seleccionadas ya no existe.',
            'category_ids.max' => 'No puedes asignar más de 10 categorías a un artículo.',
            'tag_ids.max' => 'No puedes asignar más de 20 etiquetas a un artículo.',
            'related_ids.max' => 'No puedes relacionar más de '.config('articles.max_related', 6).' artículos.',
            'related_ids.*.not_in' => 'Un artículo no puede relacionarse consigo mismo.',
            'related_ids.*.exists' => 'Alguno de los artículos relacionados ya no existe.',
            'meta_title.max' => 'El meta título no debería superar los 70 caracteres para que no se corte en Google.',
            'meta_description.max' => 'La meta descripción no debería superar los 160 caracteres para que no se corte en Google.',
            'featured_media_id.exists' => 'La imagen destacada seleccionada ya no está en la biblioteca.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'título',
            'excerpt' => 'extracto',
            'content' => 'contenido',
            'status' => 'estado',
            'visibility' => 'visibilidad',
            'published_at' => 'fecha de publicación',
            'scheduled_for' => 'fecha programada',
            'position' => 'orden',
            'category_ids' => 'categorías',
            'tag_ids' => 'etiquetas',
            'featured_media_id' => 'imagen destacada',
            'featured_image_alt' => 'texto alternativo',
            'meta_title' => 'meta título',
            'meta_description' => 'meta descripción',
            'canonical_url' => 'URL canónica',
            'og_title' => 'título para redes',
            'og_description' => 'descripción para redes',
            'is_indexable' => 'indexación',
        ];
    }

    protected function prepareForValidation(): void
    {
        // El formulario viaja como multipart por la imagen: los booleanos
        // llegan en texto.
        $this->merge([
            'is_indexable' => $this->boolean('is_indexable'),
            'position' => $this->input('position') ?? 0,
        ]);
    }
}

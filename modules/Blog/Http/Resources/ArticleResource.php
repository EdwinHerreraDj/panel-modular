<?php

declare(strict_types=1);

namespace Modules\Blog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Blog\Models\Article;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Tag;

/**
 * Artículo tal y como lo ve la web pública.
 *
 * Las claves van en español para que el JSON se lea igual que el panel, y solo
 * se expone lo publicable: ni borradores, ni datos internos, ni correos.
 *
 * @mixin Article
 */
class ArticleResource extends JsonResource
{
    /**
     * Si es false se omite el contenido completo (listados).
     */
    private bool $withContent = false;

    public function withContent(bool $withContent = true): self
    {
        $this->withContent = $withContent;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Article $article */
        $article = $this->resource;

        return array_filter([
            'id' => $article->id,
            'titulo' => $article->title,
            'slug' => $article->slug,
            'extracto' => $article->excerpt,
            'contenido_html' => $this->withContent ? $article->content : null,
            'minutos_lectura' => $article->reading_minutes,

            'imagen_destacada' => $article->featuredMedia === null ? null : [
                'alt' => $article->featured_image_alt,
                'miniatura' => $article->featuredMedia->variantUrl('thumbnail'),
                'mediana' => $article->featuredMedia->variantUrl('medium'),
                'grande' => $article->featuredMedia->variantUrl('large'),
            ],

            'autor' => $article->author === null ? null : [
                'nombre' => $article->author->name,
            ],

            'categorias' => $article->relationLoaded('categories')
                ? $article->categories->map(fn (Category $category): array => [
                    'nombre' => $category->name,
                    'slug' => $category->slug,
                ])->values()->all()
                : null,

            'etiquetas' => $article->relationLoaded('tags')
                ? $article->tags->map(fn (Tag $tag): array => [
                    'nombre' => $tag->name,
                    'slug' => $tag->slug,
                ])->values()->all()
                : null,

            'seo' => [
                'titulo' => $article->meta_title ?: $article->title,
                'descripcion' => $article->meta_description ?: $article->excerpt,
                'canonica' => $article->canonical_url,
                'indexable' => $article->is_indexable,
                'og_titulo' => $article->og_title ?: $article->meta_title ?: $article->title,
                'og_descripcion' => $article->og_description ?: $article->meta_description,
            ],

            'publicado_en' => $article->published_at?->toIso8601String(),
            'actualizado_en' => $article->updated_at?->toIso8601String(),
        ], fn (mixed $value): bool => $value !== null);
    }
}

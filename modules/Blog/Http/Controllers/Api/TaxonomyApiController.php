<?php

declare(strict_types=1);

namespace Modules\Blog\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Tag;

/**
 * Categorías y etiquetas, para construir los menús de la web pública.
 *
 * Solo salen las que tienen algún artículo publicado: un menú con secciones
 * vacías es peor que no tener menú.
 */
class TaxonomyApiController extends Controller
{
    public function categories(): JsonResponse
    {
        $categories = Category::query()
            ->withCount(['articles' => fn ($query) => $query->public()])
            ->having('articles_count', '>', 0)
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return response()->json([
            'datos' => $categories->map(fn (Category $category): array => [
                'nombre' => $category->name,
                'slug' => $category->slug,
                'descripcion' => $category->description,
                'articulos' => $category->articles_count,
                'seo' => [
                    'titulo' => $category->meta_title ?: $category->name,
                    'descripcion' => $category->meta_description ?: $category->description,
                ],
            ])->values(),
        ]);
    }

    public function tags(): JsonResponse
    {
        $tags = Tag::query()
            ->withCount(['articles' => fn ($query) => $query->public()])
            ->having('articles_count', '>', 0)
            ->orderByDesc('articles_count')
            ->orderBy('name')
            ->get();

        return response()->json([
            'datos' => $tags->map(fn (Tag $tag): array => [
                'nombre' => $tag->name,
                'slug' => $tag->slug,
                'articulos' => $tag->articles_count,
            ])->values(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Blog\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Blog\Enums\ArticleVisibility;
use Modules\Blog\Http\Resources\ArticleResource;
use Modules\Blog\Models\Article;

/**
 * Artículos publicados, para la web pública.
 *
 * Solo sale lo que está publicado y con fecha ya cumplida: un artículo
 * programado no se filtra por aquí ni conociendo su dirección.
 */
class ArticleApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(
            max((int) $request->integer('por_pagina', (int) config('api.per_page')), 1),
            (int) config('api.max_per_page'),
        );

        $articles = Article::query()
            ->public()
            ->with(['author:id,name', 'featuredMedia', 'categories:id,name,slug', 'tags:id,name,slug'])
            ->when(
                $request->filled('categoria'),
                fn (Builder $query) => $query->whereHas(
                    'categories',
                    fn (Builder $inner) => $inner->where('slug', $request->string('categoria')),
                ),
            )
            ->when(
                $request->filled('etiqueta'),
                fn (Builder $query) => $query->whereHas(
                    'tags',
                    fn (Builder $inner) => $inner->where('slug', $request->string('etiqueta')),
                ),
            )
            ->when(
                $request->filled('buscar'),
                fn (Builder $query) => $query->search((string) $request->string('buscar')),
            )
            ->orderByDesc('position')
            ->orderByDesc('published_at')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'datos' => ArticleResource::collection($articles->getCollection())->resolve($request),
            'paginacion' => [
                'pagina' => $articles->currentPage(),
                'por_pagina' => $articles->perPage(),
                'total' => $articles->total(),
                'ultima_pagina' => $articles->lastPage(),
                'hay_mas' => $articles->hasMorePages(),
            ],
        ]);
    }

    /**
     * Un artículo por su slug.
     *
     * Aquí sí entran los «solo con enlace»: existen precisamente para poder
     * compartirse por su dirección aunque no aparezcan en los listados.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $article = Article::query()
            ->where('slug', $slug)
            ->whereIn('visibility', [ArticleVisibility::Public->value, ArticleVisibility::Unlisted->value])
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->with(['author:id,name', 'featuredMedia', 'categories:id,name,slug', 'tags:id,name,slug', 'related'])
            ->first();

        if ($article === null) {
            return response()->json([
                'error' => [
                    'codigo' => 'no_encontrado',
                    'mensaje' => 'No hay ningún artículo publicado con esa dirección.',
                ],
            ], 404);
        }

        $related = $article->related
            ->filter(fn (Article $item): bool => $item->isPublished())
            ->values();

        return response()->json([
            'datos' => (new ArticleResource($article))->withContent()->resolve($request),
            'relacionados' => ArticleResource::collection($related)->resolve($request),
        ]);
    }
}

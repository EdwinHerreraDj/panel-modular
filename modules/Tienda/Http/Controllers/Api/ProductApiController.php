<?php

declare(strict_types=1);

namespace Modules\Tienda\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Tienda\Http\Resources\ProductResource;
use Modules\Tienda\Models\Category;
use Modules\Tienda\Models\Product;
use Modules\Tienda\Support\CategoryTree;

/**
 * Los artículos para la web de la tienda. Solo lectura.
 */
class ProductApiController extends Controller
{
    /** @var list<string> */
    public const SORTS = ['destacados', 'novedades', 'precio_asc', 'precio_desc', 'nombre'];

    /**
     * Relaciones que necesita el recurso, para no lanzar una consulta por
     * artículo.
     *
     * @var list<string>
     */
    private const WITH = ['images.media', 'variants.media', 'categories:id'];

    public function index(Request $request): JsonResponse
    {
        $perPage = min(
            max((int) $request->integer('por_pagina', (int) config('api.per_page')), 1),
            (int) config('api.max_per_page'),
        );

        $tree = CategoryTree::current();
        $category = null;

        if ($request->filled('categoria')) {
            $category = $this->publicCategory($tree, $request->string('categoria')->toString());

            if ($category === null) {
                return self::notFound('categoria_no_encontrada', 'No hay ninguna categoría visible con esa dirección.');
            }
        }

        $products = Product::query()
            ->public()
            ->with(self::WITH)
            ->search($request->string('buscar')->toString())
            ->when($category !== null, fn (Builder $query) => $query->inCategories($tree->descendantIds((int) $category?->id)))
            ->when($request->boolean('destacados'), fn (Builder $query) => $query->where('is_featured', true))
            ->when($request->boolean('en_oferta'), fn (Builder $query) => $query->onSale())
            ->when($request->boolean('disponibles'), fn (Builder $query) => $query->inStock())
            ->when($request->filled('marca'), fn (Builder $query) => $query->where('brand', $request->string('marca')->toString()))
            ->tap(fn (Builder $query) => $this->sort($query, $request->string('orden')->toString()))
            ->paginate($perPage, ['*'], 'pagina');

        return response()->json([
            'datos' => ProductResource::collection($products->items())->resolve(),
            'paginacion' => [
                'pagina' => $products->currentPage(),
                'por_pagina' => $products->perPage(),
                'total' => $products->total(),
                'ultima_pagina' => $products->lastPage(),
                'hay_mas' => $products->hasMorePages(),
            ],
        ]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $product = Product::query()
            ->public()
            ->with([...self::WITH, 'options'])
            ->where('slug', $slug)
            ->first();

        // Mensaje propio en lugar del genérico del framework: quien integra la
        // web tiene que poder distinguir «este artículo no existe o está
        // oculto» de «me he equivocado de dirección de la API».
        if ($product === null) {
            return self::notFound('no_encontrado', 'No hay ningún artículo visible con esa dirección.');
        }

        $related = $product->category_id === null ? [] : Product::query()
            ->public()
            ->with(self::WITH)
            ->whereKeyNot($product->id)
            ->where('category_id', $product->category_id)
            ->orderByDesc('is_featured')
            ->orderBy('position')
            ->limit(4)
            ->get()
            ->all();

        return response()->json([
            'datos' => ProductResource::detail($product, $related, $request),
        ]);
    }

    /**
     * El orden lo elige la web. Por defecto, primero lo destacado y después el
     * orden que se le haya dado en el panel.
     *
     * Ordenar por precio usa el precio del artículo antes de promociones: el
     * descuento se calcula al servir y no está en la base de datos.
     *
     * @param  Builder<Product>  $query
     */
    private function sort(Builder $query, string $sort): void
    {
        match ($sort) {
            'novedades' => $query->orderByDesc('created_at'),
            'precio_asc' => $query->orderByRaw('price IS NULL')->orderBy('price'),
            'precio_desc' => $query->orderByRaw('price IS NULL')->orderByDesc('price'),
            'nombre' => $query->orderBy('name'),
            default => $query->orderByDesc('is_featured')->orderBy('position'),
        };

        $query->orderBy('name')->orderBy('id');
    }

    private function publicCategory(CategoryTree $tree, string $slug): ?Category
    {
        $category = Category::query()->where('slug', $slug)->first();

        return $category !== null && $tree->isPublic($category->id) ? $category : null;
    }

    private static function notFound(string $code, string $message): JsonResponse
    {
        return response()->json(['error' => ['codigo' => $code, 'mensaje' => $message]], 404);
    }
}

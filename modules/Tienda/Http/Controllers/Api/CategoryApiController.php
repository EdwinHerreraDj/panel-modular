<?php

declare(strict_types=1);

namespace Modules\Tienda\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Modules\Tienda\Http\Resources\ProductResource;
use Modules\Tienda\Models\Category;
use Modules\Tienda\Models\Product;
use Modules\Tienda\Support\CategoryTree;

/**
 * Las categorías para el menú de la tienda. Solo lectura.
 */
class CategoryApiController extends Controller
{
    /**
     * El árbol entero de lo visible, con cuántos artículos hay en cada rama.
     *
     * Se manda de una vez y anidado: es lo que necesita un menú, y con unas
     * decenas de categorías no merece la pena paginarlo.
     */
    public function index(): JsonResponse
    {
        $tree = CategoryTree::current();
        $counts = $this->productsPerCategory();

        return response()->json([
            'datos' => $this->branch($tree, null, $counts),
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $tree = CategoryTree::current();
        $category = Category::query()->where('slug', $slug)->first();

        if ($category === null || ! $tree->isPublic($category->id)) {
            return response()->json([
                'error' => [
                    'codigo' => 'no_encontrado',
                    'mensaje' => 'No hay ninguna categoría visible con esa dirección.',
                ],
            ], 404);
        }

        $category = $tree->find($category->id) ?? $category;
        $counts = $this->productsPerCategory();

        return response()->json([
            'datos' => $this->node($tree, $category, $counts) + array_filter([
                'ruta' => array_map(
                    fn (Category $step): array => ['nombre' => $step->name, 'slug' => $step->slug],
                    $tree->path($category->id),
                ),
                'seo' => array_filter([
                    'titulo' => $category->meta_title,
                    'descripcion' => $category->meta_description ?? $category->description,
                ]),
            ]),
        ]);
    }

    /**
     * @param  array<int, array<int, true>>  $counts
     * @return list<array<string, mixed>>
     */
    private function branch(CategoryTree $tree, ?int $parent, array $counts): array
    {
        $nodes = [];

        foreach ($tree->childrenOf($parent) as $category) {
            if ($category->is_visible) {
                $nodes[] = $this->node($tree, $category, $counts);
            }
        }

        return $nodes;
    }

    /**
     * @param  array<int, array<int, true>>  $counts
     * @return array<string, mixed>
     */
    private function node(CategoryTree $tree, Category $category, array $counts): array
    {
        // Un artículo en dos subcategorías de la misma rama cuenta una vez.
        $products = [];

        foreach ($tree->descendantIds($category->id) as $id) {
            $products += $counts[$id] ?? [];
        }

        return array_filter([
            'id' => $category->id,
            'nombre' => $category->name,
            'slug' => $category->slug,
            'descripcion' => $category->description,
            'imagen' => ProductResource::image($category->image, $category->name),
            'articulos' => count($products),
            'subcategorias' => $this->branch($tree, $category->id, $counts),
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * Qué artículos visibles hay en cada categoría, como principal o como
     * adicional. Dos consultas para todo el árbol.
     *
     * @return array<int, array<int, true>>
     */
    private function productsPerCategory(): array
    {
        $counts = [];

        $main = Product::query()->public()->whereNotNull('category_id')->pluck('category_id', 'id');

        foreach ($main as $productId => $categoryId) {
            $counts[(int) $categoryId][(int) $productId] = true;
        }

        $extra = DB::table('shop_category_product')
            ->join('shop_products', 'shop_products.id', '=', 'shop_category_product.product_id')
            ->where('shop_products.is_visible', true)
            ->whereNull('shop_products.deleted_at')
            ->get(['shop_category_product.category_id', 'shop_category_product.product_id']);

        foreach ($extra as $row) {
            $counts[(int) $row->category_id][(int) $row->product_id] = true;
        }

        return $counts;
    }
}

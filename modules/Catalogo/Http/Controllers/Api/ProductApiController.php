<?php

declare(strict_types=1);

namespace Modules\Catalogo\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Catalogo\Http\Resources\ProductResource;
use Modules\Catalogo\Models\Product;

/**
 * El catálogo para la web pública. Solo lectura.
 */
class ProductApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('por_pagina', 24), 1), (int) config('api.max_per_page'));

        $products = Product::query()
            ->public()
            ->with('image')
            ->search($request->string('buscar')->toString())
            ->orderBy('position')
            ->orderBy('name')
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

    public function show(string $slug): JsonResponse
    {
        $product = Product::query()
            ->public()
            ->with('image')
            ->where('slug', $slug)
            ->first();

        // Mensaje propio en lugar del genérico del framework: quien integra la
        // web tiene que poder distinguir «este producto no existe o está
        // oculto» de «me he equivocado de dirección de la API».
        if ($product === null) {
            return response()->json([
                'error' => [
                    'codigo' => 'no_encontrado',
                    'mensaje' => 'No hay ningún producto visible con esa dirección.',
                ],
            ], 404);
        }

        return response()->json([
            'datos' => (new ProductResource($product))->resolve() + [
                'descripcion_html' => $product->description,
            ],
        ]);
    }
}

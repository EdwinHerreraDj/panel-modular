<?php

declare(strict_types=1);

namespace Modules\Tienda\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Tienda\Enums\PromotionScope;
use Modules\Tienda\Models\Category;
use Modules\Tienda\Models\Promotion;
use Modules\Tienda\Support\CategoryTree;
use Modules\Tienda\Support\TiendaSettings;
use Modules\Tienda\Support\WhatsApp;

/**
 * Lo que la web necesita saber de la tienda en sí: cómo contactar y qué
 * ofertas hay en marcha.
 */
class StoreApiController extends Controller
{
    public function settings(): JsonResponse
    {
        $number = TiendaSettings::whatsappNumber();

        return response()->json([
            'datos' => array_filter([
                'moneda' => TiendaSettings::currency(),
                'mostrar_unidades' => TiendaSettings::showStockQuantity(),
                'whatsapp' => $number === null ? null : [
                    'numero' => '+'.$number,
                    'url' => WhatsApp::link(),
                ],
            ], fn (mixed $value): bool => $value !== null),
        ]);
    }

    /**
     * Las promociones en vigor, para banners y avisos. El precio rebajado de
     * cada artículo ya llega en el propio artículo.
     */
    public function promotions(): JsonResponse
    {
        $tree = CategoryTree::current();

        $promotions = Promotion::query()
            ->running()
            ->with('categories:id,slug,name')
            ->orderBy('ends_at')
            ->get()
            ->map(fn (Promotion $promotion): array => array_filter([
                'id' => $promotion->id,
                'nombre' => $promotion->name,
                'descripcion' => $promotion->description,
                'tipo' => $promotion->type->value,
                'valor' => (float) $promotion->value,
                'valor_texto' => $promotion->valueLabel(),
                'alcance' => $promotion->scope->value,
                'categorias' => $promotion->scope === PromotionScope::Categories
                    ? $promotion->categories
                        ->filter(fn (Category $category): bool => $tree->isPublic($category->id))
                        ->map(fn (Category $category): array => ['nombre' => $category->name, 'slug' => $category->slug])
                        ->values()
                        ->all()
                    : null,
                'empieza_en' => $promotion->starts_at?->toIso8601String(),
                'termina_en' => $promotion->ends_at?->toIso8601String(),
            ], fn (mixed $value): bool => $value !== null))
            ->all();

        return response()->json(['datos' => $promotions]);
    }
}

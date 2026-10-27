<?php

declare(strict_types=1);

namespace Modules\Tienda\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Tienda\Actions\RegisterStockMovement;
use Modules\Tienda\Enums\StockMovementType;
use Modules\Tienda\Http\Requests\StockMovementRequest;
use Modules\Tienda\Models\Product;
use Modules\Tienda\Models\ProductVariant;
use Modules\Tienda\Models\StockMovement;
use Modules\Tienda\Support\CategoryTree;

/**
 * Inventario: cuánto queda de cada cosa y por qué.
 *
 * Es la pantalla del día a día: llega mercancía, se vende algo por WhatsApp,
 * se rompe una unidad. Va aparte de la ficha del artículo para que quien solo
 * lleva el almacén no tenga que abrir —ni poder tocar— precios y fotos.
 */
class StockController extends Controller
{
    public function __construct(private readonly RegisterStockMovement $registerStockMovement) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $tree = CategoryTree::current();
        $extra = $request->string('extra')->toString();
        $category = $request->integer('category');

        $filters = [
            'search' => trim((string) $request->string('search')),
            'status' => '',
            'extra' => in_array($extra, ['low', 'out_of_stock'], true) ? $extra : '',
            'category' => $tree->find($category) !== null ? (string) $category : '',
            'trashed' => false,
            'sort' => in_array($request->string('sort')->toString(), ['name', 'stock'], true)
                ? $request->string('sort')->toString()
                : 'name',
            'direction' => $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc',
        ];

        $products = Product::query()
            ->where('track_stock', true)
            ->with(['images.media', 'variants'])
            ->search($filters['search'])
            ->when($filters['category'] !== '', fn ($query) => $query->inCategories($tree->descendantIds((int) $filters['category'])))
            ->when($filters['extra'] === 'low', fn ($query) => $query->lowStock())
            ->when($filters['extra'] === 'out_of_stock', fn ($query) => $query->outOfStock())
            ->orderBy($filters['sort'], $filters['direction'])
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'thumbnail_url' => $product->coverUrl('thumbnail'),
                'is_visible' => $product->is_visible,
                'stock' => $product->stock,
                'threshold' => $product->lowStockThreshold(),
                'status' => $product->stockStatus()->value,
                'status_label' => $product->stockStatus()->label(),
                'status_tone' => $product->stockStatus()->tone(),
                'variants' => $product->variants->map(function (ProductVariant $variant) use ($product): array {
                    $variant->setRelation('product', $product);
                    $status = $variant->stockStatus();

                    return [
                        'id' => $variant->id,
                        'label' => $variant->label(),
                        'sku' => $variant->sku,
                        'stock' => $variant->stock,
                        'is_active' => $variant->is_active,
                        'status_label' => $status->label(),
                        'status_tone' => $status->tone(),
                    ];
                })->all(),
            ]);

        return Inertia::render('Tienda::Stock/Index', [
            'products' => $products,
            'filters' => $filters,
            'categoryOptions' => [
                ['value' => '', 'label' => 'Todas'],
                ...array_map(fn (array $o): array => ['value' => $o['value'], 'label' => $o['label']], $tree->options()),
            ],
            'typeOptions' => StockMovementType::options(),
            'canManage' => $request->user()?->can('manageStock', Product::class) ?? false,
            'counts' => [
                'low' => Product::query()->lowStock()->count(),
                'out' => Product::query()->outOfStock()->count(),
            ],
        ]);
    }

    public function store(StockMovementRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('manageStock', $product);

        $variant = null;

        if ($request->filled('variant_id')) {
            $variant = $product->variants()->whereKey($request->integer('variant_id'))->first();

            if ($variant === null) {
                throw ValidationException::withMessages(['variant_id' => 'Esa variante no es de este artículo.']);
            }
        } elseif ($product->has_variants) {
            throw ValidationException::withMessages(['variant_id' => 'Elige de qué variante es el movimiento.']);
        }

        $type = StockMovementType::from($request->string('type')->toString());

        $movement = $this->registerStockMovement->handle(
            $product,
            $variant,
            $type,
            $request->integer('quantity'),
            $request->input('note'),
        );

        $what = $variant === null ? $product->name : "{$product->name} ({$variant->label()})";

        return back()->with(
            'success',
            "Movimiento registrado en «{$what}» ({$type->label()}). Quedan {$movement->stock_after} unidades.",
        );
    }

    public function history(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $type = $request->string('extra')->toString();
        $productId = $request->integer('product');
        $product = $productId > 0 ? Product::withTrashed()->find($productId) : null;
        $timezone = Setting::current()->timezone;

        $filters = [
            'search' => '',
            'status' => '',
            'extra' => StockMovementType::tryFrom($type) !== null ? $type : '',
            'product' => $product === null ? '' : (string) $product->id,
            'trashed' => false,
            'sort' => 'created_at',
            'direction' => 'desc',
        ];

        $movements = StockMovement::query()
            ->with(['product:id,name,deleted_at', 'user:id,name,deleted_at'])
            ->when($product !== null, fn ($query) => $query->where('product_id', $product?->id))
            ->when($filters['extra'] !== '', fn ($query) => $query->where('type', $filters['extra']))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (StockMovement $movement): array => [
                'id' => $movement->id,
                'date' => $movement->created_at?->setTimezone($timezone)->format('d/m/Y H:i'),
                'product_id' => $movement->product_id,
                'product_name' => $movement->product->name ?? '—',
                'variant_label' => $movement->variant_label,
                'type_label' => $movement->type->label(),
                'quantity' => $movement->quantity,
                'stock_after' => $movement->stock_after,
                'note' => $movement->note,
                'user_name' => $movement->user?->name,
            ]);

        return Inertia::render('Tienda::Stock/History', [
            'movements' => $movements,
            'filters' => $filters,
            'product' => $product === null ? null : ['id' => $product->id, 'name' => $product->name],
            'typeOptions' => [['value' => '', 'label' => 'Todos'], ...StockMovementType::options()],
        ]);
    }
}

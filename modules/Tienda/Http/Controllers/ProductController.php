<?php

declare(strict_types=1);

namespace Modules\Tienda\Http\Controllers;

use App\Actions\Media\SyncMediaUsage;
use App\Actions\RecordActivity;
use App\Enums\ActivityEvent;
use App\Http\Controllers\Controller;
use App\Support\ImageConstraints;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Tienda\Actions\SaveProduct;
use Modules\Tienda\Http\Requests\ProductRequest;
use Modules\Tienda\Models\Product;
use Modules\Tienda\Models\ProductImage;
use Modules\Tienda\Models\ProductOption;
use Modules\Tienda\Models\ProductVariant;
use Modules\Tienda\Support\CategoryTree;
use Modules\Tienda\Support\Money;
use Modules\Tienda\Support\TiendaSettings;
use Modules\Tienda\Support\WhatsApp;

/**
 * Los artículos de la tienda.
 */
class ProductController extends Controller
{
    /** @var list<string> */
    private const SORTABLE = ['name', 'sku', 'price', 'stock', 'position', 'created_at'];

    /** @var list<string> */
    private const EXTRA = ['available', 'low', 'out_of_stock', 'on_sale', 'featured'];

    public function __construct(
        private readonly SaveProduct $saveProduct,
        private readonly RecordActivity $recordActivity,
        private readonly SyncMediaUsage $syncMediaUsage,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $tree = CategoryTree::current();
        $status = $request->string('status')->toString();
        $extra = $request->string('extra')->toString();
        $category = $request->integer('category');

        $filters = [
            'search' => trim((string) $request->string('search')),
            'status' => in_array($status, ['visible', 'hidden'], true) ? $status : '',
            'extra' => in_array($extra, self::EXTRA, true) ? $extra : '',
            'category' => $tree->find($category) !== null ? (string) $category : '',
            'trashed' => $request->boolean('trashed'),
            'sort' => in_array($request->string('sort')->toString(), self::SORTABLE, true)
                ? $request->string('sort')->toString()
                : 'position',
            'direction' => $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc',
        ];

        $products = Product::query()
            ->with(['images.media', 'variants', 'categories:id'])
            ->search($filters['search'])
            ->when($filters['status'] !== '', fn ($query) => $query->where('is_visible', $filters['status'] === 'visible'))
            ->when($filters['category'] !== '', fn ($query) => $query->inCategories($tree->descendantIds((int) $filters['category'])))
            ->when($filters['extra'] === 'available', fn ($query) => $query->inStock())
            ->when($filters['extra'] === 'low', fn ($query) => $query->lowStock())
            ->when($filters['extra'] === 'out_of_stock', fn ($query) => $query->outOfStock())
            ->when($filters['extra'] === 'on_sale', fn ($query) => $query->onSale())
            ->when($filters['extra'] === 'featured', fn ($query) => $query->where('is_featured', true))
            ->when($filters['trashed'], fn ($query) => $query->onlyTrashed())
            ->orderBy($filters['sort'], $filters['direction'])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Product $product): array => $this->toListItem($request, $product, $tree));

        return Inertia::render('Tienda::Products/Index', [
            'products' => $products,
            'filters' => $filters,
            'categoryOptions' => [['value' => '', 'label' => 'Todas'], ...self::plainOptions($tree)],
            'extraOptions' => [
                ['value' => '', 'label' => 'Todos'],
                ['value' => 'available', 'label' => 'Con existencias'],
                ['value' => 'low', 'label' => 'Quedan pocas unidades'],
                ['value' => 'out_of_stock', 'label' => 'Agotados'],
                ['value' => 'on_sale', 'label' => 'En promoción'],
                ['value' => 'featured', 'label' => 'Destacados'],
            ],
            'counts' => [
                'total' => Product::query()->count(),
                'visible' => Product::query()->public()->count(),
                'trashed' => Product::onlyTrashed()->count(),
                'outOfStock' => Product::query()->public()->outOfStock()->count(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Product::class);

        return Inertia::render('Tienda::Products/Create', [
            ...$this->formOptions($request),
            'nextPosition' => (int) Product::query()->max('position') + 1,
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $product = $this->saveProduct->handle(
            null,
            $request->validated(),
            $request->user()?->can('manageStock', Product::class) ?? false,
        );

        return redirect()
            ->route('tienda.products.edit', $product)
            ->with('success', "Artículo «{$product->name}» creado.");
    }

    public function edit(Request $request, Product $product): Response
    {
        $this->authorize('update', $product);

        $product->load(['images.media', 'options', 'variants', 'categories:id']);

        return Inertia::render('Tienda::Products/Edit', [
            ...$this->formOptions($request),
            'product' => $this->toFormItem($product),
            'whatsappUrl' => WhatsApp::forProduct($product),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $this->saveProduct->handle(
            $product,
            $request->validated(),
            $request->user()?->can('manageStock', $product) ?? false,
        );

        return back()->with('success', 'Artículo guardado.');
    }

    /**
     * Vuelve a generar la dirección a partir del nombre actual. Deja una
     * redirección desde la anterior.
     */
    public function regenerateSlug(Product $product): RedirectResponse
    {
        $this->authorize('regenerateSlug', $product);

        $previous = $product->slug;
        $product->regenerateSlug();

        if ($product->slug === $previous) {
            return back()->with('status', 'La dirección ya era la que corresponde al nombre.');
        }

        $this->recordActivity->handle(
            $product,
            ActivityEvent::Updated,
            "Dirección del artículo «{$product->name}» regenerada",
            ['slug' => [$previous, $product->slug]],
        );

        return back()->with('success', "Dirección actualizada a «{$product->slug}».");
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $name = $product->name;

        // Las fotos dejan de estar en uso: si no, la biblioteca no dejaría
        // borrarlas nunca por culpa de un artículo que está en la papelera.
        $this->syncMediaUsage->forget($product);
        $product->delete();

        $this->recordActivity->handle($product, ActivityEvent::Deleted, "Artículo «{$name}» enviado a la papelera");

        return back()->with('success', "Artículo «{$name}» enviado a la papelera.");
    }

    public function restore(Product $product): RedirectResponse
    {
        $this->authorize('restore', $product);

        $product->restore();

        foreach ($product->images()->get() as $image) {
            $this->syncMediaUsage->set($product, 'gallery-'.$image->position, $image->media_id);
        }

        foreach ($product->variants()->whereNotNull('media_id')->get() as $variant) {
            $this->syncMediaUsage->set($product, 'variant-'.$variant->id, $variant->media_id);
        }

        $this->recordActivity->handle($product, ActivityEvent::Restored, "Artículo «{$product->name}» restaurado");

        return back()->with('success', "Artículo «{$product->name}» restaurado.");
    }

    /**
     * Lo que necesitan los dos formularios.
     *
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        $tree = CategoryTree::current();

        return [
            'categoryOptions' => self::plainOptions($tree),
            'imageHint' => self::imageHint(),
            'canManageStock' => $request->user()?->can('manageStock', Product::class) ?? false,
            'defaultThreshold' => TiendaSettings::lowStockThreshold(),
            'currency' => TiendaSettings::currency(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private static function plainOptions(CategoryTree $tree): array
    {
        return array_map(
            fn (array $option): array => ['value' => $option['value'], 'label' => $option['label']],
            $tree->options(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toListItem(Request $request, Product $product, CategoryTree $tree): array
    {
        $user = $request->user();
        ['price' => $price, 'from' => $from] = $product->displayPrice();
        $status = $product->stockStatus();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'brand' => $product->brand,
            'category_label' => $product->category_id === null ? null : ($tree->label($product->category_id) ?: null),
            'price_label' => $price->formatted(),
            'previous_price_label' => $price->formattedPrevious(),
            'discount' => $price->discountPercentage(),
            'price_from' => $from,
            'has_variants' => $product->has_variants,
            'variants_count' => $product->variants->count(),
            'track_stock' => $product->track_stock,
            'stock' => $product->stock,
            'stock_status' => $status->value,
            'stock_label' => $status->label(),
            'stock_tone' => $status->tone(),
            'thumbnail_url' => $product->coverUrl('thumbnail'),
            'is_visible' => $product->is_visible,
            'is_featured' => $product->is_featured,
            'position' => $product->position,
            'deleted_at' => $product->deleted_at?->toIso8601String(),
            'can' => [
                'update' => $user?->can('update', $product) ?? false,
                'delete' => $user?->can('delete', $product) ?? false,
                'restore' => $user?->can('restore', $product) ?? false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toFormItem(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'is_slug_locked' => $product->is_slug_locked,
            'sku' => $product->sku ?? '',
            'brand' => $product->brand ?? '',
            'summary' => $product->summary ?? '',
            'description' => $product->description ?? '',
            'category_id' => $product->category_id === null ? '' : (string) $product->category_id,
            'category_ids' => $product->categories->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
            'price' => Money::forForm($product->price),
            'track_stock' => $product->track_stock,
            'stock' => $product->stock,
            'stock_loaded' => $product->stock,
            'low_stock_threshold' => $product->low_stock_threshold === null ? '' : (string) $product->low_stock_threshold,
            'is_visible' => $product->is_visible,
            'is_featured' => $product->is_featured,
            'position' => $product->position,
            'meta_title' => $product->meta_title ?? '',
            'meta_description' => $product->meta_description ?? '',
            'images' => $product->images->map(fn (ProductImage $image): array => [
                'media_id' => $image->media_id,
                'alt' => $image->alt ?? '',
                'url' => $image->url('thumbnail'),
            ])->all(),
            'options' => $product->options->map(fn (ProductOption $option): array => [
                'name' => $option->name,
                'values' => $option->values,
            ])->all(),
            'variants' => $product->variants->map(fn (ProductVariant $variant): array => [
                'id' => $variant->id,
                'values' => $variant->values(),
                'sku' => $variant->sku ?? '',
                'price' => Money::forForm($variant->price),
                'stock' => $variant->stock,
                'stock_loaded' => $variant->stock,
                'is_active' => $variant->is_active,
                'media_id' => $variant->media_id,
            ])->all(),
        ];
    }

    /**
     * Qué foto conviene subir, con los límites reales de la biblioteca.
     */
    private static function imageHint(): string
    {
        $largest = max(array_map('intval', array_values((array) config('media.variants', []))) ?: [1400]);

        return "Cuadradas y de al menos {$largest} px de ancho. La primera es la portada. "
            .ImageConstraints::for('library')->hint().'.';
    }
}

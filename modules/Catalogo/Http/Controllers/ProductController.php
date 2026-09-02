<?php

declare(strict_types=1);

namespace Modules\Catalogo\Http\Controllers;

use App\Actions\Media\SyncMediaUsage;
use App\Actions\RecordActivity;
use App\Enums\ActivityEvent;
use App\Http\Controllers\Controller;
use App\Support\ImageConstraints;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Catalogo\Actions\SaveProduct;
use Modules\Catalogo\Enums\ProductAvailability;
use Modules\Catalogo\Http\Requests\ProductRequest;
use Modules\Catalogo\Models\Product;

/**
 * Los productos del catálogo.
 *
 * Sin flujo editorial: un producto está visible o no lo está. Quien lo usa no
 * tiene que aprender estados, revisiones ni programaciones.
 */
class ProductController extends Controller
{
    /** @var list<string> */
    private const SORTABLE = ['name', 'reference', 'price', 'position', 'created_at'];

    public function __construct(
        private readonly SaveProduct $saveProduct,
        private readonly RecordActivity $recordActivity,
        private readonly SyncMediaUsage $syncMediaUsage,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $status = $request->string('status')->toString();
        $availability = $request->string('extra')->toString();

        $filters = [
            'search' => trim((string) $request->string('search')),
            'status' => in_array($status, ['visible', 'hidden'], true) ? $status : '',
            // El desplegable de disponibilidad; acepta también «rebajados»,
            // que no es un estado sino una pregunta sobre el precio.
            'extra' => in_array($availability, ['available', 'low', 'out_of_stock', 'on_sale'], true)
                ? $availability
                : '',
            'trashed' => $request->boolean('trashed'),
            'sort' => in_array($request->string('sort')->toString(), self::SORTABLE, true)
                ? $request->string('sort')->toString()
                : 'position',
            'direction' => $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc',
        ];

        $products = Product::query()
            ->with('image')
            ->search($filters['search'])
            ->when($filters['status'] !== '', fn ($query) => $query->where('is_visible', $filters['status'] === 'visible'))
            ->when($filters['extra'] === 'on_sale', fn ($query) => $query->onSale())
            ->when(
                $filters['extra'] !== '' && $filters['extra'] !== 'on_sale',
                fn ($query) => $query->where('availability', $filters['extra']),
            )
            ->when($filters['trashed'], fn ($query) => $query->onlyTrashed())
            ->orderBy($filters['sort'], $filters['direction'])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Product $product): array => $this->toListItem($request, $product));

        return Inertia::render('Catalogo::Products/Index', [
            'products' => $products,
            'filters' => $filters,
            'availabilityOptions' => [
                ['value' => '', 'label' => 'Cualquiera'],
                ...ProductAvailability::options(),
                ['value' => 'on_sale', 'label' => 'Rebajados'],
            ],
            'counts' => [
                'total' => Product::query()->count(),
                'visible' => Product::query()->public()->count(),
                'trashed' => Product::onlyTrashed()->count(),
                'outOfStock' => Product::query()
                    ->public()
                    ->where('availability', ProductAvailability::OutOfStock->value)
                    ->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Product::class);

        return Inertia::render('Catalogo::Products/Create', [
            'nextPosition' => (int) Product::query()->max('position') + 1,
            'availabilityOptions' => ProductAvailability::options(),
            'imageHint' => self::imageHint(),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $product = $this->saveProduct->handle(null, $request->validated());

        return redirect()
            ->route('products.index')
            ->with('success', "Producto «{$product->name}» creado.");
    }

    public function edit(Product $product): Response
    {
        $this->authorize('update', $product);

        return Inertia::render('Catalogo::Products/Edit', [
            'product' => $this->toFormItem($product),
            'availabilityOptions' => ProductAvailability::options(),
            'imageHint' => self::imageHint(),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $this->saveProduct->handle($product, $request->validated());

        return redirect()
            ->route('products.index')
            ->with('success', 'Producto actualizado correctamente.');
    }

    /**
     * Vuelve a generar la dirección a partir del nombre actual.
     *
     * Cambia una URL pública, así que es una acción explícita y deja una
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
            "Dirección del producto «{$product->name}» regenerada",
            ['slug' => $product->slug],
        );

        return back()->with('success', "Dirección actualizada a «{$product->slug}».");
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $name = $product->name;

        // El archivo deja de estar en uso: si no, la biblioteca no dejaría
        // borrarlo nunca por culpa de un producto que está en la papelera.
        $this->syncMediaUsage->forget($product);
        $product->delete();

        $this->recordActivity->handle($product, ActivityEvent::Deleted, "Producto «{$name}» enviado a la papelera");

        return back()->with('success', "Producto «{$name}» enviado a la papelera.");
    }

    public function restore(Product $product): RedirectResponse
    {
        $this->authorize('restore', $product);

        $product->restore();
        $this->syncMediaUsage->set($product, 'image', $product->image_media_id);

        $this->recordActivity->handle($product, ActivityEvent::Restored, "Producto «{$product->name}» restaurado");

        return back()->with('success', "Producto «{$product->name}» restaurado.");
    }

    /**
     * @return array<string, mixed>
     */
    private function toListItem(Request $request, Product $product): array
    {
        $user = $request->user();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'reference' => $product->reference,
            'summary' => $product->summary,
            'price_label' => $product->formattedPrice(),
            'previous_price_label' => $product->formattedPreviousPrice(),
            'discount' => $product->discountPercentage(),
            'availability' => $product->availability->value,
            'availability_label' => $product->availability->label(),
            'availability_tone' => $product->availability->tone(),
            'thumbnail_url' => $product->imageUrl('thumbnail'),
            'is_visible' => $product->is_visible,
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
     * Qué imagen conviene subir, con los límites reales de la biblioteca.
     *
     * El ancho recomendado es el de la variante más grande: el panel nunca
     * agranda una imagen, así que por debajo de esa medida la web sirve el
     * original y se ve peor de lo que podría.
     */
    private static function imageHint(): string
    {
        $largest = max(array_map('intval', array_values((array) config('media.variants', []))) ?: [1400]);

        return "Cuadrada y de al menos {$largest} px de ancho: es el tamaño que sirve la web, "
            .'y por debajo de ahí no se agranda. '
            .ImageConstraints::for('library')->hint().'.';
    }

    /**
     * El precio en el formulario se escribe como se lee.
     */
    private static function amountForForm(?string $amount): string
    {
        return $amount === null ? '' : number_format((float) $amount, 2, ',', '');
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
            'reference' => $product->reference,
            'summary' => $product->summary,
            'description' => $product->description,
            'price' => self::amountForForm($product->price),
            'sale_price' => self::amountForForm($product->sale_price),
            'availability' => $product->availability->value,
            'image_media_id' => $product->image_media_id,
            'image_alt' => $product->image_alt,
            'image_url' => $product->imageUrl('medium'),
            'is_visible' => $product->is_visible,
            'position' => $product->position,
            'is_slug_locked' => $product->is_slug_locked,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Tienda\Models;

use App\Models\Concerns\HasAutomaticSlug;
use App\Models\Concerns\InvalidatesApiCache;
use App\Models\Concerns\RecordsSlugRedirects;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Tienda\Database\Factories\ProductFactory;
use Modules\Tienda\Enums\StockStatus;
use Modules\Tienda\Support\Price;
use Modules\Tienda\Support\PromotionResolver;
use Modules\Tienda\Support\TiendaSettings;

/**
 * Un artículo de la tienda.
 *
 * @property int $id
 * @property int|null $category_id
 * @property string $name
 * @property string $slug
 * @property bool $is_slug_locked
 * @property string|null $sku
 * @property string|null $brand
 * @property string|null $summary
 * @property string|null $description
 * @property string|null $price
 * @property bool $has_variants
 * @property bool $track_stock
 * @property int $stock
 * @property int|null $low_stock_threshold
 * @property bool $is_visible
 * @property bool $is_featured
 * @property int $position
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Category|null $category
 * @property-read Collection<int, Category> $categories
 * @property-read Collection<int, ProductImage> $images
 * @property-read Collection<int, ProductOption> $options
 * @property-read Collection<int, ProductVariant> $variants
 */
class Product extends Model
{
    use HasAutomaticSlug;

    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use InvalidatesApiCache;
    use RecordsSlugRedirects;
    use SoftDeletes;

    protected $table = 'shop_products';

    /** @var list<string> */
    protected $fillable = [
        'category_id',
        'name',
        'sku',
        'brand',
        'summary',
        'description',
        'price',
        'has_variants',
        'track_stock',
        'low_stock_threshold',
        'is_visible',
        'is_featured',
        'position',
        'meta_title',
        'meta_description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'has_variants' => 'boolean',
            'track_stock' => 'boolean',
            'stock' => 'integer',
            'low_stock_threshold' => 'integer',
            'is_visible' => 'boolean',
            'is_featured' => 'boolean',
            'is_slug_locked' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Categorías adicionales, además de la principal.
     *
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'shop_category_product');
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    /**
     * @return HasMany<ProductOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class)->orderBy('position');
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        // `chaperone` deja puesta en cada variante su artículo al cargarlas:
        // el estado de existencias de una variante necesita el umbral del
        // artículo y, sin esto, lo pediría con una consulta por variante.
        return $this->hasMany(ProductVariant::class)->orderBy('position')->chaperone('product');
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * @return BelongsToMany<Promotion, $this>
     */
    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class, 'shop_promotion_product');
    }

    /**
     * La principal y las adicionales.
     *
     * @return list<int>
     */
    public function categoryIds(): array
    {
        $ids = $this->relationLoaded('categories') || $this->exists
            ? $this->categories->pluck('id')->all()
            : [];

        if ($this->category_id !== null) {
            $ids[] = $this->category_id;
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    public function cover(): ?ProductImage
    {
        return $this->images->first();
    }

    public function coverUrl(string $size = 'medium'): ?string
    {
        return $this->cover()?->url($size);
    }

    /**
     * @return Collection<int, ProductVariant>
     */
    public function activeVariants(): Collection
    {
        return $this->variants->filter(fn (ProductVariant $variant): bool => $variant->is_active)->values();
    }

    /*
    |--------------------------------------------------------------------------
    | Existencias
    |--------------------------------------------------------------------------
    */

    public function lowStockThreshold(): int
    {
        return $this->low_stock_threshold ?? TiendaSettings::lowStockThreshold();
    }

    /**
     * Sin control de stock un artículo siempre está disponible: es lo que se
     * fabrica por encargo o lo que nunca se acaba.
     */
    public function stockStatus(): StockStatus
    {
        if (! $this->track_stock) {
            return StockStatus::Available;
        }

        return StockStatus::for($this->stock, $this->lowStockThreshold());
    }

    /**
     * Vuelve a sumar las variantes activas en `stock`.
     *
     * Se llama cada vez que cambia una variante. Se guarda con `save()` y no
     * en silencio a propósito: el número cambia lo que sirve la API y su caché
     * tiene que enterarse.
     */
    public function refreshStockFromVariants(): void
    {
        if (! $this->has_variants) {
            return;
        }

        $this->stock = (int) $this->variants()->where('is_active', true)->sum('stock');
        $this->save();
    }

    /*
    |--------------------------------------------------------------------------
    | Precio
    |--------------------------------------------------------------------------
    */

    /**
     * Lo que se enseña en un listado.
     *
     * Con variantes de precios distintos es el más barato, y `from` avisa para
     * que la web escriba «desde». Las variantes desactivadas no cuentan.
     *
     * @return array{price: Price, from: bool}
     */
    public function displayPrice(): array
    {
        if (! $this->has_variants) {
            return ['price' => Price::of($this), 'from' => false];
        }

        $prices = $this->activeVariants()
            ->map(fn (ProductVariant $variant): Price => Price::of($this, $variant))
            ->filter(fn (Price $price): bool => $price->final !== null)
            ->sortBy(fn (Price $price): float => (float) $price->final)
            ->values();

        if ($prices->isEmpty()) {
            return ['price' => Price::of($this), 'from' => false];
        }

        /** @var Price $cheapest */
        $cheapest = $prices->first();
        $distinct = $prices->map(fn (Price $price): float => (float) $price->final)->unique()->count();

        return ['price' => $cheapest, 'from' => $distinct > 1];
    }

    /*
    |--------------------------------------------------------------------------
    | Consultas
    |--------------------------------------------------------------------------
    */

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    /**
     * Agotados: con control de stock y sin unidades.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('track_stock', true)->where('stock', '<=', 0);
    }

    /**
     * Con unidades, pero por debajo de su umbral.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('track_stock', true)
            ->where('stock', '>', 0)
            ->whereRaw('stock <= COALESCE(low_stock_threshold, ?)', [TiendaSettings::lowStockThreshold()]);
    }

    /**
     * Lo que se puede pedir hoy.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeInStock(Builder $query): Builder
    {
        return $query->where(fn (Builder $inner) => $inner
            ->where('track_stock', false)
            ->orWhere('stock', '>', 0));
    }

    /**
     * En alguna de estas categorías, como principal o como adicional.
     *
     * @param  Builder<Product>  $query
     * @param  list<int>  $ids
     * @return Builder<Product>
     */
    public function scopeInCategories(Builder $query, array $ids): Builder
    {
        return $query->where(fn (Builder $inner) => $inner
            ->whereIn('category_id', $ids)
            ->orWhereHas('categories', fn (Builder $categories) => $categories->whereIn('shop_categories.id', $ids)));
    }

    /**
     * Los que tienen alguna promoción en vigor.
     *
     * Solo los que tienen precio: sin precio no hay nada que rebajar.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeOnSale(Builder $query): Builder
    {
        $reach = PromotionResolver::current()->reach();

        $query->where(fn (Builder $priced) => $priced
            ->whereNotNull('price')
            ->orWhereHas('variants', fn (Builder $variants) => $variants->whereNotNull('price')));

        if ($reach['all']) {
            return $query;
        }

        if ($reach['categories'] === [] && $reach['products'] === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(fn (Builder $inner) => $inner
            ->whereIn('id', $reach['products'] ?: [0])
            ->orWhere(fn (Builder $byCategory) => $byCategory->inCategories($reach['categories'] ?: [0])));
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(fn (Builder $inner) => $inner
            ->where('name', 'like', $like)
            ->orWhere('sku', 'like', $like)
            ->orWhere('brand', 'like', $like)
            ->orWhere('summary', 'like', $like)
            ->orWhereHas('variants', fn (Builder $variants) => $variants->where('sku', 'like', $like)));
    }
}

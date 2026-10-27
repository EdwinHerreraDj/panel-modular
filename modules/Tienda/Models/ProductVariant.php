<?php

declare(strict_types=1);

namespace Modules\Tienda\Models;

use App\Models\Concerns\InvalidatesApiCache;
use App\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Tienda\Enums\StockStatus;

/**
 * Una combinación concreta de un artículo: la camiseta roja en talla M.
 *
 * @property int $id
 * @property int $product_id
 * @property string|null $option1
 * @property string|null $option2
 * @property string|null $option3
 * @property string|null $sku
 * @property string|null $price
 * @property int $stock
 * @property int|null $media_id
 * @property bool $is_active
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 * @property-read Media|null $media
 */
class ProductVariant extends Model
{
    use InvalidatesApiCache;

    protected $table = 'shop_product_variants';

    /** @var list<string> */
    protected $fillable = [
        'option1',
        'option2',
        'option3',
        'sku',
        'price',
        'media_id',
        'is_active',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stock' => 'integer',
            'media_id' => 'integer',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /**
     * Los valores, en el orden de las opciones.
     *
     * @return list<string>
     */
    public function values(): array
    {
        return array_values(array_filter(
            [$this->option1, $this->option2, $this->option3],
            fn (?string $value): bool => $value !== null && $value !== '',
        ));
    }

    /**
     * «M / Rojo».
     */
    public function label(): string
    {
        return implode(' / ', $this->values());
    }

    public function stockStatus(): StockStatus
    {
        if (! $this->product->track_stock) {
            return StockStatus::Available;
        }

        return StockStatus::for($this->is_active ? $this->stock : 0, $this->product->lowStockThreshold());
    }
}

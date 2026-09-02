<?php

declare(strict_types=1);

namespace Modules\Catalogo\Models;

use App\Models\Concerns\HasAutomaticSlug;
use App\Models\Concerns\InvalidatesApiCache;
use App\Models\Concerns\RecordsSlugRedirects;
use App\Models\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Catalogo\Database\Factories\ProductFactory;
use Modules\Catalogo\Enums\ProductAvailability;

/**
 * Un producto del catálogo.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_slug_locked
 * @property string|null $reference
 * @property string|null $summary
 * @property string|null $description
 * @property string|null $price
 * @property string|null $sale_price
 * @property ProductAvailability $availability
 * @property int|null $image_media_id
 * @property string|null $image_alt
 * @property bool $is_visible
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Media|null $image
 */
class Product extends Model
{
    use HasAutomaticSlug;

    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use InvalidatesApiCache;
    use RecordsSlugRedirects;
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'reference',
        'summary',
        'description',
        'price',
        'sale_price',
        'availability',
        'image_alt',
        'is_visible',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'availability' => ProductAvailability::class,
            'is_visible' => 'boolean',
            'is_slug_locked' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_media_id');
    }

    public function imageUrl(string $size = 'medium'): ?string
    {
        return $this->image?->variantUrl($size);
    }

    /**
     * ¿Está rebajado ahora mismo?
     *
     * Solo si hay las dos cifras y la rebajada es menor: un «precio rebajado»
     * más alto que el normal no es una oferta, es una errata.
     */
    public function isOnSale(): bool
    {
        return $this->price !== null
            && $this->sale_price !== null
            && (float) $this->sale_price < (float) $this->price;
    }

    /**
     * Lo que se paga hoy.
     */
    public function currentPrice(): ?string
    {
        return $this->isOnSale() ? $this->sale_price : $this->price;
    }

    /**
     * Lo que costaba antes, solo si está rebajado.
     */
    public function previousPrice(): ?string
    {
        return $this->isOnSale() ? $this->price : null;
    }

    /**
     * Cuánto se ahorra, redondeado. Para el «-20 %» de la web.
     */
    public function discountPercentage(): ?int
    {
        if (! $this->isOnSale()) {
            return null;
        }

        $before = (float) $this->price;

        return (int) round(($before - (float) $this->sale_price) / $before * 100);
    }

    /**
     * Precio con formato, o null si no lo tiene.
     */
    public function formattedPrice(): ?string
    {
        return self::format($this->currentPrice());
    }

    /**
     * El precio tachado, si lo hay.
     */
    public function formattedPreviousPrice(): ?string
    {
        return self::format($this->previousPrice());
    }

    private static function format(?string $amount): ?string
    {
        return $amount === null ? null : number_format((float) $amount, 2, ',', '.').' €';
    }

    /**
     * Lo que ve la web pública.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    /**
     * Los que están rebajados ahora mismo.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeOnSale(Builder $query): Builder
    {
        return $query->whereNotNull('sale_price')
            ->whereNotNull('price')
            ->whereColumn('sale_price', '<', 'price');
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

        return $query->where(function (Builder $inner) use ($like): void {
            $inner->where('name', 'like', $like)
                ->orWhere('reference', 'like', $like)
                ->orWhere('summary', 'like', $like);
        });
    }
}

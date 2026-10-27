<?php

declare(strict_types=1);

namespace Modules\Tienda\Models;

use App\Models\Concerns\InvalidatesApiCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Modules\Tienda\Database\Factories\PromotionFactory;
use Modules\Tienda\Enums\PromotionScope;
use Modules\Tienda\Enums\PromotionType;
use Modules\Tienda\Support\Money;
use Modules\Tienda\Support\PromotionResolver;

/**
 * Una promoción: «Rebajas de verano, -20 % en Ropa, del 1 al 31 de julio».
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property PromotionType $type
 * @property string $value
 * @property PromotionScope $scope
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Category> $categories
 * @property-read Collection<int, Product> $products
 */
class Promotion extends Model
{
    /** @use HasFactory<PromotionFactory> */
    use HasFactory;

    use InvalidatesApiCache;

    protected $table = 'shop_promotions';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'description',
        'type',
        'value',
        'scope',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => PromotionResolver::forget());
        static::deleted(fn () => PromotionResolver::forget());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PromotionType::class,
            'scope' => PromotionScope::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'shop_promotion_category');
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'shop_promotion_product');
    }

    /**
     * Las que están en vigor ahora mismo.
     *
     * @param  Builder<Promotion>  $query
     * @return Builder<Promotion>
     */
    public function scopeRunning(Builder $query): Builder
    {
        $now = now();

        return $query->where('is_active', true)
            ->where(fn (Builder $start) => $start->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $end) => $end->whereNull('ends_at')->orWhere('ends_at', '>', $now));
    }

    /**
     * En qué punto está, para el distintivo del listado.
     *
     * @return array{value: string, label: string, tone: string}
     */
    public function status(): array
    {
        $now = now();

        return match (true) {
            ! $this->is_active => ['value' => 'inactive', 'label' => 'Desactivada', 'tone' => 'neutral'],
            $this->ends_at !== null && $this->ends_at->lte($now) => ['value' => 'ended', 'label' => 'Terminada', 'tone' => 'neutral'],
            $this->starts_at !== null && $this->starts_at->gt($now) => ['value' => 'scheduled', 'label' => 'Programada', 'tone' => 'info'],
            default => ['value' => 'running', 'label' => 'En vigor', 'tone' => 'success'],
        };
    }

    /**
     * «-20 %» o «-5,00 €».
     */
    public function valueLabel(): string
    {
        return $this->type === PromotionType::Percentage
            ? '-'.rtrim(rtrim(number_format((float) $this->value, 2, ',', ''), '0'), ',').' %'
            : '-'.Money::format($this->value);
    }
}

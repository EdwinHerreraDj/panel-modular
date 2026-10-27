<?php

declare(strict_types=1);

namespace Modules\Tienda\Models;

use App\Models\Concerns\HasAutomaticSlug;
use App\Models\Concerns\InvalidatesApiCache;
use App\Models\Concerns\RecordsSlugRedirects;
use App\Models\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Tienda\Database\Factories\CategoryFactory;
use Modules\Tienda\Support\CategoryTree;

/**
 * Categoría de la tienda. Puede colgar de otra.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property bool $is_slug_locked
 * @property string|null $description
 * @property int|null $image_media_id
 * @property bool $is_visible
 * @property int $position
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Category|null $parent
 * @property-read Media|null $image
 */
class Category extends Model
{
    use HasAutomaticSlug;

    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    use InvalidatesApiCache;
    use RecordsSlugRedirects;
    use SoftDeletes;

    protected $table = 'shop_categories';

    /** @var list<string> */
    protected $fillable = [
        'parent_id',
        'name',
        'description',
        'image_media_id',
        'is_visible',
        'position',
        'meta_title',
        'meta_description',
    ];

    protected static function booted(): void
    {
        // El árbol de la petición deja de ser fiel en cuanto cambia una rama.
        static::saved(fn () => CategoryTree::forget());
        static::deleted(fn () => CategoryTree::forget());
        static::restored(fn () => CategoryTree::forget());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'is_slug_locked' => 'boolean',
            'position' => 'integer',
            'parent_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Artículos que la tienen como categoría principal.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
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
     * @param  Builder<Category>  $query
     * @return Builder<Category>
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
            ->orWhere('description', 'like', $like));
    }
}

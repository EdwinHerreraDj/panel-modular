<?php

declare(strict_types=1);

namespace Modules\Blog\Models;

use App\Models\Concerns\HasAutomaticSlug;
use App\Models\Concerns\InvalidatesApiCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Blog\Database\Factories\TagFactory;

/**
 * Etiqueta del blog.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_slug_locked
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Tag extends Model
{
    use HasAutomaticSlug;

    /** @use HasFactory<TagFactory> */
    use HasFactory;

    use InvalidatesApiCache;
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['name'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_slug_locked' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Article, $this>
     */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class);
    }

    /**
     * @param  Builder<Tag>  $query
     * @return Builder<Tag>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function (Builder $inner) use ($like): void {
            $inner->where('name', 'like', $like)->orWhere('slug', 'like', $like);
        });
    }
}

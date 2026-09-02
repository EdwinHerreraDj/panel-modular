<?php

declare(strict_types=1);

namespace Modules\Blog\Models;

use App\Enums\RevisionType;
use App\Models\Concerns\HasAutomaticSlug;
use App\Models\Concerns\InvalidatesApiCache;
use App\Models\Concerns\RecordsSlugRedirects;
use App\Models\Media;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Blog\Database\Factories\ArticleFactory;
use Modules\Blog\Enums\ArticleStatus;
use Modules\Blog\Enums\ArticleVisibility;

/**
 * Artículo del blog.
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property string|null $content
 * @property string|null $content_text
 * @property int|null $featured_media_id
 * @property string|null $featured_image_alt
 * @property int|null $author_id
 * @property ArticleStatus $status
 * @property ArticleVisibility $visibility
 * @property Carbon|null $published_at
 * @property Carbon|null $scheduled_for
 * @property int $reading_minutes
 * @property int $position
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $canonical_url
 * @property string|null $og_title
 * @property string|null $og_description
 * @property string|null $og_image_path
 * @property bool $is_indexable
 * @property bool $is_slug_locked
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User|null $author
 * @property-read Revision|null $autosave
 * @property-read Media|null $featuredMedia
 */
class Article extends Model
{
    use HasAutomaticSlug;

    /** @use HasFactory<ArticleFactory> */
    use HasFactory;

    use InvalidatesApiCache;
    use RecordsSlugRedirects;
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'title',
        'excerpt',
        'content',
        'featured_image_alt',
        'author_id',
        'status',
        'visibility',
        'published_at',
        'scheduled_for',
        'position',
        'meta_title',
        'meta_description',
        'canonical_url',
        'og_title',
        'og_description',
        'is_indexable',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ArticleStatus::class,
            'visibility' => ArticleVisibility::class,
            'published_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'reading_minutes' => 'integer',
            'position' => 'integer',
            'is_indexable' => 'boolean',
            'is_slug_locked' => 'boolean',
        ];
    }

    /**
     * El slug se deriva del título, no del nombre.
     */
    public function slugSource(): string
    {
        return 'title';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Artículos recomendados desde este. La relación es dirigida.
     *
     * @return BelongsToMany<Article, $this>
     */
    public function related(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_related', 'article_id', 'related_article_id')
            ->withPivot('position')
            ->orderBy('article_related.position');
    }

    /**
     * @return MorphMany<Revision, $this>
     */
    public function revisions(): MorphMany
    {
        return $this->morphMany(Revision::class, 'revisionable')->latest('created_at');
    }

    /**
     * Copia automática pendiente, si la hay.
     *
     * @return MorphOne<Revision, $this>
     */
    public function autosave(): MorphOne
    {
        return $this->morphOne(Revision::class, 'revisionable')
            ->where('type', RevisionType::Autosave->value)
            ->latestOfMany('created_at');
    }

    public function isPublished(): bool
    {
        return $this->status === ArticleStatus::Published;
    }

    /**
     * ¿Lo escribió esta persona?
     */
    public function belongsToAuthor(User $user): bool
    {
        return $this->author_id === $user->getKey();
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function featuredMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_media_id');
    }

    /**
     * URL de la imagen destacada en el tamaño indicado.
     */
    public function featuredImageUrl(string $size = 'large'): ?string
    {
        return $this->featuredMedia?->variantUrl($size);
    }

    /**
     * Artículos visibles en la web pública.
     *
     * @param  Builder<Article>  $query
     * @return Builder<Article>
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query
            ->where('status', ArticleStatus::Published->value)
            ->where('visibility', ArticleVisibility::Public->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * @param  Builder<Article>  $query
     * @return Builder<Article>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function (Builder $inner) use ($like): void {
            $inner->where('title', 'like', $like)
                ->orWhere('excerpt', 'like', $like)
                ->orWhere('content_text', 'like', $like);
        });
    }
}

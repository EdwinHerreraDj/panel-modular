<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MediaProcessingStatus;
use App\Models\Concerns\InvalidatesApiCache;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Archivo de la biblioteca multimedia.
 *
 * @property int $id
 * @property string $name
 * @property string|null $alt_text
 * @property string|null $description
 * @property string $disk
 * @property string $path
 * @property string $mime_type
 * @property string $extension
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property array<string, string>|null $variants
 * @property MediaProcessingStatus $processing_status
 * @property string|null $processing_error
 * @property int|null $uploaded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User|null $uploader
 * @property-read int|null $usages_count
 */
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    use InvalidatesApiCache;
    use SoftDeletes;

    protected $table = 'media';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'alt_text',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'variants' => 'array',
            'processing_status' => MediaProcessingStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return HasMany<MediaUsage, $this>
     */
    public function usages(): HasMany
    {
        return $this->hasMany(MediaUsage::class);
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * URL de una variante, con reserva al original si no existe.
     *
     * @param  string  $size  thumbnail, medium o large.
     */
    public function variantUrl(string $size, bool $preferWebp = false): string
    {
        $variants = $this->variants ?? [];

        $keys = $preferWebp ? ["{$size}.webp", $size] : [$size, "{$size}.webp"];

        foreach ($keys as $key) {
            if (isset($variants[$key])) {
                return Storage::disk($this->disk)->url($variants[$key]);
            }
        }

        return $this->url();
    }

    /**
     * ¿Está en uso en alguna parte?
     */
    public function isInUse(): bool
    {
        return $this->usages()->exists();
    }

    /**
     * Tamaño legible del archivo.
     */
    public function humanSize(): string
    {
        $bytes = $this->size;

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024).' KB';
        }

        return round($bytes / (1024 * 1024), 1).' MB';
    }

    /**
     * Todas las rutas del archivo: original y variantes.
     *
     * @return list<string>
     */
    public function allPaths(): array
    {
        return array_values(array_unique([$this->path, ...array_values($this->variants ?? [])]));
    }

    /**
     * @param  Builder<Media>  $query
     * @return Builder<Media>
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
                ->orWhere('alt_text', 'like', $like)
                ->orWhere('description', 'like', $like);
        });
    }
}

<?php

declare(strict_types=1);

namespace Modules\Tienda\Models;

use App\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una foto de la galería de un artículo. La primera es la portada.
 *
 * @property int $id
 * @property int $product_id
 * @property int $media_id
 * @property string|null $alt
 * @property int $position
 * @property-read Media|null $media
 */
class ProductImage extends Model
{
    protected $table = 'shop_product_images';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['media_id', 'alt', 'position'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'media_id' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function url(string $size = 'medium'): ?string
    {
        return $this->media?->variantUrl($size);
    }
}

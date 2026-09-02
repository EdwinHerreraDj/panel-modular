<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\ModuleRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Registro de dónde se usa un archivo de la biblioteca.
 *
 * Sirve para avisar antes de eliminar algo que está publicado, en lugar de
 * dejar imágenes rotas repartidas por el blog.
 *
 * @property int $id
 * @property int $media_id
 * @property string $usable_type
 * @property int $usable_id
 * @property string $role
 */
class MediaUsage extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'media_id',
        'usable_type',
        'usable_id',
        'role',
    ];

    /**
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function usable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Usos cuyo tipo sigue existiendo en esta instalación.
     *
     * Un archivo puede tener registrado un uso de un módulo que ya no está
     * activo. Resolver esa relación polimórfica intentaría instanciar una clase
     * inexistente y tumbaría la ficha del archivo, así que esos usos se dejan
     * fuera al consultar.
     *
     * @param  Builder<MediaUsage>  $query
     * @return Builder<MediaUsage>
     */
    public function scopeResolvable(Builder $query): Builder
    {
        return $query->whereIn('usable_type', array_keys(app(ModuleRegistry::class)->morphMap()));
    }

    /**
     * Nombre legible del elemento que usa el archivo.
     *
     * Lo decide el tipo de contenido que declara el núcleo o el módulo, no un
     * `match` con las clases dentro: así un módulo nuevo se describe solo.
     */
    public function describeUsable(): string
    {
        $usable = $this->usable;

        if ($usable === null) {
            return 'Elemento eliminado';
        }

        return app(ModuleRegistry::class)->contentType($this->usable_type)?->describeOne($usable)
            ?? 'Elemento eliminado';
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'featured' => 'Imagen destacada',
            'logo' => 'Logotipo',
            'logo_dark' => 'Logotipo (modo oscuro)',
            'favicon' => 'Favicon',
            'og_image' => 'Imagen para redes',
            default => 'Contenido',
        };
    }
}

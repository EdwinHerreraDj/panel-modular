<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\InvalidatesApiCache;
use App\Modules\ModuleRegistry;
use Database\Factories\RedirectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Redirección de una dirección antigua a la actual.
 *
 * @property int $id
 * @property string $from_path
 * @property string $to_path
 * @property int $status_code
 * @property bool $is_automatic
 * @property string|null $redirectable_type
 * @property int|null $redirectable_id
 * @property int $hits
 * @property Carbon|null $last_hit_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|null $redirectable
 */
class Redirect extends Model
{
    /** @use HasFactory<RedirectFactory> */
    use HasFactory;

    use InvalidatesApiCache;

    /** @var list<string> */
    protected $fillable = [
        'from_path',
        'to_path',
        'status_code',
        'is_automatic',
        'redirectable_type',
        'redirectable_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'is_automatic' => 'boolean',
            'hits' => 'integer',
            'last_hit_at' => 'datetime',
        ];
    }

    /**
     * Redirecciones cuyo destino sigue existiendo en esta instalación.
     *
     * Si un módulo se desactiva, sus redirecciones se quedan en la tabla —y
     * deben quedarse, porque la web pública las sigue necesitando— pero
     * resolver la relación intentaría instanciar una clase que ya no existe.
     *
     * @param  Builder<Redirect>  $query
     * @return Builder<Redirect>
     */
    public function scopeResolvable(Builder $query): Builder
    {
        return $query->whereIn('redirectable_type', array_keys(app(ModuleRegistry::class)->morphMap()));
    }

    /**
     * A qué apunta la redirección, cuando apunta a algo del panel.
     *
     * Es polimórfica a propósito: un producto de un catálogo cambia de
     * dirección y rompe enlaces igual que un artículo.
     *
     * @return MorphTo<Model, $this>
     */
    public function redirectable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Normaliza una dirección para poder compararla.
     *
     * Sin barras sobrantes, sin cadena de consulta y en minúsculas: es la única
     * forma de que «/Blog/Mi-Post/» y «blog/mi-post» se reconozcan como la
     * misma. Las URL externas se dejan tal cual.
     */
    public static function normalize(string $path): string
    {
        $path = trim($path);

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return rtrim($path, '/');
        }

        $path = (string) preg_replace('/\?.*$/', '', $path);

        return Str::lower(trim($path, "/ \t\n\r\0\x0B"));
    }

    public function isExternal(): bool
    {
        return Str::startsWith($this->to_path, ['http://', 'https://']);
    }

    /**
     * Deja constancia de que se ha usado.
     *
     * Sirve para saber qué redirecciones siguen recibiendo visitas y cuáles se
     * pueden borrar ya. Se escribe sin tocar `updated_at`: es contabilidad, no
     * una modificación.
     */
    public function recordHit(): void
    {
        self::query()->whereKey($this->getKey())->update([
            'hits' => $this->hits + 1,
            'last_hit_at' => now(),
        ]);
    }
}

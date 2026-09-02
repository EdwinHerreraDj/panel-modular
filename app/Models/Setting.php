<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\InvalidatesApiCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Configuración del sitio. Siempre existe una única fila.
 *
 * Lo que hay aquí vale para toda la instalación: cómo se llama, su marca y su
 * SEO por defecto. Lo que solo entiende un módulo vive en `module_settings`.
 *
 * Se consulta en prácticamente todas las páginas y en cada respuesta de la API
 * pública, así que se cachea y la caché se invalida al guardar.
 *
 * @property int $id
 * @property string $site_name
 * @property string|null $tagline
 * @property string|null $public_url
 * @property string $locale
 * @property string $timezone
 * @property string|null $logo_path
 * @property string|null $logo_dark_path
 * @property string|null $favicon_path
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string $title_template
 * @property bool $is_indexable
 * @property string|null $og_image_path
 * @property string $twitter_card
 * @property string|null $organization_name
 * @property array<int, array{network: string, url: string}>|null $social_profiles
 * @property Carbon|null $updated_at
 */
class Setting extends Model
{
    use InvalidatesApiCache;

    public const CACHE_KEY = 'settings.current';

    /** @var list<string> */
    protected $fillable = [
        'site_name',
        'tagline',
        'public_url',
        'locale',
        'timezone',
        'logo_path',
        'logo_dark_path',
        'favicon_path',
        'meta_title',
        'meta_description',
        'title_template',
        'is_indexable',
        'og_image_path',
        'twitter_card',
        'organization_name',
        'social_profiles',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_indexable' => 'boolean',
            'social_profiles' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * Configuración vigente, creándola con valores por defecto la primera vez.
     *
     * Lo que se cachea son los atributos en bruto, no el modelo: los almacenes
     * que serializan (base de datos, Redis, archivo) no pueden reconstruir con
     * garantías un objeto Eloquent, y al cambiar la clase devolvían un
     * `__PHP_Incomplete_Class`.
     */
    public static function current(): self
    {
        /** @var array<string, mixed> $attributes */
        $attributes = Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => (self::query()->first() ?? self::createDefault())->getRawOriginal(),
        );

        return (new self)->newFromBuilder($attributes);
    }

    private static function createDefault(): self
    {
        $setting = self::query()->create([
            'site_name' => (string) config('app.name'),
            'locale' => (string) config('app.locale', 'es'),
            'timezone' => (string) config('app.timezone', 'UTC'),
        ]);

        // El resto de columnas tienen valor por defecto en la base de datos:
        // se recargan para que la instancia devuelta los tenga también.
        $setting->refresh();

        return $setting;
    }

    /**
     * URL pública de una imagen de la configuración.
     */
    public function imageUrl(?string $path): ?string
    {
        return $path === null ? null : Storage::disk('public')->url($path);
    }

    /**
     * Título de una página aplicando la plantilla configurada.
     */
    public function formatTitle(?string $title): string
    {
        if ($title === null || trim($title) === '') {
            return $this->meta_title ?? $this->site_name;
        }

        return str_replace(
            [':title', ':blog'],
            [$title, $this->site_name],
            $this->title_template,
        );
    }
}

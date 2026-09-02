<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * Los datos del sitio tal y como los recibe la web pública.
 *
 * Están aquí y no en un controlador porque los sirven dos endpoints: `/sitio`,
 * que es el bueno, y `/blog`, que se mantiene para no romper las webs que ya
 * estaban conectadas antes de que esto dejara de ser solo un blog.
 */
final class SitePayload
{
    /**
     * @return array<string, mixed>
     */
    public static function build(): array
    {
        $settings = Setting::current();

        return [
            'nombre' => $settings->site_name,
            'lema' => $settings->tagline,
            'url_publica' => $settings->public_url,
            'idioma' => $settings->locale,
            'logo' => self::url($settings->logo_path),
            'logo_oscuro' => self::url($settings->logo_dark_path),
            'favicon' => self::url($settings->favicon_path),
            'seo' => [
                'titulo' => $settings->meta_title ?: $settings->site_name,
                'descripcion' => $settings->meta_description,
                'plantilla_titulo' => $settings->title_template,
                'indexable' => $settings->is_indexable,
                'og_imagen' => self::url($settings->og_image_path),
                'twitter_card' => $settings->twitter_card,
            ],
            'organizacion' => [
                'nombre' => $settings->organization_name ?: $settings->site_name,
                'redes' => $settings->social_profiles ?? [],
            ],
        ];
    }

    private static function url(?string $path): ?string
    {
        return $path === null || $path === '' ? null : Storage::disk(config('media.disk'))->url($path);
    }
}

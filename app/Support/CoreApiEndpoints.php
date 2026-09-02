<?php

declare(strict_types=1);

namespace App\Support;

use App\Modules\Values\ApiEndpoint;

/**
 * Endpoints de la API que no dependen del tipo de contenido.
 *
 * El estado y el sitemap se quedan aquí aunque hablen de artículos: lo que
 * cuentan y lo que listan lo aportan los módulos activos, así que un cliente
 * con catálogo y sin blog sigue teniendo las dos cosas, con sus productos
 * dentro. Partirlos por módulo obligaría a la web pública a saber qué módulos
 * hay instalados para montar su sitemap.
 *
 * Las posiciones van de diez en diez y dejan libre de la 30 a la 60, que es
 * donde se coloca el contenido.
 */
final class CoreApiEndpoints
{
    /**
     * @return list<ApiEndpoint>
     */
    public static function all(): array
    {
        return [
            new ApiEndpoint(
                key: 'estado',
                path: 'estado',
                title: 'Estado de la API',
                description: 'Comprueba que la API responde y cuántos artículos hay publicados. '
                    .'No pide clave, para poder vigilarla desde fuera.',
                requiresKey: false,
                example: [
                    'estado' => 'ok',
                    'version' => '1.0',
                    'articulos_publicados' => 37,
                    'hora' => '2026-07-31T10:15:00+00:00',
                ],
                position: 10,
            ),
            new ApiEndpoint(
                key: 'sitio',
                path: 'sitio',
                title: 'Datos del sitio',
                description: 'Nombre, lema, logotipos y SEO por defecto. Es lo que la web necesita '
                    .'para pintar la cabecera y el pie, tenga el contenido que tenga.',
                example: [
                    'datos' => [
                        'nombre' => 'Alminares',
                        'lema' => 'Novedades del sector',
                        'url_publica' => 'https://www.ejemplo.com',
                        'idioma' => 'es',
                        'logo' => 'https://ejemplo.com/storage/settings/logo.png',
                        'logo_oscuro' => null,
                        'favicon' => 'https://ejemplo.com/storage/settings/favicon.png',
                        'seo' => [
                            'titulo' => 'Alminares',
                            'descripcion' => 'Novedades, productos y consejos.',
                            'plantilla_titulo' => ':title | Alminares',
                            'indexable' => true,
                            'og_imagen' => 'https://ejemplo.com/storage/settings/og.jpg',
                            'twitter_card' => 'summary_large_image',
                        ],
                        'organizacion' => [
                            'nombre' => 'Alminares S.L.',
                            'redes' => [['network' => 'instagram', 'url' => 'https://instagram.com/alminares']],
                        ],
                    ],
                ],
                position: 20,
            ),
            new ApiEndpoint(
                key: 'redirecciones',
                path: 'redirecciones',
                title: 'Redirecciones',
                description: 'Direcciones antiguas que deben redirigir a las actuales, con un 301. '
                    .'Cárgalas al arrancar la web o cachéalas: van todas de una vez para no tener que '
                    .'preguntar en cada visita.',
                example: [
                    'datos' => [
                        [
                            'de' => 'como-elejir-la-masa',
                            'a' => 'como-elegir-la-masa-de-tu-pizza',
                            'codigo' => 301,
                            'externa' => false,
                        ],
                    ],
                    'total' => 1,
                ],
                position: 70,
            ),
            new ApiEndpoint(
                key: 'sitemap',
                path: 'sitemap',
                title: 'Direcciones para el sitemap',
                description: 'Todo lo publicado con su fecha de última modificación, para montar el '
                    .'sitemap.xml de la web. No devuelve XML: la estructura de direcciones la decide la web.',
                example: [
                    'articulos' => [
                        [
                            'slug' => 'como-elegir-la-masa-de-tu-pizza',
                            'modificado_en' => '2026-07-16T11:32:10+00:00',
                            'publicado_en' => '2026-07-14T09:00:00+00:00',
                            'indexable' => true,
                        ],
                    ],
                    'categorias' => [
                        ['slug' => 'recetas', 'modificado_en' => '2026-07-10T08:00:00+00:00'],
                    ],
                    'etiquetas' => [
                        ['slug' => 'masa', 'modificado_en' => '2026-07-10T08:00:00+00:00'],
                    ],
                    'generado_en' => '2026-07-31T10:15:00+00:00',
                ],
                position: 80,
            ),
        ];
    }
}

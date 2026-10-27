<?php

declare(strict_types=1);

namespace Modules\Tienda\Support;

use App\Modules\Values\ApiEndpoint;

/**
 * Los endpoints de la tienda con sus ejemplos.
 *
 * De aquí salen el probador del panel, la documentación descargable y el
 * OpenAPI. Los ejemplos están escritos a mano, pero una prueba comprueba que
 * cada clave documentada existe de verdad en la respuesta.
 */
final class ApiEndpoints
{
    /**
     * @return list<ApiEndpoint>
     */
    public static function all(): array
    {
        $image = [
            'alt' => 'Camiseta de algodón roja',
            'miniatura' => 'https://ejemplo.com/storage/biblioteca/variantes/camiseta-thumbnail.jpg',
            'mediana' => 'https://ejemplo.com/storage/biblioteca/variantes/camiseta-medium.jpg',
            'grande' => 'https://ejemplo.com/storage/biblioteca/variantes/camiseta-large.jpg',
        ];

        $promotion = [
            'nombre' => 'Rebajas de verano',
            'descripcion' => 'Un 20 % en toda la ropa hasta el domingo.',
            'termina_en' => '2026-08-31T21:59:00+00:00',
        ];

        $product = [
            'id' => 12,
            'nombre' => 'Camiseta de algodón',
            'slug' => 'camiseta-de-algodon',
            'referencia' => 'CAM-001',
            'marca' => 'Alminares',
            'resumen' => 'Algodón orgánico, corte recto.',
            'categoria' => ['nombre' => 'Camisetas', 'slug' => 'camisetas'],
            'precio' => 15.92,
            'precio_texto' => '15,92 €',
            'precio_anterior' => 19.9,
            'precio_anterior_texto' => '19,90 €',
            'descuento_porcentaje' => 20,
            'promocion' => $promotion,
            'precio_desde' => true,
            'disponibilidad' => 'low',
            'disponibilidad_texto' => 'Quedan pocas unidades',
            'unidades' => 3,
            'destacado' => true,
            'tiene_variantes' => true,
            'imagen' => $image,
            'whatsapp_url' => 'https://wa.me/34600123456?text=Hola%2C%20me%20interesa%20%C2%ABCamiseta%20de%20algod%C3%B3n%C2%BB',
            'actualizado_en' => '2026-10-20T10:12:00+00:00',
        ];

        $variant = [
            'id' => 31,
            'nombre' => 'M / Rojo',
            'referencia' => 'CAM-001-M-R',
            'opciones' => ['Talla' => 'M', 'Color' => 'Rojo'],
            'precio' => 15.92,
            'precio_texto' => '15,92 €',
            'precio_anterior' => 19.9,
            'precio_anterior_texto' => '19,90 €',
            'descuento_porcentaje' => 20,
            'promocion' => $promotion,
            'disponibilidad' => 'available',
            'disponibilidad_texto' => 'Disponible',
            'unidades' => 8,
            'imagen' => $image,
            'whatsapp_url' => 'https://wa.me/34600123456?text=Hola%2C%20me%20interesa%20%C2%ABCamiseta%20de%20algod%C3%B3n%C2%BB%20(M%20%2F%20Rojo)',
        ];

        $category = [
            'id' => 3,
            'nombre' => 'Ropa',
            'slug' => 'ropa',
            'descripcion' => 'Camisetas, sudaderas y pantalones.',
            'imagen' => $image,
            'articulos' => 24,
            'subcategorias' => [
                [
                    'id' => 7,
                    'nombre' => 'Camisetas',
                    'slug' => 'camisetas',
                    'articulos' => 11,
                    'subcategorias' => [],
                ],
            ],
        ];

        $pagination = [
            'pagina' => 1,
            'por_pagina' => 12,
            'total' => 37,
            'ultima_pagina' => 4,
            'hay_mas' => true,
        ];

        return [
            new ApiEndpoint(
                key: 'tienda_configuracion',
                path: 'tienda/configuracion',
                title: 'Tienda: configuración',
                description: 'La moneda y el contacto de WhatsApp de la tienda, para el botón general de la web.',
                example: [
                    'datos' => [
                        'moneda' => '€',
                        'mostrar_unidades' => true,
                        'whatsapp' => ['numero' => '+34600123456', 'url' => 'https://wa.me/34600123456'],
                    ],
                ],
                notes: ['Si no hay número de WhatsApp configurado no llega `whatsapp`: la web no debe enseñar el botón.'],
                position: 110,
            ),
            new ApiEndpoint(
                key: 'tienda_categorias',
                path: 'tienda/categorias',
                title: 'Tienda: árbol de categorías',
                description: 'Las categorías visibles, anidadas, con cuántos artículos a la venta hay en cada rama. '
                    .'Es lo que necesita el menú de la tienda.',
                example: ['datos' => [$category]],
                notes: [
                    '`articulos` cuenta también los de las subcategorías, y un artículo que está en dos de la misma rama cuenta una vez.',
                    'Una categoría oculta desaparece con todo lo que cuelga de ella.',
                ],
                position: 120,
            ),
            new ApiEndpoint(
                key: 'tienda_categoria',
                path: 'tienda/categorias/{slug}',
                title: 'Tienda: una categoría',
                description: 'Una categoría con su ruta de migas y sus subcategorías. Sus artículos se piden a '
                    .'`tienda/articulos?categoria={slug}`.',
                parameters: [
                    ['name' => 'slug', 'description' => 'Dirección de la categoría', 'example' => ''],
                ],
                example: [
                    'datos' => $category + [
                        'ruta' => [['nombre' => 'Ropa', 'slug' => 'ropa']],
                        'seo' => ['titulo' => 'Ropa de algodón', 'descripcion' => 'Camisetas, sudaderas y pantalones.'],
                    ],
                ],
                position: 130,
            ),
            new ApiEndpoint(
                key: 'tienda_articulos',
                path: 'tienda/articulos',
                title: 'Tienda: listado de artículos',
                description: 'Los artículos a la venta, con el precio ya calculado y el enlace de WhatsApp para '
                    .'preguntar por cada uno.',
                parameters: [
                    ['name' => 'pagina', 'description' => 'Número de página', 'example' => '1'],
                    ['name' => 'por_pagina', 'description' => 'Artículos por página', 'example' => '12'],
                    ['name' => 'buscar', 'description' => 'Busca en nombre, referencia, marca y resumen', 'example' => 'camiseta'],
                    ['name' => 'categoria', 'description' => 'Slug de una categoría; incluye sus subcategorías', 'example' => 'ropa'],
                    ['name' => 'destacados', 'description' => '1 para solo los destacados', 'example' => ''],
                    ['name' => 'en_oferta', 'description' => '1 para solo los que tienen una promoción en vigor', 'example' => ''],
                    ['name' => 'disponibles', 'description' => '1 para esconder los agotados', 'example' => ''],
                    ['name' => 'marca', 'description' => 'Solo los de esta marca', 'example' => ''],
                    ['name' => 'orden', 'description' => 'destacados, novedades, precio_asc, precio_desc o nombre', 'example' => 'destacados'],
                ],
                example: ['datos' => [$product], 'paginacion' => $pagination],
                notes: [
                    'Los campos vacíos no se envían: un artículo sin precio no trae `precio` y la web enseña «consultar precio».',
                    '`precio` es siempre lo que se paga hoy, con la promoción aplicada. Si llega `precio_anterior`, '
                        .'hay oferta y es el que se tacha.',
                    'Con variantes de precios distintos, `precio` es el más barato y `precio_desde` vale `true`: escribe «desde».',
                    '`disponibilidad` vale `available`, `low` u `out_of_stock`. Un artículo agotado **sigue saliendo**, '
                        .'salvo que se pida `disponibles=1`.',
                    '`unidades` solo llega si la tienda ha elegido enseñarlas en Configuración.',
                    '`whatsapp_url` abre el chat con el mensaje ya escrito. No llega si no hay número configurado.',
                    'Ordenar por precio usa el precio sin promoción.',
                    'Una categoría que no existe o está oculta responde 404 con el código `categoria_no_encontrada`.',
                ],
                position: 140,
            ),
            new ApiEndpoint(
                key: 'tienda_articulo',
                path: 'tienda/articulos/{slug}',
                title: 'Tienda: un artículo',
                description: 'La ficha completa: galería, descripción en HTML ya limpio, opciones, cada variante con '
                    .'su precio, sus existencias y su propio enlace de WhatsApp, y artículos relacionados.',
                parameters: [
                    ['name' => 'slug', 'description' => 'Dirección del artículo', 'example' => ''],
                ],
                example: [
                    'datos' => $product + [
                        'descripcion_html' => '<p>Camiseta de algodón orgánico…</p>',
                        'imagenes' => [$image],
                        'categorias' => [['nombre' => 'Camisetas', 'slug' => 'camisetas']],
                        'ruta' => [['nombre' => 'Ropa', 'slug' => 'ropa'], ['nombre' => 'Camisetas', 'slug' => 'camisetas']],
                        'opciones' => [
                            ['nombre' => 'Talla', 'valores' => ['S', 'M', 'L']],
                            ['nombre' => 'Color', 'valores' => ['Rojo', 'Azul']],
                        ],
                        'variantes' => [$variant],
                        'seo' => ['titulo' => 'Camiseta de algodón orgánico', 'descripcion' => 'Algodón orgánico, corte recto.'],
                        'relacionados' => [$product],
                    ],
                ],
                notes: [
                    'Para saber qué variante ha elegido quien compra, compara lo marcado con `variantes[].opciones`.',
                    'Las variantes desactivadas en el panel no se envían.',
                ],
                position: 150,
            ),
            new ApiEndpoint(
                key: 'tienda_promociones',
                path: 'tienda/promociones',
                title: 'Tienda: promociones en vigor',
                description: 'Las promociones activas ahora mismo, para banners y avisos. El precio rebajado de cada '
                    .'artículo ya llega en el propio artículo.',
                example: [
                    'datos' => [[
                        'id' => 2,
                        'nombre' => 'Rebajas de verano',
                        'descripcion' => 'Un 20 % en toda la ropa hasta el domingo.',
                        'tipo' => 'percentage',
                        'valor' => 20.0,
                        'valor_texto' => '-20 %',
                        'alcance' => 'categories',
                        'categorias' => [['nombre' => 'Ropa', 'slug' => 'ropa']],
                        'empieza_en' => '2026-08-01T00:00:00+00:00',
                        'termina_en' => '2026-08-31T21:59:00+00:00',
                    ]],
                ],
                notes: [
                    '`tipo` vale `percentage` o `fixed`; `alcance`, `all`, `categories` o `products`.',
                    'Las respuestas se cachean unos minutos: una promoción programada puede tardar ese rato en aparecer o desaparecer.',
                ],
                position: 160,
            ),
        ];
    }
}

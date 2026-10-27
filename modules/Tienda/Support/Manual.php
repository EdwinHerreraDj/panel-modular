<?php

declare(strict_types=1);

namespace Modules\Tienda\Support;

use App\Modules\Values\ManualSection;
use Modules\Tienda\Enums\TiendaPermission;

/**
 * Lo que la tienda aporta al manual del cliente.
 */
final class Manual
{
    /**
     * @return list<ManualSection>
     */
    public static function sections(): array
    {
        return [
            new ManualSection(
                key: 'tienda',
                title: 'La tienda',
                icon: 'ri-store-2-line',
                permission: TiendaPermission::View->value,
                blocks: [
                    ['type' => 'text', 'content' => 'La tienda tiene cuatro apartados: **Artículos**, **Categorías**, '
                        .'**Inventario** y **Promociones**. Tu web los lee solos: lo que cambies aquí se ve allí en '
                        .'unos minutos.'],

                    ['type' => 'heading', 'content' => 'Cómo compra la gente'],
                    ['type' => 'text', 'content' => 'Cada artículo de la web lleva un botón de WhatsApp que abre un '
                        .'chat contigo con el mensaje ya escrito: qué artículo, qué talla o color, y el enlace. El '
                        .'número y el mensaje se cambian en **Configuración › Tienda**.'],
                    ['type' => 'tip', 'content' => 'Cuando cierres una venta por WhatsApp, apúntala en Inventario como '
                        .'«Venta». Así el stock de la web se mantiene al día y nadie te pide algo que ya no tienes.'],

                    ['type' => 'heading', 'content' => 'Artículos'],
                    ['type' => 'text', 'content' => 'Un artículo solo sale en la web si está marcado como **visible**. '
                        .'Puedes prepararlo con calma y enseñarlo cuando esté listo. Los **destacados** salen los '
                        .'primeros.'],
                    ['type' => 'text', 'content' => 'El precio se puede dejar vacío: la web enseñará «consultar '
                        .'precio» y el cliente preguntará por WhatsApp.'],
                    ['type' => 'text', 'content' => 'Las fotos se eligen de la biblioteca. La primera es la portada; '
                        .'puedes cambiar el orden con las flechas.'],

                    ['type' => 'heading', 'content' => 'Tallas, colores y otras variantes'],
                    ['type' => 'text', 'content' => 'Si un artículo viene en varias tallas o colores, añade las '
                        .'**opciones** (por ejemplo «Talla» con S, M, L y «Color» con Rojo, Azul) y pulsa **Generar '
                        .'combinaciones**. Cada combinación tiene su propio stock, y si quieres, su propio precio y '
                        .'su foto.'],
                    ['type' => 'tip', 'content' => 'Si una combinación no la fabricas, desactívala en lugar de borrarla: '
                        .'conservas su historial.'],

                    ['type' => 'heading', 'content' => 'Categorías'],
                    ['type' => 'text', 'content' => 'Las categorías pueden tener subcategorías, hasta tres niveles '
                        .'(Ropa › Hombre › Camisetas). Cada artículo tiene una categoría principal y puede estar '
                        .'también en otras.'],

                    ['type' => 'heading', 'content' => 'Inventario'],
                    ['type' => 'text', 'content' => 'Aquí ves cuánto queda de cada cosa y registras lo que pasa: '
                        .'**entrada de mercancía**, **venta**, **devolución**, **merma o rotura** y **recuento**. '
                        .'Todo queda en el historial con quién lo hizo y cuándo.'],
                    ['type' => 'text', 'content' => 'El **recuento** es distinto: en lugar de cuánto entra o sale, '
                        .'escribes cuántas unidades hay de verdad. El panel calcula la diferencia y la apunta.'],
                    ['type' => 'tip', 'content' => 'Si un artículo no necesita control de stock (se hace por encargo, '
                        .'por ejemplo), desmarca «Controlar existencias» en su ficha y siempre saldrá disponible.'],

                    ['type' => 'heading', 'content' => 'Promociones'],
                    ['type' => 'text', 'content' => 'Una promoción rebaja un porcentaje o un importe fijo a toda la '
                        .'tienda, a unas categorías o a unos artículos concretos, entre dos fechas. No tienes que '
                        .'tocar los precios: la web enseña el precio de siempre tachado y el rebajado al lado, y '
                        .'cuando acaba la promoción todo vuelve solo.'],
                    ['type' => 'tip', 'content' => 'Si a un artículo le tocan dos promociones, se aplica la que más '
                        .'descuenta. Nunca se suman.'],
                ],
                position: 70,
            ),
        ];
    }
}

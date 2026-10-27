<?php

declare(strict_types=1);

namespace Modules\Tienda\Support;

use Modules\Tienda\Models\Product;
use Modules\Tienda\Models\ProductVariant;

/**
 * Enlaces de WhatsApp para pedir información de un artículo.
 *
 * El panel los monta y la web solo los pinta: así el mensaje se cambia desde
 * Configuración sin tocar la web, y todas las fichas dicen lo mismo.
 */
final class WhatsApp
{
    /**
     * Enlace al chat de la tienda, con un mensaje opcional ya escrito.
     *
     * Null si no hay número configurado: la web no debe enseñar el botón.
     */
    public static function link(?string $message = null): ?string
    {
        $number = TiendaSettings::whatsappNumber();

        if ($number === null) {
            return null;
        }

        $message = trim((string) $message);

        return 'https://wa.me/'.$number.($message === '' ? '' : '?text='.rawurlencode($message));
    }

    /**
     * Enlace para preguntar por un artículo o por una variante concreta.
     */
    public static function forProduct(Product $product, ?ProductVariant $variant = null): ?string
    {
        if (TiendaSettings::whatsappNumber() === null) {
            return null;
        }

        return self::link(self::message($product, $variant));
    }

    /**
     * El mensaje con los huecos rellenos.
     *
     * Los huecos que no tienen valor se quedan vacíos y se recogen los
     * espacios que sobran, para que no salga «la referencia  .» a medias.
     */
    public static function message(Product $product, ?ProductVariant $variant = null): string
    {
        $url = TiendaSettings::productUrl();
        $label = $variant?->label();

        $message = strtr(TiendaSettings::whatsappMessage(), [
            ':articulo' => $product->name,
            ':variante' => $label === null || $label === '' ? '' : ' ('.$label.')',
            ':referencia' => (string) ($variant->sku ?? $product->sku),
            ':precio' => (string) Price::of($product, $variant)->formatted(),
            ':enlace' => $url === null ? '' : str_replace(':slug', $product->slug, $url),
        ]);

        return trim((string) preg_replace('/[ \t]{2,}/', ' ', $message));
    }
}

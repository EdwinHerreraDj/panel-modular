<?php

declare(strict_types=1);

namespace Modules\Tienda\Support;

use App\Modules\ModuleSettings;

/**
 * Los ajustes de la tienda, ya con su tipo.
 *
 * Los valores por defecto están declarados en `TiendaModule::settings()`; aquí
 * solo se leen, para no repetir la clave del módulo por todo el código.
 */
final class TiendaSettings
{
    /**
     * Número de WhatsApp en el formato que pide wa.me: solo cifras, con el
     * prefijo del país y sin el «+».
     */
    public static function whatsappNumber(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) self::get('whatsapp_number'));

        return $digits === '' || $digits === null ? null : $digits;
    }

    public static function whatsappMessage(): string
    {
        return (string) self::get('whatsapp_message');
    }

    /**
     * Plantilla de la dirección de la ficha en la web, con `:slug`.
     */
    public static function productUrl(): ?string
    {
        $template = trim((string) self::get('product_url'));

        return $template === '' ? null : $template;
    }

    public static function currency(): string
    {
        return (string) self::get('currency');
    }

    public static function lowStockThreshold(): int
    {
        return max(0, (int) self::get('low_stock_threshold'));
    }

    public static function showStockQuantity(): bool
    {
        return (bool) self::get('show_stock_quantity');
    }

    private static function get(string $key): string|int|bool|null
    {
        return app(ModuleSettings::class)->get('tienda', $key);
    }
}

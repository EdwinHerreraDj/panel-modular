<?php

declare(strict_types=1);

namespace Modules\Tienda\Support;

/**
 * Importes: cómo se escriben, cómo se guardan y cómo se enseñan.
 *
 * La normalización es la misma que la del catálogo, copiada a propósito: un
 * módulo tiene que poder borrarse sin que el otro deje de funcionar.
 */
final class Money
{
    /**
     * Importe con formato español y la moneda configurada: `1.299,50 €`.
     */
    public static function format(float|string|null $amount): ?string
    {
        if ($amount === null) {
            return null;
        }

        return number_format((float) $amount, 2, ',', '.').' '.TiendaSettings::currency();
    }

    /**
     * El importe en el formulario se escribe como se lee: `149,90`.
     */
    public static function forForm(float|string|null $amount): string
    {
        return $amount === null ? '' : number_format((float) $amount, 2, ',', '');
    }

    /**
     * Convierte lo que se ha escrito en algo que la base de datos entienda.
     *
     * - Si hay coma, la coma es el decimal y los puntos son millares:
     *   `1.299,50` → `1299.50`.
     * - Si solo hay punto, es decimal cuando le siguen una o dos cifras
     *   (`149.90`) y millar cuando le siguen tres (`1.299`).
     */
    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return is_numeric($value) ? (string) $value : null;
        }

        $value = trim(str_replace(['€', '$', ' '], '', $value));

        if ($value === '') {
            return null;
        }

        if (str_contains($value, ',')) {
            return str_replace(['.', ','], ['', '.'], $value);
        }

        return preg_replace('/\.(?=\d{3}(?:\D|$))/', '', $value);
    }
}

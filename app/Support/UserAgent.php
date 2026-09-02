<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Traduce la cadena `User-Agent` a algo legible.
 *
 * No pretende ser exhaustivo ni identificar a nadie: solo convertir un texto
 * ilegible en «Chrome en Windows» para que el registro de accesos se pueda leer
 * de un vistazo. Ante la duda devuelve «Desconocido» antes que inventar.
 */
class UserAgent
{
    /** @var array<string, string> Orden importante: lo más específico primero. */
    private const BROWSERS = [
        'Edg/' => 'Edge',
        'OPR/' => 'Opera',
        'Chrome/' => 'Chrome',
        'Firefox/' => 'Firefox',
        'Safari/' => 'Safari',
    ];

    /** @var array<string, string> */
    private const SYSTEMS = [
        'Windows NT' => 'Windows',
        'iPhone' => 'iPhone',
        'iPad' => 'iPad',
        'Android' => 'Android',
        'Mac OS X' => 'macOS',
        'Linux' => 'Linux',
    ];

    public static function describe(?string $agent): string
    {
        if ($agent === null || trim($agent) === '') {
            return 'Desconocido';
        }

        $browser = null;

        foreach (self::BROWSERS as $needle => $name) {
            if (str_contains($agent, $needle)) {
                $browser = $name;
                break;
            }
        }

        $system = null;

        foreach (self::SYSTEMS as $needle => $name) {
            if (str_contains($agent, $needle)) {
                $system = $name;
                break;
            }
        }

        if ($browser !== null && $system !== null) {
            return "{$browser} en {$system}";
        }

        // Si no se reconoce, se enseña el principio de la cadena original: es
        // más útil para investigar que un «Desconocido» a secas.
        return $browser ?? $system ?? Str::limit($agent, 40);
    }
}

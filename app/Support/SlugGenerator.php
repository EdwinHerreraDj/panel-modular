<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generación de slugs únicos a partir de un texto.
 *
 * Los slugs nunca se piden en los formularios: quien usa el panel no tiene por
 * qué saber qué es uno. Se derivan del nombre y, si ya existe, se les añade un
 * sufijo numérico.
 *
 * La búsqueda de colisiones incluye los registros de la papelera: su slug sigue
 * ocupado mientras se puedan restaurar.
 */
final class SlugGenerator
{
    /**
     * @param  string  $table  Tabla donde comprobar la unicidad.
     * @param  int|null  $ignoreId  Registro que no cuenta como colisión (al editar).
     */
    public static function unique(string $source, string $table, ?int $ignoreId = null, string $column = 'slug'): string
    {
        $base = self::normalize($source);
        $slug = $base;
        $suffix = 1;

        while (self::exists($slug, $table, $column, $ignoreId)) {
            $suffix++;
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

    /**
     * Convierte un texto en un slug válido.
     *
     * Si el texto no deja ningún carácter utilizable —por ejemplo, un nombre
     * escrito solo con emojis o con un alfabeto no latino— se genera uno
     * aleatorio para no acabar con un slug vacío.
     */
    public static function normalize(string $source): string
    {
        $slug = Str::slug($source);

        if ($slug === '') {
            $slug = Str::lower(Str::random(8));
        }

        // Al recortar puede quedar un guion suelto al final.
        return trim(Str::limit($slug, 120, ''), '-');
    }

    private static function exists(string $slug, string $table, string $column, ?int $ignoreId): bool
    {
        return DB::table($table)
            ->where($column, $slug)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }
}

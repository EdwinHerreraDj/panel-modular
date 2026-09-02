<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las columnas polimórficas pasan a guardar un alias corto en lugar del nombre
 * de la clase.
 *
 * Hasta ahora `activity_logs.subject_type` guardaba `App\Models\Article`. Eso
 * ata los datos al espacio de nombres: mover el modelo al módulo del blog
 * dejaría huérfanas las cuatro tablas de cualquier instalación en marcha.
 *
 * Con un alias (`blog.article`) el dato deja de depender de dónde viva la
 * clase, y el mapa que los relaciona lo compone el registro de módulos.
 *
 * Se hace **antes** de mudar el blog, y no a la vez, para que si algo sale mal
 * se sepa cuál de las dos cosas ha sido.
 */
return new class extends Migration
{
    /**
     * Clase antigua => alias nuevo.
     *
     * Estas cadenas son historia: describen lo que hay escrito en la base de
     * datos de las instalaciones en marcha. **No se tocan aunque el modelo
     * cambie de espacio de nombres**, o la migración dejaría de encontrar sus
     * filas y no se notaría hasta el primer cliente, porque en una instalación
     * nueva las tablas están vacías cuando esto corre.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'App\Models\User' => 'core.user',
        'App\Models\Media' => 'core.media',
        'App\Models\Setting' => 'core.setting',
        'App\Models\ApiKey' => 'core.api_key',
        'App\Models\Redirect' => 'core.redirect',
        'App\Models\Article' => 'blog.article',
        'App\Models\Category' => 'blog.category',
        'App\Models\Tag' => 'blog.tag',
    ];

    /**
     * Tabla => columna de tipo.
     *
     * @var array<string, string>
     */
    private const COLUMNS = [
        'media_usages' => 'usable_type',
        'activity_logs' => 'subject_type',
        'revisions' => 'revisionable_type',
        'redirects' => 'redirectable_type',
    ];

    public function up(): void
    {
        $this->rewrite(self::ALIASES);
    }

    public function down(): void
    {
        $this->rewrite(array_flip(self::ALIASES));
    }

    /**
     * @param  array<string, string>  $map
     */
    private function rewrite(array $map): void
    {
        foreach (self::COLUMNS as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            foreach ($map as $from => $to) {
                // Un UPDATE por valor y sobre un índice: en una instalación con
                // años de auditoría son unas pocas consultas acotadas, no un
                // recorrido fila a fila.
                DB::table($table)->where($column, $from)->update([$column => $to]);
            }
        }
    }
};

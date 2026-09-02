<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las tablas de roles y permisos también guardan un tipo polimórfico.
 *
 * `spatie/laravel-permission` escribe `model_type` con `getMorphClass()`, igual
 * que las cuatro tablas de contenido. Al pasar a alias, esas dos se quedaron
 * sin convertir: el modelo pasó a buscarse como `core.user` mientras las filas
 * seguían diciendo `App\Models\User`, y el resultado es una cuenta que conserva
 * su rol en la base de datos pero se comporta como si no tuviera ninguno.
 *
 * No se nota en las pruebas porque allí las filas se escriben y se leen con el
 * mapa ya puesto: solo rompe los datos que existían de antes.
 *
 * Las cadenas de la izquierda son historia y no se tocan aunque el modelo
 * cambie de espacio de nombres.
 */
return new class extends Migration
{
    /**
     * Clase antigua => alias nuevo.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'App\Models\User' => 'core.user',
    ];

    /**
     * @var list<string>
     */
    private const TABLES = ['model_has_roles', 'model_has_permissions'];

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
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'model_type')) {
                continue;
            }

            foreach ($map as $from => $to) {
                DB::table($table)->where('model_type', $from)->update(['model_type' => $to]);
            }
        }
    }
};

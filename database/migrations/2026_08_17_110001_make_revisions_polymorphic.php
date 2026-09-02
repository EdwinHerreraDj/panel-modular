<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El historial de versiones deja de ser exclusivo de los artículos.
 *
 * El guardado automático y el poder volver atrás sirven igual para cualquier
 * contenido largo que se escriba en el panel: la ficha de un producto, la
 * descripción de un servicio. Atarlo a `articles` obligaría a duplicarlo.
 *
 * La tabla pasa a llamarse `revisions` y la columna a ser polimórfica. Se hace
 * ahora, antes de la primera instalación: después habría que migrar los datos
 * de cada cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        // El orden importa: la clave foránea y el índice se quitan **antes** de
        // renombrar la tabla. Al renombrar, MySQL conserva los nombres antiguos
        // de las restricciones, y `dropForeign(['article_id'])` los deduce del
        // nombre nuevo, así que después ya no los encontraría.
        Schema::table('article_revisions', function (Blueprint $table) {
            $table->nullableMorphs('revisionable');
        });

        // Sin el blog instalado, la tabla se creó sin `article_id` y no hay
        // nada que trasladar ni que quitar.
        if (Schema::hasColumn('article_revisions', 'article_id')) {
            DB::table('article_revisions')->update([
                'revisionable_type' => 'App\Models\Article',
                'revisionable_id' => DB::raw('article_id'),
            ]);

            Schema::table('article_revisions', function (Blueprint $table) {
                $table->dropForeign(['article_id']);
                $table->dropIndex(['article_id', 'type', 'created_at']);
                $table->dropColumn('article_id');
            });
        }

        Schema::rename('article_revisions', 'revisions');

        Schema::table('revisions', function (Blueprint $table) {
            // El índice que de verdad se usa: «las versiones de esto, de este
            // tipo, de la más reciente a la más antigua».
            $table->index(['revisionable_type', 'revisionable_id', 'type', 'created_at'], 'revisions_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('revisions', function (Blueprint $table) {
            $table->dropIndex('revisions_lookup');
        });

        Schema::rename('revisions', 'article_revisions');

        Schema::table('article_revisions', function (Blueprint $table) {
            $table->foreignId('article_id')->nullable()->constrained()->cascadeOnDelete();
        });

        DB::table('article_revisions')
            ->where('revisionable_type', 'App\Models\Article')
            ->update(['article_id' => DB::raw('revisionable_id')]);

        Schema::table('article_revisions', function (Blueprint $table) {
            $table->dropMorphs('revisionable');
            $table->index(['article_id', 'type', 'created_at']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La redirección deja de apuntar solo a un artículo.
 *
 * Cambiar la dirección de un producto de un catálogo rompe los enlaces
 * exactamente igual que la de un artículo. Con una clave foránea a `articles`
 * eso no se puede expresar.
 *
 * Se hace ahora, con la tabla recién creada, porque más adelante habría que
 * migrar los datos de cada cliente uno a uno.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('redirects', function (Blueprint $table) {
            $table->nullableMorphs('redirectable');
        });

        // Por si alguna instalación ya tuviera redirecciones automáticas.
        // Sin el blog instalado, la tabla se creó sin `article_id` y no hay
        // nada que trasladar ni que quitar.
        if (Schema::hasColumn('redirects', 'article_id')) {
            DB::table('redirects')
                ->whereNotNull('article_id')
                ->update([
                    'redirectable_type' => 'App\Models\Article',
                    'redirectable_id' => DB::raw('article_id'),
                ]);

            Schema::table('redirects', function (Blueprint $table) {
                $table->dropForeign(['article_id']);
                $table->dropIndex(['article_id']);
                $table->dropColumn('article_id');
            });
        }

    }

    public function down(): void
    {
        Schema::table('redirects', function (Blueprint $table) {
            $table->foreignId('article_id')->nullable()->constrained()->cascadeOnDelete();
            $table->index('article_id');
        });

        DB::table('redirects')
            ->where('redirectable_type', 'App\Models\Article')
            ->update(['article_id' => DB::raw('redirectable_id')]);

        Schema::table('redirects', function (Blueprint $table) {
            $table->dropMorphs('redirectable');
        });
    }
};

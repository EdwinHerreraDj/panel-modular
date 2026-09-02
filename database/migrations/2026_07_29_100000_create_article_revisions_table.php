<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de versiones de cada artículo.
 *
 * La tabla es del núcleo —una migración posterior la convierte en `revisions`,
 * polimórfica y válida para cualquier contenido—, pero nació atada al blog con
 * una clave foránea a `articles`. Esa atadura se hace condicional: en una
 * instalación que no tenga el blog activo, la tabla se crea igual y sin la
 * columna, y la migración que la convierte se adapta.
 *
 * No se cambia lo que ya corrió en las instalaciones en marcha: allí la tabla
 * se creó con `article_id` y el resultado final es idéntico.
 *
 * `snapshot` guarda el estado completo del artículo en ese momento, incluidas
 * sus categorías, etiquetas y relacionados, para poder restaurarlo tal cual.
 *
 * Hay dos tipos: `manual`, una por cada guardado, y `autosave`, una única por
 * artículo que se va reemplazando mientras se escribe.
 */
return new class extends Migration
{
    public function up(): void
    {
        $withArticles = Schema::hasTable('articles');

        Schema::create('article_revisions', function (Blueprint $table) use ($withArticles) {
            $table->id();

            if ($withArticles) {
                $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            }

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->default('manual');
            $table->json('snapshot');
            $table->timestamp('created_at')->nullable();

            if ($withArticles) {
                $table->index(['article_id', 'type', 'created_at']);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_revisions');
    }
};

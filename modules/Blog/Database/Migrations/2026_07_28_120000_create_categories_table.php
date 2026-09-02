<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categorías del blog. Planas: no hay jerarquía.
 *
 * `is_slug_locked` congela el slug en cuanto la categoría empieza a usarse en
 * artículos publicados, para no romper las URL ya indexadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();

            // SEO propio; si se deja vacío se hereda de la configuración general.
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();

            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_slug_locked')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->unique('slug');
            $table->index('name');
            $table->index('position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuración del blog. La tabla tiene siempre una única fila: al ser una
 * instalación por empresa no hay nada que multiplicar.
 *
 * Se usan columnas con tipo en lugar de pares clave/valor para poder validar,
 * indexar y consultar la configuración como cualquier otro dato.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            // --- General ---------------------------------------------------
            $table->string('blog_name');
            $table->string('tagline')->nullable();
            $table->string('public_url')->nullable();
            $table->string('locale', 5)->default('es');
            $table->string('timezone', 64)->default('Europe/Madrid');
            $table->unsignedSmallInteger('articles_per_page')->default(10);
            $table->string('logo_path')->nullable();
            $table->string('logo_dark_path')->nullable();
            $table->string('favicon_path')->nullable();

            // --- SEO por defecto -------------------------------------------
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            // Plantilla del título de las páginas, con :title como marcador.
            $table->string('title_template')->default(':title | :blog');
            $table->boolean('is_indexable')->default(true);
            $table->string('og_image_path')->nullable();
            $table->string('twitter_card', 20)->default('summary_large_image');

            // --- Redes sociales (datos estructurados) ----------------------
            $table->string('organization_name')->nullable();
            $table->json('social_profiles')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};

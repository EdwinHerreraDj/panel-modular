<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Artículos del blog.
 *
 * El contenido se guarda ya sanitizado: nunca se confía en el HTML que llega
 * del navegador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();

            // --- Contenido -------------------------------------------------
            $table->string('title');
            $table->string('slug');
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            // Versión en texto plano del contenido: se usa para el buscador y
            // para calcular el tiempo de lectura sin volver a parsear el HTML.
            $table->longText('content_text')->nullable();

            $table->string('featured_image_path')->nullable();
            $table->string('featured_image_alt')->nullable();

            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();

            // --- Flujo editorial -------------------------------------------
            $table->string('status', 20)->default('draft');
            $table->string('visibility', 20)->default('public');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->unsignedSmallInteger('reading_minutes')->default(0);
            $table->unsignedInteger('position')->default(0);

            // --- SEO -------------------------------------------------------
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('og_title')->nullable();
            $table->string('og_description', 500)->nullable();
            $table->string('og_image_path')->nullable();
            $table->boolean('is_indexable')->default(true);

            $table->boolean('is_slug_locked')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->unique('slug');
            $table->index('status');
            $table->index('author_id');
            $table->index(['status', 'published_at']);
            $table->index(['status', 'scheduled_for']);
            $table->index('position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};

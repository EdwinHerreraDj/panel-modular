<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categorías de la tienda, en árbol.
 *
 * Prefijo `shop_` en todas las tablas del módulo: el blog ya tiene
 * `categories` y el catálogo `products`, y los tres pueden convivir activos en
 * la misma instalación.
 *
 * La jerarquía es una simple columna `parent_id`. Para una tienda pequeña el
 * árbol entero son unas decenas de filas: se lee de una vez y se recorre en
 * memoria, sin estructuras de conjuntos anidados que nadie va a mantener.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('shop_categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_slug_locked')->default(false);
            $table->text('description')->nullable();

            $table->foreignId('image_media_id')->nullable()->constrained('media')->nullOnDelete();

            // Nace visible: una categoría vacía no se enseña igualmente, y
            // obligar a marcarla es un paso más que se olvida.
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('position')->default(0);

            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_categories');
    }
};

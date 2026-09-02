<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Productos del catálogo.
 *
 * Una sola tabla y sin relaciones propias: quien lo va a usar no es una persona
 * técnica, y cada categoría, variante o etiqueta que se añada es una pantalla
 * más que aprender. Lo único que apunta fuera es la imagen, que sale de la
 * biblioteca del núcleo y por tanto se reutiliza y no se puede borrar mientras
 * esté en uso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_slug_locked')->default(false);

            // Referencia del cliente. Única cuando la hay: MySQL permite
            // repetir el nulo, así que quien no la use no se entera.
            $table->string('reference', 60)->nullable()->unique();

            // Una línea para el listado de la web; la descripción larga es la
            // de la ficha.
            $table->string('summary', 255)->nullable();
            $table->text('description')->nullable();

            // Se puede dejar vacío: hay catálogos que piden presupuesto en
            // lugar de enseñar precio.
            $table->decimal('price', 10, 2)->nullable();

            $table->foreignId('image_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('image_alt')->nullable();

            $table->boolean('is_visible')->default(false);
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // El listado de la web: lo visible, en orden de presentación.
            $table->index(['is_visible', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

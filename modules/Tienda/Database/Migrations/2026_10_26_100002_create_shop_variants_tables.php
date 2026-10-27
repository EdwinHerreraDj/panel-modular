<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opciones y variantes de un artículo.
 *
 * Hasta tres opciones por artículo (talla, color, material…) y una fila por
 * cada combinación. El valor de cada opción va en su propia columna de la
 * variante —`option1`, `option2`, `option3`— y no en un JSON: así se puede
 * filtrar «talla M» con un `where` normal.
 *
 * La lista de valores de la opción sí es JSON, y a propósito: solo sirve para
 * pintarlos en el orden que eligió quien los escribió (S, M, L, XL y no
 * alfabético). Nadie filtra por ella; el dato de verdad está en las variantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_product_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('shop_products')->cascadeOnDelete();
            $table->string('name', 60);
            $table->json('values');
            // 1, 2 o 3: dice en qué columna de la variante va su valor.
            $table->unsignedTinyInteger('position');

            $table->unique(['product_id', 'position']);
        });

        Schema::create('shop_product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('shop_products')->cascadeOnDelete();

            $table->string('option1', 60)->nullable();
            $table->string('option2', 60)->nullable();
            $table->string('option3', 60)->nullable();

            $table->string('sku', 60)->nullable()->unique();
            // Vacío: la variante cuesta lo mismo que el artículo.
            $table->decimal('price', 10, 2)->nullable();
            $table->integer('stock')->default(0);

            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            // Una combinación que no se fabrica se desactiva en lugar de
            // borrarse, para no perder su historial de existencias.
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            $table->index(['product_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_product_variants');
        Schema::dropIfExists('shop_product_options');
    }
};

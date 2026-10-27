<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de existencias.
 *
 * Cada cambio de stock deja una fila: entrada de mercancía, venta, devolución,
 * merma o recuento. El número del artículo es el resultado; esto es el porqué.
 * Sin historial, la primera vez que no cuadre el stock no habrá forma de saber
 * qué pasó.
 *
 * `stock_after` se guarda en lugar de recalcularlo para que el historial siga
 * siendo legible aunque se borre una variante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('shop_products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('shop_product_variants')->nullOnDelete();
            // Nombre de la variante en el momento del movimiento: si después se
            // borra, la fila sigue diciendo de qué talla era.
            $table->string('variant_label', 200)->nullable();

            $table->string('type', 20);
            // Con signo: lo que entra suma y lo que sale resta.
            $table->integer('quantity');
            $table->integer('stock_after');
            $table->string('note')->nullable();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['product_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_stock_movements');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Disponibilidad y precio rebajado.
 *
 * La disponibilidad es una etiqueta, no un inventario: «disponible», «pocas
 * unidades» o «sin stock». Contar unidades obligaría a descontarlas con cada
 * venta, y aquí no hay ventas: esto alimenta una página, no una tienda.
 *
 * El precio rebajado es el que se paga ahora; `price` pasa a ser el de antes,
 * el que la web tacha. Se guarda así y no al revés para que quien lo rellena
 * no tenga que cambiar el precio normal cada vez que hace una oferta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('availability', 20)->default('available')->after('price');
            $table->decimal('sale_price', 10, 2)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['availability', 'sale_price']);
        });
    }
};

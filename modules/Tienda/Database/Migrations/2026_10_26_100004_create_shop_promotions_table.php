<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promociones programables.
 *
 * Una promoción no toca el precio del artículo: se calcula encima. Así, cuando
 * acaba, el precio vuelve solo y nadie tiene que acordarse de cuál era.
 *
 * Si a un artículo le alcanzan varias a la vez, gana la que más descuenta. Es
 * la regla que el cliente final espera y la única que se explica en una línea.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('description', 255)->nullable();

            $table->string('type', 20);
            $table->decimal('value', 10, 2);
            $table->string('scope', 20);

            // En UTC, como el resto de fechas. Sin inicio empieza ya; sin fin
            // dura hasta que se desactive.
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        Schema::create('shop_promotion_category', function (Blueprint $table) {
            $table->foreignId('promotion_id')->constrained('shop_promotions')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('shop_categories')->cascadeOnDelete();

            $table->primary(['promotion_id', 'category_id']);
        });

        Schema::create('shop_promotion_product', function (Blueprint $table) {
            $table->foreignId('promotion_id')->constrained('shop_promotions')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('shop_products')->cascadeOnDelete();

            $table->primary(['promotion_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_promotion_product');
        Schema::dropIfExists('shop_promotion_category');
        Schema::dropIfExists('shop_promotions');
    }
};

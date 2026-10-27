<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Artículos de la tienda, sus categorías adicionales y su galería.
 *
 * `stock` guarda las unidades del artículo. Cuando tiene variantes, guarda la
 * **suma** de las variantes activas: es un dato derivado, pero tenerlo aquí
 * permite filtrar por «agotado» o «quedan pocas» con una consulta normal, sin
 * agrupar variantes en cada listado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_products', function (Blueprint $table) {
            $table->id();

            // Categoría principal: la de la ruta de migas y la de la URL si la
            // web la usa. Las demás van en la tabla intermedia.
            $table->foreignId('category_id')->nullable()->constrained('shop_categories')->nullOnDelete();

            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_slug_locked')->default(false);

            // Única cuando la hay: MySQL permite repetir el nulo.
            $table->string('sku', 60)->nullable()->unique();
            $table->string('brand', 120)->nullable();

            $table->string('summary', 255)->nullable();
            $table->text('description')->nullable();

            // Vacío significa «precio a consultar»: con la venta por WhatsApp
            // hay artículos que se presupuestan.
            $table->decimal('price', 10, 2)->nullable();

            $table->boolean('has_variants')->default(false);
            $table->boolean('track_stock')->default(true);
            $table->integer('stock')->default(0);
            // Sin valor se usa el umbral general del módulo.
            $table->unsignedInteger('low_stock_threshold')->nullable();

            $table->boolean('is_visible')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('position')->default(0);

            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_visible', 'position']);
            $table->index(['is_visible', 'is_featured']);
            $table->index('brand');
        });

        Schema::create('shop_category_product', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained('shop_categories')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('shop_products')->cascadeOnDelete();

            $table->primary(['category_id', 'product_id']);
        });

        Schema::create('shop_product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('shop_products')->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('alt')->nullable();
            // La primera es la portada.
            $table->unsignedInteger('position')->default(0);

            $table->index(['product_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_product_images');
        Schema::dropIfExists('shop_category_product');
        Schema::dropIfExists('shop_products');
    }
};

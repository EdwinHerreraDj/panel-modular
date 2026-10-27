<?php

declare(strict_types=1);

use App\Modules\ModuleSettings;

/*
|--------------------------------------------------------------------------
| Ayudantes de prueba de la tienda
|--------------------------------------------------------------------------
|
| Los carga tests/Pest.php junto con los de cualquier otro módulo. Llevan el
| prefijo `tienda` porque son funciones globales y el catálogo ya tiene las
| suyas con nombres parecidos.
|
*/

/**
 * Un artículo válido mínimo, sin variantes.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function tiendaProductPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Camiseta de algodón',
        'sku' => 'CAM-001',
        'brand' => '',
        'summary' => 'Algodón orgánico.',
        'description' => '<p>Corte recto.</p>',
        'category_id' => null,
        'category_ids' => [],
        'price' => '19,90',
        'track_stock' => true,
        'stock' => 10,
        'low_stock_threshold' => null,
        'is_visible' => false,
        'is_featured' => false,
        'position' => 0,
        'meta_title' => '',
        'meta_description' => '',
        'images' => [],
        'options' => [],
        'variants' => [],
    ], $overrides);
}

/**
 * Opciones Talla (S, M) × Color (Rojo) con su par de variantes.
 *
 * @param  array<int, array<string, mixed>>  $variantOverrides
 * @return array<string, mixed>
 */
function tiendaVariantsPayload(array $variantOverrides = []): array
{
    $variants = [
        ['values' => ['S', 'Rojo'], 'sku' => 'CAM-S-R', 'price' => '', 'stock' => 4, 'is_active' => true, 'media_id' => null],
        ['values' => ['M', 'Rojo'], 'sku' => 'CAM-M-R', 'price' => '', 'stock' => 6, 'is_active' => true, 'media_id' => null],
    ];

    foreach ($variantOverrides as $index => $override) {
        $variants[$index] = array_merge($variants[$index] ?? [], $override);
    }

    return [
        'options' => [
            ['name' => 'Talla', 'values' => ['S', 'M']],
            ['name' => 'Color', 'values' => ['Rojo']],
        ],
        'variants' => array_values($variants),
    ];
}

/**
 * Fija ajustes de la tienda como si se hubieran guardado en Configuración.
 *
 * @param  array<string, mixed>  $values
 */
function tiendaSettings(array $values): void
{
    app(ModuleSettings::class)->put('tienda', $values);
}

<?php

declare(strict_types=1);

use App\Models\Media;
use App\Support\ApiCatalog;
use App\Support\ApiDocumentation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Catalogo\Enums\ProductAvailability;
use Modules\Catalogo\Models\Product;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('sirve solo los productos visibles', function (): void {
    Product::factory()->visible()->create(['name' => 'Visible']);
    Product::factory()->create(['name' => 'Oculto']);

    [$headers] = apiKeyHeaders();

    $this->getJson(apiUrl('productos'), $headers)
        ->assertOk()
        ->assertJsonCount(1, 'datos')
        ->assertJsonPath('datos.0.nombre', 'Visible');
});

it('no sirve un producto oculto ni conociendo su dirección', function (): void {
    $product = Product::factory()->create(['name' => 'Oculto']);

    [$headers] = apiKeyHeaders();

    $this->getJson(apiUrl('productos/'.$product->slug), $headers)
        ->assertNotFound()
        ->assertJsonPath('error.codigo', 'no_encontrado')
        ->assertJsonPath('error.mensaje', 'No hay ningún producto visible con esa dirección.');
});

it('exige clave', function (): void {
    $this->getJson(apiUrl('productos'))->assertUnauthorized();
});

it('devuelve la ficha con la descripción ya limpia', function (): void {
    $product = Product::factory()->visible()->create([
        'description' => '<p>Roble macizo.</p>',
    ]);

    [$headers] = apiKeyHeaders();

    $this->getJson(apiUrl('productos/'.$product->slug), $headers)
        ->assertOk()
        ->assertJsonPath('datos.descripcion_html', '<p>Roble macizo.</p>');
});

it('omite el precio cuando el producto no lo enseña', function (): void {
    Product::factory()->visible()->withoutPrice()->create();

    [$headers] = apiKeyHeaders();

    $response = $this->getJson(apiUrl('productos'), $headers)->assertOk();

    expect($response->json('datos.0'))->not->toHaveKey('precio');
});

it('respeta el orden de presentación', function (): void {
    Product::factory()->visible()->create(['name' => 'Segundo', 'position' => 20]);
    Product::factory()->visible()->create(['name' => 'Primero', 'position' => 10]);

    [$headers] = apiKeyHeaders();

    $this->getJson(apiUrl('productos'), $headers)
        ->assertJsonPath('datos.0.nombre', 'Primero')
        ->assertJsonPath('datos.1.nombre', 'Segundo');
});

it('cuenta sus productos en el estado de la API', function (): void {
    Product::factory()->count(3)->visible()->create();

    $this->getJson(apiUrl('estado'))
        ->assertOk()
        ->assertJsonPath('productos_visibles', 3);
});

it('aporta su sección al sitemap', function (): void {
    $product = Product::factory()->visible()->create();

    [$headers] = apiKeyHeaders();

    $this->getJson(apiUrl('sitemap'), $headers)
        ->assertOk()
        ->assertJsonPath('productos.0.slug', $product->slug);
});

it('aparece en el catálogo de la API y en su documentación', function (): void {
    $paths = array_column(ApiCatalog::endpoints(), 'path');

    expect($paths)->toContain('productos', 'productos/{slug}');

    $markdown = ApiDocumentation::markdown();

    expect($markdown)->toContain('Listado de productos')
        ->and($markdown)->toContain('/productos');
});

it('documenta exactamente las claves que devuelve', function (): void {
    // Lo que está a null no se envía, así que para comparar las claves hace
    // falta un producto con todo relleno: foto y oferta incluidas.
    Product::factory()->visible()->onSale()->create([
        'image_media_id' => Media::factory()->create()->id,
        'image_alt' => 'Una foto',
        'availability' => ProductAvailability::Low,
    ]);

    [$headers] = apiKeyHeaders();

    $real = $this->getJson(apiUrl('productos'), $headers)->json();
    $documented = ApiDocumentation::examples()['productos'];

    // Todo lo documentado tiene que existir de verdad.
    expect(array_diff(deepKeys($documented), deepKeys($real)))->toBe([]);
});

/*
|--------------------------------------------------------------------------
| Rebajas y existencias en la web
|--------------------------------------------------------------------------
*/

it('devuelve el precio de hoy y el anterior cuando hay oferta', function (): void {
    Product::factory()->visible()->onSale(119.0)->create();

    [$headers] = apiKeyHeaders();

    $product = $this->getJson(apiUrl('productos'), $headers)->assertOk()->json('datos.0');

    // JSON no distingue enteros de decimales, así que 119,00 llega como 119.
    // Para pintarlo sin pensar está `precio_texto`.
    expect((float) $product['precio'])->toBe(119.0)
        ->and($product['precio_texto'])->toBe('119,00 €')
        ->and((float) $product['precio_anterior'])->toBe(149.0)
        ->and($product['precio_anterior_texto'])->toBe('149,00 €')
        ->and($product['descuento_porcentaje'])->toBe(20);
});

it('sin oferta no manda precio anterior, para que la web no tenga que decidir', function (): void {
    Product::factory()->visible()->create(['price' => 149.0, 'sale_price' => null]);

    [$headers] = apiKeyHeaders();

    $product = $this->getJson(apiUrl('productos'), $headers)->json('datos.0');

    expect((float) $product['precio'])->toBe(149.0)
        ->and($product)->not->toHaveKey('precio_anterior')
        ->and($product)->not->toHaveKey('descuento_porcentaje');
});

it('sirve la disponibilidad para que la web la pinte', function (): void {
    Product::factory()->visible()->outOfStock()->create();

    [$headers] = apiKeyHeaders();

    $this->getJson(apiUrl('productos'), $headers)
        ->assertJsonPath('datos.0.disponibilidad', 'out_of_stock')
        ->assertJsonPath('datos.0.disponibilidad_texto', 'Sin stock');
});

it('un producto sin stock sigue apareciendo en el listado', function (): void {
    Product::factory()->visible()->outOfStock()->create(['name' => 'Agotado']);

    [$headers] = apiKeyHeaders();

    $this->getJson(apiUrl('productos'), $headers)
        ->assertJsonCount(1, 'datos')
        ->assertJsonPath('datos.0.nombre', 'Agotado');
});

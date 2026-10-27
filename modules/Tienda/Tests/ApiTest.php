<?php

declare(strict_types=1);

use App\Models\Media;
use App\Models\User;
use App\Support\ApiCatalog;
use App\Support\ApiDocumentation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Tienda\Models\Category;
use Modules\Tienda\Models\Product;
use Modules\Tienda\Models\Promotion;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * Crea un artículo con variantes a través del formulario, como lo haría el
 * panel, y lo deja visible.
 *
 * @param  array<string, mixed>  $overrides
 */
function tiendaProductWithVariants(array $overrides = []): Product
{
    test()->actingAs(User::factory()->editor()->create())
        ->post(route('tienda.products.store'), tiendaProductPayload([
            ...tiendaVariantsPayload([1 => ['price' => '24,90']]),
            'is_visible' => true,
            ...$overrides,
        ]))
        ->assertSessionHasNoErrors();

    return Product::query()->latest('id')->firstOrFail();
}

/*
|--------------------------------------------------------------------------
| Artículos
|--------------------------------------------------------------------------
*/

it('sirve solo los artículos visibles y exige clave', function (): void {
    Product::factory()->visible()->create(['name' => 'Visible']);
    Product::factory()->create(['name' => 'Oculto']);

    $this->getJson(apiUrl('tienda/articulos'))->assertUnauthorized();

    [$headers] = apiKeyHeaders();

    $this->getJson(apiUrl('tienda/articulos'), $headers)
        ->assertOk()
        ->assertJsonCount(1, 'datos')
        ->assertJsonPath('datos.0.nombre', 'Visible')
        ->assertJsonPath('paginacion.total', 1);
});

it('no sirve un artículo oculto ni conociendo su dirección', function (): void {
    $product = Product::factory()->create();

    [$headers] = apiKeyHeaders();

    $this->getJson(apiUrl('tienda/articulos/'.$product->slug), $headers)
        ->assertNotFound()
        ->assertJsonPath('error.codigo', 'no_encontrado');
});

it('filtra por categoría con sus subcategorías', function (): void {
    $ropa = Category::factory()->create(['name' => 'Ropa']);
    $camisetas = Category::factory()->childOf($ropa)->create();
    Product::factory()->visible()->in($camisetas)->create(['name' => 'Camiseta']);
    Product::factory()->visible()->create(['name' => 'Sin categoría']);

    [$headers] = apiKeyHeaders();

    $this->getJson(apiUrl('tienda/articulos?categoria='.$ropa->slug), $headers)
        ->assertJsonCount(1, 'datos')
        ->assertJsonPath('datos.0.nombre', 'Camiseta');
});

it('responde 404 a una categoría oculta', function (): void {
    $hidden = Category::factory()->hidden()->create();

    [$headers] = apiKeyHeaders();

    $this->getJson(apiUrl('tienda/articulos?categoria='.$hidden->slug), $headers)
        ->assertNotFound()
        ->assertJsonPath('error.codigo', 'categoria_no_encontrada');
});

it('filtra destacados, disponibles y en oferta', function (): void {
    Product::factory()->visible()->featured()->create(['name' => 'Destacado']);
    Product::factory()->visible()->withStock(0)->create(['name' => 'Agotado']);
    $promoted = Product::factory()->visible()->create(['name' => 'Rebajado']);
    Promotion::factory()->forProducts()->create()->products()->attach($promoted);

    [$headers] = apiKeyHeaders();

    $this->getJson(apiUrl('tienda/articulos?destacados=1'), $headers)
        ->assertJsonCount(1, 'datos')->assertJsonPath('datos.0.nombre', 'Destacado');

    $names = array_column($this->getJson(apiUrl('tienda/articulos?disponibles=1'), $headers)->json('datos'), 'nombre');
    expect($names)->not->toContain('Agotado');

    $this->getJson(apiUrl('tienda/articulos?en_oferta=1'), $headers)
        ->assertJsonCount(1, 'datos')->assertJsonPath('datos.0.nombre', 'Rebajado');
});

it('los destacados salen primero por defecto', function (): void {
    Product::factory()->visible()->create(['name' => 'Normal', 'position' => 1]);
    Product::factory()->visible()->featured()->create(['name' => 'Destacado', 'position' => 9]);

    [$headers] = apiKeyHeaders();

    $this->getJson(apiUrl('tienda/articulos'), $headers)
        ->assertJsonPath('datos.0.nombre', 'Destacado')
        ->assertJsonPath('datos.0.destacado', true)
        ->assertJsonPath('datos.1.destacado', false);
});

it('devuelve el precio ya rebajado y lo que había antes', function (): void {
    Product::factory()->visible()->create(['price' => 50]);
    Promotion::factory()->percentage(20)->create(['name' => 'Rebajas', 'description' => 'Todo al 20 %']);

    [$headers] = apiKeyHeaders();

    $product = $this->getJson(apiUrl('tienda/articulos'), $headers)->json('datos.0');

    expect((float) $product['precio'])->toBe(40.0)
        ->and($product['precio_texto'])->toBe('40,00 €')
        ->and((float) $product['precio_anterior'])->toBe(50.0)
        ->and($product['descuento_porcentaje'])->toBe(20)
        ->and($product['promocion']['nombre'])->toBe('Rebajas');
});

it('sin precio no manda precio y se puede consultar', function (): void {
    Product::factory()->visible()->withoutPrice()->create();

    [$headers] = apiKeyHeaders();

    expect($this->getJson(apiUrl('tienda/articulos'), $headers)->json('datos.0'))
        ->not->toHaveKey('precio')
        ->not->toHaveKey('precio_anterior');
});

it('con variantes de precios distintos dice «desde» el más barato', function (): void {
    tiendaProductWithVariants();

    [$headers] = apiKeyHeaders();

    $product = $this->getJson(apiUrl('tienda/articulos'), $headers)->json('datos.0');

    expect((float) $product['precio'])->toBe(19.9)
        ->and($product['precio_desde'])->toBeTrue()
        ->and($product['tiene_variantes'])->toBeTrue();
});

it('la ficha trae opciones y variantes con su propio precio, stock y enlace', function (): void {
    tiendaSettings(['whatsapp_number' => '+34 600 123 456']);
    $product = tiendaProductWithVariants();

    [$headers] = apiKeyHeaders();

    $data = $this->getJson(apiUrl('tienda/articulos/'.$product->slug), $headers)->assertOk()->json('datos');

    expect($data['opciones'])->toBe([
        ['nombre' => 'Talla', 'valores' => ['S', 'M']],
        ['nombre' => 'Color', 'valores' => ['Rojo']],
    ])
        ->and($data['variantes'])->toHaveCount(2)
        ->and($data['variantes'][1]['opciones'])->toBe(['Talla' => 'M', 'Color' => 'Rojo'])
        ->and((float) $data['variantes'][1]['precio'])->toBe(24.9)
        ->and($data['variantes'][1]['disponibilidad'])->toBe('available')
        ->and($data['variantes'][1]['whatsapp_url'])->toStartWith('https://wa.me/34600123456?text=')
        ->and(rawurldecode($data['variantes'][1]['whatsapp_url']))->toContain('(M / Rojo)');
});

it('no envía las variantes desactivadas', function (): void {
    $product = tiendaProductWithVariants(tiendaVariantsPayload([1 => ['is_active' => false]]));

    [$headers] = apiKeyHeaders();

    $this->getJson(apiUrl('tienda/articulos/'.$product->slug), $headers)
        ->assertJsonCount(1, 'datos.variantes');
});

it('solo da las unidades si la tienda quiere enseñarlas', function (): void {
    Product::factory()->visible()->withStock(7)->create();

    [$headers] = apiKeyHeaders();

    expect($this->getJson(apiUrl('tienda/articulos'), $headers)->json('datos.0'))->not->toHaveKey('unidades');

    tiendaSettings(['show_stock_quantity' => true]);

    // Cambiar un ajuste vacía la caché de la API: la misma dirección ya
    // responde con el dato nuevo.
    $this->getJson(apiUrl('tienda/articulos'), $headers)->assertJsonPath('datos.0.unidades', 7);
});

it('sin número de WhatsApp no manda enlace', function (): void {
    Product::factory()->visible()->create();

    [$headers] = apiKeyHeaders();

    expect($this->getJson(apiUrl('tienda/articulos'), $headers)->json('datos.0'))->not->toHaveKey('whatsapp_url');
});

it('rellena el mensaje de WhatsApp con la plantilla de Configuración', function (): void {
    tiendaSettings([
        'whatsapp_number' => '600123456',
        'whatsapp_message' => 'Quiero :articulo (:referencia) a :precio. :enlace',
        'product_url' => 'https://mitienda.com/p/:slug',
    ]);
    $product = Product::factory()->visible()->create(['name' => 'Taza', 'sku' => 'TZ-1', 'price' => 9]);

    [$headers] = apiKeyHeaders();

    $url = $this->getJson(apiUrl('tienda/articulos/'.$product->slug), $headers)->json('datos.whatsapp_url');

    expect(rawurldecode((string) $url))
        ->toBe('https://wa.me/600123456?text=Quiero Taza (TZ-1) a 9,00 €. https://mitienda.com/p/'.$product->slug);
});

it('la ficha trae galería, ruta de migas y relacionados', function (): void {
    $ropa = Category::factory()->create(['name' => 'Ropa']);
    $camisetas = Category::factory()->childOf($ropa)->create(['name' => 'Camisetas']);
    $product = Product::factory()->visible()->in($camisetas)->create();
    $product->images()->create(['media_id' => Media::factory()->create()->id, 'alt' => 'Frente', 'position' => 0]);
    Product::factory()->visible()->in($camisetas)->create(['name' => 'Hermana']);

    [$headers] = apiKeyHeaders();

    $data = $this->getJson(apiUrl('tienda/articulos/'.$product->slug), $headers)->json('datos');

    expect(array_column($data['ruta'], 'nombre'))->toBe(['Ropa', 'Camisetas'])
        ->and($data['imagenes'][0]['alt'])->toBe('Frente')
        ->and(array_column($data['relacionados'], 'nombre'))->toBe(['Hermana']);
});

/*
|--------------------------------------------------------------------------
| Categorías, promociones y configuración
|--------------------------------------------------------------------------
*/

it('sirve el árbol de categorías visibles con sus recuentos', function (): void {
    $ropa = Category::factory()->create(['name' => 'Ropa']);
    $camisetas = Category::factory()->childOf($ropa)->create(['name' => 'Camisetas']);
    Category::factory()->hidden()->create(['name' => 'Oculta']);

    Product::factory()->visible()->in($camisetas)->create();
    $both = Product::factory()->visible()->in($ropa)->create();
    $both->categories()->attach($camisetas);
    Product::factory()->in($ropa)->create(); // oculto: no cuenta

    [$headers] = apiKeyHeaders();

    $this->getJson(apiUrl('tienda/categorias'), $headers)
        ->assertOk()
        ->assertJsonCount(1, 'datos')
        ->assertJsonPath('datos.0.nombre', 'Ropa')
        ->assertJsonPath('datos.0.articulos', 2)
        ->assertJsonPath('datos.0.subcategorias.0.nombre', 'Camisetas')
        ->assertJsonPath('datos.0.subcategorias.0.articulos', 2);
});

it('una subcategoría de una categoría oculta no se sirve', function (): void {
    $hidden = Category::factory()->hidden()->create();
    $child = Category::factory()->childOf($hidden)->create();

    [$headers] = apiKeyHeaders();

    $this->getJson(apiUrl('tienda/categorias/'.$child->slug), $headers)->assertNotFound();
});

it('sirve una categoría con su ruta', function (): void {
    $ropa = Category::factory()->create(['name' => 'Ropa']);
    $camisetas = Category::factory()->childOf($ropa)->create(['name' => 'Camisetas']);

    [$headers] = apiKeyHeaders();

    $this->getJson(apiUrl('tienda/categorias/'.$camisetas->slug), $headers)
        ->assertOk()
        ->assertJsonPath('datos.nombre', 'Camisetas')
        ->assertJsonPath('datos.ruta.0.nombre', 'Ropa');
});

it('sirve solo las promociones en vigor', function (): void {
    Promotion::factory()->create(['name' => 'Ahora']);
    Promotion::factory()->create(['name' => 'Mañana', 'starts_at' => now()->addDay()]);

    [$headers] = apiKeyHeaders();

    $this->getJson(apiUrl('tienda/promociones'), $headers)
        ->assertJsonCount(1, 'datos')
        ->assertJsonPath('datos.0.nombre', 'Ahora')
        ->assertJsonPath('datos.0.valor_texto', '-20 %');
});

it('sirve la configuración de contacto', function (): void {
    [$headers] = apiKeyHeaders();

    expect($this->getJson(apiUrl('tienda/configuracion'), $headers)->json('datos'))->not->toHaveKey('whatsapp');

    tiendaSettings(['whatsapp_number' => '+34 600 123 456']);

    $this->getJson(apiUrl('tienda/configuracion'), $headers)
        ->assertJsonPath('datos.whatsapp.numero', '+34600123456')
        ->assertJsonPath('datos.whatsapp.url', 'https://wa.me/34600123456')
        ->assertJsonPath('datos.moneda', '€');
});

/*
|--------------------------------------------------------------------------
| Lo que aporta al núcleo
|--------------------------------------------------------------------------
*/

it('aparece en el catálogo de la API y en su documentación', function (): void {
    $paths = array_column(ApiCatalog::endpoints(), 'path');

    expect($paths)->toContain('tienda/articulos', 'tienda/articulos/{slug}', 'tienda/categorias', 'tienda/promociones');

    expect(ApiDocumentation::markdown())->toContain('Tienda: listado de artículos');
});

it('documenta claves que existen de verdad', function (): void {
    tiendaSettings(['whatsapp_number' => '600123456', 'show_stock_quantity' => true]);

    $ropa = Category::factory()->create([
        'image_media_id' => Media::factory()->create()->id,
        'meta_title' => 'Ropa de algodón',
    ]);
    $camisetas = Category::factory()->childOf($ropa)->create(['image_media_id' => Media::factory()->create()->id]);
    $product = tiendaProductWithVariants([
        'brand' => 'Alminares',
        'category_id' => $camisetas->id,
        'is_featured' => true,
        'stock' => 2,
        'meta_title' => 'Título',
        'images' => [['media_id' => Media::factory()->create()->id, 'alt' => 'Frente']],
    ]);
    $product->variants()->first()?->update(['media_id' => Media::factory()->create()->id]);
    // El relacionado también con todo relleno: la documentación lo describe
    // con la misma forma que el artículo.
    Product::factory()->visible()->in($camisetas)->create(['brand' => 'Alminares'])
        ->images()->create(['media_id' => Media::factory()->create()->id, 'position' => 0]);
    Promotion::factory()->forCategories()->create([
        'description' => 'Texto',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ])->categories()->attach($ropa);

    [$headers] = apiKeyHeaders();

    $examples = ApiDocumentation::examples();

    $checks = [
        'tienda_articulos' => 'tienda/articulos?orden=destacados',
        'tienda_articulo' => 'tienda/articulos/'.$product->slug,
        'tienda_categorias' => 'tienda/categorias',
        'tienda_categoria' => 'tienda/categorias/'.$ropa->slug,
        'tienda_promociones' => 'tienda/promociones',
        'tienda_configuracion' => 'tienda/configuracion',
    ];

    foreach ($checks as $key => $path) {
        $real = $this->getJson(apiUrl($path), $headers)->assertOk()->json();

        expect(array_diff(deepKeys($examples[$key]), deepKeys($real)))
            ->toBe([], "La documentación de {$key} trae claves que la respuesta no tiene");
    }
});

it('cuenta sus artículos en el estado y aporta su sección al sitemap', function (): void {
    $product = Product::factory()->visible()->create();
    Product::factory()->create();

    $this->getJson(apiUrl('estado'))->assertJsonPath('articulos_tienda_visibles', 1);

    [$headers] = apiKeyHeaders();

    $this->getJson(apiUrl('sitemap'), $headers)
        ->assertJsonPath('tienda_articulos.0.slug', $product->slug);
});

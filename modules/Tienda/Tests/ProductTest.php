<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Media;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;
use Modules\Tienda\Enums\StockMovementType;
use Modules\Tienda\Models\Category;
use Modules\Tienda\Models\Product;
use Modules\Tienda\Models\StockMovement;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->editor = User::factory()->editor()->create();
    $this->reader = User::factory()->reader()->create();
});

/*
|--------------------------------------------------------------------------
| Listado
|--------------------------------------------------------------------------
*/

it('lista los artículos a quien puede verlos', function (): void {
    Product::factory()->create(['name' => 'Sudadera']);

    $this->actingAs($this->reader)
        ->get(route('tienda.products.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Tienda::Products/Index')
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Sudadera'));
});

it('filtra por categoría incluyendo sus subcategorías', function (): void {
    $ropa = Category::factory()->create(['name' => 'Ropa']);
    $camisetas = Category::factory()->childOf($ropa)->create(['name' => 'Camisetas']);
    $otra = Category::factory()->create(['name' => 'Hogar']);

    Product::factory()->in($camisetas)->create(['name' => 'Camiseta']);
    Product::factory()->in($otra)->create(['name' => 'Cojín']);

    $this->actingAs($this->editor)
        ->get(route('tienda.products.index', ['category' => $ropa->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Camiseta'));
});

it('filtra agotados y con pocas unidades', function (): void {
    Product::factory()->withStock(0)->create(['name' => 'Agotado']);
    Product::factory()->withStock(2)->create(['name' => 'Pocas']);
    Product::factory()->withStock(50)->create(['name' => 'Muchas']);

    $this->actingAs($this->editor)
        ->get(route('tienda.products.index', ['extra' => 'out_of_stock']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Agotado'));

    $this->actingAs($this->editor)
        ->get(route('tienda.products.index', ['extra' => 'low']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Pocas'));
});

/*
|--------------------------------------------------------------------------
| Alta y edición
|--------------------------------------------------------------------------
*/

it('crea un artículo y apunta el stock inicial en el historial', function (): void {
    $this->actingAs($this->editor)
        ->post(route('tienda.products.store'), tiendaProductPayload())
        ->assertSessionHasNoErrors();

    $product = Product::query()->firstOrFail();

    expect($product->slug)->toBe('camiseta-de-algodon')
        ->and($product->price)->toBe('19.90')
        ->and($product->stock)->toBe(10);

    $movement = StockMovement::query()->firstOrFail();

    expect($movement->type)->toBe(StockMovementType::Entry)
        ->and($movement->quantity)->toBe(10)
        ->and($movement->stock_after)->toBe(10)
        ->and($movement->note)->toBe('Stock inicial')
        ->and($movement->user_id)->toBe($this->editor->id);
});

it('cambiar las unidades desde la ficha queda como recuento', function (): void {
    $this->actingAs($this->editor)->post(route('tienda.products.store'), tiendaProductPayload());
    $product = Product::query()->firstOrFail();

    $this->actingAs($this->editor)
        ->put(route('tienda.products.update', $product), tiendaProductPayload(['stock' => 7, 'stock_loaded' => 10]))
        ->assertSessionHasNoErrors();

    expect($product->fresh()->stock)->toBe(7);

    $last = StockMovement::query()->latest('id')->firstOrFail();

    expect($last->type)->toBe(StockMovementType::Count)
        ->and($last->quantity)->toBe(-3);
});

it('guardar la ficha sin tocar las unidades no pisa una venta registrada mientras tanto', function (): void {
    $this->actingAs($this->editor)->post(route('tienda.products.store'), tiendaProductPayload());
    $product = Product::query()->firstOrFail();

    // Alguien vende 2 desde el inventario mientras la ficha sigue abierta
    // con las 10 de antes.
    $this->actingAs($this->editor)->post(route('tienda.stock.store', $product), [
        'type' => 'sale', 'quantity' => 2,
    ]);

    $this->actingAs($this->editor)
        ->put(route('tienda.products.update', $product), tiendaProductPayload(['stock' => 10, 'stock_loaded' => 10]));

    expect($product->fresh()->stock)->toBe(8);
});

it('quien no gestiona el stock no lo cambia desde la ficha', function (): void {
    $product = Product::factory()->withStock(5)->create(['sku' => 'CAM-001']);
    Role::findByName(RoleName::Editor->value)->revokePermissionTo('tienda.stock.manage');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->editor->fresh())
        ->put(route('tienda.products.update', $product), tiendaProductPayload(['stock' => 99, 'stock_loaded' => 5]))
        ->assertSessionHasNoErrors();

    expect($product->fresh()->stock)->toBe(5);
});

it('entiende el precio como lo escribe la gente', function (string $written, ?string $expected): void {
    $this->actingAs($this->editor)
        ->post(route('tienda.products.store'), tiendaProductPayload(['price' => $written]))
        ->assertSessionHasNoErrors();

    expect(Product::query()->firstOrFail()->price)->toBe($expected);
})->with([
    'con coma' => ['19,90', '19.90'],
    'con punto' => ['19.90', '19.90'],
    'con millar' => ['1.299,50', '1299.50'],
    'con símbolo' => ['25 €', '25.00'],
    'vacío' => ['', null],
]);

it('impide repetir la referencia, también contra la papelera', function (): void {
    Product::factory()->create(['sku' => 'CAM-001'])->delete();

    $this->actingAs($this->editor)
        ->post(route('tienda.products.store'), tiendaProductPayload())
        ->assertSessionHasErrors(['sku' => 'Ya hay otro artículo con esa referencia.']);
});

it('limpia el HTML de la descripción', function (): void {
    $this->actingAs($this->editor)->post(route('tienda.products.store'), tiendaProductPayload([
        'description' => '<p>Bonita</p><script>alert(1)</script>',
    ]));

    expect(Product::query()->firstOrFail()->description)
        ->toContain('Bonita')
        ->not->toContain('<script>');
});

it('guarda categorías adicionales sin repetir la principal', function (): void {
    $ropa = Category::factory()->create();
    $ofertas = Category::factory()->create();

    $this->actingAs($this->editor)->post(route('tienda.products.store'), tiendaProductPayload([
        'category_id' => $ropa->id,
        'category_ids' => [$ropa->id, $ofertas->id],
    ]))->assertSessionHasNoErrors();

    $product = Product::query()->firstOrFail();

    expect($product->category_id)->toBe($ropa->id)
        ->and($product->categories->pluck('id')->all())->toBe([$ofertas->id]);
});

it('congela la dirección en cuanto el artículo se hace visible', function (): void {
    $this->actingAs($this->editor)->post(route('tienda.products.store'), tiendaProductPayload(['is_visible' => true]));

    expect(Product::query()->firstOrFail()->is_slug_locked)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Galería
|--------------------------------------------------------------------------
*/

it('guarda la galería en orden y registra el uso de cada foto', function (): void {
    $first = Media::factory()->create();
    $second = Media::factory()->create();

    $this->actingAs($this->editor)->post(route('tienda.products.store'), tiendaProductPayload([
        'images' => [
            ['media_id' => $second->id, 'alt' => 'De frente'],
            ['media_id' => $first->id, 'alt' => 'De espaldas'],
        ],
    ]))->assertSessionHasNoErrors();

    $product = Product::query()->firstOrFail();

    expect($product->images->pluck('media_id')->all())->toBe([$second->id, $first->id])
        ->and($product->cover()?->alt)->toBe('De frente');

    $this->assertDatabaseHas('media_usages', [
        'media_id' => $first->id,
        'usable_type' => 'tienda.product',
        'usable_id' => $product->id,
    ]);
});

it('no deja añadir la misma foto dos veces', function (): void {
    $media = Media::factory()->create();

    $this->actingAs($this->editor)->post(route('tienda.products.store'), tiendaProductPayload([
        'images' => [['media_id' => $media->id], ['media_id' => $media->id]],
    ]))->assertSessionHasErrors('images.1.media_id');
});

/*
|--------------------------------------------------------------------------
| Variantes
|--------------------------------------------------------------------------
*/

it('crea variantes con su propio stock y suma el total en el artículo', function (): void {
    $this->actingAs($this->editor)
        ->post(route('tienda.products.store'), tiendaProductPayload(tiendaVariantsPayload()))
        ->assertSessionHasNoErrors();

    $product = Product::query()->with('variants')->firstOrFail();

    expect($product->has_variants)->toBeTrue()
        ->and($product->variants)->toHaveCount(2)
        ->and($product->variants[0]->label())->toBe('S / Rojo')
        ->and($product->variants[1]->stock)->toBe(6)
        ->and($product->stock)->toBe(10);

    expect(StockMovement::query()->whereNotNull('variant_id')->count())->toBe(2);
});

it('conserva el stock de una variante al añadir una talla nueva', function (): void {
    $this->actingAs($this->editor)->post(route('tienda.products.store'), tiendaProductPayload(tiendaVariantsPayload()));
    $product = Product::query()->with('variants')->firstOrFail();
    $medium = $product->variants[1];

    $payload = tiendaVariantsPayload([
        0 => ['id' => $product->variants[0]->id, 'stock_loaded' => 4],
        1 => ['id' => $medium->id, 'stock_loaded' => 6],
        2 => ['values' => ['L', 'Rojo'], 'sku' => 'CAM-L-R', 'price' => '24,90', 'stock' => 3, 'is_active' => true],
    ]);
    $payload['options'][0]['values'] = ['S', 'M', 'L'];

    $this->actingAs($this->editor)
        ->put(route('tienda.products.update', $product), tiendaProductPayload($payload))
        ->assertSessionHasNoErrors();

    $product = $product->fresh(['variants']);

    expect($product->variants)->toHaveCount(3)
        ->and($product->variants->firstWhere('id', $medium->id)?->stock)->toBe(6)
        ->and($product->variants[2]->price)->toBe('24.90')
        ->and($product->stock)->toBe(13);
});

it('rechaza una variante con un valor que no es de sus opciones', function (): void {
    $payload = tiendaVariantsPayload([0 => ['values' => ['XXL', 'Rojo']]]);

    $this->actingAs($this->editor)
        ->post(route('tienda.products.store'), tiendaProductPayload($payload))
        ->assertSessionHasErrors(['variants.0.values' => '«XXL» no es un valor de «Talla».']);
});

it('rechaza combinaciones repetidas', function (): void {
    $payload = tiendaVariantsPayload([1 => ['values' => ['S', 'Rojo']]]);

    $this->actingAs($this->editor)
        ->post(route('tienda.products.store'), tiendaProductPayload($payload))
        ->assertSessionHasErrors(['variants.1.values' => 'Esta combinación está repetida.']);
});

it('no admite más de tres opciones', function (): void {
    $this->actingAs($this->editor)->post(route('tienda.products.store'), tiendaProductPayload([
        'options' => [
            ['name' => 'A', 'values' => ['1']],
            ['name' => 'B', 'values' => ['1']],
            ['name' => 'C', 'values' => ['1']],
            ['name' => 'D', 'values' => ['1']],
        ],
        'variants' => [['values' => ['1', '1', '1', '1']]],
    ]))->assertSessionHasErrors('options');
});

it('las variantes desactivadas no cuentan en el stock total', function (): void {
    $payload = tiendaVariantsPayload([1 => ['is_active' => false]]);

    $this->actingAs($this->editor)->post(route('tienda.products.store'), tiendaProductPayload($payload));

    expect(Product::query()->firstOrFail()->stock)->toBe(4);
});

it('quitar las opciones vuelve a un artículo sencillo', function (): void {
    $this->actingAs($this->editor)->post(route('tienda.products.store'), tiendaProductPayload(tiendaVariantsPayload()));
    $product = Product::query()->firstOrFail();

    $this->actingAs($this->editor)
        ->put(route('tienda.products.update', $product), tiendaProductPayload(['stock' => 3, 'stock_loaded' => 10]))
        ->assertSessionHasNoErrors();

    $product = $product->fresh(['variants', 'options']);

    expect($product->has_variants)->toBeFalse()
        ->and($product->variants)->toHaveCount(0)
        ->and($product->options)->toHaveCount(0)
        ->and($product->stock)->toBe(3);
});

/*
|--------------------------------------------------------------------------
| Papelera, permisos y lo que aporta el módulo
|--------------------------------------------------------------------------
*/

it('envía un artículo a la papelera, libera sus fotos y lo restaura', function (): void {
    $media = Media::factory()->create();
    $this->actingAs($this->editor)->post(route('tienda.products.store'), tiendaProductPayload([
        'images' => [['media_id' => $media->id]],
    ]));
    $product = Product::query()->firstOrFail();

    $this->actingAs($this->editor)->delete(route('tienda.products.destroy', $product));

    expect($product->fresh()->trashed())->toBeTrue();
    $this->assertDatabaseMissing('media_usages', ['usable_type' => 'tienda.product', 'usable_id' => $product->id]);

    $this->actingAs($this->editor)->post(route('tienda.products.restore', $product));

    expect($product->fresh()->trashed())->toBeFalse();
    $this->assertDatabaseHas('media_usages', ['usable_type' => 'tienda.product', 'usable_id' => $product->id]);
});

it('deja consultar pero no modificar a quien solo puede ver', function (): void {
    $product = Product::factory()->create();

    $this->actingAs($this->reader)->get(route('tienda.products.index'))->assertOk();
    $this->actingAs($this->reader)->get(route('tienda.products.create'))->assertForbidden();
    $this->actingAs($this->reader)->post(route('tienda.products.store'), tiendaProductPayload())->assertForbidden();
    $this->actingAs($this->reader)->get(route('tienda.products.edit', $product))->assertForbidden();
    $this->actingAs($this->reader)->delete(route('tienda.products.destroy', $product))->assertForbidden();
});

it('exige sesión iniciada', function (): void {
    $this->get(route('tienda.products.index'))->assertRedirect(route('login'));
});

it('deja constancia en la auditoría con su alias', function (): void {
    $this->actingAs($this->editor)->post(route('tienda.products.store'), tiendaProductPayload());

    $product = Product::query()->firstOrFail();

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => 'tienda.product',
        'subject_id' => $product->id,
        'event' => 'created',
    ]);
});

it('aporta su menú, sus tarjetas y sus avisos al panel', function (): void {
    Product::factory()->visible()->withStock(0)->create();
    Product::factory()->visible()->withStock(1)->create();
    Product::factory()->create();

    $this->actingAs($this->editor)
        ->get(route('dashboard'))
        ->assertInertia(function (AssertableInertia $page): void {
            /** @var array<string, mixed> $props */
            $props = $page->toArray()['props'];

            $labels = array_merge(...array_map(
                fn (array $group): array => array_column($group['items'], 'label'),
                $props['menu'],
            ));

            expect($labels)->toContain('Artículos', 'Inventario', 'Promociones')
                ->and(array_column($props['cards'], 'count', 'key'))
                ->toHaveKey('tienda.total', 3)
                ->toHaveKey('tienda.visible', 2)
                ->and(array_column($props['alerts'], 'text', 'key'))
                ->toHaveKey('tienda.agotados', 'Hay 1 artículo agotado a la vista en la tienda.')
                ->toHaveKey('tienda.pocas-unidades', 'A 1 artículo de la tienda le quedan pocas unidades.');
        });
});

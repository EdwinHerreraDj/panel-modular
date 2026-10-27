<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;
use Modules\Tienda\Enums\StockStatus;
use Modules\Tienda\Models\Product;
use Modules\Tienda\Models\StockMovement;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->editor = User::factory()->editor()->create();
});

it('registra entradas, ventas, devoluciones y mermas', function (string $type, int $quantity, int $expected): void {
    $product = Product::factory()->withStock(10)->create();

    $this->actingAs($this->editor)
        ->post(route('tienda.stock.store', $product), ['type' => $type, 'quantity' => $quantity, 'note' => 'Pedido 42'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    expect($product->fresh()->stock)->toBe($expected);

    $movement = StockMovement::query()->firstOrFail();

    expect($movement->quantity)->toBe($expected - 10)
        ->and($movement->stock_after)->toBe($expected)
        ->and($movement->note)->toBe('Pedido 42');
})->with([
    'entrada' => ['entry', 5, 15],
    'venta' => ['sale', 3, 7],
    'devolución' => ['return', 1, 11],
    'merma' => ['loss', 2, 8],
]);

it('un recuento fija el número y apunta la diferencia', function (): void {
    $product = Product::factory()->withStock(10)->create();

    $this->actingAs($this->editor)
        ->post(route('tienda.stock.store', $product), ['type' => 'count', 'quantity' => 6]);

    expect($product->fresh()->stock)->toBe(6)
        ->and(StockMovement::query()->firstOrFail()->quantity)->toBe(-4);
});

it('no deja vender lo que no hay', function (): void {
    $product = Product::factory()->withStock(2)->create();

    $this->actingAs($this->editor)
        ->post(route('tienda.stock.store', $product), ['type' => 'sale', 'quantity' => 3])
        ->assertSessionHasErrors(['quantity' => 'Solo quedan 2 unidades.']);

    expect($product->fresh()->stock)->toBe(2)
        ->and(StockMovement::query()->count())->toBe(0);
});

it('en un artículo con variantes exige elegir la variante', function (): void {
    $this->actingAs($this->editor)->post(route('tienda.products.store'), tiendaProductPayload(tiendaVariantsPayload()));
    $product = Product::query()->firstOrFail();

    $this->actingAs($this->editor)
        ->post(route('tienda.stock.store', $product), ['type' => 'sale', 'quantity' => 1])
        ->assertSessionHasErrors('variant_id');
});

it('mover una variante actualiza el total del artículo', function (): void {
    $this->actingAs($this->editor)->post(route('tienda.products.store'), tiendaProductPayload(tiendaVariantsPayload()));
    $product = Product::query()->with('variants')->firstOrFail();
    $small = $product->variants[0];

    $this->actingAs($this->editor)
        ->post(route('tienda.stock.store', $product), ['variant_id' => $small->id, 'type' => 'sale', 'quantity' => 4])
        ->assertSessionHasNoErrors();

    $product = $product->fresh(['variants']);

    expect($product->variants[0]->stock)->toBe(0)
        ->and($product->variants[0]->stockStatus())->toBe(StockStatus::OutOfStock)
        ->and($product->stock)->toBe(6);

    expect(StockMovement::query()->latest('id')->firstOrFail()->variant_label)->toBe('S / Rojo');
});

it('no acepta la variante de otro artículo', function (): void {
    $this->actingAs($this->editor)->post(route('tienda.products.store'), tiendaProductPayload(tiendaVariantsPayload()));
    $withVariants = Product::query()->with('variants')->firstOrFail();
    $other = Product::factory()->create();

    $this->actingAs($this->editor)
        ->post(route('tienda.stock.store', $other), [
            'variant_id' => $withVariants->variants[0]->id,
            'type' => 'entry',
            'quantity' => 1,
        ])
        ->assertSessionHasErrors('variant_id');
});

it('el umbral de pocas unidades sale de Configuración o del propio artículo', function (): void {
    tiendaSettings(['low_stock_threshold' => 5]);

    $general = Product::factory()->withStock(4)->create();
    $own = Product::factory()->withStock(4)->create(['low_stock_threshold' => 2]);

    expect($general->stockStatus())->toBe(StockStatus::Low)
        ->and($own->stockStatus())->toBe(StockStatus::Available);
});

it('sin control de stock un artículo siempre está disponible', function (): void {
    $product = Product::factory()->withStock(0)->create(['track_stock' => false]);

    expect($product->stockStatus())->toBe(StockStatus::Available);
});

it('enseña el inventario y el historial', function (): void {
    $product = Product::factory()->withStock(3)->create(['name' => 'Taza']);

    $this->actingAs($this->editor)->post(route('tienda.stock.store', $product), ['type' => 'sale', 'quantity' => 1]);

    $this->actingAs($this->editor)
        ->get(route('tienda.stock.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Tienda::Stock/Index')
            ->where('products.data.0.name', 'Taza')
            ->where('products.data.0.stock', 2));

    $this->actingAs($this->editor)
        ->get(route('tienda.stock.history', ['product' => $product->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Tienda::Stock/History')
            ->where('movements.data.0.product_name', 'Taza')
            ->where('movements.data.0.quantity', -1)
            ->where('movements.data.0.user_name', $this->editor->name));
});

it('una persona lectora ve el inventario pero no lo mueve', function (): void {
    $reader = User::factory()->reader()->create();
    $product = Product::factory()->create();

    $this->actingAs($reader)->get(route('tienda.stock.index'))->assertOk();
    $this->actingAs($reader)
        ->post(route('tienda.stock.store', $product), ['type' => 'entry', 'quantity' => 1])
        ->assertForbidden();
});

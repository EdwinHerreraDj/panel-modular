<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;
use Modules\Tienda\Models\Category;
use Modules\Tienda\Models\Product;
use Modules\Tienda\Models\Promotion;
use Modules\Tienda\Support\Price;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->editor = User::factory()->editor()->create();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function tiendaPromotionPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Rebajas de verano',
        'description' => '',
        'type' => 'percentage',
        'value' => '20',
        'scope' => 'all',
        'category_ids' => [],
        'product_ids' => [],
        'starts_at' => '',
        'ends_at' => '',
        'is_active' => true,
    ], $overrides);
}

it('aplica un porcentaje a toda la tienda', function (): void {
    $product = Product::factory()->create(['price' => 50]);
    Promotion::factory()->percentage(20)->create();

    $price = Price::of($product);

    expect($price->final)->toBe(40.0)
        ->and($price->previous())->toBe(50.0)
        ->and($price->discountPercentage())->toBe(20)
        ->and($price->formatted())->toBe('40,00 €');
});

it('un descuento fijo nunca deja el precio en negativo', function (): void {
    $product = Product::factory()->create(['price' => 8]);
    Promotion::factory()->fixed(10)->create();

    expect(Price::of($product)->final)->toBe(0.0);
});

it('una promoción de categoría alcanza a sus subcategorías y a las categorías adicionales', function (): void {
    $ropa = Category::factory()->create();
    $camisetas = Category::factory()->childOf($ropa)->create();
    $ofertas = Category::factory()->create();

    $inChild = Product::factory()->in($camisetas)->create(['price' => 100]);
    $asExtra = Product::factory()->create(['price' => 100]);
    $asExtra->categories()->attach($ropa);
    $outside = Product::factory()->in($ofertas)->create(['price' => 100]);

    Promotion::factory()->forCategories()->percentage(10)->create()->categories()->attach($ropa);

    expect(Price::of($inChild)->final)->toBe(90.0)
        ->and(Price::of($asExtra->fresh())->final)->toBe(90.0)
        ->and(Price::of($outside)->final)->toBe(100.0);
});

it('si alcanzan dos promociones, gana la que más descuenta', function (): void {
    $product = Product::factory()->create(['price' => 100]);
    Promotion::factory()->percentage(10)->create(['name' => 'Pequeña']);
    Promotion::factory()->forProducts()->fixed(25)->create(['name' => 'Grande'])->products()->attach($product);

    $price = Price::of($product);

    expect($price->final)->toBe(75.0)
        ->and($price->promotion?->name)->toBe('Grande');
});

it('respeta las fechas y el interruptor', function (): void {
    $product = Product::factory()->create(['price' => 100]);

    Promotion::factory()->create(['starts_at' => now()->addDay()]);
    Promotion::factory()->create(['ends_at' => now()->subMinute()]);
    Promotion::factory()->create(['is_active' => false]);

    expect(Price::of($product)->isOnSale())->toBeFalse();
});

it('un artículo sin precio no tiene nada que rebajar', function (): void {
    $product = Product::factory()->withoutPrice()->create();
    Promotion::factory()->create();

    expect(Price::of($product)->final)->toBeNull();
});

it('guarda las fechas en UTC a partir de la hora del sitio', function (): void {
    Setting::current()->update(['timezone' => 'Europe/Madrid']);

    $this->actingAs($this->editor)
        ->post(route('tienda.promotions.store'), tiendaPromotionPayload([
            'starts_at' => '2026-07-01T00:00',
            'ends_at' => '2026-07-31T23:59',
        ]))
        ->assertSessionHasNoErrors();

    $promotion = Promotion::query()->firstOrFail();

    // En julio Madrid va dos horas por delante de UTC.
    expect($promotion->starts_at?->utc()->format('Y-m-d H:i'))->toBe('2026-06-30 22:00')
        ->and($promotion->ends_at?->utc()->format('Y-m-d H:i'))->toBe('2026-07-31 21:59');
});

it('solo guarda las categorías si el alcance es por categorías', function (): void {
    $ropa = Category::factory()->create();

    $this->actingAs($this->editor)->post(route('tienda.promotions.store'), tiendaPromotionPayload([
        'scope' => 'all',
        'category_ids' => [$ropa->id],
    ]))->assertSessionHasNoErrors();

    expect(Promotion::query()->firstOrFail()->categories)->toHaveCount(0);
});

it('valida lo que no tiene sentido', function (array $payload, string $field): void {
    $this->actingAs($this->editor)
        ->post(route('tienda.promotions.store'), tiendaPromotionPayload($payload))
        ->assertSessionHasErrors($field);
})->with([
    'más del 100 %' => [['value' => '120'], 'value'],
    'descuento cero' => [['value' => '0'], 'value'],
    'categorías sin elegir' => [['scope' => 'categories'], 'category_ids'],
    'artículos sin elegir' => [['scope' => 'products'], 'product_ids'],
    'termina antes de empezar' => [['starts_at' => '2026-07-10T00:00', 'ends_at' => '2026-07-01T00:00'], 'ends_at'],
]);

it('una persona lectora no puede crear promociones', function (): void {
    $reader = User::factory()->reader()->create();

    $this->actingAs($reader)->get(route('tienda.promotions.index'))->assertOk();
    $this->actingAs($reader)
        ->post(route('tienda.promotions.store'), tiendaPromotionPayload())
        ->assertForbidden();
});

it('borra una promoción y deja constancia', function (): void {
    $promotion = Promotion::factory()->create();

    $this->actingAs($this->editor)->delete(route('tienda.promotions.destroy', $promotion));

    expect(Promotion::query()->count())->toBe(0);
    $this->assertDatabaseHas('activity_logs', ['subject_type' => 'tienda.promotion', 'event' => 'deleted']);
});

it('una promoción en vigor deja de aplicarse al terminar', function (): void {
    $product = Product::factory()->create(['price' => 100]);
    Promotion::factory()->create(['ends_at' => now()->addHour()]);

    expect(Price::of($product)->isOnSale())->toBeTrue();

    Carbon::setTestNow(now()->addHours(2));
    request()->attributes->remove('tienda.promotions');

    expect(Price::of($product)->isOnSale())->toBeFalse();

    Carbon::setTestNow();
});

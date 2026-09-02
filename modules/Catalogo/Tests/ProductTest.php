<?php

declare(strict_types=1);

use App\Models\Media;
use App\Models\Redirect;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;
use Modules\Catalogo\Enums\ProductAvailability;
use Modules\Catalogo\Models\Product;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->editor = User::factory()->editor()->create();
    $this->reader = User::factory()->reader()->create();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function productPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Silla de roble macizo',
        'reference' => 'SR-2043',
        'summary' => 'Madera maciza, tres acabados.',
        'description' => '<p>Fabricada a mano.</p>',
        'price' => '149,90',
        'sale_price' => '',
        'availability' => 'available',
        'image_media_id' => null,
        'image_alt' => '',
        'is_visible' => false,
        'position' => 0,
    ], $overrides);
}

/*
|--------------------------------------------------------------------------
| Listado
|--------------------------------------------------------------------------
*/

it('lista los productos a quien puede verlos', function (): void {
    Product::factory()->create(['name' => 'Mesa de comedor']);

    $this->actingAs($this->reader)
        ->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Catalogo::Products/Index')
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Mesa de comedor'));
});

it('busca por nombre, referencia y resumen', function (): void {
    Product::factory()->create(['name' => 'Mesa', 'reference' => 'AAA-1', 'summary' => 'De pino']);
    Product::factory()->create(['name' => 'Silla', 'reference' => 'BBB-2', 'summary' => 'De roble']);

    $this->actingAs($this->editor)
        ->get(route('products.index', ['search' => 'BBB']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Silla'));
});

it('filtra por lo que se está enseñando en la web', function (): void {
    Product::factory()->visible()->create(['name' => 'Visible']);
    Product::factory()->create(['name' => 'Oculto']);

    $this->actingAs($this->editor)
        ->get(route('products.index', ['status' => 'visible']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Visible'));
});

/*
|--------------------------------------------------------------------------
| Alta y edición
|--------------------------------------------------------------------------
*/

it('crea un producto sin pedir la dirección', function (): void {
    $this->actingAs($this->editor)
        ->post(route('products.store'), productPayload())
        ->assertRedirect(route('products.index'));

    $product = Product::query()->firstOrFail();

    expect($product->slug)->toBe('silla-de-roble-macizo')
        ->and($product->is_slug_locked)->toBeFalse()
        ->and($product->is_visible)->toBeFalse();
});

it('entiende el precio escrito como lo escribe la gente', function (string $written, string $expected): void {
    $this->actingAs($this->editor)
        ->post(route('products.store'), productPayload(['price' => $written]));

    expect(Product::query()->firstOrFail()->price)->toBe($expected);
})->with([
    'con coma' => ['149,90', '149.90'],
    'con punto' => ['149.90', '149.90'],
    'con millar' => ['1.299,50', '1299.50'],
    'entero' => ['150', '150.00'],
]);

it('deja el precio vacío para los catálogos que no lo enseñan', function (): void {
    $this->actingAs($this->editor)
        ->post(route('products.store'), productPayload(['price' => '']))
        ->assertSessionHasNoErrors();

    expect(Product::query()->firstOrFail()->price)->toBeNull();
});

it('exige el nombre, en español', function (): void {
    $this->actingAs($this->editor)
        ->post(route('products.store'), productPayload(['name' => '']))
        ->assertSessionHasErrors(['name' => 'Ponle un nombre al producto.']);
});

it('impide repetir la referencia', function (): void {
    Product::factory()->create(['reference' => 'SR-2043']);

    $this->actingAs($this->editor)
        ->post(route('products.store'), productPayload())
        ->assertSessionHasErrors(['reference' => 'Ya hay otro producto con esa referencia.']);
});

it('permite varios productos sin referencia', function (): void {
    Product::factory()->create(['reference' => null]);

    $this->actingAs($this->editor)
        ->post(route('products.store'), productPayload(['reference' => '']))
        ->assertSessionHasNoErrors();

    expect(Product::query()->count())->toBe(2);
});

it('limpia el HTML de la descripción', function (): void {
    $this->actingAs($this->editor)
        ->post(route('products.store'), productPayload([
            'description' => '<p>Buena silla</p><script>alert(1)</script>',
        ]));

    expect(Product::query()->firstOrFail()->description)
        ->toContain('Buena silla')
        ->not->toContain('<script>');
});

it('congela la dirección en cuanto el producto se hace visible', function (): void {
    $this->actingAs($this->editor)->post(route('products.store'), productPayload());
    $product = Product::query()->firstOrFail();

    expect($product->is_slug_locked)->toBeFalse();

    $this->actingAs($this->editor)
        ->post(route('products.update', $product), productPayload(['is_visible' => true]));

    expect($product->fresh()->is_slug_locked)->toBeTrue();
});

it('sigue el nombre mientras no esté visible', function (): void {
    $this->actingAs($this->editor)->post(route('products.store'), productPayload());
    $product = Product::query()->firstOrFail();

    $this->actingAs($this->editor)
        ->post(route('products.update', $product), productPayload(['name' => 'Silla de haya']));

    expect($product->fresh()->slug)->toBe('silla-de-haya');
});

it('deja una redirección al regenerar la dirección de un producto visible', function (): void {
    $this->actingAs($this->editor)
        ->post(route('products.store'), productPayload(['is_visible' => true]));

    $product = Product::query()->firstOrFail();
    $previous = $product->slug;

    $product->forceFill(['name' => 'Silla de haya'])->save();

    $this->actingAs($this->editor)
        ->post(route('products.slug', $product))
        ->assertSessionHas('success');

    expect($product->fresh()->slug)->toBe('silla-de-haya')
        ->and(Redirect::query()->where('from_path', $previous)->exists())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Biblioteca
|--------------------------------------------------------------------------
*/

it('registra dónde se usa la imagen para que no se pueda borrar', function (): void {
    $media = Media::factory()->create();

    $this->actingAs($this->editor)
        ->post(route('products.store'), productPayload(['image_media_id' => $media->id]));

    $product = Product::query()->firstOrFail();

    $this->assertDatabaseHas('media_usages', [
        'media_id' => $media->id,
        'usable_type' => $product->getMorphClass(),
        'usable_id' => $product->id,
        'role' => 'image',
    ]);
});

it('libera la imagen al enviar el producto a la papelera', function (): void {
    $media = Media::factory()->create();

    $this->actingAs($this->editor)
        ->post(route('products.store'), productPayload(['image_media_id' => $media->id]));

    $product = Product::query()->firstOrFail();

    $this->actingAs($this->editor)->delete(route('products.destroy', $product));

    $this->assertDatabaseMissing('media_usages', ['usable_id' => $product->id, 'role' => 'image']);
});

/*
|--------------------------------------------------------------------------
| Papelera y permisos
|--------------------------------------------------------------------------
*/

it('envía un producto a la papelera y lo restaura', function (): void {
    $product = Product::factory()->create();

    $this->actingAs($this->editor)->delete(route('products.destroy', $product));
    expect($product->fresh()->trashed())->toBeTrue();

    $this->actingAs($this->editor)->post(route('products.restore', $product));
    expect($product->fresh()->trashed())->toBeFalse();
});

it('deja consultar pero no modificar a quien solo puede ver', function (): void {
    $product = Product::factory()->create();

    $this->actingAs($this->reader)->get(route('products.index'))->assertOk();
    $this->actingAs($this->reader)->get(route('products.create'))->assertForbidden();
    $this->actingAs($this->reader)->post(route('products.store'), productPayload())->assertForbidden();
    $this->actingAs($this->reader)->get(route('products.edit', $product))->assertForbidden();
    $this->actingAs($this->reader)->delete(route('products.destroy', $product))->assertForbidden();
});

it('exige sesión iniciada', function (): void {
    $this->get(route('products.index'))->assertRedirect(route('login'));
});

/*
|--------------------------------------------------------------------------
| Lo que aporta el módulo
|--------------------------------------------------------------------------
*/

it('deja constancia en la auditoría con su alias', function (): void {
    $this->actingAs($this->editor)->post(route('products.store'), productPayload());

    $product = Product::query()->firstOrFail();

    expect($product->getMorphClass())->toBe('catalogo.product');

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => 'catalogo.product',
        'subject_id' => $product->id,
        'event' => 'created',
    ]);
});

it('aporta su entrada de menú y sus tarjetas al panel', function (): void {
    Product::factory()->count(2)->visible()->create();
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

            expect($labels)->toContain('Productos')
                ->and(array_column($props['cards'], 'count', 'key'))
                ->toHaveKey('catalogo.total', 3)
                ->toHaveKey('catalogo.visible', 2);
        });
});

/*
|--------------------------------------------------------------------------
| Rebajas y existencias
|--------------------------------------------------------------------------
*/

it('guarda una rebaja y calcula lo que se ve en la web', function (): void {
    $this->actingAs($this->editor)
        ->post(route('products.store'), productPayload(['price' => '149,00', 'sale_price' => '119,00']))
        ->assertSessionHasNoErrors();

    $product = Product::query()->firstOrFail();

    expect($product->isOnSale())->toBeTrue()
        ->and($product->currentPrice())->toBe('119.00')
        ->and($product->previousPrice())->toBe('149.00')
        ->and($product->discountPercentage())->toBe(20)
        ->and($product->formattedPrice())->toBe('119,00 €')
        ->and($product->formattedPreviousPrice())->toBe('149,00 €');
});

it('no acepta una rebaja que no rebaja', function (string $price, string $sale): void {
    $this->actingAs($this->editor)
        ->post(route('products.store'), productPayload(['price' => $price, 'sale_price' => $sale]))
        ->assertSessionHasErrors([
            'sale_price' => 'El precio rebajado tiene que ser menor que el precio normal.',
        ]);
})->with([
    'más caro' => ['100,00', '120,00'],
    'igual' => ['100,00', '100,00'],
]);

it('quitar el precio rebajado devuelve el producto a su precio normal', function (): void {
    $this->actingAs($this->editor)
        ->post(route('products.store'), productPayload(['price' => '149,00', 'sale_price' => '119,00']));

    $product = Product::query()->firstOrFail();
    expect($product->isOnSale())->toBeTrue();

    $this->actingAs($this->editor)
        ->post(route('products.update', $product), productPayload(['price' => '149,00', 'sale_price' => '']));

    $product = $product->fresh();

    expect($product->isOnSale())->toBeFalse()
        ->and($product->sale_price)->toBeNull()
        ->and($product->formattedPrice())->toBe('149,00 €')
        ->and($product->formattedPreviousPrice())->toBeNull();
});

it('un producto nace disponible', function (): void {
    $this->actingAs($this->editor)->post(route('products.store'), productPayload());

    expect(Product::query()->firstOrFail()->availability)->toBe(ProductAvailability::Available);
});

it('se puede marcar sin stock o con pocas unidades', function (string $value, string $label): void {
    $this->actingAs($this->editor)
        ->post(route('products.store'), productPayload(['availability' => $value]))
        ->assertSessionHasNoErrors();

    expect(Product::query()->firstOrFail()->availability->label())->toBe($label);
})->with([
    'pocas' => ['low', 'Pocas unidades'],
    'ninguna' => ['out_of_stock', 'Sin stock'],
]);

it('rechaza una disponibilidad inventada', function (): void {
    $this->actingAs($this->editor)
        ->post(route('products.store'), productPayload(['availability' => 'agotadisimo']))
        ->assertSessionHasErrors('availability');
});

it('un producto sin stock sigue siendo visible en la web', function (): void {
    Product::factory()->visible()->outOfStock()->create();

    expect(Product::query()->public()->count())->toBe(1);
});

it('filtra el listado por existencias y por rebajados', function (): void {
    Product::factory()->create(['name' => 'Con stock']);
    Product::factory()->outOfStock()->create(['name' => 'Agotado']);
    Product::factory()->onSale()->create(['name' => 'De oferta']);

    $this->actingAs($this->editor)
        ->get(route('products.index', ['extra' => 'out_of_stock']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Agotado'));

    $this->actingAs($this->editor)
        ->get(route('products.index', ['extra' => 'on_sale']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'De oferta'));
});

it('avisa en el panel de lo que está en la web sin stock', function (): void {
    Product::factory()->count(2)->visible()->outOfStock()->create();
    Product::factory()->outOfStock()->create(); // oculto: no cuenta

    $this->actingAs($this->editor)
        ->get(route('dashboard'))
        ->assertInertia(function (AssertableInertia $page): void {
            /** @var list<array{key: string, text: string}> $alerts */
            $alerts = $page->toArray()['props']['alerts'];
            $alert = array_values(array_filter($alerts, fn (array $a): bool => $a['key'] === 'catalogo.sin-stock'));

            expect($alert)->toHaveCount(1)
                ->and($alert[0]['text'])->toBe('Hay 2 productos en la web marcados como sin stock.');
        });
});

it('dice qué imagen conviene subir, con los límites reales de la biblioteca', function (): void {
    $this->actingAs($this->editor)
        ->get(route('products.create'))
        ->assertInertia(function (AssertableInertia $page): void {
            /** @var string $hint */
            $hint = $page->toArray()['props']['imageHint'];

            // El ancho recomendado sale de la variante mayor, no de un número
            // escrito a mano: si se cambia en config/media.php, el aviso cambia.
            $mayor = max(array_map('intval', array_values((array) config('media.variants'))));

            expect($hint)->toContain((string) $mayor)
                ->and($hint)->toContain('PNG')
                ->and($hint)->toContain('5 MB');
        });
});

it('también lo dice al editar', function (): void {
    $product = Product::factory()->create();

    $this->actingAs($this->editor)
        ->get(route('products.edit', $product))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('imageHint'));
});

<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;
use Modules\Tienda\Models\Category;
use Modules\Tienda\Models\Product;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->editor = User::factory()->editor()->create();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function tiendaCategoryPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Camisetas',
        'parent_id' => '',
        'description' => '',
        'image_media_id' => null,
        'is_visible' => true,
        'position' => 0,
        'meta_title' => '',
        'meta_description' => '',
    ], $overrides);
}

it('enseña el árbol en orden, con la profundidad de cada una', function (): void {
    $ropa = Category::factory()->create(['name' => 'Ropa', 'position' => 1]);
    Category::factory()->childOf($ropa)->create(['name' => 'Camisetas']);
    Category::factory()->create(['name' => 'Hogar', 'position' => 2]);

    $this->actingAs($this->editor)
        ->get(route('tienda.categories.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Tienda::Categories/Index')
            ->where('categories.0.name', 'Ropa')
            ->where('categories.1.name', 'Camisetas')
            ->where('categories.1.depth', 1)
            ->where('categories.2.name', 'Hogar'));
});

it('crea una subcategoría', function (): void {
    $ropa = Category::factory()->create();

    $this->actingAs($this->editor)
        ->post(route('tienda.categories.store'), tiendaCategoryPayload(['parent_id' => $ropa->id]))
        ->assertSessionHasNoErrors();

    expect(Category::query()->where('name', 'Camisetas')->firstOrFail()->parent_id)->toBe($ropa->id);
});

it('no deja colgar una categoría de una de sus hijas', function (): void {
    $ropa = Category::factory()->create();
    $camisetas = Category::factory()->childOf($ropa)->create();

    $this->actingAs($this->editor)
        ->put(route('tienda.categories.update', $ropa), tiendaCategoryPayload([
            'name' => $ropa->name,
            'parent_id' => $camisetas->id,
        ]))
        ->assertSessionHasErrors('parent_id');
});

it('no pasa de tres niveles', function (): void {
    $uno = Category::factory()->create();
    $dos = Category::factory()->childOf($uno)->create();
    $tres = Category::factory()->childOf($dos)->create();

    $this->actingAs($this->editor)
        ->post(route('tienda.categories.store'), tiendaCategoryPayload(['parent_id' => $tres->id]))
        ->assertSessionHasErrors('parent_id');
});

it('no borra una categoría con subcategorías', function (): void {
    $ropa = Category::factory()->create();
    Category::factory()->childOf($ropa)->create();

    $this->actingAs($this->editor)
        ->delete(route('tienda.categories.destroy', $ropa))
        ->assertSessionHas('error');

    expect($ropa->fresh()->trashed())->toBeFalse();
});

it('al borrar una categoría sus artículos siguen ahí', function (): void {
    $ropa = Category::factory()->create();
    $product = Product::factory()->in($ropa)->create();

    $this->actingAs($this->editor)->delete(route('tienda.categories.destroy', $ropa));

    expect($ropa->fresh()->trashed())->toBeTrue()
        ->and($product->fresh()->trashed())->toBeFalse();
});

it('restaurar una subcategoría cuya madre sigue borrada la deja en la raíz', function (): void {
    $ropa = Category::factory()->create();
    $camisetas = Category::factory()->childOf($ropa)->create();

    $camisetas->delete();
    $ropa->delete();

    $this->actingAs($this->editor)->post(route('tienda.categories.restore', $camisetas));

    expect($camisetas->fresh()->parent_id)->toBeNull();
});

it('una persona lectora no puede tocar categorías', function (): void {
    $reader = User::factory()->reader()->create();

    $this->actingAs($reader)->get(route('tienda.categories.index'))->assertOk();
    $this->actingAs($reader)->post(route('tienda.categories.store'), tiendaCategoryPayload())->assertForbidden();
});

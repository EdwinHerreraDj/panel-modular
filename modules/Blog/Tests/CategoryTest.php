<?php

declare(strict_types=1);

use App\Enums\ActivityEvent;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;
use Modules\Blog\Models\Category;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->editor = User::factory()->editor()->create();
});

it('lista las categorías a quien puede ver contenido', function (): void {
    Category::factory()->count(3)->create();

    $this->actingAs($this->editor)
        ->get(route('categories.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Blog::Categories/Index')
            ->has('categories.data', 3));
});

it('busca categorías por nombre y descripción', function (): void {
    Category::factory()->create(['name' => 'Persianas', 'description' => 'Todo sobre persianas']);
    Category::factory()->create(['name' => 'Puertas', 'description' => 'Puertas de interior']);

    $this->actingAs($this->editor)
        ->get(route('categories.index', ['search' => 'persian']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('categories.data', 1)
            ->where('categories.data.0.name', 'Persianas'));
});

it('crea una categoría sin pedir el slug', function (): void {
    $this->actingAs($this->editor)
        ->post(route('categories.store'), [
            'name' => 'Puertas y ventanas',
            'description' => 'Consejos de instalación',
            'meta_title' => 'Puertas y ventanas',
            'meta_description' => 'Todo lo que necesitas saber.',
            'position' => 1,
        ])
        ->assertRedirect(route('categories.index'))
        ->assertSessionHasNoErrors();

    $category = Category::query()->where('name', 'Puertas y ventanas')->firstOrFail();

    expect($category->slug)->toBe('puertas-y-ventanas')
        ->and($category->is_slug_locked)->toBeFalse();

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => (new Category)->getMorphClass(),
        'subject_id' => $category->id,
        'event' => ActivityEvent::Created->value,
    ]);
});

it('exige el nombre en español', function (): void {
    $this->actingAs($this->editor)
        ->from(route('categories.create'))
        ->post(route('categories.store'), [])
        ->assertSessionHasErrors(['name' => 'El campo nombre es obligatorio.']);
});

it('impide repetir el nombre de una categoría', function (): void {
    Category::factory()->create(['name' => 'Reformas']);

    $this->actingAs($this->editor)
        ->from(route('categories.create'))
        ->post(route('categories.store'), ['name' => 'Reformas', 'position' => 0])
        ->assertSessionHasErrors(['name' => 'Ya existe una categoría con ese nombre.']);
});

it('limita la longitud de los metadatos', function (): void {
    $this->actingAs($this->editor)
        ->from(route('categories.create'))
        ->post(route('categories.store'), [
            'name' => 'Reformas',
            'position' => 0,
            'meta_description' => str_repeat('a', 161),
        ])
        ->assertSessionHasErrors('meta_description');
});

it('actualiza una categoría', function (): void {
    $category = Category::factory()->create(['name' => 'Nombre antiguo']);

    $this->actingAs($this->editor)
        ->put(route('categories.update', $category), [
            'name' => 'Nombre nuevo',
            'description' => 'Descripción nueva',
            'position' => 5,
        ])
        ->assertRedirect(route('categories.index'))
        ->assertSessionHasNoErrors();

    $category->refresh();

    expect($category->name)->toBe('Nombre nuevo')
        ->and($category->slug)->toBe('nombre-nuevo')
        ->and($category->position)->toBe(5);
});

it('conserva el slug al renombrar una categoría en uso', function (): void {
    $category = Category::factory()->slugLocked()->create(['name' => 'Reformas']);
    $slug = $category->slug;

    $this->actingAs($this->editor)
        ->put(route('categories.update', $category), [
            'name' => 'Reformas integrales',
            'position' => 0,
        ])
        ->assertSessionHasNoErrors();

    expect($category->fresh()->slug)->toBe($slug)
        ->and($category->fresh()->name)->toBe('Reformas integrales');
});

it('regenera el slug bajo petición explícita', function (): void {
    $category = Category::factory()->slugLocked()->create(['name' => 'Reformas']);
    $category->update(['name' => 'Reformas integrales']);

    $this->actingAs($this->editor)
        ->from(route('categories.edit', $category))
        ->post(route('categories.slug', $category))
        ->assertRedirect(route('categories.edit', $category))
        ->assertSessionHas('success');

    expect($category->fresh()->slug)->toBe('reformas-integrales');

    $this->assertDatabaseHas('activity_logs', [
        'subject_id' => $category->id,
        'event' => ActivityEvent::Updated->value,
    ]);
});

it('envía una categoría a la papelera y la restaura', function (): void {
    $category = Category::factory()->create();

    $this->actingAs($this->editor)
        ->delete(route('categories.destroy', $category))
        ->assertRedirect(route('categories.index'));

    expect($category->fresh()->trashed())->toBeTrue();

    $this->actingAs($this->editor)
        ->post(route('categories.restore', $category))
        ->assertRedirect(route('categories.index'));

    expect($category->fresh()->trashed())->toBeFalse();
});

it('muestra la papelera solo cuando se pide', function (): void {
    Category::factory()->create()->delete();

    $this->actingAs($this->editor)
        ->get(route('categories.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('categories.data', 0));

    $this->actingAs($this->editor)
        ->get(route('categories.index', ['trashed' => 1]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('categories.data', 1));
});

it('deja consultar pero no modificar a quien solo puede ver contenido', function (string $state): void {
    $user = User::factory()->{$state}()->create();
    $category = Category::factory()->create();

    $this->actingAs($user)->get(route('categories.index'))->assertOk();

    $this->actingAs($user)->get(route('categories.create'))->assertForbidden();
    $this->actingAs($user)
        ->put(route('categories.update', $category), ['name' => 'Otro', 'position' => 0])
        ->assertForbidden();
    $this->actingAs($user)->delete(route('categories.destroy', $category))->assertForbidden();
})->with(['reviewer', 'author', 'reader']);

it('permite al superadministrador gestionar categorías', function (): void {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->post(route('categories.store'), ['name' => 'Reformas', 'position' => 0])
        ->assertSessionHasNoErrors();

    expect(Category::query()->where('name', 'Reformas')->exists())->toBeTrue();
});

<?php

declare(strict_types=1);

use App\Enums\ActivityEvent;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;
use Modules\Blog\Models\Tag;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->editor = User::factory()->editor()->create();
});

it('lista las etiquetas a quien puede ver contenido', function (): void {
    Tag::factory()->count(4)->create();

    $this->actingAs($this->editor)
        ->get(route('tags.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Blog::Tags/Index')
            ->has('tags.data', 4)
            ->where('can.create', true));
});

it('crea una etiqueta y genera su slug', function (): void {
    $this->actingAs($this->editor)
        ->from(route('tags.index'))
        ->post(route('tags.store'), ['name' => 'Ahorro energético'])
        ->assertRedirect(route('tags.index'))
        ->assertSessionHasNoErrors();

    $tag = Tag::query()->where('name', 'Ahorro energético')->firstOrFail();

    expect($tag->slug)->toBe('ahorro-energetico');

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => (new Tag)->getMorphClass(),
        'subject_id' => $tag->id,
        'event' => ActivityEvent::Created->value,
    ]);
});

it('impide repetir el nombre de una etiqueta', function (): void {
    Tag::factory()->create(['name' => 'Persianas']);

    $this->actingAs($this->editor)
        ->from(route('tags.index'))
        ->post(route('tags.store'), ['name' => 'Persianas'])
        ->assertSessionHasErrors(['name' => 'Ya existe una etiqueta con ese nombre.']);
});

it('exige el nombre en español', function (): void {
    $this->actingAs($this->editor)
        ->from(route('tags.index'))
        ->post(route('tags.store'), [])
        ->assertSessionHasErrors(['name' => 'El campo nombre es obligatorio.']);
});

it('actualiza una etiqueta y su slug si todavía no se usa', function (): void {
    $tag = Tag::factory()->create(['name' => 'Persianss']);

    $this->actingAs($this->editor)
        ->from(route('tags.index'))
        ->put(route('tags.update', $tag), ['name' => 'Persianas'])
        ->assertSessionHasNoErrors();

    expect($tag->fresh()->slug)->toBe('persianas');
});

it('conserva el slug de una etiqueta ya en uso', function (): void {
    $tag = Tag::factory()->slugLocked()->create(['name' => 'Persianas']);

    $this->actingAs($this->editor)
        ->from(route('tags.index'))
        ->put(route('tags.update', $tag), ['name' => 'Persianas enrollables'])
        ->assertSessionHasNoErrors();

    expect($tag->fresh()->slug)->toBe('persianas');
});

it('regenera el slug de una etiqueta bajo petición explícita', function (): void {
    $tag = Tag::factory()->slugLocked()->create(['name' => 'Persianas']);
    $tag->update(['name' => 'Persianas enrollables']);

    $this->actingAs($this->editor)
        ->from(route('tags.index'))
        ->post(route('tags.slug', $tag))
        ->assertSessionHas('success');

    expect($tag->fresh()->slug)->toBe('persianas-enrollables');
});

it('envía una etiqueta a la papelera y la restaura', function (): void {
    $tag = Tag::factory()->create();

    $this->actingAs($this->editor)
        ->from(route('tags.index'))
        ->delete(route('tags.destroy', $tag))
        ->assertSessionHas('success');

    expect($tag->fresh()->trashed())->toBeTrue();

    $this->actingAs($this->editor)
        ->from(route('tags.index'))
        ->post(route('tags.restore', $tag))
        ->assertSessionHas('success');

    expect($tag->fresh()->trashed())->toBeFalse();
});

it('deja consultar pero no modificar a quien solo puede ver contenido', function (string $state): void {
    $user = User::factory()->{$state}()->create();
    $tag = Tag::factory()->create();

    $this->actingAs($user)
        ->get(route('tags.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can.create', false));

    $this->actingAs($user)->post(route('tags.store'), ['name' => 'Nueva'])->assertForbidden();
    $this->actingAs($user)->put(route('tags.update', $tag), ['name' => 'Otra'])->assertForbidden();
    $this->actingAs($user)->delete(route('tags.destroy', $tag))->assertForbidden();
})->with(['reviewer', 'author', 'reader']);

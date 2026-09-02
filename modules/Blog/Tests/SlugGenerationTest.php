<?php

declare(strict_types=1);

use App\Support\SlugGenerator;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Tag;

/**
 * El slug no se pide nunca en los formularios: se deriva del nombre.
 * Estas pruebas fijan ese comportamiento.
 */
it('genera el slug a partir del nombre al crear', function (): void {
    $category = Category::create(['name' => 'Puertas y ventanas', 'position' => 0]);

    expect($category->slug)->toBe('puertas-y-ventanas');
});

it('quita los acentos y los signos', function (): void {
    $category = Category::create(['name' => '¿Cómo elegir persianas?', 'position' => 0]);

    expect($category->slug)->toBe('como-elegir-persianas');
});

it('añade un sufijo cuando el slug ya existe', function (): void {
    Category::create(['name' => 'Reformas', 'position' => 0]);
    $second = Category::create(['name' => 'reformas', 'position' => 0]);
    $third = Category::create(['name' => 'REFORMAS', 'position' => 0]);

    expect($second->slug)->toBe('reformas-2')
        ->and($third->slug)->toBe('reformas-3');
});

it('tiene en cuenta los registros de la papelera al buscar colisiones', function (): void {
    $original = Category::create(['name' => 'Reformas', 'position' => 0]);
    $original->delete();

    $nueva = Category::create(['name' => 'Reformas', 'position' => 0]);

    // El slug anterior sigue reservado mientras se pueda restaurar.
    expect($nueva->slug)->toBe('reformas-2');
});

it('sigue al nombre mientras la categoría no se usa', function (): void {
    $category = Category::create(['name' => 'Nombre con errata', 'position' => 0]);

    $category->update(['name' => 'Nombre corregido']);

    expect($category->fresh()->slug)->toBe('nombre-corregido');
});

it('congela el slug en cuanto la categoría se usa', function (): void {
    $category = Category::create(['name' => 'Reformas', 'position' => 0]);

    $category->lockSlug();
    $category->update(['name' => 'Reformas integrales']);

    expect($category->fresh()->slug)->toBe('reformas')
        ->and($category->fresh()->name)->toBe('Reformas integrales');
});

it('permite regenerar el slug de forma explícita aunque esté congelado', function (): void {
    $category = Category::create(['name' => 'Reformas', 'position' => 0]);
    $category->lockSlug();
    $category->update(['name' => 'Reformas integrales']);

    $category->regenerateSlug();

    expect($category->fresh()->slug)->toBe('reformas-integrales');
});

it('genera un slug aleatorio si el nombre no deja caracteres utilizables', function (): void {
    $tag = Tag::create(['name' => '★★★']);

    expect($tag->slug)->not->toBeEmpty()
        ->and($tag->slug)->toMatch('/^[a-z0-9-]+$/');
});

it('recorta los slugs muy largos sin dejar un guion suelto al final', function (): void {
    $slug = SlugGenerator::normalize(str_repeat('palabra ', 40));

    expect(strlen($slug))->toBeLessThanOrEqual(120)
        ->and($slug)->not->toEndWith('-');
});

it('no toca el slug al guardar si el nombre no ha cambiado', function (): void {
    $tag = Tag::create(['name' => 'Persianas']);
    $original = $tag->slug;

    $tag->touch();

    expect($tag->fresh()->slug)->toBe($original);
});

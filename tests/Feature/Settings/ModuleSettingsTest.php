<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\ModuleSettings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;

/**
 * Cada módulo activo aporta sus propios ajustes a la pantalla de Configuración.
 */
beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->superAdmin = User::factory()->superAdmin()->create();
});

it('enseña los ajustes que declara cada módulo', function (): void {
    requiresModule('blog');

    $this->actingAs($this->superAdmin)
        ->get(route('settings.edit'))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page): void {
            /** @var list<array{module: string, title: string, fields: list<array<string, mixed>>}> $sections */
            $sections = $page->toArray()['props']['moduleSettings'];
            $blog = array_values(array_filter($sections, fn (array $s): bool => $s['module'] === 'blog'));

            expect($blog)->toHaveCount(1)
                ->and($blog[0]['title'])->toBe('Blog')
                ->and($blog[0]['fields'][0]['key'])->toBe('articles_per_page')
                ->and($blog[0]['fields'][0]['label'])->toBe('Artículos por página')
                ->and($blog[0]['fields'][0]['value'])->toBe(12);
        });
});

it('guarda lo que envía cada módulo', function (): void {
    requiresModule('blog');

    $this->actingAs($this->superAdmin)
        ->post(route('settings.update'), settingsPayload([
            'modules' => ['blog' => ['articles_per_page' => 24]],
        ]))
        ->assertSessionHasNoErrors();

    expect(app(ModuleSettings::class)->get('blog', 'articles_per_page'))->toBe(24);
});

it('valida con las reglas que declara el módulo', function (): void {
    requiresModule('blog');

    $this->actingAs($this->superAdmin)
        ->from(route('settings.edit'))
        ->post(route('settings.update'), settingsPayload([
            'modules' => ['blog' => ['articles_per_page' => 500]],
        ]))
        ->assertSessionHasErrors('modules.blog.articles_per_page');
});

it('ignora una clave que el módulo no ha declarado', function (): void {
    requiresModule('blog');

    $this->actingAs($this->superAdmin)
        ->post(route('settings.update'), settingsPayload([
            'modules' => ['blog' => ['articles_per_page' => 20, 'inventado' => 'lo que sea']],
        ]))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('module_settings', ['key' => 'inventado']);
});

it('no exige los ajustes de un módulo si el formulario no los manda', function (): void {
    $this->actingAs($this->superAdmin)
        ->post(route('settings.update'), settingsPayload())
        ->assertSessionHasNoErrors();
});

it('el ajuste del blog llega a su endpoint heredado', function (): void {
    requiresModule('blog');

    app(ModuleSettings::class)->put('blog', ['articles_per_page' => 30]);

    [$headers] = apiKeyHeaders();

    $this->getJson(apiUrl('blog'), $headers)
        ->assertJsonPath('datos.articulos_por_pagina', 30);
});

it('devuelve el valor por defecto mientras nadie lo haya tocado', function (): void {
    requiresModule('blog');

    expect(app(ModuleSettings::class)->get('blog', 'articles_per_page'))->toBe(12);
});

<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Support\ApiDocumentation;
use Database\Seeders\RolesAndPermissionsSeeder;

/**
 * `/sitio` son los datos de la instalación, tenga el contenido que tenga.
 */
beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('sirve los datos del sitio', function (): void {
    Setting::current()->forceFill([
        'site_name' => 'Alminares',
        'tagline' => 'Novedades del sector',
    ])->save();

    [$headers] = apiKeyHeaders();

    $this->getJson(apiUrl('sitio'), $headers)
        ->assertOk()
        ->assertJsonPath('datos.nombre', 'Alminares')
        ->assertJsonPath('datos.lema', 'Novedades del sector')
        ->assertJsonStructure(['datos' => ['nombre', 'idioma', 'seo', 'organizacion']]);
});

it('no mezcla ajustes de ningún módulo en los datos del sitio', function (): void {
    [$headers] = apiKeyHeaders();

    $datos = $this->getJson(apiUrl('sitio'), $headers)->json('datos');

    expect($datos)->not->toHaveKey('articulos_por_pagina');
});

it('exige clave', function (): void {
    $this->getJson(apiUrl('sitio'))->assertUnauthorized();
});

it('documenta exactamente las claves que devuelve', function (): void {
    Setting::current()->forceFill([
        'social_profiles' => [['network' => 'instagram', 'url' => 'https://instagram.com/ejemplo']],
    ])->save();

    [$headers] = apiKeyHeaders();

    $real = $this->getJson(apiUrl('sitio'), $headers)->json();

    expect(array_diff(deepKeys(ApiDocumentation::examples()['sitio']), deepKeys($real)))->toBe([]);
});

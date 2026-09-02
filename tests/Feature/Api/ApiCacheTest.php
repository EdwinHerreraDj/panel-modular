<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Models\User;
use App\Support\ApiCache;
use Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Blog\Models\Article;
use Modules\Blog\Models\Category;

beforeEach(function (): void {
    config()->set('api.require_key', true);
    config()->set('api.cache_seconds', 300);
});

/*
|--------------------------------------------------------------------------
| Funcionamiento básico
|--------------------------------------------------------------------------
*/

it('sirve la segunda petición desde la caché', function (): void {
    requiresModule('blog');

    [$headers] = apiKeyHeaders();
    Article::factory()->published()->create(['title' => 'Primero']);

    $first = $this->withHeaders($headers)->getJson(apiUrl('articulos'))->assertOk();
    expect($first->headers->get('X-Cache'))->toBe('MISS');

    $second = $this->withHeaders($headers)->getJson(apiUrl('articulos'))->assertOk();
    expect($second->headers->get('X-Cache'))->toBe('HIT')
        ->and($second->json())->toBe($first->json());
});

it('no cachea nada cuando el plazo es cero', function (): void {
    requiresModule('blog');

    config()->set('api.cache_seconds', 0);

    [$headers] = apiKeyHeaders();
    Article::factory()->published()->create();

    $this->withHeaders($headers)->getJson(apiUrl('articulos'));

    $response = $this->withHeaders($headers)->getJson(apiUrl('articulos'))->assertOk();

    expect($response->headers->get('X-Cache'))->toBeNull();
});

it('guarda por separado cada combinación de parámetros', function (): void {
    requiresModule('blog');

    [$headers] = apiKeyHeaders();
    Article::factory()->count(4)->published()->create();

    $uno = $this->withHeaders($headers)->getJson(apiUrl('articulos?por_pagina=1'))->assertOk();
    $dos = $this->withHeaders($headers)->getJson(apiUrl('articulos?por_pagina=2'))->assertOk();

    expect($uno->json('datos'))->toHaveCount(1)
        ->and($dos->json('datos'))->toHaveCount(2)
        ->and($dos->headers->get('X-Cache'))->toBe('MISS');
});

it('comparte entrada aunque los parámetros vengan en otro orden', function (): void {
    requiresModule('blog');

    [$headers] = apiKeyHeaders();
    Article::factory()->count(3)->published()->create();

    $this->withHeaders($headers)->getJson(apiUrl('articulos?por_pagina=2&pagina=1'))->assertOk();

    $segunda = $this->withHeaders($headers)->getJson(apiUrl('articulos?pagina=1&por_pagina=2'))->assertOk();

    expect($segunda->headers->get('X-Cache'))->toBe('HIT');
});

/*
|--------------------------------------------------------------------------
| Invalidación
|--------------------------------------------------------------------------
*/

it('deja de servir lo viejo en cuanto se publica un artículo', function (): void {
    requiresModule('blog');

    [$headers] = apiKeyHeaders();
    Article::factory()->published()->create(['title' => 'El primero']);

    $antes = $this->withHeaders($headers)->getJson(apiUrl('articulos'))->assertOk();
    expect($antes->json('datos'))->toHaveCount(1);

    // Se cachea…
    $this->withHeaders($headers)->getJson(apiUrl('articulos'));

    // …y ahora entra uno nuevo.
    Article::factory()->published()->create(['title' => 'El segundo']);

    $despues = $this->withHeaders($headers)->getJson(apiUrl('articulos'))->assertOk();

    expect($despues->headers->get('X-Cache'))->toBe('MISS')
        ->and($despues->json('datos'))->toHaveCount(2);
});

it('se invalida al cambiar cualquier cosa que sirva la API', function (string $action): void {
    requiresModule('blog');

    [$headers] = apiKeyHeaders();
    $article = Article::factory()->published()->create();

    $this->withHeaders($headers)->getJson(apiUrl('articulos'));
    expect($this->withHeaders($headers)->getJson(apiUrl('articulos'))->headers->get('X-Cache'))->toBe('HIT');

    match ($action) {
        'editar artículo' => $article->update(['title' => 'Otro título']),
        'borrar artículo' => $article->delete(),
        'crear categoría' => Category::factory()->create(),
        'cambiar la configuración' => Setting::current()->forceFill(['site_name' => 'Otro'])->save(),
    };

    expect($this->withHeaders($headers)->getJson(apiUrl('articulos'))->headers->get('X-Cache'))->toBe('MISS');
})->with(['editar artículo', 'borrar artículo', 'crear categoría', 'cambiar la configuración']);

it('sube la versión al vaciar, sin borrar entradas una a una', function (): void {
    $before = ApiCache::version();

    ApiCache::flush();

    expect(ApiCache::version())->toBeGreaterThan($before);
});

/*
|--------------------------------------------------------------------------
| Lo que NO debe cachearse
|--------------------------------------------------------------------------
*/

it('no cachea las respuestas de error', function (): void {
    requiresModule('blog');

    [$headers] = apiKeyHeaders();

    $this->withHeaders($headers)->getJson(apiUrl('articulos/no-existe'))->assertNotFound();

    // Ahora sí existe: si el 404 se hubiese cacheado, seguiría fallando.
    $article = Article::factory()->published()->create(['title' => 'Ya existe']);

    $this->withHeaders($headers)
        ->getJson(apiUrl('articulos/'.$article->slug))
        ->assertOk()
        ->assertJsonPath('datos.titulo', 'Ya existe');
});

it('una clave revocada deja de servir aunque la respuesta esté cacheada', function (): void {
    requiresModule('blog');

    [$headers, $key] = apiKeyHeaders();
    Article::factory()->published()->create();

    $this->withHeaders($headers)->getJson(apiUrl('articulos'))->assertOk();
    $this->withHeaders($headers)->getJson(apiUrl('articulos'))->assertOk();

    $key->revoke();

    $this->withHeaders($headers)->getJson(apiUrl('articulos'))->assertForbidden();
});

it('sigue contando el uso de la clave aunque responda la caché', function (): void {
    requiresModule('blog');

    [$headers, $key] = apiKeyHeaders();
    Article::factory()->published()->create();

    $this->withHeaders($headers)->getJson(apiUrl('articulos'));
    $this->withHeaders($headers)->getJson(apiUrl('articulos'));
    $this->withHeaders($headers)->getJson(apiUrl('articulos'));

    expect($key->refresh()->requests_count)->toBe(3);
});

it('no mezcla la cabecera de CORS de un dominio con la de otro', function (): void {
    requiresModule('blog');

    [$headers] = apiKeyHeaders();
    Article::factory()->published()->create();

    config()->set('api.allowed_origins', ['https://www.una.com', 'https://www.otra.com']);

    $una = $this->withHeaders($headers + ['Origin' => 'https://www.una.com'])
        ->getJson(apiUrl('articulos'))
        ->assertOk();

    // La segunda sale de la caché, pero la cabecera debe ser la suya.
    $otra = $this->withHeaders($headers + ['Origin' => 'https://www.otra.com'])
        ->getJson(apiUrl('articulos'))
        ->assertOk();

    expect($una->headers->get('Access-Control-Allow-Origin'))->toBe('https://www.una.com')
        ->and($otra->headers->get('X-Cache'))->toBe('HIT')
        ->and($otra->headers->get('Access-Control-Allow-Origin'))->toBe('https://www.otra.com');
});

/*
|--------------------------------------------------------------------------
| Vaciado desde el panel
|--------------------------------------------------------------------------
*/

it('el superadministrador puede vaciar la caché a mano', function (): void {
    requiresModule('blog');

    $this->seed(RolesAndPermissionsSeeder::class);
    $superAdmin = User::factory()->superAdmin()->create();

    [$headers] = apiKeyHeaders();
    Article::factory()->published()->create();

    $this->withHeaders($headers)->getJson(apiUrl('articulos'));
    expect($this->withHeaders($headers)->getJson(apiUrl('articulos'))->headers->get('X-Cache'))->toBe('HIT');

    $this->actingAs($superAdmin)
        ->from(route('api.connection'))
        ->post(route('api.connection.flush'))
        ->assertSessionHas('success');

    expect($this->withHeaders($headers)->getJson(apiUrl('articulos'))->headers->get('X-Cache'))->toBe('MISS');
});

it('el editor no puede vaciar la caché', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->post(route('api.connection.flush'))->assertForbidden();
});

it('la primerísima invalidación también invalida', function (): void {
    requiresModule('blog');

    // Con la caché recién estrenada, la clave de versión todavía no existe.
    // Es el caso de un despliegue nuevo: alguien lee la API, se publica un
    // artículo y lo publicado tiene que verse ya.
    [$headers] = apiKeyHeaders();

    $response = $this->withHeaders($headers)->getJson(apiUrl('articulos'))->assertOk();
    expect($response->json('datos'))->toHaveCount(0);

    Article::factory()->published()->create(['title' => 'El primero de todos']);

    $despues = $this->withHeaders($headers)->getJson(apiUrl('articulos'))->assertOk();

    expect($despues->headers->get('X-Cache'))->toBe('MISS')
        ->and($despues->json('datos.0.titulo'))->toBe('El primero de todos');
});

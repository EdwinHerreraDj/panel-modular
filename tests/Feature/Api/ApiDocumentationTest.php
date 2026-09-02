<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\Media;
use App\Models\Setting;
use App\Models\User;
use App\Support\ApiCatalog;
use App\Support\ApiDocumentation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Blog\Models\Article;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Tag;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->superAdmin = User::factory()->superAdmin()->create();
});

/**
 * Artículo publicado con todo relleno.
 *
 * Los ejemplos documentan campos que solo aparecen si existen (la imagen
 * destacada desaparece de la respuesta si no la hay), así que para comparar
 * hace falta un artículo completo.
 *
 * @param  array<string, mixed>  $overrides
 */
function articleWithEverything(array $overrides = []): Article
{
    $media = Media::factory()->create();

    $article = Article::factory()->published()->create($overrides + [
        'featured_media_id' => $media->id,
        'featured_image_alt' => 'Una imagen',
    ]);

    $article->categories()->attach(Category::factory()->create());
    $article->tags()->attach(Tag::factory()->create());

    return $article;
}

/*
|--------------------------------------------------------------------------
| Los ejemplos deben describir la API de verdad
|--------------------------------------------------------------------------
*/

it('documenta exactamente las claves que devuelve el listado de artículos', function (): void {
    requiresModule('blog');

    ['plain' => $plain] = ApiKey::generate('Documentación');

    articleWithEverything();

    $real = $this->withHeaders([(string) config('api.header') => $plain])
        ->getJson(apiUrl('articulos'))
        ->json();

    $documented = ApiDocumentation::examples()['articulos'];

    // Todo lo documentado tiene que existir de verdad. Al revés no se exige:
    // un campo nulo desaparece de la respuesta y no siempre saldrá.
    expect(array_diff(deepKeys($documented), deepKeys($real)))->toBe([]);
});

it('documenta exactamente las claves que devuelve un artículo', function (): void {
    requiresModule('blog');

    ['plain' => $plain] = ApiKey::generate('Documentación');

    $article = articleWithEverything(['content' => '<p>Hola.</p>']);

    $real = $this->withHeaders([(string) config('api.header') => $plain])
        ->getJson(apiUrl('articulos/'.$article->slug))
        ->json();

    expect(array_diff(deepKeys(ApiDocumentation::examples()['articulo']), deepKeys($real)))->toBe([]);
});

it('documenta exactamente las claves de las taxonomías y del blog', function (string $key, string $path): void {
    requiresModule('blog');

    ['plain' => $plain] = ApiKey::generate('Documentación');

    Setting::current()->forceFill([
        'social_profiles' => [['network' => 'instagram', 'url' => 'https://instagram.com/ejemplo']],
    ])->save();

    articleWithEverything();

    $real = $this->withHeaders([(string) config('api.header') => $plain])
        ->getJson(apiUrl($path))
        ->json();

    expect(array_diff(deepKeys(ApiDocumentation::examples()[$key]), deepKeys($real)))->toBe([]);
})->with([
    'categorías' => ['categorias', 'categorias'],
    'etiquetas' => ['etiquetas', 'etiquetas'],
    'blog' => ['blog', 'blog'],
]);

it('documenta exactamente las claves del estado', function (): void {
    requiresModule('blog');

    $real = $this->getJson(apiUrl('estado'))->json();

    expect(array_diff(deepKeys(ApiDocumentation::examples()['estado']), deepKeys($real)))->toBe([]);
});

it('documenta todos los endpoints que existen', function (): void {
    $markdown = ApiDocumentation::markdown();

    foreach (ApiCatalog::endpoints() as $endpoint) {
        expect($markdown)->toContain($endpoint['title'])
            ->and($markdown)->toContain('/'.$endpoint['path']);
    }
});

it('documenta todos los códigos de error que la API puede devolver', function (): void {
    $documented = array_column(ApiDocumentation::errors(), 'codigo');

    foreach (['clave_ausente', 'clave_invalida', 'clave_no_utilizable', 'origen_no_permitido',
        'no_encontrado', 'demasiadas_peticiones', 'error_servidor'] as $code) {
        expect($documented)->toContain($code);
    }
});

/*
|--------------------------------------------------------------------------
| Descarga
|--------------------------------------------------------------------------
*/

it('descarga la documentación con la dirección real de esta instalación', function (): void {
    $response = $this->actingAs($this->superAdmin)
        ->get(route('api.connection.docs'))
        ->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('attachment')
        ->and($response->headers->get('Content-Type'))->toContain('text/markdown');

    expect($response->content())
        ->toContain(ApiCatalog::url())
        ->toContain((string) config('api.header'))
        ->toContain('# API del blog');
});

it('nunca mete una clave real en la documentación', function (): void {
    ['plain' => $plain] = ApiKey::generate('Web corporativa');

    $content = ApiDocumentation::markdown();

    expect($content)->not->toContain($plain)
        ->and($content)->not->toContain('Web corporativa');
});

it('descarga la especificación OpenAPI válida', function (): void {
    requiresModule('blog');

    $response = $this->actingAs($this->superAdmin)
        ->get(route('api.connection.docs', ['formato' => 'openapi']))
        ->assertOk();

    $spec = json_decode($response->content(), true);

    expect($spec['openapi'])->toBe('3.1.0')
        ->and($spec['servers'][0]['url'])->toBe(ApiCatalog::url())
        ->and($spec['components']['securitySchemes']['ClaveApi']['name'])->toBe((string) config('api.header'))
        ->and($spec['paths'])->toHaveCount(count(ApiCatalog::endpoints()));

    // El endpoint de estado no lleva seguridad; el resto sí.
    expect($spec['paths']['/estado']['get']['security'])->toBe([])
        ->and($spec['paths']['/articulos']['get']['security'])->toBe([['ClaveApi' => []]]);
});

it('solo el superadministrador descarga la documentación', function (string $role): void {
    $user = User::factory()->{$role}()->create();

    $this->actingAs($user)->get(route('api.connection.docs'))->assertForbidden();
})->with(['editor', 'author', 'reader']);

/*
|--------------------------------------------------------------------------
| Comando
|--------------------------------------------------------------------------
*/

it('escribe la documentación en el repositorio', function (): void {
    requiresModule('blog');

    $path = 'tests/tmp/API-PRUEBA.md';

    $this->artisan('api:documentar', ['--ruta' => $path, '--openapi' => true])
        ->assertSuccessful();

    expect(file_exists(base_path($path)))->toBeTrue()
        ->and(file_get_contents(base_path($path)))->toContain('# API del blog')
        ->and(file_exists(base_path('tests/tmp/API-PRUEBA.openapi.json')))->toBeTrue();

    // Limpieza: solo lo que ha creado esta prueba.
    unlink(base_path($path));
    unlink(base_path('tests/tmp/API-PRUEBA.openapi.json'));
    rmdir(base_path('tests/tmp'));
});

it('avisa en el documento cuando la dirección solo vale en este ordenador', function (string $url): void {
    config()->set('app.url', $url);

    expect(ApiDocumentation::addressIsLocal())->toBeTrue()
        ->and(ApiDocumentation::markdown())->toContain('no funcionará fuera de este ordenador');
})->with([
    'localhost' => 'http://localhost:8000',
    'ip' => 'http://127.0.0.1:8000',
    'dominio .test' => 'http://blog.test',
]);

it('no avisa cuando la dirección es un dominio real', function (): void {
    config()->set('app.url', 'https://blog.alminares.es');

    expect(ApiDocumentation::addressIsLocal())->toBeFalse()
        ->and(ApiDocumentation::markdown())
        ->not->toContain('no funcionará fuera de este ordenador')
        ->toContain('https://blog.alminares.es/api/v1');
});

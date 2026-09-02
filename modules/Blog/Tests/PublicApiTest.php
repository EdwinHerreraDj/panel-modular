<?php

declare(strict_types=1);

use App\Models\ApiKey;
use Modules\Blog\Enums\ArticleStatus;
use Modules\Blog\Enums\ArticleVisibility;
use Modules\Blog\Models\Article;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Tag;

beforeEach(function (): void {
    config()->set('api.require_key', true);
});

/*
|--------------------------------------------------------------------------
| Autenticación
|--------------------------------------------------------------------------
*/

it('rechaza el acceso sin clave', function (): void {
    $this->getJson(apiUrl('articulos'))
        ->assertUnauthorized()
        ->assertJsonPath('error.codigo', 'clave_ausente');
});

it('rechaza una clave inventada', function (): void {
    $this->withHeaders([(string) config('api.header') => 'blog_esto_no_existe'])
        ->getJson(apiUrl('articulos'))
        ->assertUnauthorized()
        ->assertJsonPath('error.codigo', 'clave_invalida');
});

it('rechaza una clave revocada', function (): void {
    [$headers, $key] = apiKeyHeaders();
    $key->revoke();

    $this->withHeaders($headers)
        ->getJson(apiUrl('articulos'))
        ->assertForbidden()
        ->assertJsonPath('error.mensaje', 'La clave de API ha sido revocada.');
});

it('rechaza una clave caducada', function (): void {
    [$headers, $key] = apiKeyHeaders();
    $key->forceFill(['expires_at' => now()->subDay()])->save();

    $this->withHeaders($headers)
        ->getJson(apiUrl('articulos'))
        ->assertForbidden()
        ->assertJsonPath('error.codigo', 'clave_no_utilizable');
});

it('acepta la clave como Bearer', function (): void {
    ['plain' => $plain] = ApiKey::generate('Web de pruebas');

    $this->withHeaders(['Authorization' => 'Bearer '.$plain])
        ->getJson(apiUrl('articulos'))
        ->assertOk();
});

it('rechaza la clave desde un dominio que no es el suyo', function (): void {
    [$headers] = apiKeyHeaders(['https://www.miweb.com']);

    $this->withHeaders($headers + ['Origin' => 'https://www.otraweb.com'])
        ->getJson(apiUrl('articulos'))
        ->assertForbidden()
        ->assertJsonPath('error.codigo', 'origen_no_permitido');

    $this->withHeaders($headers + ['Origin' => 'https://www.miweb.com'])
        ->getJson(apiUrl('articulos'))
        ->assertOk();
});

it('deja constancia de cada uso de la clave', function (): void {
    [$headers, $key] = apiKeyHeaders();

    $this->withHeaders($headers)->getJson(apiUrl('articulos'))->assertOk();

    $key->refresh();

    expect($key->requests_count)->toBe(1)
        ->and($key->last_used_at)->not->toBeNull();
});

it('no guarda la clave en claro en ningún sitio', function (): void {
    ['key' => $key, 'plain' => $plain] = ApiKey::generate('Web de pruebas');

    expect($key->getAttributes())->not->toContain($plain)
        ->and($key->key_hash)->toBe(hash('sha256', $plain))
        ->and($key->toArray())->not->toHaveKey('key_hash');
});

/*
|--------------------------------------------------------------------------
| Estado
|--------------------------------------------------------------------------
*/

it('responde al estado sin necesidad de clave', function (): void {
    Article::factory()->count(2)->published()->create();

    $this->getJson(apiUrl('estado'))
        ->assertOk()
        ->assertJsonPath('estado', 'ok')
        ->assertJsonPath('articulos_publicados', 2);
});

/*
|--------------------------------------------------------------------------
| Artículos
|--------------------------------------------------------------------------
*/

it('solo devuelve artículos publicados', function (): void {
    [$headers] = apiKeyHeaders();

    $published = Article::factory()->published()->create(['title' => 'Visible']);
    Article::factory()->create(['title' => 'Borrador', 'status' => ArticleStatus::Draft]);
    Article::factory()->create([
        'title' => 'Programado',
        'status' => ArticleStatus::Scheduled,
        'scheduled_for' => now()->addWeek(),
    ]);

    $response = $this->withHeaders($headers)->getJson(apiUrl('articulos'))->assertOk();

    expect($response->json('datos'))->toHaveCount(1)
        ->and($response->json('datos.0.titulo'))->toBe('Visible')
        ->and($response->json('datos.0.slug'))->toBe($published->slug);
});

it('no filtra nunca el contenido de un borrador por su dirección', function (): void {
    [$headers] = apiKeyHeaders();

    $draft = Article::factory()->create(['status' => ArticleStatus::Draft]);

    $this->withHeaders($headers)
        ->getJson(apiUrl('articulos/'.$draft->slug))
        ->assertNotFound()
        ->assertJsonPath('error.codigo', 'no_encontrado');
});

it('no lista los artículos solo con enlace pero sí los sirve por su dirección', function (): void {
    [$headers] = apiKeyHeaders();

    $unlisted = Article::factory()->published()->create([
        'visibility' => ArticleVisibility::Unlisted,
    ]);

    $list = $this->withHeaders($headers)->getJson(apiUrl('articulos'))->assertOk();
    expect($list->json('datos'))->toHaveCount(0);

    $this->withHeaders($headers)
        ->getJson(apiUrl('articulos/'.$unlisted->slug))
        ->assertOk()
        ->assertJsonPath('datos.slug', $unlisted->slug);
});

it('no adelanta un artículo con fecha de publicación futura', function (): void {
    [$headers] = apiKeyHeaders();

    Article::factory()->create([
        'status' => ArticleStatus::Published,
        'published_at' => now()->addHour(),
    ]);

    expect($this->withHeaders($headers)->getJson(apiUrl('articulos'))->json('datos'))->toHaveCount(0);
});

it('devuelve el contenido completo solo en el detalle', function (): void {
    [$headers] = apiKeyHeaders();

    $article = Article::factory()->published()->create([
        'content' => '<p>El cuerpo del artículo.</p>',
    ]);

    $list = $this->withHeaders($headers)->getJson(apiUrl('articulos'))->assertOk();
    expect($list->json('datos.0'))->not->toHaveKey('contenido_html');

    $this->withHeaders($headers)
        ->getJson(apiUrl('articulos/'.$article->slug))
        ->assertOk()
        ->assertJsonPath('datos.contenido_html', '<p>El cuerpo del artículo.</p>');
});

it('filtra por categoría y por etiqueta', function (): void {
    [$headers] = apiKeyHeaders();

    $category = Category::factory()->create(['name' => 'Noticias']);
    $tag = Tag::factory()->create(['name' => 'Novedades']);

    $matching = Article::factory()->published()->create();
    $matching->categories()->attach($category);
    $matching->tags()->attach($tag);

    Article::factory()->published()->create();

    expect(
        $this->withHeaders($headers)
            ->getJson(apiUrl('articulos?categoria='.$category->slug))
            ->json('datos'),
    )->toHaveCount(1);

    expect(
        $this->withHeaders($headers)
            ->getJson(apiUrl('articulos?etiqueta='.$tag->slug))
            ->json('datos'),
    )->toHaveCount(1);
});

it('respeta el máximo de artículos por página', function (): void {
    [$headers] = apiKeyHeaders();

    Article::factory()->count(3)->published()->create();

    $response = $this->withHeaders($headers)
        ->getJson(apiUrl('articulos?por_pagina=9999'))
        ->assertOk();

    expect($response->json('paginacion.por_pagina'))->toBe((int) config('api.max_per_page'));
});

it('nunca expone el correo del autor', function (): void {
    [$headers] = apiKeyHeaders();

    $article = Article::factory()->published()->create();

    $body = $this->withHeaders($headers)->getJson(apiUrl('articulos/'.$article->slug))->content();

    expect($body)->not->toContain((string) $article->author?->email);
});

/*
|--------------------------------------------------------------------------
| Taxonomías
|--------------------------------------------------------------------------
*/

it('solo devuelve categorías y etiquetas con artículos publicados', function (): void {
    [$headers] = apiKeyHeaders();

    $used = Category::factory()->create(['name' => 'Con artículos']);
    Category::factory()->create(['name' => 'Vacía']);

    $article = Article::factory()->published()->create();
    $article->categories()->attach($used);

    $response = $this->withHeaders($headers)->getJson(apiUrl('categorias'))->assertOk();

    expect($response->json('datos'))->toHaveCount(1)
        ->and($response->json('datos.0.nombre'))->toBe('Con artículos')
        ->and($response->json('datos.0.articulos'))->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Errores
|--------------------------------------------------------------------------
*/

it('devuelve los errores en JSON y en español', function (): void {
    [$headers] = apiKeyHeaders();

    $this->withHeaders($headers)
        ->get(apiUrl('esto-no-existe'))
        ->assertNotFound()
        ->assertJsonPath('error.mensaje', 'La dirección solicitada no existe en esta API.');
});

/*
|--------------------------------------------------------------------------
| CORS
|--------------------------------------------------------------------------
*/

it('nunca abre la API a cualquier dominio', function (): void {
    [$headers] = apiKeyHeaders(['https://www.miweb.com']);

    // El CORS que trae Laravel responde «*» para api/* si se deja activo, lo
    // que dejaría entrar a cualquier web. Debe estar desactivado.
    expect(config('cors.paths'))->toBe([]);

    $response = $this->withHeaders($headers + ['Origin' => 'https://www.miweb.com'])
        ->getJson(apiUrl('articulos'))
        ->assertOk();

    expect($response->headers->get('Access-Control-Allow-Origin'))->toBe('https://www.miweb.com');
});

it('no devuelve cabecera de CORS a un dominio que no está autorizado', function (): void {
    $response = $this->withHeaders(['Origin' => 'https://www.web-ajena.com'])
        ->getJson(apiUrl('estado'))
        ->assertOk();

    expect($response->headers->get('Access-Control-Allow-Origin'))->toBeNull()
        ->and($response->headers->get('Vary'))->toContain('Origin');
});

it('responde a la comprobación previa del navegador sin exigir clave', function (): void {
    apiKeyHeaders(['https://www.miweb.com']);

    $response = $this->call('OPTIONS', apiUrl('articulos'), server: [
        'HTTP_ORIGIN' => 'https://www.miweb.com',
    ]);

    expect($response->getStatusCode())->toBe(204)
        ->and($response->headers->get('Access-Control-Allow-Origin'))->toBe('https://www.miweb.com')
        ->and($response->headers->get('Access-Control-Allow-Headers'))->toContain((string) config('api.header'));
});

it('mantiene /blog para las webs conectadas antes de que hubiera módulos', function (): void {
    [$headers] = apiKeyHeaders();

    $datos = $this->getJson(apiUrl('blog'), $headers)->assertOk()->json('datos');

    // Los mismos datos del sitio, más lo único que solo entiende el blog.
    expect($datos)->toHaveKey('nombre')
        ->and($datos)->toHaveKey('seo')
        ->and($datos['articulos_por_pagina'])->toBe(12);
});

<?php

declare(strict_types=1);

use App\Enums\RevisionType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;
use Modules\Blog\Models\Article;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->editor = User::factory()->editor()->create();
});

it('guarda una copia automática sin tocar el artículo', function (): void {
    $article = Article::factory()->published()->by($this->editor)->create([
        'title' => 'Título publicado',
        'content' => '<p>Contenido publicado</p>',
    ]);

    $this->actingAs($this->editor)
        ->postJson(route('articles.autosave', $article), [
            'title' => 'Título a medias',
            'content' => '<p>Estoy escribiendo…</p>',
        ])
        ->assertOk()
        ->assertJsonStructure(['saved_at']);

    $article->refresh();

    // Lo publicado no ha cambiado.
    expect($article->title)->toBe('Título publicado')
        ->and($article->content)->toContain('Contenido publicado');

    // Y la copia sí está guardada.
    $autosave = $article->autosave()->first();

    expect($autosave)->not->toBeNull()
        ->and($autosave?->type)->toBe(RevisionType::Autosave)
        ->and($autosave?->snapshot['title'])->toBe('Título a medias');
});

it('conserva una sola copia automática por artículo', function (): void {
    $article = Article::factory()->by($this->editor)->create();

    foreach (['Uno', 'Dos', 'Tres'] as $title) {
        $this->actingAs($this->editor)->postJson(route('articles.autosave', $article), [
            'title' => $title,
            'content' => '<p>Texto</p>',
        ]);
    }

    $autosaves = $article->revisions()->where('type', RevisionType::Autosave->value)->get();

    expect($autosaves)->toHaveCount(1)
        ->and($autosaves->first()?->snapshot['title'])->toBe('Tres');
});

it('sanitiza también el contenido de la copia automática', function (): void {
    $article = Article::factory()->by($this->editor)->create();

    $this->actingAs($this->editor)->postJson(route('articles.autosave', $article), [
        'title' => 'Con basura',
        'content' => '<p>Texto</p><script>alert("xss")</script>',
    ]);

    $content = (string) $article->autosave()->first()?->snapshot['content'];

    expect($content)->toContain('Texto')
        ->and($content)->not->toContain('<script');
});

it('detecta la copia automática aunque comparta segundo con el guardado', function (): void {
    // Sin adelantar el reloj: es lo que ocurre de verdad al escribir justo
    // después de guardar, y donde una comparación estricta la perdía.
    $article = Article::factory()->by($this->editor)->create();

    $this->actingAs($this->editor)->postJson(route('articles.autosave', $article), [
        'title' => 'Recién escrito',
        'content' => '<p>Texto</p>',
    ]);

    $this->actingAs($this->editor)
        ->get(route('articles.edit', $article))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('autosave.title', 'Recién escrito'));

    $this->actingAs($this->editor)
        ->get(route('articles.preview', $article))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('article.title', 'Recién escrito')
            ->where('fromAutosave', true));
});

it('avisa en el editor cuando hay una copia más reciente', function (): void {
    $article = Article::factory()->by($this->editor)->create();

    $this->actingAs($this->editor)
        ->get(route('articles.edit', $article))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('autosave', null));

    $this->travel(1)->minutes();

    $this->actingAs($this->editor)->postJson(route('articles.autosave', $article), [
        'title' => 'Pendiente de recuperar',
        'content' => '<p>Texto</p>',
    ]);

    $this->actingAs($this->editor)
        ->get(route('articles.edit', $article))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('autosave.title', 'Pendiente de recuperar')
            ->has('autosave.saved_at'));
});

it('descarta la copia automática al guardar el artículo', function (): void {
    $article = Article::factory()->by($this->editor)->create();

    $this->actingAs($this->editor)->postJson(route('articles.autosave', $article), [
        'title' => 'A medias',
        'content' => '<p>Texto</p>',
    ]);

    $this->assertDatabaseHas('revisions', [
        'revisionable_type' => $article->getMorphClass(),
        'revisionable_id' => $article->id,
        'type' => RevisionType::Autosave->value,
    ]);

    $this->actingAs($this->editor)->post(route('articles.update', $article), articlePayload([
        'title' => 'Guardado de verdad',
    ]));

    // Se comprueba contra la tabla, no a través de la relación: así la prueba
    // no depende de cómo esté definida.
    $this->assertDatabaseMissing('revisions', [
        'revisionable_type' => $article->getMorphClass(),
        'revisionable_id' => $article->id,
        'type' => RevisionType::Autosave->value,
    ]);
});

it('permite descartar la copia automática a mano', function (): void {
    $article = Article::factory()->by($this->editor)->create();

    $this->actingAs($this->editor)->postJson(route('articles.autosave', $article), [
        'title' => 'A medias',
        'content' => '<p>Texto</p>',
    ]);

    $this->actingAs($this->editor)
        ->deleteJson(route('articles.autosave.discard', $article))
        ->assertOk();

    $this->assertDatabaseMissing('revisions', [
        'revisionable_type' => $article->getMorphClass(),
        'revisionable_id' => $article->id,
        'type' => RevisionType::Autosave->value,
    ]);
});

it('no deja autoguardar en artículos ajenos', function (): void {
    $author = User::factory()->author()->create();
    $article = Article::factory()->create();

    $this->actingAs($author)
        ->postJson(route('articles.autosave', $article), ['title' => 'Intruso'])
        ->assertForbidden();
});

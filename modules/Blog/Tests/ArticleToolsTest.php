<?php

declare(strict_types=1);

use App\Models\Media;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;
use Modules\Blog\Enums\ArticleStatus;
use Modules\Blog\Models\Article;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Tag;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->editor = User::factory()->editor()->create();
});

/* ----------------------------------------------------------------------------
 | Duplicación
 * -------------------------------------------------------------------------- */

it('duplica un artículo como borrador', function (): void {
    $category = Category::factory()->create();
    $tag = Tag::factory()->create();

    $original = Article::factory()->published()->by($this->editor)->create([
        'title' => 'Guía de persianas',
        'canonical_url' => 'https://www.ejemplo.es/original',
    ]);
    $original->categories()->attach($category);
    $original->tags()->attach($tag);

    $this->actingAs($this->editor)
        ->post(route('articles.duplicate', $original))
        ->assertRedirect()
        ->assertSessionHas('success');

    $copy = Article::query()->where('id', '!=', $original->id)->firstOrFail();

    expect($copy->title)->toBe('Guía de persianas (copia)')
        ->and($copy->status)->toBe(ArticleStatus::Draft)
        ->and($copy->published_at)->toBeNull()
        ->and($copy->is_slug_locked)->toBeFalse()
        ->and($copy->slug)->not->toBe($original->slug)
        // La canónica no se copia: apuntaría al original.
        ->and($copy->canonical_url)->toBeNull()
        ->and($copy->categories)->toHaveCount(1)
        ->and($copy->tags)->toHaveCount(1);
});

it('la copia comparte la imagen destacada de la biblioteca', function (): void {
    $media = Media::factory()->create();
    $original = Article::factory()->by($this->editor)->create();

    $this->actingAs($this->editor)->post(route('articles.update', $original), articlePayload([
        'title' => $original->title,
        'featured_media_id' => $media->id,
    ]));

    $this->actingAs($this->editor)->post(route('articles.duplicate', $original));

    $copy = Article::query()->where('id', '!=', $original->id)->firstOrFail();

    // Reutilizar el mismo archivo es justo lo que aporta la biblioteca; el
    // registro de usos impide que se elimine mientras alguno lo necesite.
    expect($copy->featured_media_id)->toBe($media->id)
        ->and($media->fresh()->usages()->count())->toBe(2);
});

it('no deja duplicar a quien no puede crear artículos', function (): void {
    $reader = User::factory()->reader()->create();
    $article = Article::factory()->create();

    $this->actingAs($reader)->post(route('articles.duplicate', $article))->assertForbidden();
});

/* ----------------------------------------------------------------------------
 | Artículos relacionados
 * -------------------------------------------------------------------------- */

it('guarda los artículos relacionados', function (): void {
    $article = Article::factory()->by($this->editor)->create();
    $others = Article::factory()->count(2)->create();

    $this->actingAs($this->editor)
        ->post(route('articles.update', $article), articlePayload([
            'title' => $article->title,
            'related_ids' => $others->pluck('id')->all(),
        ]))
        ->assertSessionHasNoErrors();

    expect($article->fresh()->related)->toHaveCount(2);
});

it('impide relacionar un artículo consigo mismo', function (): void {
    $article = Article::factory()->by($this->editor)->create();

    $this->actingAs($this->editor)
        ->from(route('articles.edit', $article))
        ->post(route('articles.update', $article), articlePayload([
            'title' => $article->title,
            'related_ids' => [$article->id],
        ]))
        ->assertSessionHasErrors('related_ids.0');

    expect($article->fresh()->related)->toHaveCount(0);
});

it('limita el número de artículos relacionados', function (): void {
    $article = Article::factory()->by($this->editor)->create();
    $others = Article::factory()->count(8)->create();

    $this->actingAs($this->editor)
        ->from(route('articles.edit', $article))
        ->post(route('articles.update', $article), articlePayload([
            'title' => $article->title,
            'related_ids' => $others->pluck('id')->all(),
        ]))
        ->assertSessionHasErrors('related_ids');
});

/* ----------------------------------------------------------------------------
 | Vista previa
 * -------------------------------------------------------------------------- */

it('muestra la vista previa del artículo', function (): void {
    $article = Article::factory()->by($this->editor)->create([
        'title' => 'Guía de persianas',
        'content' => '<p>El contenido</p>',
    ]);

    $this->actingAs($this->editor)
        ->get(route('articles.preview', $article))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Blog::Articles/Preview')
            ->where('article.title', 'Guía de persianas')
            ->where('fromAutosave', false)
            ->has('meta.title'));
});

it('la vista previa usa la copia automática si es más reciente', function (): void {
    $article = Article::factory()->by($this->editor)->create(['title' => 'Título guardado']);

    $this->travel(1)->minutes();

    $this->actingAs($this->editor)->postJson(route('articles.autosave', $article), [
        'title' => 'Título a medias',
        'content' => '<p>Sin guardar</p>',
    ]);

    $this->actingAs($this->editor)
        ->get(route('articles.preview', $article))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('article.title', 'Título a medias')
            ->where('fromAutosave', true));
});

it('no deja previsualizar artículos ajenos a quien no puede editarlos', function (): void {
    $author = User::factory()->author()->create();
    $article = Article::factory()->create();

    $this->actingAs($author)->get(route('articles.preview', $article))->assertForbidden();
});

/* ----------------------------------------------------------------------------
 | Acciones masivas
 * -------------------------------------------------------------------------- */

it('cambia el estado de varios artículos a la vez', function (): void {
    $articles = Article::factory()->count(3)->by($this->editor)->create();

    $this->actingAs($this->editor)
        ->from(route('articles.index'))
        ->post(route('articles.bulk.status'), [
            'ids' => $articles->pluck('id')->all(),
            'status' => 'published',
        ])
        ->assertSessionHas('success');

    $articles->each(function (Article $article): void {
        expect($article->fresh()->status)->toBe(ArticleStatus::Published)
            ->and($article->fresh()->published_at)->not->toBeNull()
            ->and($article->fresh()->is_slug_locked)->toBeTrue();
    });
});

it('omite los artículos que no admiten la transición e informa de ello', function (): void {
    $draft = Article::factory()->by($this->editor)->create();
    // De publicado no se puede pasar a pendiente de revisión.
    $published = Article::factory()->published()->by($this->editor)->create();

    $this->actingAs($this->editor)
        ->from(route('articles.index'))
        ->post(route('articles.bulk.status'), [
            'ids' => [$draft->id, $published->id],
            'status' => 'pending_review',
        ])
        ->assertSessionHas('success');

    expect($draft->fresh()->status)->toBe(ArticleStatus::PendingReview)
        ->and($published->fresh()->status)->toBe(ArticleStatus::Published)
        ->and(session('success'))->toContain('Se ha omitido 1');
});

it('deniega la acción masiva si falta el permiso del estado de destino', function (): void {
    $author = User::factory()->author()->create();
    $articles = Article::factory()->count(2)->by($author)->create();

    $this->actingAs($author)
        ->post(route('articles.bulk.status'), [
            'ids' => $articles->pluck('id')->all(),
            'status' => 'published',
        ])
        ->assertForbidden();

    $articles->each(fn (Article $article) => expect($article->fresh()->status)->toBe(ArticleStatus::Draft));
});

it('omite los artículos que el usuario no puede editar', function (): void {
    $author = User::factory()->author()->create();
    $own = Article::factory()->by($author)->create();
    $foreign = Article::factory()->create();

    $this->actingAs($author)
        ->from(route('articles.index'))
        ->post(route('articles.bulk.status'), [
            'ids' => [$own->id, $foreign->id],
            'status' => 'pending_review',
        ])
        ->assertSessionHas('success');

    expect($own->fresh()->status)->toBe(ArticleStatus::PendingReview)
        ->and($foreign->fresh()->status)->toBe(ArticleStatus::Draft);
});

it('exige seleccionar al menos un artículo', function (): void {
    $this->actingAs($this->editor)
        ->from(route('articles.index'))
        ->post(route('articles.bulk.status'), ['ids' => [], 'status' => 'published'])
        ->assertSessionHasErrors(['ids' => 'Selecciona al menos un artículo.']);
});

it('deja constancia en la auditoría de cada cambio masivo', function (): void {
    $article = Article::factory()->by($this->editor)->create();

    $this->actingAs($this->editor)
        ->from(route('articles.index'))
        ->post(route('articles.bulk.status'), ['ids' => [$article->id], 'status' => 'published']);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => (new Article)->getMorphClass(),
        'subject_id' => $article->id,
        'user_id' => $this->editor->id,
    ]);
});

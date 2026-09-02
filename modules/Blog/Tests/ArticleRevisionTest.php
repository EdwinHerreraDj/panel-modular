<?php

declare(strict_types=1);

use App\Enums\RevisionType;
use App\Models\Revision;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;
use Modules\Blog\Enums\ArticleStatus;
use Modules\Blog\Models\Article;
use Modules\Blog\Models\Category;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->editor = User::factory()->editor()->create();
});

it('guarda una versión en cada guardado', function (): void {
    $this->actingAs($this->editor)->post(route('articles.store'), articlePayload());

    $article = Article::query()->firstOrFail();

    expect($article->revisions()->count())->toBe(1);

    $this->actingAs($this->editor)->post(route('articles.update', $article), articlePayload([
        'title' => 'Título revisado',
    ]));

    expect($article->fresh()->revisions()->count())->toBe(2);
});

it('la versión guarda también la clasificación', function (): void {
    $category = Category::factory()->create();

    $this->actingAs($this->editor)->post(route('articles.store'), articlePayload([
        'category_ids' => [$category->id],
    ]));

    $revision = Revision::query()->firstOrFail();

    expect($revision->snapshot['category_ids'])->toBe([$category->id])
        ->and($revision->snapshot['title'])->toBe('Cómo elegir las persianas de casa');
});

it('muestra el historial de un artículo', function (): void {
    $article = Article::factory()->by($this->editor)->create();
    Revision::create([
        'revisionable_type' => $article->getMorphClass(),
        'revisionable_id' => $article->id,
        'user_id' => $this->editor->id,
        'type' => RevisionType::Manual,
        'snapshot' => ['title' => 'Versión antigua'],
    ]);

    $this->actingAs($this->editor)
        ->get(route('articles.revisions.index', $article))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Blog::Articles/Revisions')
            ->has('revisions', 1)
            ->where('revisions.0.title', 'Versión antigua'));
});

it('restaura el contenido de una versión anterior', function (): void {
    $this->actingAs($this->editor)->post(route('articles.store'), articlePayload([
        'title' => 'Título original',
        'content' => '<p>Contenido original</p>',
    ]));

    $article = Article::query()->firstOrFail();
    $original = $article->revisions()->firstOrFail();

    $this->actingAs($this->editor)->post(route('articles.update', $article), articlePayload([
        'title' => 'Título cambiado',
        'content' => '<p>Contenido cambiado</p>',
    ]));

    expect($article->fresh()->title)->toBe('Título cambiado');

    $this->actingAs($this->editor)
        ->post(route('articles.revisions.restore', [$article, $original]))
        ->assertRedirect(route('articles.edit', $article))
        ->assertSessionHas('success');

    $article->refresh();

    expect($article->title)->toBe('Título original')
        ->and($article->content)->toContain('Contenido original')
        // El tiempo de lectura se recalcula con el contenido restaurado.
        ->and($article->reading_minutes)->toBe(1);
});

it('no cambia el estado de publicación al restaurar', function (): void {
    $article = Article::factory()->published()->by($this->editor)->create();

    $revision = Revision::create([
        'revisionable_type' => $article->getMorphClass(),
        'revisionable_id' => $article->id,
        'user_id' => $this->editor->id,
        'type' => RevisionType::Manual,
        'snapshot' => [
            'title' => 'Cuando era borrador',
            'content' => '<p>Texto antiguo</p>',
            // La instantánea dice borrador, pero eso no debe aplicarse.
            'status' => 'draft',
            'visibility' => 'public',
            'is_indexable' => true,
        ],
    ]);

    $this->actingAs($this->editor)->post(route('articles.revisions.restore', [$article, $revision]));

    $article->refresh();

    expect($article->title)->toBe('Cuando era borrador')
        ->and($article->status)->toBe(ArticleStatus::Published);
});

it('guarda el estado actual antes de restaurar', function (): void {
    $this->actingAs($this->editor)->post(route('articles.store'), articlePayload(['title' => 'Uno']));

    $article = Article::query()->firstOrFail();
    $first = $article->revisions()->firstOrFail();

    $this->actingAs($this->editor)->post(route('articles.update', $article), articlePayload([
        'title' => 'Dos',
    ]));

    $this->actingAs($this->editor)->post(route('articles.revisions.restore', [$article, $first]));

    // Alta + modificación + salvaguarda previa a la restauración.
    expect($article->fresh()->revisions()->count())->toBe(3);

    $titles = $article->fresh()->revisions()->get()->map(
        fn (Revision $revision): string => $revision->snapshot['title'],
    );

    expect($titles)->toContain('Dos');
});

it('vuelve a sanitizar el contenido al restaurar', function (): void {
    $article = Article::factory()->by($this->editor)->create();

    $revision = Revision::create([
        'revisionable_type' => $article->getMorphClass(),
        'revisionable_id' => $article->id,
        'user_id' => $this->editor->id,
        'type' => RevisionType::Manual,
        'snapshot' => [
            'title' => 'Con basura',
            'content' => '<p>Texto</p><script>alert("xss")</script>',
            'is_indexable' => true,
        ],
    ]);

    $this->actingAs($this->editor)->post(route('articles.revisions.restore', [$article, $revision]));

    expect($article->fresh()->content)->toContain('Texto')
        ->and($article->fresh()->content)->not->toContain('<script');
});

it('no deja restaurar una versión de otro artículo', function (): void {
    $article = Article::factory()->by($this->editor)->create();
    $other = Article::factory()->create();

    $revision = Revision::create([
        'revisionable_type' => $other->getMorphClass(),
        'revisionable_id' => $other->id,
        'user_id' => $this->editor->id,
        'type' => RevisionType::Manual,
        'snapshot' => ['title' => 'Ajena'],
    ]);

    $this->actingAs($this->editor)
        ->post(route('articles.revisions.restore', [$article, $revision]))
        ->assertNotFound();
});

it('descarta las versiones más antiguas por encima del límite', function (): void {
    config()->set('articles.max_revisions', 3);

    $this->actingAs($this->editor)->post(route('articles.store'), articlePayload());
    $article = Article::query()->firstOrFail();

    foreach (range(1, 5) as $index) {
        $this->actingAs($this->editor)->post(route('articles.update', $article), articlePayload([
            'title' => "Versión {$index}",
        ]));
    }

    expect($article->fresh()->revisions()->count())->toBe(3);

    // Se conservan las más recientes.
    $titles = $article->fresh()->revisions()->get()->map(
        fn (Revision $revision): string => $revision->snapshot['title'],
    );

    expect($titles)->toContain('Versión 5')
        ->and($titles)->not->toContain('Versión 1');
});

it('no deja ver el historial a quien no puede editar el artículo', function (): void {
    $author = User::factory()->author()->create();
    $article = Article::factory()->create();

    $this->actingAs($author)->get(route('articles.revisions.index', $article))->assertForbidden();
});

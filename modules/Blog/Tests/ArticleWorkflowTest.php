<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Blog\Enums\ArticleStatus;
use Modules\Blog\Models\Article;

/**
 * Flujo editorial: quién puede llevar un artículo a cada estado.
 */
beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('deja al autor crear y enviar a revisión, pero no publicar', function (): void {
    $author = User::factory()->author()->create();

    $this->actingAs($author)
        ->post(route('articles.store'), articlePayload(['status' => 'pending_review']))
        ->assertSessionHasNoErrors();

    expect(Article::query()->firstOrFail()->status)->toBe(ArticleStatus::PendingReview);

    $this->actingAs($author)
        ->from(route('articles.create'))
        ->post(route('articles.store'), articlePayload([
            'title' => 'Otro artículo',
            'status' => 'published',
        ]))
        ->assertSessionHasErrors('status');

    expect(session('errors')->first('status'))->toContain('No tienes permiso');
});

it('impide al autor editar los artículos de otra persona', function (): void {
    $author = User::factory()->author()->create();
    $otherArticle = Article::factory()->create();

    $this->actingAs($author)->get(route('articles.edit', $otherArticle))->assertForbidden();

    $this->actingAs($author)
        ->post(route('articles.update', $otherArticle), articlePayload(['title' => 'Secuestrado']))
        ->assertForbidden();

    expect($otherArticle->fresh()->title)->not->toBe('Secuestrado');
});

it('deja al autor editar los suyos', function (): void {
    $author = User::factory()->author()->create();
    $article = Article::factory()->by($author)->create();

    $this->actingAs($author)
        ->post(route('articles.update', $article), articlePayload([
            'title' => 'Título revisado',
            'status' => 'draft',
        ]))
        ->assertSessionHasNoErrors();

    expect($article->fresh()->title)->toBe('Título revisado');
});

it('permite al revisor aprobar pero no publicar', function (): void {
    $reviewer = User::factory()->reviewer()->create();
    $article = Article::factory()->pendingReview()->create();

    // El revisor no tiene permiso para editar el contenido ajeno.
    $this->actingAs($reviewer)
        ->post(route('articles.update', $article), articlePayload(['title' => $article->title]))
        ->assertForbidden();

    expect($reviewer->can('blog.articles.approve'))->toBeTrue()
        ->and($reviewer->can('blog.articles.publish'))->toBeFalse();
});

it('permite al editor publicar, programar y archivar', function (): void {
    $editor = User::factory()->editor()->create();
    $article = Article::factory()->by($editor)->create();

    $this->actingAs($editor)
        ->post(route('articles.update', $article), articlePayload([
            'title' => $article->title,
            'status' => 'published',
        ]))
        ->assertSessionHasNoErrors();

    expect($article->fresh()->status)->toBe(ArticleStatus::Published);

    $this->actingAs($editor)
        ->post(route('articles.update', $article), articlePayload([
            'title' => $article->title,
            'status' => 'archived',
        ]))
        ->assertSessionHasNoErrors();

    expect($article->fresh()->status)->toBe(ArticleStatus::Archived);
});

it('rechaza una transición que el flujo no contempla', function (): void {
    $editor = User::factory()->editor()->create();
    // De publicado solo se puede volver a borrador o archivar.
    $article = Article::factory()->published()->by($editor)->create();

    $this->actingAs($editor)
        ->from(route('articles.edit', $article))
        ->post(route('articles.update', $article), articlePayload([
            'title' => $article->title,
            'status' => 'pending_review',
        ]))
        ->assertSessionHasErrors('status');

    expect(session('errors')->first('status'))->toContain('No se puede pasar');
    expect($article->fresh()->status)->toBe(ArticleStatus::Published);
});

it('solo ofrece los estados alcanzables con los permisos de cada rol', function (): void {
    $author = User::factory()->author()->create();

    $response = $this->actingAs($author)->get(route('articles.create'));

    /** @var array<int, array{value: string}> $options */
    $options = $response->viewData('page')['props']['statusOptions'];
    $values = array_column($options, 'value');

    expect($values)->toContain('draft')
        ->and($values)->toContain('pending_review')
        ->and($values)->not->toContain('published')
        ->and($values)->not->toContain('scheduled')
        ->and($values)->not->toContain('approved');
});

it('deja al autor descartar su propio borrador pero no un artículo publicado', function (): void {
    $author = User::factory()->author()->create();

    $draft = Article::factory()->by($author)->create();
    $published = Article::factory()->published()->by($author)->create();

    $this->actingAs($author)->delete(route('articles.destroy', $draft))->assertRedirect();
    expect($draft->fresh()->trashed())->toBeTrue();

    $this->actingAs($author)->delete(route('articles.destroy', $published))->assertForbidden();
    expect($published->fresh()->trashed())->toBeFalse();
});

it('no deja al lector crear ni editar nada', function (): void {
    $reader = User::factory()->reader()->create();
    $article = Article::factory()->create();

    $this->actingAs($reader)->get(route('articles.index'))->assertOk();
    $this->actingAs($reader)->get(route('articles.create'))->assertForbidden();
    $this->actingAs($reader)->post(route('articles.store'), articlePayload())->assertForbidden();
    $this->actingAs($reader)->get(route('articles.edit', $article))->assertForbidden();
    $this->actingAs($reader)->delete(route('articles.destroy', $article))->assertForbidden();
});

it('el scope público solo devuelve lo realmente visible', function (): void {
    Article::factory()->published()->create(['title' => 'Visible']);
    Article::factory()->published()->unlisted()->create(['title' => 'Solo con enlace']);
    Article::factory()->create(['title' => 'Borrador']);
    Article::factory()->scheduled()->create(['title' => 'Programado']);
    Article::factory()->archived()->create(['title' => 'Archivado']);

    // Publicado pero con fecha futura: todavía no debe salir.
    Article::factory()->published()->create([
        'title' => 'Futuro',
        'published_at' => now()->addDay(),
    ]);

    expect(Article::query()->public()->pluck('title')->all())->toBe(['Visible']);
});

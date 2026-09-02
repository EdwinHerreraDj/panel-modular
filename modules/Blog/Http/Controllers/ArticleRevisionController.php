<?php

declare(strict_types=1);

namespace Modules\Blog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Blog\Actions\RestoreArticleRevision;
use Modules\Blog\Models\Article;

/**
 * Historial de versiones de un artículo.
 */
class ArticleRevisionController extends Controller
{
    public function __construct(private readonly RestoreArticleRevision $restore) {}

    public function index(Request $request, Article $article): Response
    {
        $this->authorize('update', $article);

        $revisions = $article->revisions()
            ->with('user:id,name')
            ->limit(60)
            ->get()
            ->map(fn (Revision $revision): array => [
                'id' => $revision->id,
                'type' => $revision->type->value,
                'type_label' => $revision->type->label(),
                'author_name' => $revision->user?->name,
                'created_at' => $revision->created_at?->toIso8601String(),
                'title' => $revision->summary(),
            ]);

        return Inertia::render('Blog::Articles/Revisions', [
            'article' => [
                'id' => $article->id,
                'title' => $article->title,
                'status_label' => $article->status->label(),
            ],
            'revisions' => $revisions->all(),
        ]);
    }

    public function show(Article $article, Revision $revision): Response
    {
        $this->authorize('update', $article);

        abort_unless(
            $revision->revisionable_type === $article->getMorphClass()
            && $revision->revisionable_id === $article->id,
            404,
        );

        $revision->load('user:id,name');

        return Inertia::render('Blog::Articles/RevisionDetail', [
            'article' => ['id' => $article->id, 'title' => $article->title],
            'revision' => [
                'id' => $revision->id,
                'type_label' => $revision->type->label(),
                'author_name' => $revision->user?->name,
                'created_at' => $revision->created_at?->toIso8601String(),
                'title' => $revision->snapshot['title'] ?? '',
                'excerpt' => $revision->snapshot['excerpt'] ?? null,
                'content' => $revision->snapshot['content'] ?? '',
            ],
        ]);
    }

    public function restore(Request $request, Article $article, Revision $revision): RedirectResponse
    {
        $this->authorize('update', $article);

        abort_unless(
            $revision->revisionable_type === $article->getMorphClass()
            && $revision->revisionable_id === $article->id,
            404,
        );

        /** @var User $user */
        $user = $request->user();

        $this->restore->handle($article, $revision, $user);

        return redirect()
            ->route('articles.edit', $article)
            ->with(
                'success',
                'Versión restaurada. El estado de publicación no ha cambiado: revísalo antes de guardar.',
            );
    }
}

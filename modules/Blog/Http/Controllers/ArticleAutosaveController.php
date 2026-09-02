<?php

declare(strict_types=1);

namespace Modules\Blog\Http\Controllers;

use App\Enums\RevisionType;
use App\Http\Controllers\Controller;
use App\Support\HtmlSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Blog\Actions\RecordArticleRevision;
use Modules\Blog\Models\Article;

/**
 * Guardado automático mientras se escribe.
 *
 * No toca el artículo: guarda una copia aparte en el historial. Así una
 * edición a medias de algo ya publicado no llega nunca a la web.
 */
class ArticleAutosaveController extends Controller
{
    public function __construct(
        private readonly RecordArticleRevision $recordRevision,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    public function store(Request $request, Article $article): JsonResponse
    {
        $this->authorize('update', $article);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string', 'max:'.config('sanitizer.max_length', 500_000)],
        ]);

        $revision = $this->recordRevision->autosave($article, $request->user(), [
            'title' => $validated['title'] ?? $article->title,
            'excerpt' => $validated['excerpt'] ?? null,
            // También se limpia aquí: el contenido acabará mostrándose en la
            // vista previa y en el historial.
            'content' => $this->sanitizer->sanitize($validated['content'] ?? null),
        ]);

        return response()->json([
            'saved_at' => $revision->created_at?->toIso8601String(),
        ]);
    }

    /**
     * Descarta la copia automática pendiente.
     */
    public function destroy(Article $article): JsonResponse
    {
        $this->authorize('update', $article);

        // Por tipo, no a través de la relación `autosave`: al ser un
        // `latestOfMany` su consulta no se traduce bien a un DELETE.
        $article->revisions()->where('type', RevisionType::Autosave->value)->delete();

        return response()->json(['discarded' => true]);
    }
}

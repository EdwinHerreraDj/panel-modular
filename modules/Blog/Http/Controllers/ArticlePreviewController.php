<?php

declare(strict_types=1);

namespace Modules\Blog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Blog\Models\Article;

/**
 * Vista previa del artículo dentro del panel.
 *
 * La web pública es externa y consume la API, así que no hay una URL real que
 * previsualizar: aquí se muestra el contenido con la misma maquetación que
 * tendrá el texto una vez publicado.
 */
class ArticlePreviewController extends Controller
{
    public function __invoke(Request $request, Article $article): Response
    {
        $this->authorize('update', $article);

        $article->load(['author:id,name', 'categories:id,name', 'tags:id,name']);

        // Si hay una copia automática pendiente se previsualiza esa: es lo que
        // la persona tiene delante en el editor.
        //
        // La comparación admite la igualdad a propósito: guardar el artículo
        // borra su copia automática, así que cualquiera que exista es
        // posterior, aunque comparta segundo con el último guardado.
        $autosave = $article->autosave()->first();
        $useAutosave = $request->boolean('borrador', true)
            && $autosave !== null
            && $autosave->created_at?->greaterThanOrEqualTo($article->updated_at ?? now()->subCentury());

        // `$useAutosave` ya garantiza que la copia existe.
        $snapshot = $useAutosave ? $autosave->snapshot : [];
        $setting = Setting::current();

        return Inertia::render('Blog::Articles/Preview', [
            'article' => [
                'id' => $article->id,
                'title' => $snapshot['title'] ?? $article->title,
                'excerpt' => $snapshot['excerpt'] ?? $article->excerpt,
                'content' => $snapshot['content'] ?? $article->content ?? '',
                'status_label' => $article->status->label(),
                'reading_minutes' => $article->reading_minutes,
                'published_at' => $article->published_at?->toIso8601String(),
                'author_name' => $article->author?->name,
                'featured_image_url' => $article->featuredImageUrl(),
                'featured_image_alt' => $article->featured_image_alt,
                'categories' => $article->categories->pluck('name')->all(),
                'tags' => $article->tags->pluck('name')->all(),
            ],
            'meta' => [
                'title' => $setting->formatTitle($article->meta_title ?? $article->title),
                'description' => $article->meta_description ?? $article->excerpt ?? $setting->meta_description,
                'is_indexable' => $article->is_indexable && $setting->is_indexable,
                'canonical_url' => $article->canonical_url,
            ],
            'fromAutosave' => $useAutosave,
        ]);
    }
}

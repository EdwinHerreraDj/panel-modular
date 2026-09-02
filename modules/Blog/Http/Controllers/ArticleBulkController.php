<?php

declare(strict_types=1);

namespace Modules\Blog\Http\Controllers;

use App\Actions\RecordActivity;
use App\Enums\ActivityEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Modules\Blog\Enums\ArticleStatus;
use Modules\Blog\Models\Article;

/**
 * Cambio de estado de varios artículos a la vez.
 *
 * Cada artículo se comprueba por separado: permiso de edición, permiso del
 * estado de destino y transición válida desde su estado actual. Los que no
 * cumplen se omiten y se informa de cuántos han quedado fuera, en lugar de
 * fallar en bloque o de aplicar el cambio a medias.
 */
class ArticleBulkController extends Controller
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function updateStatus(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', Rule::exists('articles', 'id')->withoutTrashed()],
            'status' => ['required', new Enum(ArticleStatus::class)],
        ], [
            'ids.required' => 'Selecciona al menos un artículo.',
            'ids.max' => 'No puedes cambiar más de 100 artículos a la vez.',
        ]);

        $target = ArticleStatus::from($validated['status']);
        $user = $request->user();

        abort_if($user === null, 403);

        // Un permiso que no se tiene invalida la acción entera: no tiene
        // sentido dejar que lo intente artículo por artículo.
        $permission = $target->requiredPermission();

        if ($permission !== null && $user->cannot($permission->value)) {
            abort(403);
        }

        $articles = Article::query()->whereIn('id', $validated['ids'])->get();

        $changed = 0;
        $skipped = 0;

        foreach ($articles as $article) {
            if ($user->cannot('update', $article) || ! $article->status->canTransitionTo($target)) {
                $skipped++;

                continue;
            }

            if ($article->status === $target) {
                $skipped++;

                continue;
            }

            DB::transaction(function () use ($article, $target): void {
                $previous = $article->status;

                $article->status = $target;

                if ($target === ArticleStatus::Published) {
                    $article->published_at ??= now();
                    $article->scheduled_for = null;
                }

                $article->save();

                if ($article->isPublished()) {
                    $article->lockSlug();
                }

                $this->recordActivity->handle($article, ActivityEvent::Updated, sprintf(
                    'Cambio masivo del artículo «%s»: %s → %s',
                    $article->title,
                    $previous->label(),
                    $target->label(),
                ));
            });

            $changed++;
        }

        return back()->with(
            $changed > 0 ? 'success' : 'error',
            $this->summary($changed, $skipped, $target),
        );
    }

    private function summary(int $changed, int $skipped, ArticleStatus $target): string
    {
        if ($changed === 0) {
            return 'No se ha podido cambiar ningún artículo: revisa los permisos y el estado de cada uno.';
        }

        $message = $changed === 1
            ? "1 artículo pasa a «{$target->label()}»."
            : "{$changed} artículos pasan a «{$target->label()}».";

        if ($skipped > 0) {
            $message .= $skipped === 1
                ? ' Se ha omitido 1 porque no admitía ese cambio.'
                : " Se han omitido {$skipped} porque no admitían ese cambio.";
        }

        return $message;
    }
}

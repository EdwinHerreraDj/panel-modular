<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RecordActivity;
use App\Enums\ActivityEvent;
use App\Http\Requests\StoreRedirectRequest;
use App\Models\Redirect;
use App\Modules\ModuleRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Redirecciones de direcciones antiguas.
 *
 * Las automáticas las crea el sistema al cambiar la dirección de un artículo;
 * las manuales sirven sobre todo para traer un blog que ya existía sin perder
 * lo que Google tenía indexado.
 */
class RedirectController extends Controller
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function index(Request $request): Response
    {
        $filters = [
            'search' => trim((string) $request->string('search')),
            'status' => (string) $request->string('status'),   // origen
        ];

        $redirects = $this->query($filters)
            ->resolvable()
            ->with('redirectable')
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Redirect $redirect): array => [
                'id' => $redirect->id,
                'from_path' => $redirect->from_path,
                'to_path' => $redirect->to_path,
                'status_code' => $redirect->status_code,
                'is_automatic' => $redirect->is_automatic,
                'is_external' => $redirect->isExternal(),
                'target_title' => $this->targetTitle($redirect),
                'target_url' => $this->targetUrl($redirect),
                'hits' => $redirect->hits,
                'last_hit_at' => $redirect->last_hit_at?->toIso8601String(),
                'created_at' => $redirect->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Redirects/Index', [
            'redirects' => $redirects,
            'filters' => $filters + ['trashed' => false, 'sort' => 'created_at', 'direction' => 'desc', 'extra' => ''],
            'originOptions' => [
                ['value' => 'automatic', 'label' => 'Creadas por el sistema'],
                ['value' => 'manual', 'label' => 'Añadidas a mano'],
            ],
            'counts' => [
                'total' => Redirect::query()->count(),
                'automatic' => Redirect::query()->where('is_automatic', true)->count(),
                'used' => Redirect::query()->where('hits', '>', 0)->count(),
            ],
        ]);
    }

    public function store(StoreRedirectRequest $request): RedirectResponse
    {
        $redirect = Redirect::create($request->toRedirect());

        $this->recordActivity->handle(
            $redirect,
            ActivityEvent::Created,
            'Redirección añadida: '.$redirect->from_path.' → '.$redirect->to_path,
        );

        return back()->with('success', 'Redirección añadida. La web la aplicará en cuanto recargue la lista.');
    }

    public function destroy(Redirect $redirect): RedirectResponse
    {
        $description = 'Redirección eliminada: '.$redirect->from_path.' → '.$redirect->to_path;

        $redirect->delete();

        $this->recordActivity->handle($redirect, ActivityEvent::Deleted, $description);

        return back()->with('success', 'Redirección eliminada. Quien llegue por esa dirección verá un 404.');
    }

    /**
     * Nombre de aquello a lo que apunta, si sigue existiendo.
     *
     * Se pregunta por el atributo en lugar de comprobar la clase: así un
     * módulo nuevo se muestra sin tocar esto, siempre que su modelo tenga
     * `title` o `name`.
     */
    private function targetTitle(Redirect $redirect): ?string
    {
        $target = $redirect->redirectable;

        if ($target === null) {
            return null;
        }

        return $target->getAttribute('title') ?? $target->getAttribute('name');
    }

    /**
     * Enlace de edición, solo para lo que tiene pantalla propia.
     *
     * Lo resuelve el tipo de contenido, así que una redirección que apunte a un
     * producto enlazará a su ficha sin que este controlador sepa qué es un
     * producto.
     */
    private function targetUrl(Redirect $redirect): ?string
    {
        if ($redirect->redirectable_type === null || $redirect->redirectable_id === null) {
            return null;
        }

        return app(ModuleRegistry::class)
            ->contentType($redirect->redirectable_type)
            ?->urlFor($redirect->redirectable_id);
    }

    /**
     * @param  array{search: string, status: string}  $filters
     * @return Builder<Redirect>
     */
    private function query(array $filters): Builder
    {
        return Redirect::query()
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $term = '%'.$filters['search'].'%';

                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('from_path', 'like', $term)->orWhere('to_path', 'like', $term);
                });
            })
            ->when(
                in_array($filters['status'], ['automatic', 'manual'], true),
                fn (Builder $query) => $query->where('is_automatic', $filters['status'] === 'automatic'),
            );
    }
}

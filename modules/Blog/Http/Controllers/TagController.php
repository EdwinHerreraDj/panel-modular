<?php

declare(strict_types=1);

namespace Modules\Blog\Http\Controllers;

use App\Actions\RecordActivity;
use App\Enums\ActivityEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Blog\Http\Requests\TagRequest;
use Modules\Blog\Models\Tag;

/**
 * Etiquetas del blog. Se gestionan desde un único listado con formularios en
 * ventana modal: solo tienen nombre y no compensa una página por cada una.
 */
class TagController extends Controller
{
    /** @var list<string> */
    private const SORTABLE = ['name', 'created_at'];

    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Tag::class);

        $filters = [
            'search' => trim((string) $request->string('search')),
            'status' => '',
            'trashed' => $request->boolean('trashed'),
            'sort' => in_array($request->string('sort')->toString(), self::SORTABLE, true)
                ? $request->string('sort')->toString()
                : 'name',
            'direction' => $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc',
        ];

        $tags = Tag::query()
            ->search($filters['search'])
            ->when($filters['trashed'], fn ($query) => $query->onlyTrashed())
            ->orderBy($filters['sort'], $filters['direction'])
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Tag $tag): array => $this->toListItem($request, $tag));

        return Inertia::render('Blog::Tags/Index', [
            'tags' => $tags,
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', Tag::class) ?? false,
            ],
        ]);
    }

    public function store(TagRequest $request): RedirectResponse
    {
        $tag = DB::transaction(function () use ($request): Tag {
            $tag = Tag::create($request->validated());

            $this->recordActivity->handle(
                $tag,
                ActivityEvent::Created,
                "Alta de la etiqueta «{$tag->name}»",
            );

            return $tag;
        });

        return back()->with('success', "Etiqueta «{$tag->name}» creada.");
    }

    public function update(TagRequest $request, Tag $tag): RedirectResponse
    {
        DB::transaction(function () use ($request, $tag): void {
            $tag->update($request->validated());

            if ($tag->wasChanged()) {
                $this->recordActivity->forUpdate($tag, "Modificación de la etiqueta «{$tag->name}»");
            }
        });

        return back()->with('success', 'Etiqueta actualizada correctamente.');
    }

    public function regenerateSlug(Tag $tag): RedirectResponse
    {
        $this->authorize('regenerateSlug', $tag);

        $previous = $tag->slug;

        DB::transaction(function () use ($tag, $previous): void {
            $tag->regenerateSlug();

            $this->recordActivity->handle(
                $tag,
                ActivityEvent::Updated,
                "Slug regenerado para «{$tag->name}»",
                ['slug' => [$previous, $tag->slug]],
            );
        });

        return back()->with('success', "La dirección de la etiqueta es ahora «{$tag->slug}».");
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        $this->authorize('delete', $tag);

        DB::transaction(function () use ($tag): void {
            $tag->delete();

            $this->recordActivity->handle(
                $tag,
                ActivityEvent::Deleted,
                "Etiqueta «{$tag->name}» enviada a la papelera",
            );
        });

        return back()->with('success', 'Etiqueta enviada a la papelera.');
    }

    public function restore(Tag $tag): RedirectResponse
    {
        $this->authorize('restore', $tag);

        DB::transaction(function () use ($tag): void {
            $tag->restore();

            $this->recordActivity->handle(
                $tag,
                ActivityEvent::Restored,
                "Etiqueta «{$tag->name}» restaurada",
            );
        });

        return back()->with('success', 'Etiqueta restaurada correctamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function toListItem(Request $request, Tag $tag): array
    {
        $user = $request->user();

        return [
            'id' => $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
            'is_slug_locked' => $tag->is_slug_locked,
            'deleted_at' => $tag->deleted_at?->toIso8601String(),
            'can' => [
                'update' => $user?->can('update', $tag) ?? false,
                'delete' => $user?->can('delete', $tag) ?? false,
                'restore' => $user?->can('restore', $tag) ?? false,
            ],
        ];
    }
}

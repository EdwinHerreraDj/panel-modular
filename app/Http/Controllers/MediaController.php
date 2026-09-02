<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Media\DeleteMedia;
use App\Actions\Media\StoreMedia;
use App\Actions\RecordActivity;
use App\Http\Requests\StoreMediaRequest;
use App\Models\Media;
use App\Models\MediaUsage;
use App\Models\User;
use App\Support\ImageConstraints;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Biblioteca multimedia.
 */
class MediaController extends Controller
{
    /** @var list<string> */
    private const SORTABLE = ['name', 'size', 'created_at'];

    public function __construct(
        private readonly StoreMedia $storeMedia,
        private readonly DeleteMedia $deleteMedia,
        private readonly RecordActivity $recordActivity,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Media::class);

        $filters = [
            'search' => trim((string) $request->string('search')),
            'status' => '',
            'extra' => $request->string('extra')->toString(),
            'trashed' => $request->boolean('trashed'),
            'sort' => in_array($request->string('sort')->toString(), self::SORTABLE, true)
                ? $request->string('sort')->toString()
                : 'created_at',
            'direction' => $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc',
        ];

        $media = Media::query()
            ->withCount('usages')
            ->search($filters['search'])
            // «unused» permite localizar de un vistazo lo que ocupa espacio sin
            // estar publicado en ninguna parte.
            ->when($filters['extra'] === 'unused', fn ($query) => $query->doesntHave('usages'))
            ->when($filters['extra'] === 'used', fn ($query) => $query->has('usages'))
            ->when($filters['trashed'], fn ($query) => $query->onlyTrashed())
            ->orderBy($filters['sort'], $filters['direction'])
            ->paginate(24)
            ->withQueryString()
            ->through(fn (Media $item): array => $this->toListItem($request, $item));

        return Inertia::render('Media/Index', [
            'media' => $media,
            'filters' => $filters,
            'constraints' => ImageConstraints::for('library')->toArray(),
            'maxFiles' => (int) config('media.library.max_files', 10),
            'trashedCount' => Media::onlyTrashed()->count(),
            'can' => [
                'create' => $request->user()?->can('create', Media::class) ?? false,
                'delete' => $request->user()?->can('delete', new Media) ?? false,
                'purge' => $request->user()?->can('forceDelete', new Media) ?? false,
            ],
        ]);
    }

    public function store(StoreMediaRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var list<UploadedFile> $files */
        $files = $request->file('files', []);
        $uploaded = 0;

        foreach ($files as $file) {
            $this->storeMedia->handle($file, $user);
            $uploaded++;
        }

        return back()->with('success', $uploaded === 1
            ? 'Archivo subido a la biblioteca.'
            : "{$uploaded} archivos subidos a la biblioteca.");
    }

    /**
     * Detalle con la lista de sitios donde se está usando.
     */
    public function show(Media $media): JsonResponse
    {
        $this->authorize('view', $media);

        $media->load([
            'usages' => fn ($query) => $query->resolvable()->with('usable'),
            'uploader:id,name',
        ]);

        return response()->json([
            'id' => $media->id,
            'name' => $media->name,
            'alt_text' => $media->alt_text,
            'description' => $media->description,
            'url' => $media->url(),
            'preview_url' => $media->variantUrl('medium'),
            'dimensions' => $media->width === null ? null : "{$media->width} × {$media->height} px",
            'size' => $media->humanSize(),
            'mime_type' => $media->mime_type,
            'uploader_name' => $media->uploader?->name,
            'created_at' => $media->created_at?->toIso8601String(),
            'variants' => array_keys($media->variants ?? []),
            'usages' => $media->usages->map(fn (MediaUsage $usage): array => [
                'label' => $usage->describeUsable(),
                'role' => $usage->roleLabel(),
            ])->all(),
        ]);
    }

    public function update(Request $request, Media $media): RedirectResponse
    {
        $this->authorize('update', $media);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ], [
            'name.required' => 'El nombre es obligatorio.',
        ]);

        DB::transaction(function () use ($media, $validated): void {
            $media->update($validated);

            if ($media->wasChanged()) {
                $this->recordActivity->forUpdate($media, "Modificación del archivo «{$media->name}»");
            }
        });

        return back()->with('success', 'Archivo actualizado correctamente.');
    }

    public function destroy(Media $media): RedirectResponse
    {
        $this->authorize('delete', $media);

        // Lanza un error de validación si está en uso.
        $this->deleteMedia->handle($media);

        return back()->with('success', 'Archivo enviado a la papelera.');
    }

    public function restore(Media $media): RedirectResponse
    {
        $this->authorize('restore', $media);

        $media->restore();

        return back()->with('success', 'Archivo restaurado correctamente.');
    }

    /**
     * Borrado definitivo, incluidos los archivos del disco.
     */
    public function purge(Media $media): RedirectResponse
    {
        $this->authorize('forceDelete', $media);

        $this->deleteMedia->purge($media);

        return back()->with('success', 'Archivo eliminado definitivamente.');
    }

    /**
     * Vacía la papelera. Libera el espacio en disco, que es lo que suele
     * escasear en un alojamiento compartido.
     */
    public function emptyTrash(): RedirectResponse
    {
        $this->authorize('forceDelete', new Media);

        ['purged' => $purged, 'kept' => $kept] = $this->deleteMedia->emptyTrash();

        if ($purged === 0 && $kept === 0) {
            return back()->with('success', 'La papelera ya estaba vacía.');
        }

        $message = $purged === 1
            ? 'Se ha eliminado 1 archivo definitivamente.'
            : "Se han eliminado {$purged} archivos definitivamente.";

        if ($kept > 0) {
            $message .= $kept === 1
                ? ' Se ha conservado 1 porque ha vuelto a usarse.'
                : " Se han conservado {$kept} porque han vuelto a usarse.";
        }

        return back()->with('success', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function toListItem(Request $request, Media $media): array
    {
        $user = $request->user();

        return [
            'id' => $media->id,
            'name' => $media->name,
            'alt_text' => $media->alt_text,
            'description' => $media->description,
            'url' => $media->url(),
            'thumbnail_url' => $media->variantUrl('thumbnail'),
            'preview_url' => $media->variantUrl('medium'),
            'size' => $media->humanSize(),
            'dimensions' => $media->width === null ? null : "{$media->width} × {$media->height} px",
            'mime_type' => $media->mime_type,
            'status' => $media->processing_status->value,
            'status_label' => $media->processing_status->label(),
            'status_tone' => $media->processing_status->tone(),
            'usages_count' => $media->usages_count ?? 0,
            'deleted_at' => $media->deleted_at?->toIso8601String(),
            'created_at' => $media->created_at?->toIso8601String(),
            'can' => [
                'update' => $user?->can('update', $media) ?? false,
                'delete' => $user?->can('delete', $media) ?? false,
                'restore' => $user?->can('restore', $media) ?? false,
                'purge' => $user?->can('forceDelete', $media) ?? false,
            ],
        ];
    }
}

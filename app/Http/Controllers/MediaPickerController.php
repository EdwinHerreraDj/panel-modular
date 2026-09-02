<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Media\StoreMedia;
use App\Http\Requests\StoreMediaRequest;
use App\Models\Media;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Selector de imágenes de la biblioteca.
 *
 * Devuelve JSON para la ventana que se abre desde los formularios, sin
 * recargar la página en la que se está trabajando.
 */
class MediaPickerController extends Controller
{
    public function __construct(private readonly StoreMedia $storeMedia) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Media::class);

        $media = Media::query()
            ->search(trim((string) $request->string('search')))
            ->latest()
            ->paginate(24, ['*'], 'page', max(1, $request->integer('page', 1)));

        return response()->json([
            'data' => $media->getCollection()->map(fn (Media $item): array => $this->toItem($item))->all(),
            'current_page' => $media->currentPage(),
            'last_page' => $media->lastPage(),
            'total' => $media->total(),
        ]);
    }

    /**
     * Subida rápida desde el propio selector.
     */
    public function store(StoreMediaRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var list<UploadedFile> $files */
        $files = $request->file('files', []);
        $created = [];

        foreach ($files as $file) {
            $created[] = $this->toItem($this->storeMedia->handle($file, $user));
        }

        return response()->json(['data' => $created], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function toItem(Media $media): array
    {
        return [
            'id' => $media->id,
            'name' => $media->name,
            'alt_text' => $media->alt_text,
            'thumbnail_url' => $media->variantUrl('thumbnail'),
            'preview_url' => $media->variantUrl('medium'),
            'dimensions' => $media->width === null ? null : "{$media->width} × {$media->height} px",
            'size' => $media->humanSize(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Tienda\Actions;

use App\Actions\Media\SyncMediaUsage;
use App\Actions\RecordActivity;
use App\Enums\ActivityEvent;
use Illuminate\Support\Facades\DB;
use Modules\Tienda\Models\Category;

/**
 * Crea o actualiza una categoría de la tienda.
 */
final class SaveCategory
{
    public function __construct(
        private readonly SyncMediaUsage $syncMediaUsage,
        private readonly RecordActivity $recordActivity,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?Category $category, array $data): Category
    {
        $isNew = $category === null;

        return DB::transaction(function () use ($category, $data, $isNew): Category {
            $category ??= new Category;

            $category->fill([
                'parent_id' => $data['parent_id'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'image_media_id' => $data['image_media_id'] ?? null,
                'is_visible' => (bool) ($data['is_visible'] ?? true),
                'position' => (int) ($data['position'] ?? 0),
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
            ]);

            $changes = $isNew ? null : array_diff_key($category->getDirty(), ['updated_at' => true]);

            $category->save();

            // Igual que un artículo: visible, su dirección ya puede estar
            // enlazada desde fuera y deja de seguir al nombre.
            if ($category->is_visible) {
                $category->lockSlug();
            }

            $this->syncMediaUsage->set($category, 'image', $category->image_media_id);

            $this->recordActivity->handle(
                $category,
                $isNew ? ActivityEvent::Created : ActivityEvent::Updated,
                ($isNew ? 'Categoría de la tienda creada: ' : 'Categoría de la tienda modificada: ').$category->name,
                $changes === [] ? null : $changes,
            );

            return $category;
        });
    }
}

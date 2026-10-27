<?php

declare(strict_types=1);

namespace Modules\Tienda\Support;

use Modules\Tienda\Models\Category;

/**
 * El árbol de categorías, leído una sola vez por petición.
 *
 * Una tienda pequeña tiene unas decenas de categorías: es más barato y más
 * claro traerlas todas y recorrerlas en memoria que resolver la jerarquía con
 * consultas recursivas.
 *
 * Se guarda en los atributos de la petición y no en el contenedor: así cada
 * petición —también en las pruebas— parte de datos frescos, y guardar una
 * categoría lo olvida en el acto.
 */
final class CategoryTree
{
    private const KEY = 'tienda.category-tree';

    /** Profundidad máxima: Ropa › Hombre › Camisetas. */
    public const MAX_DEPTH = 3;

    /** @var array<int, Category> */
    private array $byId = [];

    /** @var array<int, list<int>> */
    private array $children = [];

    /**
     * @param  iterable<Category>  $categories
     */
    private function __construct(iterable $categories)
    {
        foreach ($categories as $category) {
            $this->byId[$category->id] = $category;
            $this->children[(int) $category->parent_id][] = $category->id;
        }
    }

    public static function current(): self
    {
        $request = request();
        $tree = $request->attributes->get(self::KEY);

        if (! $tree instanceof self) {
            $tree = new self(
                Category::query()
                    ->with('image')
                    ->orderBy('position')
                    ->orderBy('name')
                    ->get(),
            );

            $request->attributes->set(self::KEY, $tree);
        }

        return $tree;
    }

    public static function forget(): void
    {
        request()->attributes->remove(self::KEY);
    }

    public function find(?int $id): ?Category
    {
        return $id === null ? null : ($this->byId[$id] ?? null);
    }

    /**
     * Hijas directas, en orden.
     *
     * @return list<Category>
     */
    public function childrenOf(?int $id): array
    {
        return array_map(fn (int $child): Category => $this->byId[$child], $this->children[(int) $id] ?? []);
    }

    /**
     * La categoría y todas las que cuelgan de ella.
     *
     * @return list<int>
     */
    public function descendantIds(int $id): array
    {
        if (! isset($this->byId[$id])) {
            return [];
        }

        $ids = [$id];

        foreach ($this->children[$id] ?? [] as $child) {
            $ids = [...$ids, ...$this->descendantIds($child)];
        }

        return $ids;
    }

    /**
     * De la raíz a la categoría, ella incluida. Para la ruta de migas.
     *
     * @return list<Category>
     */
    public function path(int $id): array
    {
        $path = [];
        $seen = [];

        while (isset($this->byId[$id]) && ! isset($seen[$id])) {
            $seen[$id] = true;
            array_unshift($path, $this->byId[$id]);
            $id = (int) $this->byId[$id]->parent_id;
        }

        return $path;
    }

    public function depth(int $id): int
    {
        return count($this->path($id));
    }

    /**
     * Cuántos niveles cuelgan por debajo, ella sin contar.
     */
    public function height(int $id): int
    {
        $height = 0;

        foreach ($this->children[$id] ?? [] as $child) {
            $height = max($height, 1 + $this->height($child));
        }

        return $height;
    }

    /**
     * ¿Se ve en la web? Solo si ella y todas las de encima son visibles: una
     * subcategoría de una categoría oculta no tiene por dónde aparecer.
     */
    public function isPublic(int $id): bool
    {
        $path = $this->path($id);

        foreach ($path as $category) {
            if (! $category->is_visible) {
                return false;
            }
        }

        return $path !== [];
    }

    /**
     * Nombre completo: «Ropa › Camisetas».
     */
    public function label(int $id): string
    {
        return implode(' › ', array_map(fn (Category $category): string => $category->name, $this->path($id)));
    }

    /**
     * Todas, en orden de árbol, para los desplegables.
     *
     * @param  int|null  $exclude  Esta y sus descendientes no se ofrecen: no se
     *                             puede colgar una categoría de sí misma.
     * @return list<array{value: string, label: string, depth: int}>
     */
    public function options(?int $exclude = null): array
    {
        $excluded = $exclude === null ? [] : array_flip($this->descendantIds($exclude));
        $options = [];

        $walk = function (?int $parent, int $depth) use (&$walk, &$options, $excluded): void {
            foreach ($this->childrenOf($parent) as $category) {
                if (isset($excluded[$category->id])) {
                    continue;
                }

                $options[] = [
                    'value' => (string) $category->id,
                    'label' => str_repeat('— ', $depth).$category->name,
                    'depth' => $depth,
                ];

                $walk($category->id, $depth + 1);
            }
        };

        $walk(null, 0);

        return $options;
    }
}

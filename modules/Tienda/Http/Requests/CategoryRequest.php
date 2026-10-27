<?php

declare(strict_types=1);

namespace Modules\Tienda\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Tienda\Models\Category;
use Modules\Tienda\Support\CategoryTree;

/**
 * Validación de una categoría de la tienda.
 *
 * Lo delicado es dónde se cuelga: ni de sí misma, ni de una de sus hijas —eso
 * haría un bucle—, ni tan abajo que el árbol pase de tres niveles.
 */
class CategoryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer', Rule::exists('shop_categories', 'id')->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:2000'],
            'image_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'is_visible' => ['boolean'],
            'position' => ['integer', 'min:0', 'max:100000'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:160'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $parent = $this->input('parent_id');

                if ($parent === null || $validator->errors()->has('parent_id')) {
                    return;
                }

                $tree = CategoryTree::current();
                $category = $this->route('category');
                $id = $category instanceof Category ? $category->id : null;

                if ($id !== null && in_array((int) $parent, $tree->descendantIds($id), true)) {
                    $validator->errors()->add('parent_id', 'Una categoría no puede ir dentro de sí misma ni de una de sus subcategorías.');

                    return;
                }

                $levels = $tree->depth((int) $parent) + 1 + ($id === null ? 0 : $tree->height($id));

                if ($levels > CategoryTree::MAX_DEPTH) {
                    $validator->errors()->add(
                        'parent_id',
                        'Así quedarían más de '.CategoryTree::MAX_DEPTH.' niveles de categorías. Elige una categoría más arriba.',
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'parent_id' => 'categoría superior',
            'description' => 'descripción',
            'image_media_id' => 'imagen',
            'position' => 'orden',
            'meta_title' => 'título para buscadores',
            'meta_description' => 'descripción para buscadores',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Ponle un nombre a la categoría.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'parent_id' => $this->filled('parent_id') ? $this->input('parent_id') : null,
            'position' => $this->input('position') ?? 0,
        ]);
    }
}

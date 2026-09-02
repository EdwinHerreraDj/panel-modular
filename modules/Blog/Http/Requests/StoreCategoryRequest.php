<?php

declare(strict_types=1);

namespace Modules\Blog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Blog\Models\Category;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Category::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // No se pide el slug: se genera a partir del nombre.
            'name' => [
                'required', 'string', 'max:120',
                // También frente a las categorías de la papelera: su nombre
                // sigue reservado mientras se puedan restaurar.
                Rule::unique('categories', 'name'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'position' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Ya existe una categoría con ese nombre.',
            'meta_title.max' => 'El meta título no debería superar los 70 caracteres para que no se corte en Google.',
            'meta_description.max' => 'La meta descripción no debería superar los 160 caracteres para que no se corte en Google.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'description' => 'descripción',
            'meta_title' => 'meta título',
            'meta_description' => 'meta descripción',
            'position' => 'orden',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'position' => $this->input('position') ?? 0,
        ]);
    }
}

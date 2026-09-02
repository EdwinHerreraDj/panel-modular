<?php

declare(strict_types=1);

namespace Modules\Blog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Blog\Models\Tag;

/**
 * Alta y edición de etiquetas. Comparten reglas: solo tienen nombre.
 */
class TagRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tag = $this->route('tag');

        return $tag instanceof Tag
            ? ($this->user()?->can('update', $tag) ?? false)
            : ($this->user()?->can('create', Tag::class) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tag = $this->route('tag');

        $unique = Rule::unique('tags', 'name');

        if ($tag instanceof Tag) {
            $unique = $unique->ignoreModel($tag);
        }

        return [
            'name' => ['required', 'string', 'max:80', $unique],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Ya existe una etiqueta con ese nombre.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nombre'];
    }
}

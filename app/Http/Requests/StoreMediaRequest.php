<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Media;
use App\Rules\ImageFitsInMemory;
use App\Support\ImageConstraints;
use Illuminate\Foundation\Http\FormRequest;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Media::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $constraints = ImageConstraints::for('library');

        return [
            'files' => ['required', 'array', 'min:1', 'max:'.config('media.library.max_files', 10)],
            // La comprobación de memoria va al final: solo tiene sentido si el
            // archivo ya ha pasado por ser una imagen válida.
            'files.*' => [...$constraints->validationRules(), new ImageFitsInMemory],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $constraints = ImageConstraints::for('library');

        return [
            'files.required' => 'Selecciona al menos un archivo.',
            'files.max' => 'No puedes subir más de '.config('media.library.max_files', 10).' archivos a la vez.',
            'files.*.mimetypes' => "Cada archivo debe ser una imagen {$constraints->formatsLabel()}.",
            'files.*.image' => 'Todos los archivos deben ser imágenes.',
            'files.*.max' => 'Cada imagen debe pesar como mucho '
                .round($constraints->maxKilobytes / 1024, 1).' MB.',
            'files.*.dimensions' => sprintf(
                'Las dimensiones deben estar entre %d×%d y %d×%d píxeles.',
                $constraints->minWidth,
                $constraints->minHeight,
                $constraints->maxWidth,
                $constraints->maxHeight,
            ),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['files' => 'archivos'];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Catalogo\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Catalogo\Enums\ProductAvailability;

/**
 * Validación de un producto.
 *
 * La dirección no se pide: se genera del nombre. El precio puede ir vacío,
 * porque hay catálogos que no lo enseñan, y el rebajado solo tiene sentido
 * si es menor que el normal.
 */
class ProductRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('product')?->getKey();

        return [
            'name' => ['required', 'string', 'max:200'],
            'reference' => [
                'nullable', 'string', 'max:60',
                Rule::unique('products', 'reference')->ignore($id)->whereNull('deleted_at'),
            ],
            'summary' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:50000'],
            // Coma o punto: la gente escribe el precio como le sale.
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            // Una rebaja que no baja el precio es una errata, no una oferta.
            'sale_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'lt:price'],
            'availability' => ['required', Rule::enum(ProductAvailability::class)],
            'image_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'image_alt' => ['nullable', 'string', 'max:255'],
            'is_visible' => ['boolean'],
            'position' => ['integer', 'min:0', 'max:100000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'reference' => 'referencia',
            'summary' => 'resumen',
            'description' => 'descripción',
            'price' => 'precio',
            'sale_price' => 'precio rebajado',
            'availability' => 'disponibilidad',
            'image_media_id' => 'imagen',
            'image_alt' => 'texto alternativo',
            'is_visible' => 'visibilidad',
            'position' => 'orden',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Ponle un nombre al producto.',
            'reference.unique' => 'Ya hay otro producto con esa referencia.',
            'price.numeric' => 'El precio tiene que ser un número, sin el símbolo del euro.',
            'price.min' => 'El precio no puede ser negativo.',
            'sale_price.numeric' => 'El precio rebajado tiene que ser un número, sin el símbolo del euro.',
            'sale_price.lt' => 'El precio rebajado tiene que ser menor que el precio normal.',
        ];
    }

    /**
     * El precio se escribe como se dice, no como lo quiere la base de datos.
     *
     * En España el decimal es la coma, pero mucha gente teclea el punto. Se
     * distingue así:
     *
     * - Si hay coma, la coma es el decimal y los puntos son millares:
     *   `1.299,50` → `1299.50`.
     * - Si solo hay punto, es decimal cuando le siguen una o dos cifras
     *   (`149.90`) y millar cuando le siguen tres (`1.299`).
     */
    protected function prepareForValidation(): void
    {
        foreach (['price', 'sale_price'] as $field) {
            $this->merge([$field => self::normalizeAmount($this->input($field))]);
        }
    }

    /**
     * Convierte lo que se ha escrito en algo que la base de datos entienda.
     */
    private static function normalizeAmount(mixed $value): ?string
    {
        if (! is_string($value)) {
            return is_numeric($value) ? (string) $value : null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (str_contains($value, ',')) {
            return str_replace(['.', ','], ['', '.'], $value);
        }

        // Un punto seguido de tres cifras separa millares, no decimales.
        return preg_replace('/\.(?=\d{3}(?:\D|$))/', '', $value);
    }
}

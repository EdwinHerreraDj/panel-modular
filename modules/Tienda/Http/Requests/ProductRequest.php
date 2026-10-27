<?php

declare(strict_types=1);

namespace Modules\Tienda\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Tienda\Models\Product;
use Modules\Tienda\Models\ProductVariant;
use Modules\Tienda\Support\Money;

/**
 * Validación de un artículo, con su galería y sus variantes.
 *
 * Lo que no se puede expresar con reglas sueltas —que cada variante use
 * valores de sus opciones, que no haya dos combinaciones iguales, que una
 * referencia no la use otra variante— se comprueba al final, en `after()`.
 */
class ProductRequest extends FormRequest
{
    /** Más de tres opciones hacen una tabla de variantes que nadie mantiene. */
    public const MAX_OPTIONS = 3;

    public const MAX_VARIANTS = 250;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->product()?->getKey();

        return [
            'name' => ['required', 'string', 'max:200'],
            // Contra todas las filas, también las de la papelera: el índice
            // único de la base de datos tampoco distingue.
            'sku' => ['nullable', 'string', 'max:60', Rule::unique('shop_products', 'sku')->ignore($id)],
            'brand' => ['nullable', 'string', 'max:120'],
            'summary' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:50000'],

            'category_id' => ['nullable', 'integer', Rule::exists('shop_categories', 'id')->whereNull('deleted_at')],
            'category_ids' => ['array', 'max:20'],
            'category_ids.*' => ['integer', 'distinct', Rule::exists('shop_categories', 'id')->whereNull('deleted_at')],

            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],

            'track_stock' => ['boolean'],
            'stock' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'stock_loaded' => ['nullable', 'integer'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:100000'],

            'is_visible' => ['boolean'],
            'is_featured' => ['boolean'],
            'position' => ['integer', 'min:0', 'max:100000'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:160'],

            'images' => ['array', 'max:20'],
            'images.*.media_id' => ['required', 'integer', 'distinct', 'exists:media,id'],
            'images.*.alt' => ['nullable', 'string', 'max:255'],

            'options' => ['array', 'max:'.self::MAX_OPTIONS],
            'options.*.name' => ['required', 'string', 'max:60', 'distinct:ignore_case'],
            'options.*.values' => ['required', 'array', 'min:1', 'max:50'],
            'options.*.values.*' => ['required', 'string', 'max:60'],

            'variants' => ['array', 'max:'.self::MAX_VARIANTS, 'required_with:options.0'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.values' => ['required', 'array'],
            'variants.*.values.*' => ['required', 'string', 'max:60'],
            'variants.*.sku' => ['nullable', 'string', 'max:60', 'distinct'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'variants.*.stock_loaded' => ['nullable', 'integer'],
            'variants.*.is_active' => ['boolean'],
            'variants.*.media_id' => ['nullable', 'integer', 'exists:media,id'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->checkVariants($validator),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'sku' => 'referencia',
            'brand' => 'marca',
            'summary' => 'resumen',
            'description' => 'descripción',
            'category_id' => 'categoría',
            'category_ids' => 'otras categorías',
            'price' => 'precio',
            'stock' => 'unidades',
            'low_stock_threshold' => 'aviso de pocas unidades',
            'position' => 'orden',
            'meta_title' => 'título para buscadores',
            'meta_description' => 'descripción para buscadores',
            'images' => 'fotos',
            'options' => 'opciones',
            'options.*.name' => 'nombre de la opción',
            'options.*.values' => 'valores de la opción',
            'variants' => 'variantes',
            'variants.*.sku' => 'referencia de la variante',
            'variants.*.price' => 'precio de la variante',
            'variants.*.stock' => 'unidades de la variante',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Ponle un nombre al artículo.',
            'sku.unique' => 'Ya hay otro artículo con esa referencia.',
            'price.numeric' => 'El precio tiene que ser un número, sin el símbolo de la moneda.',
            'price.min' => 'El precio no puede ser negativo.',
            'options.max' => 'Un artículo puede tener como mucho tres opciones (por ejemplo talla, color y material).',
            'options.*.name.distinct' => 'Hay dos opciones con el mismo nombre.',
            'options.*.values.required' => 'Cada opción necesita al menos un valor.',
            'variants.required_with' => 'Has definido opciones pero ninguna variante. Pulsa «Generar combinaciones».',
            'variants.max' => 'Demasiadas combinaciones: como mucho '.self::MAX_VARIANTS.'. Reduce valores o crea artículos separados.',
            'variants.*.sku.distinct' => 'Dos variantes tienen la misma referencia.',
            'variants.*.price.numeric' => 'El precio de la variante tiene que ser un número.',
            'images.*.media_id.distinct' => 'Has añadido la misma foto dos veces.',
        ];
    }

    /**
     * Los importes y los valores llegan como los escribe la gente.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['price' => Money::normalize($this->input('price'))]);

        $options = [];

        foreach ((array) $this->input('options', []) as $option) {
            if (! is_array($option)) {
                continue;
            }

            $values = array_values(array_unique(array_filter(
                array_map(fn (mixed $value): string => trim((string) $value), (array) ($option['values'] ?? [])),
                fn (string $value): bool => $value !== '',
            )));

            $name = trim((string) ($option['name'] ?? ''));

            // Una opción vacía del todo es una fila que se dejó a medias.
            if ($name === '' && $values === []) {
                continue;
            }

            $options[] = ['name' => $name, 'values' => $values];
        }

        $variants = [];

        foreach ((array) $this->input('variants', []) as $variant) {
            if (! is_array($variant)) {
                continue;
            }

            $variant['values'] = array_map(fn (mixed $value): string => trim((string) $value), (array) ($variant['values'] ?? []));
            $variant['price'] = Money::normalize($variant['price'] ?? null);
            $variant['sku'] = is_string($variant['sku'] ?? null) && trim($variant['sku']) !== '' ? trim($variant['sku']) : null;
            $variants[] = $variant;
        }

        $this->merge([
            'options' => $options,
            'variants' => $options === [] ? [] : $variants,
        ]);
    }

    private function checkVariants(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        /** @var list<array{name: string, values: list<string>}> $options */
        $options = $this->input('options', []);
        /** @var list<array<string, mixed>> $variants */
        $variants = $this->input('variants', []);

        $seen = [];

        foreach ($variants as $index => $variant) {
            /** @var list<string> $values */
            $values = $variant['values'];

            if (count($values) !== count($options)) {
                $validator->errors()->add("variants.{$index}.values", 'Esta variante no tiene un valor para cada opción.');

                continue;
            }

            foreach ($values as $position => $value) {
                if (! in_array($value, $options[$position]['values'], true)) {
                    $validator->errors()->add(
                        "variants.{$index}.values",
                        "«{$value}» no es un valor de «{$options[$position]['name']}».",
                    );
                }
            }

            $key = mb_strtolower(implode("\x1F", $values));

            if (isset($seen[$key])) {
                $validator->errors()->add("variants.{$index}.values", 'Esta combinación está repetida.');
            }

            $seen[$key] = true;
        }

        $skus = array_values(array_filter(array_column($variants, 'sku')));

        if ($skus === []) {
            return;
        }

        $taken = ProductVariant::query()
            ->whereIn('sku', $skus)
            ->when($this->product() !== null, fn ($query) => $query->where('product_id', '!=', $this->product()?->getKey()))
            ->pluck('sku')
            ->all();

        foreach ($variants as $index => $variant) {
            if (($variant['sku'] ?? null) !== null && in_array($variant['sku'], $taken, true)) {
                $validator->errors()->add("variants.{$index}.sku", 'Otra variante de otro artículo ya usa esa referencia.');
            }
        }
    }

    private function product(): ?Product
    {
        $product = $this->route('product');

        return $product instanceof Product ? $product : null;
    }
}

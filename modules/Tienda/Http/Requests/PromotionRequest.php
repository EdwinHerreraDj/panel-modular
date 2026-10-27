<?php

declare(strict_types=1);

namespace Modules\Tienda\Http\Requests;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Modules\Tienda\Enums\PromotionScope;
use Modules\Tienda\Enums\PromotionType;
use Modules\Tienda\Support\Money;
use Throwable;

/**
 * Validación de una promoción.
 *
 * Las fechas llegan en la hora del sitio —la que ve quien la programa— y se
 * guardan en UTC. Sin esa conversión, unas rebajas «desde las 00:00» en Madrid
 * empezarían a las 01:00 o a las 02:00 según la época del año.
 */
class PromotionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isPercentage = $this->input('type') === PromotionType::Percentage->value;

        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(PromotionType::class)],
            'value' => ['required', 'numeric', 'gt:0', $isPercentage ? 'max:100' : 'max:99999999.99'],
            'scope' => ['required', Rule::enum(PromotionScope::class)],
            'category_ids' => [
                Rule::requiredIf($this->input('scope') === PromotionScope::Categories->value),
                'array',
            ],
            'category_ids.*' => ['integer', 'distinct', Rule::exists('shop_categories', 'id')->whereNull('deleted_at')],
            'product_ids' => [
                Rule::requiredIf($this->input('scope') === PromotionScope::Products->value),
                'array',
            ],
            'product_ids.*' => ['integer', 'distinct', Rule::exists('shop_products', 'id')->whereNull('deleted_at')],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'description' => 'texto para la web',
            'type' => 'tipo de descuento',
            'value' => 'descuento',
            'scope' => 'a qué se aplica',
            'category_ids' => 'categorías',
            'product_ids' => 'artículos',
            'starts_at' => 'inicio',
            'ends_at' => 'fin',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Ponle un nombre a la promoción.',
            'value.gt' => 'El descuento tiene que ser mayor que cero.',
            'value.max' => $this->input('type') === PromotionType::Percentage->value
                ? 'Un porcentaje no puede pasar del 100 %.'
                : 'El descuento es demasiado grande.',
            'category_ids.required' => 'Elige al menos una categoría.',
            'product_ids.required' => 'Elige al menos un artículo.',
            'ends_at.after' => 'La promoción tiene que terminar después de empezar.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'value' => Money::normalize($this->input('value')),
            'starts_at' => self::toUtc($this->input('starts_at')),
            'ends_at' => self::toUtc($this->input('ends_at')),
        ]);
    }

    /**
     * De la hora del sitio a UTC. Lo que no se entiende se deja pasar tal cual
     * para que lo rechace la regla `date` con su mensaje.
     */
    private static function toUtc(mixed $value): mixed
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value, Setting::current()->timezone)->utc()->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return $value;
        }
    }
}

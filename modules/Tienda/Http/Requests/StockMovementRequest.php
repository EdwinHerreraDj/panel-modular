<?php

declare(strict_types=1);

namespace Modules\Tienda\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Tienda\Enums\StockMovementType;

/**
 * Un movimiento de stock registrado a mano.
 *
 * En un recuento la cantidad es lo que hay en la estantería, así que el cero
 * vale. En el resto es cuánto entra o sale, y un movimiento de cero unidades
 * no explica nada.
 */
class StockMovementRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isCount = $this->input('type') === StockMovementType::Count->value;

        return [
            'variant_id' => ['nullable', 'integer'],
            'type' => ['required', Rule::enum(StockMovementType::class)],
            'quantity' => ['required', 'integer', $isCount ? 'min:0' : 'min:1', 'max:10000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => 'tipo de movimiento',
            'quantity' => 'cantidad',
            'note' => 'nota',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.min' => 'Indica cuántas unidades entran o salen.',
        ];
    }
}

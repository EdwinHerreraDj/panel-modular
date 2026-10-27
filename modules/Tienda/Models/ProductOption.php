<?php

declare(strict_types=1);

namespace Modules\Tienda\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Una opción de un artículo: «Talla» con sus valores «S, M, L».
 *
 * @property int $id
 * @property int $product_id
 * @property string $name
 * @property list<string> $values
 * @property int $position 1, 2 o 3: la columna de la variante que le toca
 */
class ProductOption extends Model
{
    protected $table = 'shop_product_options';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['name', 'values', 'position'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'values' => 'array',
            'position' => 'integer',
        ];
    }
}

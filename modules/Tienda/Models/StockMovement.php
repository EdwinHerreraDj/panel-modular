<?php

declare(strict_types=1);

namespace Modules\Tienda\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Tienda\Enums\StockMovementType;

/**
 * Una línea del historial de existencias. No se edita ni se borra: si hay un
 * error, se corrige con otro movimiento, como en un libro de cuentas.
 *
 * @property int $id
 * @property int $product_id
 * @property int|null $variant_id
 * @property string|null $variant_label
 * @property StockMovementType $type
 * @property int $quantity
 * @property int $stock_after
 * @property string|null $note
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property-read Product|null $product
 * @property-read User|null $user
 */
class StockMovement extends Model
{
    protected $table = 'shop_stock_movements';

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'product_id',
        'variant_id',
        'variant_label',
        'type',
        'quantity',
        'stock_after',
        'note',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'integer',
            'stock_after' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}

<?php

declare(strict_types=1);

namespace Modules\Tienda\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Tienda\Enums\StockMovementType;
use Modules\Tienda\Models\Product;
use Modules\Tienda\Models\ProductVariant;
use Modules\Tienda\Models\StockMovement;

/**
 * Mueve el stock de un artículo o de una variante y deja constancia.
 *
 * Es la única puerta por la que cambian las existencias: la pantalla de
 * inventario, la ficha del artículo y lo que venga después —un carrito, una
 * importación— pasan por aquí, así que el historial siempre cuadra con el
 * número.
 *
 * La fila se bloquea mientras se calcula: dos ventas registradas a la vez
 * desde dos pestañas no pueden leer el mismo stock y pisarse.
 */
final class RegisterStockMovement
{
    public function handle(
        Product $product,
        ?ProductVariant $variant,
        StockMovementType $type,
        int $quantity,
        ?string $note = null,
    ): StockMovement {
        return DB::transaction(function () use ($product, $variant, $type, $quantity, $note): StockMovement {
            /** @var Product|ProductVariant $target */
            $target = $variant === null
                ? Product::query()->withTrashed()->lockForUpdate()->findOrFail($product->id)
                : ProductVariant::query()->lockForUpdate()->findOrFail($variant->id);

            $current = $target->stock;

            $after = $type === StockMovementType::Count
                ? $quantity
                : $current + $type->direction() * abs($quantity);

            // Vender lo que no hay es un error al teclear, no un stock
            // negativo: se avisa en lugar de dejar el número imposible.
            if ($after < 0) {
                throw ValidationException::withMessages([
                    'quantity' => $current === 1
                        ? 'Solo queda 1 unidad.'
                        : "Solo quedan {$current} unidades.",
                ]);
            }

            $target->forceFill(['stock' => $after])->save();

            if ($variant !== null) {
                $product->refresh()->refreshStockFromVariants();
            } else {
                $product->setRawAttributes($target->getAttributes(), true);
            }

            return StockMovement::query()->create([
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'variant_label' => $variant?->label(),
                'type' => $type,
                'quantity' => $after - $current,
                'stock_after' => $after,
                'note' => $note === null || trim($note) === '' ? null : trim($note),
                'user_id' => Auth::id(),
            ]);
        });
    }
}

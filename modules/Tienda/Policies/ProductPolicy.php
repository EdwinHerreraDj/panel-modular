<?php

declare(strict_types=1);

namespace Modules\Tienda\Policies;

use App\Models\User;
use Modules\Tienda\Enums\TiendaPermission;
use Modules\Tienda\Models\Product;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(TiendaPermission::View->value);
    }

    public function view(User $user, Product $product): bool
    {
        return $user->can(TiendaPermission::View->value);
    }

    public function create(User $user): bool
    {
        return $user->can(TiendaPermission::ProductsManage->value);
    }

    public function update(User $user, Product $product): bool
    {
        return $user->can(TiendaPermission::ProductsManage->value);
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->can(TiendaPermission::ProductsDelete->value);
    }

    public function restore(User $user, Product $product): bool
    {
        return $user->can(TiendaPermission::ProductsDelete->value);
    }

    /**
     * Regenerar la dirección cambia una URL pública, así que es una acción
     * aparte de editar.
     */
    public function regenerateSlug(User $user, Product $product): bool
    {
        return $user->can(TiendaPermission::ProductsManage->value);
    }

    /**
     * Entradas, salidas y recuentos. Va aparte de editar el artículo: quien
     * atiende el almacén no tiene por qué poder cambiar precios.
     */
    public function manageStock(User $user, ?Product $product = null): bool
    {
        return $user->can(TiendaPermission::StockManage->value);
    }
}

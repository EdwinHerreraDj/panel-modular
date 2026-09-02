<?php

declare(strict_types=1);

namespace Modules\Catalogo\Policies;

use App\Models\User;
use Modules\Catalogo\Enums\CatalogoPermission;
use Modules\Catalogo\Models\Product;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(CatalogoPermission::ProductsView->value);
    }

    public function view(User $user, Product $product): bool
    {
        return $user->can(CatalogoPermission::ProductsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(CatalogoPermission::ProductsManage->value);
    }

    public function update(User $user, Product $product): bool
    {
        return $user->can(CatalogoPermission::ProductsManage->value);
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->can(CatalogoPermission::ProductsDelete->value);
    }

    public function restore(User $user, Product $product): bool
    {
        return $user->can(CatalogoPermission::ProductsDelete->value);
    }

    /**
     * Regenerar la dirección cambia una URL pública, así que es una acción
     * aparte de editar.
     */
    public function regenerateSlug(User $user, Product $product): bool
    {
        return $user->can(CatalogoPermission::ProductsManage->value);
    }
}

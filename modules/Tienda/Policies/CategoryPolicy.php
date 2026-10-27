<?php

declare(strict_types=1);

namespace Modules\Tienda\Policies;

use App\Models\User;
use Modules\Tienda\Enums\TiendaPermission;
use Modules\Tienda\Models\Category;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(TiendaPermission::View->value);
    }

    public function create(User $user): bool
    {
        return $user->can(TiendaPermission::CategoriesManage->value);
    }

    public function update(User $user, Category $category): bool
    {
        return $user->can(TiendaPermission::CategoriesManage->value);
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->can(TiendaPermission::CategoriesManage->value);
    }

    public function restore(User $user, Category $category): bool
    {
        return $user->can(TiendaPermission::CategoriesManage->value);
    }

    public function regenerateSlug(User $user, Category $category): bool
    {
        return $user->can(TiendaPermission::CategoriesManage->value);
    }
}

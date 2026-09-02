<?php

declare(strict_types=1);

namespace Modules\Blog\Policies;

use App\Models\User;
use Modules\Blog\Enums\BlogPermission;
use Modules\Blog\Models\Category;

/**
 * Autorización sobre las categorías.
 *
 * El superadministrador se resuelve antes, en el Gate::before.
 */
class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(BlogPermission::ArticlesView->value);
    }

    public function view(User $user, Category $category): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(BlogPermission::CategoriesManage->value);
    }

    public function update(User $user, Category $category): bool
    {
        return $user->can(BlogPermission::CategoriesManage->value);
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->can(BlogPermission::CategoriesManage->value);
    }

    public function restore(User $user, Category $category): bool
    {
        return $user->can(BlogPermission::CategoriesManage->value);
    }

    /**
     * Regenerar el slug cambia una URL pública, así que se reserva a quien
     * gestiona el contenido.
     */
    public function regenerateSlug(User $user, Category $category): bool
    {
        return $user->can(BlogPermission::CategoriesManage->value);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Blog\Policies;

use App\Models\User;
use Modules\Blog\Enums\BlogPermission;
use Modules\Blog\Models\Tag;

/**
 * Autorización sobre las etiquetas.
 */
class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(BlogPermission::ArticlesView->value);
    }

    public function view(User $user, Tag $tag): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(BlogPermission::TagsManage->value);
    }

    public function update(User $user, Tag $tag): bool
    {
        return $user->can(BlogPermission::TagsManage->value);
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $user->can(BlogPermission::TagsManage->value);
    }

    public function restore(User $user, Tag $tag): bool
    {
        return $user->can(BlogPermission::TagsManage->value);
    }

    public function regenerateSlug(User $user, Tag $tag): bool
    {
        return $user->can(BlogPermission::TagsManage->value);
    }
}

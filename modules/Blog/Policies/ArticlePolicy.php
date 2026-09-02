<?php

declare(strict_types=1);

namespace Modules\Blog\Policies;

use App\Models\User;
use Modules\Blog\Enums\ArticleStatus;
use Modules\Blog\Enums\BlogPermission;
use Modules\Blog\Models\Article;

/**
 * Autorización sobre los artículos.
 *
 * La distinción importante es entre editar «lo propio» y editar «cualquier
 * cosa»: un autor solo puede tocar lo que ha escrito él.
 */
class ArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(BlogPermission::ArticlesView->value);
    }

    public function view(User $user, Article $article): bool
    {
        return $user->can(BlogPermission::ArticlesView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(BlogPermission::ArticlesCreate->value);
    }

    public function update(User $user, Article $article): bool
    {
        if ($user->can(BlogPermission::ArticlesUpdateAny->value)) {
            return true;
        }

        return $user->can(BlogPermission::ArticlesUpdateOwn->value) && $article->belongsToAuthor($user);
    }

    /**
     * Enviar a la papelera.
     *
     * Quien solo edita lo suyo puede descartar sus propios borradores; en
     * cuanto el artículo ha salido de esa fase hace falta el permiso de
     * eliminación.
     */
    public function delete(User $user, Article $article): bool
    {
        if ($user->can(BlogPermission::ArticlesDelete->value)) {
            return true;
        }

        return $article->status === ArticleStatus::Draft
            && $article->belongsToAuthor($user)
            && $user->can(BlogPermission::ArticlesUpdateOwn->value);
    }

    public function restore(User $user, Article $article): bool
    {
        return $user->can(BlogPermission::ArticlesRestore->value);
    }

    public function forceDelete(User $user, Article $article): bool
    {
        return false;
    }

    public function publish(User $user, Article $article): bool
    {
        return $user->can(BlogPermission::ArticlesPublish->value);
    }

    public function schedule(User $user, Article $article): bool
    {
        return $user->can(BlogPermission::ArticlesSchedule->value);
    }

    public function approve(User $user, Article $article): bool
    {
        return $user->can(BlogPermission::ArticlesApprove->value);
    }

    /**
     * Regenerar el slug cambia una URL pública ya indexada.
     */
    public function regenerateSlug(User $user, Article $article): bool
    {
        return $user->can(BlogPermission::ArticlesUpdateAny->value);
    }
}

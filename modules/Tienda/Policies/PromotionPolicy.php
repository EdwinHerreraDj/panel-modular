<?php

declare(strict_types=1);

namespace Modules\Tienda\Policies;

use App\Models\User;
use Modules\Tienda\Enums\TiendaPermission;
use Modules\Tienda\Models\Promotion;

class PromotionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(TiendaPermission::View->value);
    }

    public function create(User $user): bool
    {
        return $user->can(TiendaPermission::PromotionsManage->value);
    }

    public function update(User $user, Promotion $promotion): bool
    {
        return $user->can(TiendaPermission::PromotionsManage->value);
    }

    public function delete(User $user, Promotion $promotion): bool
    {
        return $user->can(TiendaPermission::PromotionsManage->value);
    }
}

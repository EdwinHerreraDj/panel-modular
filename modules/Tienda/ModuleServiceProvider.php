<?php

declare(strict_types=1);

namespace Modules\Tienda;

use App\Modules\Module;
use App\Modules\ModuleProvider;
use Modules\Tienda\Models\Category;
use Modules\Tienda\Models\Product;
use Modules\Tienda\Models\Promotion;
use Modules\Tienda\Policies\CategoryPolicy;
use Modules\Tienda\Policies\ProductPolicy;
use Modules\Tienda\Policies\PromotionPolicy;

class ModuleServiceProvider extends ModuleProvider
{
    public function module(): Module
    {
        return new TiendaModule;
    }

    /**
     * Fuera de `App\`, Laravel no descubre las policies solo.
     */
    protected function policies(): array
    {
        return [
            Product::class => ProductPolicy::class,
            Category::class => CategoryPolicy::class,
            Promotion::class => PromotionPolicy::class,
        ];
    }
}

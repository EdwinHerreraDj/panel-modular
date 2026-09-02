<?php

declare(strict_types=1);

namespace Modules\Catalogo;

use App\Modules\Module;
use App\Modules\ModuleProvider;
use Modules\Catalogo\Models\Product;
use Modules\Catalogo\Policies\ProductPolicy;

class ModuleServiceProvider extends ModuleProvider
{
    public function module(): Module
    {
        return new CatalogoModule;
    }

    /**
     * Fuera de `App\`, Laravel no descubre las policies solo.
     */
    protected function policies(): array
    {
        return [
            Product::class => ProductPolicy::class,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Catalogo\Enums;

/**
 * Permisos del catálogo.
 *
 * Tres y no once: un catálogo no tiene flujo editorial. Un producto se ve, se
 * gestiona o se retira, y punto.
 */
enum CatalogoPermission: string
{
    case ProductsView = 'catalogo.products.view';
    case ProductsManage = 'catalogo.products.manage';
    case ProductsDelete = 'catalogo.products.delete';

    public function label(): string
    {
        return match ($this) {
            self::ProductsView => 'Ver productos',
            self::ProductsManage => 'Crear y editar productos',
            self::ProductsDelete => 'Enviar productos a la papelera',
        };
    }
}

<?php

declare(strict_types=1);

namespace Modules\Tienda\Enums;

/**
 * Permisos de la tienda.
 *
 * Separados por tarea y no por pantalla: quien atiende el almacén puede
 * registrar entradas y salidas sin poder cambiar precios, y quien lleva las
 * ofertas no tiene por qué tocar el catálogo.
 */
enum TiendaPermission: string
{
    case View = 'tienda.view';
    case ProductsManage = 'tienda.products.manage';
    case ProductsDelete = 'tienda.products.delete';
    case CategoriesManage = 'tienda.categories.manage';
    case StockManage = 'tienda.stock.manage';
    case PromotionsManage = 'tienda.promotions.manage';

    public function label(): string
    {
        return match ($this) {
            self::View => 'Ver la tienda',
            self::ProductsManage => 'Crear y editar artículos',
            self::ProductsDelete => 'Enviar artículos a la papelera',
            self::CategoriesManage => 'Gestionar las categorías de la tienda',
            self::StockManage => 'Registrar entradas y salidas de stock',
            self::PromotionsManage => 'Gestionar promociones',
        };
    }
}

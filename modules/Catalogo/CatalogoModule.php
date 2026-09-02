<?php

declare(strict_types=1);

namespace Modules\Catalogo;

use App\Enums\RoleName;
use App\Models\User;
use App\Modules\BaseModule;
use App\Modules\Values\ApiEndpoint;
use App\Modules\Values\ContentType;
use App\Modules\Values\DashboardAlert;
use App\Modules\Values\DashboardCard;
use App\Modules\Values\DashboardContribution;
use App\Modules\Values\ManualSection;
use App\Modules\Values\MenuItem;
use App\Modules\Values\ModulePermission;
use App\Modules\Values\PublicCount;
use Modules\Catalogo\Enums\CatalogoPermission;
use Modules\Catalogo\Enums\ProductAvailability;
use Modules\Catalogo\Models\Product;

/**
 * Catálogo de productos.
 *
 * Deliberadamente pequeño: una tabla, un formulario y un listado. La web
 * pública lo pinta en una página y el cliente lo mantiene sin saber lo que es
 * un slug ni un estado de publicación.
 */
class CatalogoModule extends BaseModule
{
    public function key(): string
    {
        return 'catalogo';
    }

    public function name(): string
    {
        return 'Catálogo';
    }

    public function permissions(): array
    {
        $roles = [
            CatalogoPermission::ProductsView->value => [
                RoleName::Editor, RoleName::Reviewer, RoleName::Author, RoleName::Reader,
            ],
            CatalogoPermission::ProductsManage->value => [RoleName::Editor],
            CatalogoPermission::ProductsDelete->value => [RoleName::Editor],
        ];

        return array_map(
            fn (CatalogoPermission $permission): ModulePermission => new ModulePermission(
                value: $permission->value,
                label: $permission->label(),
                group: 'Catálogo',
                roles: $roles[$permission->value],
            ),
            CatalogoPermission::cases(),
        );
    }

    public function menu(): array
    {
        return [
            new MenuItem(
                label: 'Productos',
                route: 'products.index',
                icon: 'box',
                activePattern: 'products.*',
                permission: CatalogoPermission::ProductsView->value,
                position: 35,
            ),
        ];
    }

    public function contentTypes(): array
    {
        return [
            new ContentType(
                model: Product::class,
                alias: 'catalogo.product',
                singular: 'Producto',
                plural: 'Productos',
                editUrl: fn (int|string $id): string => route('products.edit', $id),
            ),
        ];
    }

    public function dashboard(User $user): DashboardContribution
    {
        if ($user->cannot(CatalogoPermission::ProductsView->value)) {
            return DashboardContribution::empty();
        }

        $total = Product::query()->count();
        $visible = Product::query()->public()->count();

        $outOfStock = Product::query()
            ->public()
            ->where('availability', ProductAvailability::OutOfStock->value)
            ->count();

        return new DashboardContribution(
            alerts: $outOfStock === 0 ? [] : [
                new DashboardAlert(
                    key: 'catalogo.sin-stock',
                    text: $outOfStock === 1
                        ? 'Hay 1 producto en la web marcado como sin stock.'
                        : "Hay {$outOfStock} productos en la web marcados como sin stock.",
                    tone: 'warning',
                    actionLabel: 'Verlos',
                    actionHref: route('products.index', ['extra' => ProductAvailability::OutOfStock->value]),
                    position: 50,
                ),
            ],
            cards: [
                new DashboardCard(
                    key: 'catalogo.total',
                    label: 'Productos',
                    count: $total,
                    icon: 'box',
                    tone: 'primary',
                    route: 'products.index',
                    position: 50,
                ),
                new DashboardCard(
                    key: 'catalogo.visible',
                    label: 'Visibles en la web',
                    count: $visible,
                    icon: 'check',
                    tone: 'success',
                    route: 'products.index',
                    query: ['status' => 'visible'],
                    hint: $total > 0 && $visible === 0 ? 'Ninguno se está enseñando todavía' : null,
                    position: 60,
                ),
            ],
        );
    }

    public function publicCounts(): array
    {
        return [
            new PublicCount(
                key: 'productos_visibles',
                label: 'Productos que sirve ahora mismo',
                count: Product::query()->public()->count(),
            ),
        ];
    }

    public function sitemapSections(): array
    {
        return [
            'productos' => Product::query()
                ->public()
                ->orderBy('position')
                ->get(['slug', 'updated_at'])
                ->map(fn (Product $product): array => [
                    'slug' => $product->slug,
                    'modificado_en' => $product->updated_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }

    public function apiEndpoints(): array
    {
        $product = [
            'id' => 4,
            'nombre' => 'Silla de roble macizo',
            'slug' => 'silla-de-roble-macizo',
            'referencia' => 'SR-2043',
            'resumen' => 'Madera maciza, disponible en tres acabados.',
            'precio' => 119.0,
            'precio_texto' => '119,00 €',
            'precio_anterior' => 149.0,
            'precio_anterior_texto' => '149,00 €',
            'descuento_porcentaje' => 20,
            'disponibilidad' => 'low',
            'disponibilidad_texto' => 'Pocas unidades',
            'imagen' => [
                'alt' => 'Silla de roble vista de frente',
                'miniatura' => 'https://ejemplo.com/storage/biblioteca/variantes/silla-thumbnail.jpg',
                'mediana' => 'https://ejemplo.com/storage/biblioteca/variantes/silla-medium.jpg',
                'grande' => 'https://ejemplo.com/storage/biblioteca/variantes/silla-large.jpg',
            ],
            'actualizado_en' => '2026-08-19T10:12:00+00:00',
        ];

        return [
            new ApiEndpoint(
                key: 'productos',
                path: 'productos',
                title: 'Listado de productos',
                description: 'Los productos marcados como visibles, en el orden en que se han colocado '
                    .'en el panel. Lo que no está visible no sale ni conociendo su dirección.',
                parameters: [
                    ['name' => 'pagina', 'description' => 'Número de página', 'example' => '1'],
                    ['name' => 'por_pagina', 'description' => 'Productos por página', 'example' => '24'],
                    ['name' => 'buscar', 'description' => 'Busca en nombre, referencia y resumen', 'example' => 'roble'],
                ],
                example: [
                    'datos' => [$product],
                    'paginacion' => [
                        'pagina' => 1,
                        'por_pagina' => 24,
                        'total' => 37,
                        'ultima_pagina' => 2,
                        'hay_mas' => true,
                    ],
                ],
                notes: [
                    'Los campos vacíos no se envían: si un producto no tiene precio, no aparece `precio`.',
                    '`precio` es siempre lo que se paga hoy. Cuando el producto está rebajado llega además '
                        .'`precio_anterior`, que es el que hay que tachar, y `descuento_porcentaje`. Si no '
                        .'llega `precio_anterior`, no hay oferta.',
                    '`disponibilidad` vale `available`, `low` o `out_of_stock`. Un producto sin stock **sigue '
                        .'saliendo** en el listado: se marca, no se esconde.',
                ],
                position: 90,
            ),
            new ApiEndpoint(
                key: 'producto',
                path: 'productos/{slug}',
                title: 'Un producto',
                description: 'La ficha completa, con la descripción en HTML ya limpio y seguro para insertar.',
                parameters: [
                    ['name' => 'slug', 'description' => 'Dirección del producto', 'example' => ''],
                ],
                example: [
                    'datos' => $product + [
                        'descripcion_html' => '<p>Fabricada en roble macizo…</p>',
                    ],
                ],
                position: 100,
            ),
        ];
    }

    public function manualSections(): array
    {
        return [
            new ManualSection(
                key: 'catalogo',
                title: 'El catálogo de productos',
                icon: 'ri-price-tag-3-line',
                permission: CatalogoPermission::ProductsView->value,
                blocks: [
                    ['type' => 'text', 'content' => 'En «Productos» tienes la lista de todo lo que enseña '
                        .'tu web. Cada producto es una ficha: un nombre, una foto, una descripción y, si '
                        .'quieres, un precio.'],

                    ['type' => 'heading', 'content' => 'Visible u oculto'],
                    ['type' => 'text', 'content' => 'Un producto solo aparece en la web si está marcado '
                        .'como **visible**. Puedes crearlo, dejarlo a medias y marcarlo visible más '
                        .'adelante: mientras tanto no lo ve nadie.'],
                    ['type' => 'tip', 'content' => 'Para retirar algo de la web no hace falta borrarlo. '
                        .'Quítale la marca de visible y desaparece, pero lo conservas por si vuelve.'],

                    ['type' => 'heading', 'content' => 'El precio'],
                    ['type' => 'text', 'content' => 'Se puede dejar vacío. Hay catálogos que prefieren que '
                        .'el cliente pregunte en lugar de enseñar la cifra, y la web sabe qué hacer en los '
                        .'dos casos.'],
                    ['type' => 'text', 'content' => 'Escríbelo como lo dirías: `149,90`. No hace falta el '
                        .'símbolo del euro.'],

                    ['type' => 'heading', 'content' => 'Rebajas'],
                    ['type' => 'text', 'content' => 'Para poner algo de oferta no cambies el precio: deja el '
                        .'de siempre donde está y rellena **precio rebajado**. La web enseñará el precio '
                        .'antiguo tachado y el nuevo al lado.'],
                    ['type' => 'tip', 'content' => 'Cuando se acabe la oferta, borra el precio rebajado y '
                        .'todo vuelve a la normalidad. No tienes que acordarte de cuál era el precio bueno.'],

                    ['type' => 'heading', 'content' => 'Si te quedas sin existencias'],
                    ['type' => 'text', 'content' => 'En **disponibilidad** puedes marcar «pocas unidades» o '
                        .'«sin stock». El producto sigue saliendo en la web: lo que cambia es que aparece '
                        .'avisado, para que quien lo vea no se lleve una sorpresa al preguntar.'],
                    ['type' => 'tip', 'content' => 'Si prefieres que desaparezca del todo, quítale la marca '
                        .'de visible en lugar de marcarlo sin stock.'],

                    ['type' => 'heading', 'content' => 'El orden'],
                    ['type' => 'text', 'content' => 'El número de orden decide en qué posición sale cada '
                        .'producto en la web. Los más bajos salen primero. Si no te importa el orden, '
                        .'déjalos como están y saldrán por nombre.'],

                    ['type' => 'heading', 'content' => 'La foto'],
                    ['type' => 'text', 'content' => 'Se elige de la biblioteca, la misma que usan los '
                        .'artículos. Así una foto subida una vez se puede usar en varios sitios, y el '
                        .'panel no te dejará borrarla mientras algún producto la esté usando.'],
                ],
                position: 65,
            ),
        ];
    }
}

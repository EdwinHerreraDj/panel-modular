<?php

declare(strict_types=1);

namespace Modules\Tienda;

use App\Enums\RoleName;
use App\Models\User;
use App\Modules\BaseModule;
use App\Modules\Values\ContentType;
use App\Modules\Values\DashboardAction;
use App\Modules\Values\DashboardAlert;
use App\Modules\Values\DashboardCard;
use App\Modules\Values\DashboardContribution;
use App\Modules\Values\MenuItem;
use App\Modules\Values\ModulePermission;
use App\Modules\Values\ModuleSetting;
use App\Modules\Values\PublicCount;
use Modules\Tienda\Enums\TiendaPermission;
use Modules\Tienda\Models\Category;
use Modules\Tienda\Models\Product;
use Modules\Tienda\Models\Promotion;
use Modules\Tienda\Support\ApiEndpoints;
use Modules\Tienda\Support\CategoryTree;
use Modules\Tienda\Support\Manual;

/**
 * Tienda: artículos con variantes, categorías en árbol, inventario con
 * historial y promociones programables.
 *
 * Es el hermano mayor del catálogo, no su sustituto. El catálogo enseña
 * productos; la tienda los vende. Por ahora la venta se cierra por WhatsApp:
 * la API sirve a cada artículo su enlace con el mensaje ya escrito. El día que
 * haya carrito, las existencias ya se mueven por una sola puerta
 * (`RegisterStockMovement`) y el pedido solo tendrá que llamarla.
 */
class TiendaModule extends BaseModule
{
    public function key(): string
    {
        return 'tienda';
    }

    public function name(): string
    {
        return 'Tienda';
    }

    public function permissions(): array
    {
        $roles = [
            TiendaPermission::View->value => [
                RoleName::Editor, RoleName::Reviewer, RoleName::Author, RoleName::Reader,
            ],
            TiendaPermission::ProductsManage->value => [RoleName::Editor],
            TiendaPermission::ProductsDelete->value => [RoleName::Editor],
            TiendaPermission::CategoriesManage->value => [RoleName::Editor],
            TiendaPermission::StockManage->value => [RoleName::Editor],
            TiendaPermission::PromotionsManage->value => [RoleName::Editor],
        ];

        return array_map(
            fn (TiendaPermission $permission): ModulePermission => new ModulePermission(
                value: $permission->value,
                label: $permission->label(),
                group: 'Tienda',
                roles: $roles[$permission->value],
            ),
            TiendaPermission::cases(),
        );
    }

    public function menu(): array
    {
        $view = TiendaPermission::View->value;

        return [
            new MenuItem(
                label: 'Artículos',
                route: 'tienda.products.index',
                icon: 'box',
                activePattern: 'tienda.products.*',
                permission: $view,
                position: 10,
            ),
            new MenuItem(
                label: 'Categorías',
                route: 'tienda.categories.index',
                icon: 'folder',
                activePattern: 'tienda.categories.*',
                permission: $view,
                position: 20,
            ),
            new MenuItem(
                label: 'Inventario',
                route: 'tienda.stock.index',
                icon: 'stock',
                activePattern: 'tienda.stock.*',
                permission: $view,
                position: 30,
            ),
            new MenuItem(
                label: 'Promociones',
                route: 'tienda.promotions.index',
                icon: 'sale',
                activePattern: 'tienda.promotions.*',
                permission: $view,
                position: 40,
            ),
        ];
    }

    public function contentTypes(): array
    {
        return [
            new ContentType(
                model: Product::class,
                alias: 'tienda.product',
                singular: 'Artículo de la tienda',
                plural: 'Artículos de la tienda',
                editUrl: fn (int|string $id): string => route('tienda.products.edit', $id),
            ),
            new ContentType(
                model: Category::class,
                alias: 'tienda.category',
                singular: 'Categoría de la tienda',
                plural: 'Categorías de la tienda',
                editUrl: fn (int|string $id): string => route('tienda.categories.edit', $id),
            ),
            new ContentType(
                model: Promotion::class,
                alias: 'tienda.promotion',
                singular: 'Promoción',
                plural: 'Promociones',
                editUrl: fn (int|string $id): string => route('tienda.promotions.edit', $id),
            ),
        ];
    }

    public function settings(): array
    {
        return [
            new ModuleSetting(
                key: 'whatsapp_number',
                label: 'Número de WhatsApp',
                rules: ['nullable', 'string', 'regex:/^\+?[0-9 ]{8,20}$/'],
                default: '',
                hint: 'Con el prefijo del país, por ejemplo +34 600 123 456. Vacío, la web no enseña el botón.',
            ),
            new ModuleSetting(
                key: 'whatsapp_message',
                label: 'Mensaje de WhatsApp',
                rules: ['required', 'string', 'max:500'],
                default: 'Hola, me interesa «:articulo»:variante. ¿Me podéis dar más información? :enlace',
                hint: 'Lo que aparece ya escrito al pulsar el botón. Puedes usar :articulo, :variante, '
                    .':referencia, :precio y :enlace.',
            ),
            new ModuleSetting(
                key: 'product_url',
                label: 'Dirección de la ficha en la web',
                rules: ['nullable', 'string', 'max:255', 'regex:/^https?:\/\/\S+:slug\S*$/'],
                default: '',
                hint: 'Con :slug donde va el artículo, por ejemplo https://mitienda.com/articulo/:slug. '
                    .'Se usa para el enlace del mensaje.',
            ),
            new ModuleSetting(
                key: 'currency',
                label: 'Moneda',
                rules: ['required', 'string', 'max:5'],
                default: '€',
                hint: 'El símbolo que acompaña a los precios.',
            ),
            new ModuleSetting(
                key: 'low_stock_threshold',
                label: 'Aviso de pocas unidades',
                type: ModuleSetting::NUMBER,
                rules: ['required', 'integer', 'min:0', 'max:10000'],
                default: 3,
                hint: 'Con estas unidades o menos, el artículo sale como «quedan pocas». Cada artículo puede tener el suyo.',
            ),
            new ModuleSetting(
                key: 'show_stock_quantity',
                label: 'Enseñar en la web cuántas unidades quedan',
                type: ModuleSetting::BOOLEAN,
                default: false,
                hint: 'Si no, la web solo sabe si hay, si quedan pocas o si está agotado.',
            ),
        ];
    }

    public function dashboard(User $user): DashboardContribution
    {
        if ($user->cannot(TiendaPermission::View->value)) {
            return DashboardContribution::empty();
        }

        $total = Product::query()->count();
        $visible = Product::query()->public()->count();
        $out = Product::query()->public()->outOfStock()->count();
        $low = Product::query()->public()->lowStock()->count();

        $alerts = [];

        if ($out > 0) {
            $alerts[] = new DashboardAlert(
                key: 'tienda.agotados',
                text: $out === 1
                    ? 'Hay 1 artículo agotado a la vista en la tienda.'
                    : "Hay {$out} artículos agotados a la vista en la tienda.",
                tone: 'danger',
                actionLabel: 'Ver inventario',
                actionHref: route('tienda.stock.index', ['extra' => 'out_of_stock']),
                position: 40,
            );
        }

        if ($low > 0) {
            $alerts[] = new DashboardAlert(
                key: 'tienda.pocas-unidades',
                text: $low === 1
                    ? 'A 1 artículo de la tienda le quedan pocas unidades.'
                    : "A {$low} artículos de la tienda les quedan pocas unidades.",
                tone: 'warning',
                actionLabel: 'Ver inventario',
                actionHref: route('tienda.stock.index', ['extra' => 'low']),
                position: 45,
            );
        }

        return new DashboardContribution(
            cards: [
                new DashboardCard(
                    key: 'tienda.total',
                    label: 'Artículos en la tienda',
                    count: $total,
                    icon: 'box',
                    tone: 'primary',
                    route: 'tienda.products.index',
                    position: 70,
                ),
                new DashboardCard(
                    key: 'tienda.visible',
                    label: 'A la venta en la web',
                    count: $visible,
                    icon: 'check',
                    tone: 'success',
                    route: 'tienda.products.index',
                    query: ['status' => 'visible'],
                    hint: $total > 0 && $visible === 0 ? 'Ninguno se está enseñando todavía' : null,
                    position: 80,
                ),
            ],
            alerts: $alerts,
            actions: $user->can(TiendaPermission::ProductsManage->value) ? [
                new DashboardAction(
                    label: 'Nuevo artículo',
                    href: route('tienda.products.create'),
                    icon: 'box',
                    position: 60,
                ),
            ] : [],
        );
    }

    public function publicCounts(): array
    {
        return [
            new PublicCount(
                key: 'articulos_tienda_visibles',
                label: 'Artículos de la tienda que sirve ahora mismo',
                count: Product::query()->public()->count(),
            ),
        ];
    }

    public function sitemapSections(): array
    {
        $tree = CategoryTree::current();

        return [
            'tienda_articulos' => Product::query()
                ->public()
                ->orderBy('position')
                ->get(['slug', 'updated_at'])
                ->map(fn (Product $product): array => [
                    'slug' => $product->slug,
                    'modificado_en' => $product->updated_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'tienda_categorias' => array_values(array_map(
                fn (array $option): array => [
                    'slug' => (string) $tree->find((int) $option['value'])?->slug,
                    'modificado_en' => $tree->find((int) $option['value'])?->updated_at?->toIso8601String(),
                ],
                array_filter($tree->options(), fn (array $option): bool => $tree->isPublic((int) $option['value'])),
            )),
        ];
    }

    public function apiEndpoints(): array
    {
        return ApiEndpoints::all();
    }

    public function manualSections(): array
    {
        return Manual::sections();
    }
}

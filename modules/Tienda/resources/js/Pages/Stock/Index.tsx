import { Link } from '@inertiajs/react'
import { useState } from 'react'
import DashboardLayout from '@/Layouts/DashboardLayout'
import Card from '@/Components/UI/Card'
import DataTable, { type Column } from '@/Components/UI/DataTable'
import EmptyState from '@/Components/UI/EmptyState'
import LinkButton from '@/Components/UI/LinkButton'
import Pagination from '@/Components/UI/Pagination'
import SearchInput from '@/Components/UI/SearchInput'
import SelectField from '@/Components/UI/SelectField'
import StatusBadge from '@/Components/UI/StatusBadge'
import { useResourceFilters } from '@/hooks/useResourceFilters'
import type { Paginated, ResourceFilterValues, SelectOption } from '@/types'
import StockMovementModal, { type StockTarget } from '../../Components/StockMovementModal'
import type { StockProductRow } from '../../types'

interface Props {
    products: Paginated<StockProductRow>
    filters: ResourceFilterValues & { category: string }
    categoryOptions: SelectOption[]
    typeOptions: SelectOption[]
    canManage: boolean
    counts: { low: number; out: number }
}

export default function StockIndex({
    products,
    filters: initial,
    categoryOptions,
    typeOptions,
    canManage,
    counts,
}: Props) {
    const { filters, loading, update, updateCustom, toggleSort, reset, hasActiveFilters } = useResourceFilters(
        'tienda.stock.index',
        {
            search: initial.search,
            status: '',
            extra: initial.extra ?? '',
            trashed: false,
            sort: initial.sort,
            direction: initial.direction,
            custom: { category: initial.category },
        },
    )

    const [target, setTarget] = useState<StockTarget | null>(null)

    function moveButton(next: StockTarget) {
        if (!canManage) {
            return null
        }

        return (
            <button
                type="button"
                className="btn btn-outline-primary whitespace-nowrap rounded-lg px-3 py-1 text-sm"
                onClick={() => setTarget(next)}
            >
                Registrar movimiento
            </button>
        )
    }

    const columns: Column<StockProductRow>[] = [
        {
            key: 'name',
            header: 'Artículo',
            align: 'start',
            sortable: true,
            cell: (product) => (
                <div className="flex items-center gap-3">
                    {product.thumbnail_url ? (
                        <img
                            src={product.thumbnail_url}
                            alt=""
                            className="h-10 w-10 shrink-0 rounded-lg object-cover"
                        />
                    ) : (
                        <span
                            className="h-10 w-10 shrink-0 rounded-lg bg-neutral-200 dark:bg-neutral-600"
                            aria-hidden="true"
                        />
                    )}
                    <span className="min-w-0">
                        <span className="block font-medium text-neutral-900 dark:text-white">{product.name}</span>
                        <span className="text-secondary-light block text-xs">
                            {product.sku ?? 'Sin referencia'}
                            {!product.is_visible && ' · oculto en la web'}
                        </span>
                    </span>
                </div>
            ),
        },
        {
            key: 'stock',
            header: 'Unidades',
            sortable: true,
            cell: (product) =>
                product.variants.length === 0 ? (
                    <span className="flex items-center gap-2 whitespace-nowrap">
                        <span className="text-base font-semibold tabular-nums">{product.stock}</span>
                        <StatusBadge tone={product.status_tone} label={product.status_label} />
                    </span>
                ) : (
                    <ul className="mb-0 flex min-w-[320px] flex-col gap-2">
                        {product.variants.map((variant) => (
                            <li
                                key={variant.id}
                                className={`flex items-center justify-between gap-3 ${variant.is_active ? '' : 'opacity-50'}`}
                            >
                                <span className="flex items-center gap-2">
                                    <span className="w-28 truncate">{variant.label}</span>
                                    <span className="w-10 text-end font-semibold tabular-nums">{variant.stock}</span>
                                    {variant.is_active ? (
                                        <StatusBadge tone={variant.status_tone} label={variant.status_label} />
                                    ) : (
                                        <StatusBadge tone="neutral" label="Desactivada" />
                                    )}
                                </span>
                                {moveButton({
                                    productId: product.id,
                                    variantId: variant.id,
                                    label: `${product.name} (${variant.label})`,
                                    stock: variant.stock,
                                })}
                            </li>
                        ))}
                        <li className="text-secondary-light text-xs">Total: {product.stock} unidades</li>
                    </ul>
                ),
        },
        {
            key: 'actions',
            header: '',
            cell: (product) => (
                <div className="flex items-center justify-end gap-2">
                    {product.variants.length === 0 &&
                        moveButton({
                            productId: product.id,
                            variantId: null,
                            label: product.name,
                            stock: product.stock,
                        })}
                    <Link
                        href={route('tienda.stock.history', { product: product.id })}
                        className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-neutral-600 hover:bg-primary-50 hover:text-primary-600 dark:text-neutral-200"
                        aria-label={`Historial de ${product.name}`}
                        title="Historial"
                    >
                        <i className="ri-history-line" aria-hidden="true" />
                    </Link>
                </div>
            ),
        },
    ]

    return (
        <DashboardLayout title="Inventario" breadcrumb="Tienda">
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h5 className="mb-1 text-xl font-semibold">Inventario</h5>
                    <p className="text-secondary-light mb-0 text-sm">
                        {counts.out === 0 && counts.low === 0 ? (
                            'Todo con existencias.'
                        ) : (
                            <>
                                {counts.out > 0 && (
                                    <span className="text-danger-600 dark:text-danger-400">
                                        {counts.out === 1 ? '1 agotado. ' : `${counts.out} agotados. `}
                                    </span>
                                )}
                                {counts.low > 0 && (
                                    <span className="text-warning-600 dark:text-warning-400">
                                        {counts.low === 1
                                            ? '1 con pocas unidades.'
                                            : `${counts.low} con pocas unidades.`}
                                    </span>
                                )}
                            </>
                        )}
                    </p>
                </div>

                <LinkButton href={route('tienda.stock.history')} variant="outline">
                    Ver todos los movimientos
                </LinkButton>
            </div>

            <Card>
                <div className="mb-4 flex flex-wrap items-end gap-3">
                    <div className="min-w-56 flex-1">
                        <SearchInput
                            value={filters.search}
                            onChange={(value) => update({ search: value })}
                            placeholder="Buscar por nombre o referencia"
                        />
                    </div>

                    <SelectField
                        label="Categoría"
                        value={filters.custom?.category ?? ''}
                        onChange={(e) => updateCustom('category', e.target.value)}
                        options={categoryOptions}
                    />

                    <SelectField
                        label="Mostrar"
                        value={filters.extra ?? ''}
                        onChange={(e) => update({ extra: e.target.value })}
                        options={[
                            { value: '', label: 'Todos' },
                            { value: 'low', label: 'Quedan pocas unidades' },
                            { value: 'out_of_stock', label: 'Agotados' },
                        ]}
                    />

                    {hasActiveFilters && (
                        <button type="button" className="btn btn-outline-primary" onClick={reset}>
                            Quitar filtros
                        </button>
                    )}
                </div>

                <DataTable
                    columns={columns}
                    rows={products.data}
                    rowKey={(product) => product.id}
                    sort={{ field: filters.sort, direction: filters.direction }}
                    onSort={toggleSort}
                    loading={loading}
                    empty={
                        <EmptyState
                            title={hasActiveFilters ? 'Nada coincide' : 'No hay artículos con control de existencias'}
                            description={
                                hasActiveFilters
                                    ? 'Prueba a quitar los filtros.'
                                    : 'Aquí salen los artículos que tienen marcado «Controlar existencias».'
                            }
                        />
                    }
                />

                <Pagination
                    links={products.links}
                    from={products.from}
                    to={products.to}
                    total={products.total}
                    itemsLabel="artículos"
                />
            </Card>

            <StockMovementModal target={target} typeOptions={typeOptions} onClose={() => setTarget(null)} />
        </DashboardLayout>
    )
}

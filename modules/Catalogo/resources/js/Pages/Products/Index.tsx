import { router } from '@inertiajs/react'
import { useState } from 'react'
import IconPlus from '~icons/ic/baseline-add'
import DashboardLayout from '@/Layouts/DashboardLayout'
import Card from '@/Components/UI/Card'
import DataTable, { type Column } from '@/Components/UI/DataTable'
import SearchInput from '@/Components/UI/SearchInput'
import SelectField from '@/Components/UI/SelectField'
import StatusBadge from '@/Components/UI/StatusBadge'
import Pagination from '@/Components/UI/Pagination'
import EmptyState from '@/Components/UI/EmptyState'
import LinkButton from '@/Components/UI/LinkButton'
import ConfirmDialog from '@/Components/UI/ConfirmDialog'
import { ActionButton, ActionLink, RowActions } from '@/Components/UI/RowActions'
import { useResourceFilters } from '@/hooks/useResourceFilters'
import { usePermissions } from '@/hooks/usePermissions'
import type { Paginated, ResourceFilterValues } from '@/types'
import type { ProductListItem } from '../../types'

interface Props {
    products: Paginated<ProductListItem>
    filters: ResourceFilterValues
    availabilityOptions: { value: string; label: string }[]
    counts: { total: number; visible: number; trashed: number; outOfStock: number }
}

export default function ProductsIndex({
    products,
    filters: initial,
    availabilityOptions,
    counts,
}: Props) {
    const { can } = usePermissions()
    const { filters, loading, update, toggleSort, reset, hasActiveFilters } = useResourceFilters(
        'products.index',
        {
            search: initial.search,
            status: initial.status,
            extra: initial.extra ?? '',
            trashed: initial.trashed,
            sort: initial.sort,
            direction: initial.direction,
        },
    )

    const [pendingDelete, setPendingDelete] = useState<ProductListItem | null>(null)
    const [processing, setProcessing] = useState(false)

    function confirmDelete() {
        if (!pendingDelete) {
            return
        }

        router.delete(route('products.destroy', pendingDelete.id), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false)
                setPendingDelete(null)
            },
        })
    }

    const columns: Column<ProductListItem>[] = [
        {
            key: 'name',
            header: 'Producto',
            align: 'start',
            sortable: true,
            cell: (product: ProductListItem) => (
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
                        <span className="block truncate font-medium text-neutral-900 dark:text-white">
                            {product.name}
                        </span>
                        {product.summary && (
                            <span className="text-secondary-light block truncate text-xs">
                                {product.summary}
                            </span>
                        )}
                    </span>
                </div>
            ),
        },
        {
            key: 'reference',
            header: 'Referencia',
            sortable: true,
            cell: (product: ProductListItem) => product.reference ?? <span className="text-secondary-light">—</span>,
        },
        {
            key: 'price',
            header: 'Precio',
            sortable: true,
            cell: (product: ProductListItem) => {
                if (!product.price_label) {
                    return <span className="text-secondary-light">A consultar</span>
                }

                return (
                    <span className="whitespace-nowrap">
                        {product.previous_price_label && (
                            <s className="text-secondary-light me-1">{product.previous_price_label}</s>
                        )}
                        <span className={product.previous_price_label ? 'font-semibold text-success-600' : ''}>
                            {product.price_label}
                        </span>
                        {product.discount !== null && (
                            <span className="text-secondary-light ms-1 text-xs">−{product.discount} %</span>
                        )}
                    </span>
                )
            },
        },
        {
            key: 'availability',
            header: 'Existencias',
            cell: (product: ProductListItem) => (
                <StatusBadge tone={product.availability_tone} label={product.availability_label} />
            ),
        },
        {
            key: 'is_visible',
            header: 'En la web',
            cell: (product: ProductListItem) =>
                product.is_visible ? (
                    <StatusBadge tone="success" label="Visible" />
                ) : (
                    <StatusBadge tone="neutral" label="Oculto" />
                ),
        },
        {
            key: 'position',
            header: 'Orden',
            sortable: true,
            cell: (product: ProductListItem) => product.position,
        },
        {
            key: 'actions',
            header: '',
            cell: (product: ProductListItem) => (
                <RowActions>
                    {product.deleted_at ? (
                        product.can.restore && (
                            <ActionButton
                                onClick={() =>
                                    router.post(
                                        route('products.restore', product.id),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                                label={`Restaurar ${product.name}`}
                                icon="ri-arrow-go-back-line"
                            />
                        )
                    ) : (
                        <>
                            {product.can.update && (
                                <ActionLink
                                    href={route('products.edit', product.id)}
                                    label={`Editar ${product.name}`}
                                    icon="ri-edit-line"
                                />
                            )}
                            {product.can.delete && (
                                <ActionButton
                                    onClick={() => setPendingDelete(product)}
                                    label={`Enviar ${product.name} a la papelera`}
                                    icon="ri-delete-bin-line"
                                    tone="danger"
                                />
                            )}
                        </>
                    )}
                </RowActions>
            ),
        },
    ]

    return (
        <DashboardLayout title="Productos" breadcrumb="Productos">
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h5 className="mb-1 text-xl font-semibold">Productos</h5>
                    <p className="text-secondary-light mb-0 text-sm">
                        {counts.total === 0
                            ? 'Todavía no has creado ninguno.'
                            : `${counts.visible} de ${counts.total} se están enseñando en la web.`}
                        {counts.outOfStock > 0 && (
                            <>
                                {' '}
                                <span className="text-warning-600 dark:text-warning-400">
                                    {counts.outOfStock === 1
                                        ? '1 está marcado como sin stock.'
                                        : `${counts.outOfStock} están marcados como sin stock.`}
                                </span>
                            </>
                        )}
                    </p>
                </div>

                {can('catalogo.products.manage') && (
                    <LinkButton href={route('products.create')} icon={<IconPlus />}>
                        Nuevo producto
                    </LinkButton>
                )}
            </div>

            <Card>
                <div className="mb-4 flex flex-wrap items-end gap-3">
                    <div className="min-w-56 flex-1">
                        <SearchInput
                            value={filters.search}
                            onChange={(value) => update({ search: value })}
                            placeholder="Buscar por nombre, referencia o resumen"
                        />
                    </div>

                    <SelectField
                        label="En la web"
                        value={filters.status}
                        onChange={(e) => update({ status: e.target.value })}
                        options={[
                            { value: '', label: 'Todos' },
                            { value: 'visible', label: 'Visibles' },
                            { value: 'hidden', label: 'Ocultos' },
                        ]}
                    />

                    <SelectField
                        label="Existencias"
                        value={filters.extra ?? ''}
                        onChange={(e) => update({ extra: e.target.value })}
                        options={availabilityOptions}
                    />

                    {counts.trashed > 0 && (
                        <SelectField
                            label="Papelera"
                            value={filters.trashed ? '1' : ''}
                            onChange={(e) => update({ trashed: e.target.value === '1' })}
                            options={[
                                { value: '', label: 'Activos' },
                                { value: '1', label: `Papelera (${counts.trashed})` },
                            ]}
                        />
                    )}

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
                            title={hasActiveFilters ? 'Ningún producto coincide' : 'Todavía no hay productos'}
                            description={
                                hasActiveFilters
                                    ? 'Prueba a quitar los filtros.'
                                    : 'Crea el primero y aparecerá en tu web en cuanto lo marques como visible.'
                            }
                        />
                    }
                />

                <Pagination
                    links={products.links}
                    from={products.from}
                    to={products.to}
                    total={products.total}
                    itemsLabel="productos"
                />
            </Card>

            <ConfirmDialog
                open={pendingDelete !== null}
                title="Enviar a la papelera"
                message={
                    pendingDelete
                        ? `«${pendingDelete.name}» dejará de verse en la web. Podrás recuperarlo desde la papelera.`
                        : ''
                }
                confirmLabel="Enviar a la papelera"
                processing={processing}
                tone="danger"
                onConfirm={confirmDelete}
                onCancel={() => setPendingDelete(null)}
            />
        </DashboardLayout>
    )
}

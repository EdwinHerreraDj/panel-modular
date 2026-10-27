import { router } from '@inertiajs/react'
import DashboardLayout from '@/Layouts/DashboardLayout'
import Card from '@/Components/UI/Card'
import DataTable, { type Column } from '@/Components/UI/DataTable'
import EmptyState from '@/Components/UI/EmptyState'
import LinkButton from '@/Components/UI/LinkButton'
import Pagination from '@/Components/UI/Pagination'
import SelectField from '@/Components/UI/SelectField'
import type { Paginated, ResourceFilterValues, SelectOption } from '@/types'
import type { StockMovementRow } from '../../types'

interface Props {
    movements: Paginated<StockMovementRow>
    filters: ResourceFilterValues & { product: string }
    product: { id: number; name: string } | null
    typeOptions: SelectOption[]
}

/**
 * El libro de existencias: cada entrada y salida, quién y por qué.
 */
export default function StockHistory({ movements, filters, product, typeOptions }: Props) {
    function filterByType(type: string) {
        router.get(
            route('tienda.stock.history'),
            {
                ...(product ? { product: product.id } : {}),
                ...(type ? { extra: type } : {}),
            },
            { preserveState: true, preserveScroll: true, replace: true },
        )
    }

    const columns: Column<StockMovementRow>[] = [
        {
            key: 'date',
            header: 'Fecha',
            align: 'start',
            cell: (movement) => <span className="whitespace-nowrap">{movement.date}</span>,
        },
        {
            key: 'product',
            header: 'Artículo',
            align: 'start',
            cell: (movement) => (
                <span>
                    <span className="font-medium text-neutral-900 dark:text-white">{movement.product_name}</span>
                    {movement.variant_label && (
                        <span className="text-secondary-light ms-1 text-sm">({movement.variant_label})</span>
                    )}
                </span>
            ),
        },
        {
            key: 'type',
            header: 'Movimiento',
            cell: (movement) => movement.type_label,
        },
        {
            key: 'quantity',
            header: 'Cantidad',
            cell: (movement) => (
                <span
                    className={`font-semibold tabular-nums ${
                        movement.quantity > 0 ? 'text-success-600' : movement.quantity < 0 ? 'text-danger-600' : ''
                    }`}
                >
                    {movement.quantity > 0 ? `+${movement.quantity}` : movement.quantity}
                </span>
            ),
        },
        {
            key: 'stock_after',
            header: 'Quedan',
            cell: (movement) => <span className="tabular-nums">{movement.stock_after}</span>,
        },
        {
            key: 'note',
            header: 'Nota',
            align: 'start',
            cell: (movement) => movement.note ?? <span className="text-secondary-light">—</span>,
        },
        {
            key: 'user',
            header: 'Quién',
            cell: (movement) => movement.user_name ?? <span className="text-secondary-light">—</span>,
        },
    ]

    return (
        <DashboardLayout title="Movimientos de stock" breadcrumb="Tienda">
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h5 className="mb-1 text-xl font-semibold">
                        {product ? `Movimientos de «${product.name}»` : 'Movimientos de stock'}
                    </h5>
                    <p className="text-secondary-light mb-0 text-sm">
                        Cada cambio de existencias, con quién lo hizo. No se puede borrar: un error se corrige con otro
                        movimiento.
                    </p>
                </div>

                <div className="flex flex-wrap gap-3">
                    {product && (
                        <LinkButton href={route('tienda.stock.history')} variant="outline">
                            Ver todos
                        </LinkButton>
                    )}
                    <LinkButton href={route('tienda.stock.index')} variant="outline">
                        Volver al inventario
                    </LinkButton>
                </div>
            </div>

            <Card>
                <div className="mb-4 flex flex-wrap items-end gap-3">
                    <SelectField
                        label="Tipo de movimiento"
                        value={filters.extra ?? ''}
                        onChange={(e) => filterByType(e.target.value)}
                        options={typeOptions}
                    />
                </div>

                <DataTable
                    columns={columns}
                    rows={movements.data}
                    rowKey={(movement) => movement.id}
                    empty={<EmptyState title="Sin movimientos" description="Todavía no se ha registrado ninguno." />}
                />

                <Pagination
                    links={movements.links}
                    from={movements.from}
                    to={movements.to}
                    total={movements.total}
                    itemsLabel="movimientos"
                />
            </Card>
        </DashboardLayout>
    )
}

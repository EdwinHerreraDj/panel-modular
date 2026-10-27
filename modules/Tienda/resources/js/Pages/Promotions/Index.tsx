import { router } from '@inertiajs/react'
import { useState } from 'react'
import IconPlus from '~icons/ic/baseline-add'
import DashboardLayout from '@/Layouts/DashboardLayout'
import Card from '@/Components/UI/Card'
import DataTable, { type Column } from '@/Components/UI/DataTable'
import EmptyState from '@/Components/UI/EmptyState'
import LinkButton from '@/Components/UI/LinkButton'
import Pagination from '@/Components/UI/Pagination'
import StatusBadge from '@/Components/UI/StatusBadge'
import ConfirmDialog from '@/Components/UI/ConfirmDialog'
import { ActionButton, ActionLink, RowActions } from '@/Components/UI/RowActions'
import { usePermissions } from '@/hooks/usePermissions'
import type { Paginated } from '@/types'
import type { PromotionListItem } from '../../types'

interface Props {
    promotions: Paginated<PromotionListItem>
}

export default function PromotionsIndex({ promotions }: Props) {
    const { can } = usePermissions()
    const [pendingDelete, setPendingDelete] = useState<PromotionListItem | null>(null)
    const [processing, setProcessing] = useState(false)

    function confirmDelete() {
        if (!pendingDelete) {
            return
        }

        router.delete(route('tienda.promotions.destroy', pendingDelete.id), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false)
                setPendingDelete(null)
            },
        })
    }

    const columns: Column<PromotionListItem>[] = [
        {
            key: 'name',
            header: 'Promoción',
            align: 'start',
            cell: (promotion) => <span className="font-medium text-neutral-900 dark:text-white">{promotion.name}</span>,
        },
        {
            key: 'value',
            header: 'Descuento',
            cell: (promotion) => <span className="font-semibold text-success-600">{promotion.value_label}</span>,
        },
        {
            key: 'scope',
            header: 'Se aplica a',
            cell: (promotion) => promotion.scope_label,
        },
        {
            key: 'dates',
            header: 'Fechas',
            cell: (promotion) => (
                <span className="whitespace-nowrap text-sm">
                    {promotion.starts_at ?? 'Ya'} → {promotion.ends_at ?? 'Sin fin'}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Estado',
            cell: (promotion) => <StatusBadge tone={promotion.status.tone} label={promotion.status.label} />,
        },
        {
            key: 'actions',
            header: '',
            cell: (promotion) => (
                <RowActions>
                    {promotion.can.update && (
                        <ActionLink
                            href={route('tienda.promotions.edit', promotion.id)}
                            label={`Editar ${promotion.name}`}
                            icon="ri-edit-line"
                        />
                    )}
                    {promotion.can.delete && (
                        <ActionButton
                            onClick={() => setPendingDelete(promotion)}
                            label={`Eliminar ${promotion.name}`}
                            icon="ri-delete-bin-line"
                            tone="danger"
                        />
                    )}
                </RowActions>
            ),
        },
    ]

    return (
        <DashboardLayout title="Promociones" breadcrumb="Tienda">
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h5 className="mb-1 text-xl font-semibold">Promociones</h5>
                    <p className="text-secondary-light mb-0 text-sm">
                        Descuentos con fecha. No cambian el precio de los artículos: se calculan encima.
                    </p>
                </div>

                {can('tienda.promotions.manage') && (
                    <LinkButton href={route('tienda.promotions.create')} icon={<IconPlus />}>
                        Nueva promoción
                    </LinkButton>
                )}
            </div>

            <Card>
                <DataTable
                    columns={columns}
                    rows={promotions.data}
                    rowKey={(promotion) => promotion.id}
                    empty={
                        <EmptyState
                            title="Todavía no hay promociones"
                            description="Crea una para rebajar toda la tienda, unas categorías o unos artículos concretos."
                        />
                    }
                />

                <Pagination
                    links={promotions.links}
                    from={promotions.from}
                    to={promotions.to}
                    total={promotions.total}
                    itemsLabel="promociones"
                />
            </Card>

            <ConfirmDialog
                open={pendingDelete !== null}
                title="Eliminar promoción"
                message={
                    pendingDelete
                        ? `«${pendingDelete.name}» se borrará y los precios de la web volverán a ser los de siempre. Si solo quieres pausarla, desactívala.`
                        : ''
                }
                confirmLabel="Eliminar"
                processing={processing}
                tone="danger"
                onConfirm={confirmDelete}
                onCancel={() => setPendingDelete(null)}
            />
        </DashboardLayout>
    )
}

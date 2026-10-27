import { Link, router } from '@inertiajs/react'
import { useState } from 'react'
import IconPlus from '~icons/ic/baseline-add'
import DashboardLayout from '@/Layouts/DashboardLayout'
import Card from '@/Components/UI/Card'
import DataTable, { type Column } from '@/Components/UI/DataTable'
import EmptyState from '@/Components/UI/EmptyState'
import LinkButton from '@/Components/UI/LinkButton'
import StatusBadge from '@/Components/UI/StatusBadge'
import ConfirmDialog from '@/Components/UI/ConfirmDialog'
import { ActionButton, ActionLink, RowActions } from '@/Components/UI/RowActions'
import { usePermissions } from '@/hooks/usePermissions'
import type { ShopCategoryRow } from '../../types'

interface TrashedCategory {
    id: number
    name: string
    deleted_at: string | null
    can_restore: boolean
}

interface Props {
    categories: ShopCategoryRow[]
    trashed: TrashedCategory[]
}

/** Niveles máximos del árbol; coincide con CategoryTree::MAX_DEPTH. */
const MAX_DEPTH = 3

export default function ShopCategoriesIndex({ categories, trashed }: Props) {
    const { can } = usePermissions()
    const [pendingDelete, setPendingDelete] = useState<ShopCategoryRow | null>(null)
    const [processing, setProcessing] = useState(false)
    const canManage = can('tienda.categories.manage')

    function confirmDelete() {
        if (!pendingDelete) {
            return
        }

        router.delete(route('tienda.categories.destroy', pendingDelete.id), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false)
                setPendingDelete(null)
            },
        })
    }

    const columns: Column<ShopCategoryRow>[] = [
        {
            key: 'name',
            header: 'Categoría',
            align: 'start',
            cell: (category) => (
                <div className="flex items-center gap-3" style={{ paddingInlineStart: `${category.depth * 28}px` }}>
                    {category.depth > 0 && (
                        <i className="ri-corner-down-right-line text-neutral-400" aria-hidden="true" />
                    )}
                    {category.thumbnail_url ? (
                        <img src={category.thumbnail_url} alt="" className="h-9 w-9 shrink-0 rounded-lg object-cover" />
                    ) : (
                        <span
                            className="h-9 w-9 shrink-0 rounded-lg bg-neutral-200 dark:bg-neutral-600"
                            aria-hidden="true"
                        />
                    )}
                    <span className="font-medium text-neutral-900 dark:text-white">{category.name}</span>
                </div>
            ),
        },
        {
            key: 'products_count',
            header: 'Artículos',
            cell: (category) => (
                <Link
                    href={route('tienda.products.index', {
                        category: category.id,
                    })}
                    className="text-primary-600 hover:underline"
                >
                    {category.products_count}
                </Link>
            ),
        },
        {
            key: 'is_visible',
            header: 'En la web',
            cell: (category) =>
                category.is_public ? (
                    <StatusBadge tone="success" label="Visible" />
                ) : category.is_visible ? (
                    <StatusBadge tone="warning" label="Oculta por la de arriba" />
                ) : (
                    <StatusBadge tone="neutral" label="Oculta" />
                ),
        },
        {
            key: 'position',
            header: 'Orden',
            cell: (category) => category.position,
        },
        {
            key: 'actions',
            header: '',
            cell: (category) => (
                <RowActions>
                    {canManage && category.depth + 1 < MAX_DEPTH && (
                        <ActionLink
                            href={route('tienda.categories.create', {
                                parent: category.id,
                            })}
                            label={`Añadir una subcategoría a ${category.name}`}
                            icon="ri-add-line"
                        />
                    )}
                    {category.can.update && (
                        <ActionLink
                            href={route('tienda.categories.edit', category.id)}
                            label={`Editar ${category.name}`}
                            icon="ri-edit-line"
                        />
                    )}
                    {category.can.delete && (
                        <ActionButton
                            onClick={() => setPendingDelete(category)}
                            label={`Enviar ${category.name} a la papelera`}
                            icon="ri-delete-bin-line"
                            tone="danger"
                        />
                    )}
                </RowActions>
            ),
        },
    ]

    return (
        <DashboardLayout title="Categorías de la tienda" breadcrumb="Tienda">
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h5 className="mb-1 text-xl font-semibold">Categorías</h5>
                    <p className="text-secondary-light mb-0 text-sm">
                        El menú de la tienda. Pueden tener subcategorías hasta tres niveles.
                    </p>
                </div>

                {canManage && (
                    <LinkButton href={route('tienda.categories.create')} icon={<IconPlus />}>
                        Nueva categoría
                    </LinkButton>
                )}
            </div>

            <Card>
                <DataTable
                    columns={columns}
                    rows={categories}
                    rowKey={(category) => category.id}
                    empty={
                        <EmptyState
                            title="Todavía no hay categorías"
                            description="Crea las principales y después sus subcategorías."
                        />
                    }
                />
            </Card>

            {trashed.length > 0 && (
                <div className="mt-6">
                    <Card header={<h6 className="mb-0 text-base font-semibold">En la papelera</h6>}>
                        <ul className="mb-0 flex flex-col gap-2">
                            {trashed.map((category) => (
                                <li key={category.id} className="flex items-center justify-between gap-3">
                                    <span>{category.name}</span>
                                    {category.can_restore && (
                                        <button
                                            type="button"
                                            className="btn btn-outline-primary rounded-lg px-3 py-1 text-sm"
                                            onClick={() =>
                                                router.post(
                                                    route('tienda.categories.restore', category.id),
                                                    {},
                                                    {
                                                        preserveScroll: true,
                                                    },
                                                )
                                            }
                                        >
                                            Restaurar
                                        </button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </Card>
                </div>
            )}

            <ConfirmDialog
                open={pendingDelete !== null}
                title="Enviar a la papelera"
                message={
                    pendingDelete
                        ? pendingDelete.children_count > 0
                            ? `«${pendingDelete.name}» tiene subcategorías. Muévelas o bórralas antes.`
                            : `«${pendingDelete.name}» dejará de verse en la tienda. Sus artículos no se borran.`
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

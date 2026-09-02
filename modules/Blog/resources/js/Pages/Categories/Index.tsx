import { router } from '@inertiajs/react'
import { useState } from 'react'
import IconPlus from '~icons/ic/baseline-add'
import DashboardLayout from '@/Layouts/DashboardLayout'
import Card from '@/Components/UI/Card'
import DataTable, { type Column } from '@/Components/UI/DataTable'
import SearchInput from '@/Components/UI/SearchInput'
import StatusBadge from '@/Components/UI/StatusBadge'
import Pagination from '@/Components/UI/Pagination'
import EmptyState from '@/Components/UI/EmptyState'
import LinkButton from '@/Components/UI/LinkButton'
import ConfirmDialog from '@/Components/UI/ConfirmDialog'
import { ActionButton, ActionLink, RowActions } from '@/Components/UI/RowActions'
import { useResourceFilters } from '@/hooks/useResourceFilters'
import { usePermissions } from '@/hooks/usePermissions'
import type { Paginated, ResourceFilterValues } from '@/types'
import type { CategoryListItem } from '../../types'

interface CategoriesIndexProps {
    categories: Paginated<CategoryListItem>
    filters: ResourceFilterValues
}

export default function CategoriesIndex({ categories, filters: initial }: CategoriesIndexProps) {
    const { can } = usePermissions()
    const { filters, loading, update, toggleSort, reset, hasActiveFilters } = useResourceFilters(
        'categories.index',
        {
            search: initial.search,
            status: '',
            trashed: initial.trashed,
            sort: initial.sort,
            direction: initial.direction,
        },
    )

    const [pendingDelete, setPendingDelete] = useState<CategoryListItem | null>(null)
    const [processing, setProcessing] = useState(false)

    function confirmDelete() {
        if (!pendingDelete) {
            return
        }

        router.delete(route('categories.destroy', pendingDelete.id), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false)
                setPendingDelete(null)
            },
        })
    }

    const columns: Column<CategoryListItem>[] = [
        {
            key: 'name',
            header: 'Categoría',
            align: 'start',
            sortable: true,
            cell: (category) => (
                <div>
                    <span className="font-medium text-neutral-900 dark:text-white">{category.name}</span>
                    <span className="text-secondary-light block font-mono text-sm">/{category.slug}</span>
                </div>
            ),
        },
        {
            key: 'description',
            header: 'Descripción',
            align: 'start',
            cell: (category) =>
                category.description ? (
                    <span className="text-secondary-light line-clamp-2 max-w-[420px] text-sm">
                        {category.description}
                    </span>
                ) : (
                    <span className="text-neutral-400">—</span>
                ),
        },
        {
            key: 'position',
            header: 'Orden',
            sortable: true,
            cell: (category) => category.position,
        },
        {
            key: 'status',
            header: 'Estado',
            cell: (category) =>
                category.deleted_at ? (
                    <StatusBadge label="En la papelera" tone="danger" />
                ) : category.is_slug_locked ? (
                    <StatusBadge label="En uso" tone="success" />
                ) : (
                    <StatusBadge label="Sin usar" tone="neutral" />
                ),
        },
        {
            key: 'actions',
            header: 'Acciones',
            cell: (category) => (
                <RowActions>
                    {category.deleted_at ? (
                        category.can.restore && (
                            <ActionButton
                                onClick={() =>
                                    router.post(
                                        route('categories.restore', category.id),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                                label={`Restaurar ${category.name}`}
                                icon="ri-arrow-go-back-line"
                            />
                        )
                    ) : (
                        <>
                            {category.can.update && (
                                <ActionLink
                                    href={route('categories.edit', category.id)}
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
                        </>
                    )}
                </RowActions>
            ),
        },
    ]

    return (
        <DashboardLayout title="Categorías" breadcrumb="Contenido">
            <Card
                header={
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h6 className="mb-0 text-base font-semibold">Categorías del blog</h6>

                        {can('blog.categories.manage') && (
                            <LinkButton href={route('categories.create')} icon={<IconPlus />}>
                                Nueva categoría
                            </LinkButton>
                        )}
                    </div>
                }
                padded={false}
            >
                <div className="flex flex-wrap items-end gap-3 px-6 py-4">
                    <div className="min-w-[220px] flex-1">
                        <SearchInput
                            value={filters.search}
                            onChange={(value) => update({ search: value })}
                            label="Buscar categorías"
                            placeholder="Buscar por nombre o descripción…"
                        />
                    </div>

                    <label className="flex items-center gap-2 py-2.5 text-sm">
                        <input
                            type="checkbox"
                            checked={filters.trashed}
                            onChange={(event) => update({ trashed: event.target.checked })}
                            className="form-check-input border border-neutral-300"
                        />
                        Ver papelera
                    </label>

                    {hasActiveFilters && (
                        <button
                            type="button"
                            onClick={reset}
                            className="py-2.5 text-sm font-medium text-primary-600 hover:underline"
                        >
                            Limpiar filtros
                        </button>
                    )}
                </div>

                <div className="px-6 pb-6">
                    <DataTable
                        columns={columns}
                        rows={categories.data}
                        rowKey={(category) => category.id}
                        sort={{ field: filters.sort, direction: filters.direction }}
                        onSort={toggleSort}
                        loading={loading}
                        empty={
                            <EmptyState
                                title={
                                    hasActiveFilters
                                        ? 'No hay categorías que coincidan con la búsqueda'
                                        : 'Todavía no hay categorías'
                                }
                                description={
                                    hasActiveFilters
                                        ? 'Prueba a cambiar la búsqueda o a quitar algún filtro.'
                                        : 'Las categorías agrupan los artículos y tienen su propia página en la web.'
                                }
                                action={
                                    hasActiveFilters ? (
                                        <button
                                            type="button"
                                            onClick={reset}
                                            className="text-sm font-medium text-primary-600 hover:underline"
                                        >
                                            Limpiar filtros
                                        </button>
                                    ) : can('blog.categories.manage') ? (
                                        <LinkButton href={route('categories.create')} icon={<IconPlus />}>
                                            Nueva categoría
                                        </LinkButton>
                                    ) : undefined
                                }
                            />
                        }
                    />

                    <Pagination
                        links={categories.links}
                        from={categories.from}
                        to={categories.to}
                        total={categories.total}
                        itemsLabel="categorías"
                    />
                </div>
            </Card>

            <ConfirmDialog
                open={pendingDelete !== null}
                title="Enviar categoría a la papelera"
                message={
                    pendingDelete
                        ? `«${pendingDelete.name}» dejará de estar disponible en la web. Podrás restaurarla más adelante.`
                        : ''
                }
                confirmLabel="Enviar a la papelera"
                processing={processing}
                onConfirm={confirmDelete}
                onCancel={() => setPendingDelete(null)}
            />
        </DashboardLayout>
    )
}

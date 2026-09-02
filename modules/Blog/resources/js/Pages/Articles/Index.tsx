import { Link, router } from '@inertiajs/react'
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
import type { Paginated, ResourceFilterValues, SelectOption } from '@/types'
import type { ArticleListItem } from '../../types'

interface ArticlesIndexProps {
    articles: Paginated<ArticleListItem>
    filters: ResourceFilterValues & { mine: boolean }
    statusOptions: SelectOption[]
    categoryOptions: SelectOption[]
    can: { create: boolean }
}

const DATE_FORMAT = new Intl.DateTimeFormat('es-ES', { dateStyle: 'short', timeStyle: 'short' })

export default function ArticlesIndex({
    articles,
    filters: initial,
    statusOptions,
    categoryOptions,
    can,
}: ArticlesIndexProps) {
    const { filters, loading, update, toggleSort, reset, hasActiveFilters } = useResourceFilters(
        'articles.index',
        {
            search: initial.search,
            status: initial.status,
            trashed: initial.trashed,
            sort: initial.sort,
            direction: initial.direction,
            extra: initial.extra ?? '',
        },
    )

    const [pendingDelete, setPendingDelete] = useState<ArticleListItem | null>(null)
    const [processing, setProcessing] = useState(false)
    const [selected, setSelected] = useState<number[]>([])
    const [bulkStatus, setBulkStatus] = useState('')

    function applyBulkStatus() {
        if (selected.length === 0 || bulkStatus === '') {
            return
        }

        router.post(
            route('articles.bulk.status'),
            { ids: selected, status: bulkStatus },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => {
                    setProcessing(false)
                    setSelected([])
                    setBulkStatus('')
                },
            },
        )
    }

    function confirmDelete() {
        if (!pendingDelete) {
            return
        }

        router.delete(route('articles.destroy', pendingDelete.id), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false)
                setPendingDelete(null)
            },
        })
    }

    const columns: Column<ArticleListItem>[] = [
        {
            key: 'title',
            header: 'Artículo',
            align: 'start',
            sortable: true,
            cell: (article) => (
                <div className="max-w-[420px]">
                    {article.can.update ? (
                        <Link
                            href={route('articles.edit', article.id)}
                            className="font-medium text-neutral-900 hover:text-primary-600 dark:text-white"
                        >
                            {article.title}
                        </Link>
                    ) : (
                        <span className="font-medium text-neutral-900 dark:text-white">
                            {article.title}
                        </span>
                    )}

                    <span className="text-secondary-light block truncate font-mono text-sm">
                        /{article.slug}
                    </span>

                    {article.categories.length > 0 && (
                        <span className="text-secondary-light block truncate text-sm">
                            {article.categories.join(' · ')}
                        </span>
                    )}
                </div>
            ),
        },
        {
            key: 'author',
            header: 'Autor',
            cell: (article) => (
                <span>
                    {article.author_name ?? <span className="text-neutral-400">—</span>}
                    {article.is_mine && (
                        <span className="text-secondary-light block text-xs">(tú)</span>
                    )}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Estado',
            sortable: true,
            cell: (article) =>
                article.deleted_at ? (
                    <StatusBadge label="En la papelera" tone="danger" />
                ) : (
                    <div className="flex flex-col items-center gap-1">
                        <StatusBadge label={article.status_label} tone={article.status_tone} />
                        {article.visibility === 'unlisted' && (
                            <span className="text-secondary-light text-xs">Solo con enlace</span>
                        )}
                    </div>
                ),
        },
        {
            key: 'published_at',
            header: 'Fecha',
            sortable: true,
            cell: (article) => {
                if (article.scheduled_for) {
                    return (
                        <span className="text-sm">
                            <span className="text-secondary-light block text-xs">Programado</span>
                            {DATE_FORMAT.format(new Date(article.scheduled_for))}
                        </span>
                    )
                }

                if (article.published_at) {
                    return (
                        <span className="text-sm">{DATE_FORMAT.format(new Date(article.published_at))}</span>
                    )
                }

                return <span className="text-neutral-400">—</span>
            },
        },
        {
            key: 'actions',
            header: 'Acciones',
            cell: (article) => (
                <RowActions>
                    {article.deleted_at ? (
                        article.can.restore && (
                            <ActionButton
                                onClick={() =>
                                    router.post(
                                        route('articles.restore', article.id),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                                label={`Restaurar ${article.title}`}
                                icon="ri-arrow-go-back-line"
                            />
                        )
                    ) : (
                        <>
                            {article.can.update && (
                                <ActionLink
                                    href={route('articles.edit', article.id)}
                                    label={`Editar ${article.title}`}
                                    icon="ri-edit-line"
                                />
                            )}
                            {article.can.delete && (
                                <ActionButton
                                    onClick={() => setPendingDelete(article)}
                                    label={`Enviar ${article.title} a la papelera`}
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
        <DashboardLayout title="Artículos" breadcrumb="Contenido">
            <Card
                header={
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h6 className="mb-0 text-base font-semibold">Artículos del blog</h6>

                        {can.create && (
                            <LinkButton href={route('articles.create')} icon={<IconPlus />}>
                                Nuevo artículo
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
                            label="Buscar artículos"
                            placeholder="Buscar por título, extracto o contenido…"
                        />
                    </div>

                    <div className="w-full sm:w-[190px]">
                        <select
                            value={filters.status}
                            onChange={(event) => update({ status: event.target.value })}
                            className="form-select field-control h-[52px] rounded-lg"
                            aria-label="Filtrar por estado"
                        >
                            <option value="">Todos los estados</option>
                            {statusOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    {categoryOptions.length > 0 && (
                        <div className="w-full sm:w-[190px]">
                            <select
                                value={filters.extra ?? ''}
                                onChange={(event) => update({ extra: event.target.value })}
                                className="form-select field-control h-[52px] rounded-lg"
                                aria-label="Filtrar por categoría"
                            >
                                <option value="">Todas las categorías</option>
                                {categoryOptions.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}

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

                {selected.length > 0 && (
                    <div className="flex flex-wrap items-center gap-3 border-y border-neutral-200 bg-primary-50 px-6 py-3 dark:border-neutral-600 dark:bg-primary-600/20">
                        <span className="text-sm font-medium">
                            {selected.length === 1
                                ? '1 artículo seleccionado'
                                : `${selected.length} artículos seleccionados`}
                        </span>

                        <select
                            value={bulkStatus}
                            onChange={(event) => setBulkStatus(event.target.value)}
                            className="form-select field-control h-10 w-auto rounded-lg py-1.5 text-sm"
                            aria-label="Cambiar el estado de los seleccionados"
                        >
                            <option value="">Cambiar estado a…</option>
                            {statusOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>

                        <button
                            type="button"
                            onClick={applyBulkStatus}
                            disabled={bulkStatus === '' || processing}
                            className="btn btn-primary rounded-lg px-4 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {processing ? 'Aplicando…' : 'Aplicar'}
                        </button>

                        <button
                            type="button"
                            onClick={() => setSelected([])}
                            className="text-sm font-medium text-neutral-600 hover:underline dark:text-neutral-300"
                        >
                            Quitar la selección
                        </button>
                    </div>
                )}

                <div className="px-6 pb-6 pt-4">
                    <DataTable
                        columns={columns}
                        rows={articles.data}
                        rowKey={(article) => article.id}
                        sort={{ field: filters.sort, direction: filters.direction }}
                        onSort={toggleSort}
                        loading={loading}
                        selected={filters.trashed ? undefined : selected}
                        onSelectedChange={filters.trashed ? undefined : setSelected}
                        isSelectable={(article) => article.can.update}
                        empty={
                            <EmptyState
                                title={
                                    hasActiveFilters
                                        ? 'No hay artículos que coincidan con los filtros'
                                        : 'Todavía no hay artículos'
                                }
                                description={
                                    hasActiveFilters
                                        ? 'Prueba a cambiar la búsqueda o a quitar algún filtro.'
                                        : 'Escribe el primer artículo del blog.'
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
                                    ) : can.create ? (
                                        <LinkButton href={route('articles.create')} icon={<IconPlus />}>
                                            Nuevo artículo
                                        </LinkButton>
                                    ) : undefined
                                }
                            />
                        }
                    />

                    <Pagination
                        links={articles.links}
                        from={articles.from}
                        to={articles.to}
                        total={articles.total}
                        itemsLabel="artículos"
                    />
                </div>
            </Card>

            <ConfirmDialog
                open={pendingDelete !== null}
                title="Enviar artículo a la papelera"
                message={
                    pendingDelete
                        ? `«${pendingDelete.title}» dejará de estar disponible en la web. Podrás restaurarlo más adelante.`
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

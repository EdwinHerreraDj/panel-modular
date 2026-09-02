import { router, useForm } from '@inertiajs/react'
import { useEffect, useState, type FormEvent } from 'react'
import IconPlus from '~icons/ic/baseline-add'
import DashboardLayout from '@/Layouts/DashboardLayout'
import Card from '@/Components/UI/Card'
import DataTable, { type Column } from '@/Components/UI/DataTable'
import SearchInput from '@/Components/UI/SearchInput'
import StatusBadge from '@/Components/UI/StatusBadge'
import Pagination from '@/Components/UI/Pagination'
import EmptyState from '@/Components/UI/EmptyState'
import Modal from '@/Components/UI/Modal'
import ConfirmDialog from '@/Components/UI/ConfirmDialog'
import TextField from '@/Components/UI/TextField'
import { ActionButton, RowActions } from '@/Components/UI/RowActions'
import { useResourceFilters } from '@/hooks/useResourceFilters'
import type { Paginated, ResourceFilterValues } from '@/types'
import type { TagListItem } from '../../types'

interface TagsIndexProps {
    tags: Paginated<TagListItem>
    filters: ResourceFilterValues
    can: { create: boolean }
}

interface TagForm {
    name: string
    [key: string]: string
}

export default function TagsIndex({ tags, filters: initial, can }: TagsIndexProps) {
    const { filters, loading, update, toggleSort, reset, hasActiveFilters } = useResourceFilters(
        'tags.index',
        {
            search: initial.search,
            status: '',
            trashed: initial.trashed,
            sort: initial.sort,
            direction: initial.direction,
        },
    )

    // null = cerrado · 'new' = alta · objeto = edición
    const [editing, setEditing] = useState<TagListItem | 'new' | null>(null)
    const [pendingDelete, setPendingDelete] = useState<TagListItem | null>(null)
    const [deleting, setDeleting] = useState(false)

    const form = useForm<TagForm>({ name: '' })

    useEffect(() => {
        if (editing === 'new') {
            form.setData('name', '')
        } else if (editing) {
            form.setData('name', editing.name)
        }

        form.clearErrors()
        // Solo debe reaccionar a la apertura del modal.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [editing])

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault()

        const options = {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        }

        if (editing === 'new') {
            form.post(route('tags.store'), options)
        } else if (editing) {
            form.put(route('tags.update', editing.id), options)
        }
    }

    function confirmDelete() {
        if (!pendingDelete) {
            return
        }

        router.delete(route('tags.destroy', pendingDelete.id), {
            preserveScroll: true,
            onStart: () => setDeleting(true),
            onFinish: () => {
                setDeleting(false)
                setPendingDelete(null)
            },
        })
    }

    const columns: Column<TagListItem>[] = [
        {
            key: 'name',
            header: 'Etiqueta',
            align: 'start',
            sortable: true,
            cell: (tag) => (
                <div>
                    <span className="font-medium text-neutral-900 dark:text-white">{tag.name}</span>
                    <span className="text-secondary-light block font-mono text-sm">/{tag.slug}</span>
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Estado',
            cell: (tag) =>
                tag.deleted_at ? (
                    <StatusBadge label="En la papelera" tone="danger" />
                ) : tag.is_slug_locked ? (
                    <StatusBadge label="En uso" tone="success" />
                ) : (
                    <StatusBadge label="Sin usar" tone="neutral" />
                ),
        },
        {
            key: 'actions',
            header: 'Acciones',
            cell: (tag) => (
                <RowActions>
                    {tag.deleted_at ? (
                        tag.can.restore && (
                            <ActionButton
                                onClick={() =>
                                    router.post(route('tags.restore', tag.id), {}, { preserveScroll: true })
                                }
                                label={`Restaurar ${tag.name}`}
                                icon="ri-arrow-go-back-line"
                            />
                        )
                    ) : (
                        <>
                            {tag.can.update && (
                                <ActionButton
                                    onClick={() => setEditing(tag)}
                                    label={`Editar ${tag.name}`}
                                    icon="ri-edit-line"
                                />
                            )}
                            {tag.can.delete && (
                                <ActionButton
                                    onClick={() => setPendingDelete(tag)}
                                    label={`Enviar ${tag.name} a la papelera`}
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
        <DashboardLayout title="Etiquetas" breadcrumb="Contenido">
            <Card
                header={
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h6 className="mb-0 text-base font-semibold">Etiquetas del blog</h6>

                        {can.create && (
                            <button
                                type="button"
                                onClick={() => setEditing('new')}
                                className="btn btn-primary items-center gap-2 rounded-lg px-5 py-2.5 text-sm"
                            >
                                <IconPlus />
                                Nueva etiqueta
                            </button>
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
                            label="Buscar etiquetas"
                            placeholder="Buscar por nombre…"
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
                        rows={tags.data}
                        rowKey={(tag) => tag.id}
                        sort={{ field: filters.sort, direction: filters.direction }}
                        onSort={toggleSort}
                        loading={loading}
                        empty={
                            <EmptyState
                                title={
                                    hasActiveFilters
                                        ? 'No hay etiquetas que coincidan con la búsqueda'
                                        : 'Todavía no hay etiquetas'
                                }
                                description={
                                    hasActiveFilters
                                        ? 'Prueba a cambiar la búsqueda o a quitar algún filtro.'
                                        : 'Las etiquetas relacionan artículos entre sí por temas concretos.'
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
                                        <button
                                            type="button"
                                            onClick={() => setEditing('new')}
                                            className="btn btn-primary items-center gap-2 rounded-lg px-5 py-2.5 text-sm"
                                        >
                                            <IconPlus />
                                            Nueva etiqueta
                                        </button>
                                    ) : undefined
                                }
                            />
                        }
                    />

                    <Pagination
                        links={tags.links}
                        from={tags.from}
                        to={tags.to}
                        total={tags.total}
                        itemsLabel="etiquetas"
                    />
                </div>
            </Card>

            <Modal
                open={editing !== null}
                onClose={() => setEditing(null)}
                title={editing === 'new' ? 'Nueva etiqueta' : 'Editar etiqueta'}
            >
                <form onSubmit={submit} noValidate>
                    <TextField
                        label="Nombre"
                        name="name"
                        value={form.data.name}
                        onChange={(event) => form.setData('name', event.target.value)}
                        error={form.errors.name}
                        maxLength={80}
                        autoFocus
                        required
                    />

                    {editing !== 'new' && editing && (
                        <p className="text-secondary-light mt-3 mb-0 text-sm">
                            Dirección pública: <span className="font-mono">/{editing.slug}</span>
                            {editing.is_slug_locked
                                ? ' · se mantiene fija porque la etiqueta ya se usa.'
                                : ' · se actualizará sola al cambiar el nombre.'}
                        </p>
                    )}

                    <div className="mt-6 flex flex-wrap justify-end gap-3">
                        <button
                            type="button"
                            onClick={() => setEditing(null)}
                            className="btn rounded-lg border border-neutral-300 px-5 py-2.5 text-sm font-medium text-neutral-600 hover:bg-neutral-100 dark:border-neutral-500 dark:text-neutral-200 dark:hover:bg-neutral-600"
                        >
                            Cancelar
                        </button>

                        <button
                            type="submit"
                            disabled={form.processing}
                            aria-busy={form.processing}
                            className="btn btn-primary rounded-lg px-5 py-2.5 text-sm disabled:cursor-not-allowed disabled:opacity-70"
                        >
                            {form.processing ? 'Guardando…' : 'Guardar'}
                        </button>
                    </div>
                </form>
            </Modal>

            <ConfirmDialog
                open={pendingDelete !== null}
                title="Enviar etiqueta a la papelera"
                message={
                    pendingDelete
                        ? `«${pendingDelete.name}» dejará de estar disponible en la web. Podrás restaurarla más adelante.`
                        : ''
                }
                confirmLabel="Enviar a la papelera"
                processing={deleting}
                onConfirm={confirmDelete}
                onCancel={() => setPendingDelete(null)}
            />
        </DashboardLayout>
    )
}

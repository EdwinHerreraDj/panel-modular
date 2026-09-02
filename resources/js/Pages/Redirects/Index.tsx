import { Link, router, useForm } from '@inertiajs/react'
import { useState } from 'react'
import DashboardLayout from '@/Layouts/DashboardLayout'
import Card from '@/Components/UI/Card'
import DataTable, { type Column } from '@/Components/UI/DataTable'
import SearchInput from '@/Components/UI/SearchInput'
import StatusBadge from '@/Components/UI/StatusBadge'
import Pagination from '@/Components/UI/Pagination'
import EmptyState from '@/Components/UI/EmptyState'
import ConfirmDialog from '@/Components/UI/ConfirmDialog'
import TextField from '@/Components/UI/TextField'
import SelectField from '@/Components/UI/SelectField'
import { ActionButton, RowActions } from '@/Components/UI/RowActions'
import { useResourceFilters } from '@/hooks/useResourceFilters'
import type { Paginated, ResourceFilterValues, SelectOption } from '@/types'

interface RedirectItem {
    id: number
    from_path: string
    to_path: string
    status_code: number
    is_automatic: boolean
    is_external: boolean
    target_title: string | null
    target_url: string | null
    hits: number
    last_hit_at: string | null
    created_at: string | null
}

interface RedirectsIndexProps {
    redirects: Paginated<RedirectItem>
    filters: ResourceFilterValues
    originOptions: SelectOption[]
    counts: { total: number; automatic: number; used: number }
}

interface RedirectForm {
    from_path: string
    to_path: string
    status_code: string
    [key: string]: string
}

const DATE_FORMAT = new Intl.DateTimeFormat('es-ES', { dateStyle: 'short' })

export default function RedirectsIndex({
    redirects,
    filters: initial,
    originOptions,
    counts,
}: RedirectsIndexProps) {
    const { filters, loading, update, reset, hasActiveFilters } = useResourceFilters('redirects.index', {
        search: initial.search,
        status: initial.status,
        trashed: false,
        sort: 'created_at',
        direction: 'desc',
        extra: '',
    })

    const [adding, setAdding] = useState(false)
    const [pendingDelete, setPendingDelete] = useState<RedirectItem | null>(null)
    const [processing, setProcessing] = useState(false)

    const { data, setData, post, reset: resetForm, processing: saving, errors } = useForm<RedirectForm>({
        from_path: '',
        to_path: '',
        status_code: '301',
    })

    function submit() {
        post(route('redirects.store'), {
            preserveScroll: true,
            onSuccess: () => {
                resetForm()
                setAdding(false)
            },
        })
    }

    const columns: Column<RedirectItem>[] = [
        {
            key: 'from_path',
            header: 'Dirección antigua',
            align: 'start',
            cell: (item) => (
                <code className="block break-all font-mono text-sm text-neutral-900 dark:text-white">
                    /{item.from_path}
                </code>
            ),
        },
        {
            key: 'to_path',
            header: 'Lleva a',
            align: 'start',
            cell: (item) => (
                <span className="block min-w-0">
                    <code className="block break-all font-mono text-sm text-primary-600 dark:text-primary-400">
                        {item.is_external ? item.to_path : `/${item.to_path}`}
                    </code>
                    {item.target_title && item.target_url && (
                        <Link
                            href={item.target_url}
                            className="text-secondary-light block truncate text-xs hover:underline"
                        >
                            {item.target_title}
                        </Link>
                    )}
                </span>
            ),
        },
        {
            key: 'origin',
            header: 'Origen',
            cell: (item) =>
                item.is_automatic ? (
                    <StatusBadge label="Automática" tone="info" />
                ) : (
                    <StatusBadge label="Manual" tone="neutral" />
                ),
        },
        {
            key: 'hits',
            header: 'Visitas',
            cell: (item) => (
                <span className="block">
                    <span className="block text-sm">{item.hits}</span>
                    {item.last_hit_at && (
                        <span className="text-secondary-light block text-xs">
                            {DATE_FORMAT.format(new Date(item.last_hit_at))}
                        </span>
                    )}
                </span>
            ),
        },
        {
            key: 'actions',
            header: 'Acciones',
            cell: (item) => (
                <RowActions>
                    <ActionButton
                        onClick={() => setPendingDelete(item)}
                        label={`Eliminar la redirección de ${item.from_path}`}
                        icon="ri-delete-bin-line"
                        tone="danger"
                    />
                </RowActions>
            ),
        },
    ]

    return (
        <DashboardLayout title="Redirecciones" breadcrumb="Contenido">
            <div className="mb-6 grid gap-4 sm:grid-cols-3">
                {[
                    { label: 'Redirecciones activas', value: counts.total },
                    { label: 'Creadas al cambiar una dirección', value: counts.automatic },
                    { label: 'Que ya han recibido visitas', value: counts.used },
                ].map((card) => (
                    <div
                        key={card.label}
                        className="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-600 dark:bg-dark-2"
                    >
                        <span className="block text-2xl font-semibold text-neutral-900 dark:text-white">
                            {card.value}
                        </span>
                        <span className="text-secondary-light mt-1 block text-sm">{card.label}</span>
                    </div>
                ))}
            </div>

            <Card
                header={
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h6 className="mb-1 text-base font-semibold">Direcciones antiguas</h6>
                            <p className="text-secondary-light mb-0 text-sm">
                                Cuando cambia la dirección de un artículo se guarda sola, para que los
                                enlaces que ya circulan sigan funcionando.
                            </p>
                        </div>

                        {!adding && (
                            <button
                                type="button"
                                onClick={() => setAdding(true)}
                                className="btn btn-primary flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm"
                            >
                                <i className="ri-add-line" aria-hidden="true" />
                                Añadir a mano
                            </button>
                        )}
                    </div>
                }
                padded={false}
            >
                {adding && (
                    <div className="border-b border-neutral-200 px-6 py-5 dark:border-neutral-600">
                        <p className="text-secondary-light mb-4 text-sm">
                            Útil sobre todo al traer un blog que ya existía: apunta cada dirección vieja a
                            la nueva y no se pierde lo que Google tenía indexado.
                        </p>

                        <div className="grid gap-5 lg:grid-cols-3">
                            <TextField
                                label="Dirección antigua"
                                name="from_path"
                                value={data.from_path}
                                onChange={(event) => setData('from_path', event.target.value)}
                                error={errors.from_path}
                                placeholder="blog/mi-articulo-viejo"
                                hint="Sin el dominio. Da igual con barra o sin ella."
                                required
                            />

                            <TextField
                                label="Lleva a"
                                name="to_path"
                                value={data.to_path}
                                onChange={(event) => setData('to_path', event.target.value)}
                                error={errors.to_path}
                                placeholder="mi-articulo-nuevo"
                                hint="La dirección de un artículo, o una URL completa si está fuera."
                                required
                            />

                            <SelectField
                                label="Tipo"
                                name="status_code"
                                value={data.status_code}
                                onChange={(event) => setData('status_code', event.target.value)}
                                error={errors.status_code}
                                options={[
                                    { value: '301', label: '301 · Permanente (recomendada)' },
                                    { value: '302', label: '302 · Temporal' },
                                ]}
                                hint="La permanente traslada el posicionamiento; la temporal no."
                            />
                        </div>

                        <div className="mt-5 flex flex-wrap gap-3">
                            <button
                                type="button"
                                onClick={submit}
                                disabled={saving}
                                className="btn btn-primary rounded-lg px-5 py-2.5 text-sm disabled:opacity-70"
                            >
                                {saving ? 'Guardando…' : 'Añadir redirección'}
                            </button>
                            <button
                                type="button"
                                onClick={() => {
                                    resetForm()
                                    setAdding(false)
                                }}
                                className="px-2 py-2.5 text-sm font-medium text-neutral-600 hover:underline dark:text-neutral-300"
                            >
                                Cancelar
                            </button>
                        </div>
                    </div>
                )}

                <div className="flex flex-wrap items-end gap-3 px-6 py-4">
                    <div className="min-w-[220px] flex-1">
                        <SearchInput
                            value={filters.search}
                            onChange={(value) => update({ search: value })}
                            label="Buscar redirecciones"
                            placeholder="Buscar por dirección…"
                        />
                    </div>

                    <div className="w-full sm:w-[220px]">
                        <select
                            value={filters.status}
                            onChange={(event) => update({ status: event.target.value })}
                            className="form-select rounded-lg"
                            aria-label="Filtrar por origen"
                        >
                            <option value="">Todas</option>
                            {originOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </div>

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
                        rows={redirects.data}
                        rowKey={(item) => item.id}
                        loading={loading}
                        empty={
                            <EmptyState
                                title={
                                    hasActiveFilters
                                        ? 'No hay redirecciones que coincidan'
                                        : 'Todavía no hay ninguna redirección'
                                }
                                description={
                                    hasActiveFilters
                                        ? 'Prueba a cambiar la búsqueda o a quitar el filtro.'
                                        : 'Se crearán solas cuando cambie la dirección de un artículo publicado. También puedes añadirlas a mano.'
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
                                    ) : undefined
                                }
                            />
                        }
                    />

                    <Pagination
                        links={redirects.links}
                        from={redirects.from}
                        to={redirects.to}
                        total={redirects.total}
                        itemsLabel="redirecciones"
                    />
                </div>
            </Card>

            <ConfirmDialog
                open={pendingDelete !== null}
                title="Eliminar la redirección"
                message={
                    pendingDelete
                        ? `Quien llegue a «/${pendingDelete.from_path}» verá un error 404 en lugar de acabar en el artículo.${
                              pendingDelete.hits > 0
                                  ? ` Ojo: esta redirección ha recibido ${pendingDelete.hits} visitas.`
                                  : ''
                          }`
                        : ''
                }
                confirmLabel="Eliminar"
                processing={processing}
                onConfirm={() => {
                    if (!pendingDelete) return

                    router.delete(route('redirects.destroy', pendingDelete.id), {
                        preserveScroll: true,
                        onStart: () => setProcessing(true),
                        onFinish: () => {
                            setProcessing(false)
                            setPendingDelete(null)
                        },
                    })
                }}
                onCancel={() => setPendingDelete(null)}
            />
        </DashboardLayout>
    )
}

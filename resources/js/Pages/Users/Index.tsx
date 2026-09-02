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
import type { Paginated, ResourceFilterValues, RoleOption, SelectOption, UserListItem } from '@/types'

interface UsersIndexProps {
    users: Paginated<UserListItem>
    filters: ResourceFilterValues
    statusOptions: SelectOption[]
    roleOptions: RoleOption[]
}

const DATE_FORMAT = new Intl.DateTimeFormat('es-ES', { dateStyle: 'short', timeStyle: 'short' })

const STATUS_TONES = {
    active: 'success',
    invited: 'warning',
    suspended: 'danger',
} as const

export default function UsersIndex({ users, filters: initial, statusOptions, roleOptions }: UsersIndexProps) {
    const { filters, loading, update, toggleSort, reset, hasActiveFilters } = useResourceFilters(
        'users.index',
        {
            search: initial.search,
            status: initial.status,
            trashed: initial.trashed,
            sort: initial.sort,
            direction: initial.direction,
            extra: initial.extra ?? '',
        },
    )

    const [pendingDelete, setPendingDelete] = useState<UserListItem | null>(null)
    const [processing, setProcessing] = useState(false)

    function confirmDelete() {
        if (!pendingDelete) {
            return
        }

        router.delete(route('users.destroy', pendingDelete.id), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false)
                setPendingDelete(null)
            },
        })
    }

    const columns: Column<UserListItem>[] = [
        {
            key: 'name',
            header: 'Usuario',
            align: 'start',
            sortable: true,
            cell: (user) => (
                <div className="flex items-center gap-3">
                    <span
                        className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-600 text-sm font-semibold text-white"
                        aria-hidden="true"
                    >
                        {user.initials}
                    </span>

                    <span className="min-w-0">
                        <span className="block font-medium text-neutral-900 dark:text-white">
                            {user.name}
                            {user.is_self && (
                                <span className="text-secondary-light ms-2 text-xs font-normal">(tú)</span>
                            )}
                        </span>
                        <span className="text-secondary-light block truncate text-sm">{user.email}</span>
                    </span>
                </div>
            ),
        },
        {
            key: 'role',
            header: 'Rol',
            cell: (user) => <StatusBadge label={user.role_label} tone={user.role_tone} />,
        },
        {
            key: 'status',
            header: 'Estado',
            sortable: true,
            cell: (user) =>
                user.deleted_at ? (
                    <StatusBadge label="En la papelera" tone="danger" />
                ) : (
                    <div className="flex flex-col items-center gap-1">
                        <StatusBadge label={user.status_label} tone={STATUS_TONES[user.status]} />
                        {user.must_change_password && (
                            <span className="text-secondary-light text-xs">Debe cambiar la contraseña</span>
                        )}
                    </div>
                ),
        },
        {
            key: 'last_login_at',
            header: 'Último acceso',
            sortable: true,
            cell: (user) =>
                user.last_login_at ? (
                    DATE_FORMAT.format(new Date(user.last_login_at))
                ) : (
                    <span className="text-neutral-400">Nunca</span>
                ),
        },
        {
            key: 'actions',
            header: 'Acciones',
            cell: (user) => (
                <RowActions>
                    {user.deleted_at ? (
                        user.can.restore && (
                            <ActionButton
                                onClick={() =>
                                    router.post(route('users.restore', user.id), {}, { preserveScroll: true })
                                }
                                label={`Restaurar ${user.name}`}
                                icon="ri-arrow-go-back-line"
                            />
                        )
                    ) : (
                        <>
                            {user.can.update && (
                                <ActionLink
                                    href={route('users.edit', user.id)}
                                    label={`Editar ${user.name}`}
                                    icon="ri-edit-line"
                                />
                            )}
                            {user.can.delete && (
                                <ActionButton
                                    onClick={() => setPendingDelete(user)}
                                    label={`Enviar ${user.name} a la papelera`}
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
        <DashboardLayout title="Usuarios" breadcrumb="Administración">
            <Card
                header={
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h6 className="mb-0 text-base font-semibold">Cuentas de la aplicación</h6>

                        <LinkButton href={route('users.create')} icon={<IconPlus />}>
                            Nuevo usuario
                        </LinkButton>
                    </div>
                }
                padded={false}
            >
                <div className="flex flex-wrap items-end gap-3 px-6 py-4">
                    <div className="min-w-[220px] flex-1">
                        <SearchInput
                            value={filters.search}
                            onChange={(value) => update({ search: value })}
                            label="Buscar usuarios"
                            placeholder="Buscar por nombre o correo…"
                        />
                    </div>

                    <div className="w-full sm:w-[190px]">
                        <select
                            value={filters.extra ?? ''}
                            onChange={(event) => update({ extra: event.target.value })}
                            className="form-select rounded-lg"
                            aria-label="Filtrar por rol"
                        >
                            <option value="">Todos los roles</option>
                            {roleOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="w-full sm:w-[170px]">
                        <select
                            value={filters.status}
                            onChange={(event) => update({ status: event.target.value })}
                            className="form-select rounded-lg"
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
                        rows={users.data}
                        rowKey={(user) => user.id}
                        sort={{ field: filters.sort, direction: filters.direction }}
                        onSort={toggleSort}
                        loading={loading}
                        empty={
                            <EmptyState
                                title={
                                    hasActiveFilters
                                        ? 'No hay usuarios que coincidan con los filtros'
                                        : 'Todavía no hay más usuarios'
                                }
                                description={
                                    hasActiveFilters
                                        ? 'Prueba a cambiar la búsqueda o a quitar algún filtro.'
                                        : 'Crea las cuentas del equipo y asigna a cada persona su rol.'
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
                                    ) : (
                                        <LinkButton href={route('users.create')} icon={<IconPlus />}>
                                            Nuevo usuario
                                        </LinkButton>
                                    )
                                }
                            />
                        }
                    />

                    <Pagination
                        links={users.links}
                        from={users.from}
                        to={users.to}
                        total={users.total}
                        itemsLabel="usuarios"
                    />
                </div>
            </Card>

            <ConfirmDialog
                open={pendingDelete !== null}
                title="Enviar usuario a la papelera"
                message={
                    pendingDelete
                        ? `«${pendingDelete.name}» perderá el acceso de inmediato y se cerrarán sus sesiones abiertas. Podrás restaurarlo más adelante.`
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

import { Link, useForm } from '@inertiajs/react'
import { useState, type FormEvent } from 'react'
import DashboardLayout from '@/Layouts/DashboardLayout'
import Card from '@/Components/UI/Card'
import Alert from '@/Components/UI/Alert'
import TextField from '@/Components/UI/TextField'
import SelectField from '@/Components/UI/SelectField'
import PasswordField from '@/Components/UI/PasswordField'
import RoleSelector from '@/Components/UI/RoleSelector'
import PasswordRequirements from '@/Components/UI/PasswordRequirements'
import type { PasswordPolicy, RoleName, RoleOption, SelectOption, UserStatus } from '@/types'

interface EditableUser {
    id: number
    name: string
    email: string
    role: RoleName | null
    status: UserStatus
    must_change_password: boolean
    last_login_at: string | null
    created_at: string | null
}

interface UsersEditProps {
    user: EditableUser
    roleOptions: RoleOption[]
    statusOptions: SelectOption[]
    passwordPolicy: PasswordPolicy
    isSelf: boolean
}

interface EditUserForm {
    name: string
    email: string
    role: RoleName | ''
    status: UserStatus
    [key: string]: string
}

interface PasswordForm {
    password: string
    password_confirmation: string
    [key: string]: string
}

const DATE_FORMAT = new Intl.DateTimeFormat('es-ES', { dateStyle: 'long', timeStyle: 'short' })

export default function UsersEdit({
    user,
    roleOptions,
    statusOptions,
    passwordPolicy,
    isSelf,
}: UsersEditProps) {
    const details = useForm<EditUserForm>({
        name: user.name,
        email: user.email,
        role: user.role ?? '',
        status: user.status,
    })

    const password = useForm<PasswordForm>({
        password: '',
        password_confirmation: '',
    })

    const [passwordSaved, setPasswordSaved] = useState(false)

    function submitDetails(event: FormEvent<HTMLFormElement>) {
        event.preventDefault()
        details.put(route('users.update', user.id))
    }

    function submitPassword(event: FormEvent<HTMLFormElement>) {
        event.preventDefault()

        password.put(route('users.password', user.id), {
            preserveScroll: true,
            onSuccess: () => {
                password.reset()
                setPasswordSaved(true)
            },
        })
    }

    return (
        <DashboardLayout title={`Editar ${user.name}`} breadcrumb="Usuarios">
            <div className="grid gap-6 xl:grid-cols-3">
                <div className="xl:col-span-2">
                    <Card header={<h6 className="mb-0 text-base font-semibold">Datos de la cuenta</h6>}>
                        <form onSubmit={submitDetails} noValidate>
                            {Object.keys(details.errors).length > 0 && (
                                <Alert variant="danger">
                                    Revisa los campos marcados: hay datos que no son correctos.
                                </Alert>
                            )}

                            <div className="grid gap-5 md:grid-cols-2">
                                <TextField
                                    label="Nombre completo"
                                    name="name"
                                    value={details.data.name}
                                    onChange={(event) => details.setData('name', event.target.value)}
                                    error={details.errors.name}
                                    maxLength={150}
                                    required
                                />

                                <TextField
                                    label="Correo electrónico"
                                    name="email"
                                    type="email"
                                    value={details.data.email}
                                    onChange={(event) => details.setData('email', event.target.value)}
                                    error={details.errors.email}
                                    maxLength={190}
                                    required
                                />

                                <SelectField
                                    label="Estado"
                                    name="status"
                                    value={details.data.status}
                                    onChange={(event) =>
                                        details.setData('status', event.target.value as UserStatus)
                                    }
                                    options={statusOptions}
                                    error={details.errors.status}
                                    disabled={isSelf}
                                    required
                                />
                            </div>

                            <div className="mt-6">
                                <RoleSelector
                                    value={details.data.role}
                                    onChange={(role) => details.setData('role', role)}
                                    options={roleOptions}
                                    error={details.errors.role}
                                    disabled={isSelf}
                                    disabledReason="No puedes cambiar tu propio rol ni tu propio estado. Pídeselo a otra persona con permisos de superadministrador."
                                />
                            </div>

                            <div className="mt-8 flex flex-wrap items-center justify-end gap-3">
                                {details.isDirty && (
                                    <p className="text-secondary-light me-auto mb-0 text-sm">
                                        Tienes cambios sin guardar.
                                    </p>
                                )}

                                <Link
                                    href={route('users.index')}
                                    className="btn rounded-lg border border-neutral-300 px-5 py-2.5 text-sm font-medium text-neutral-600 hover:bg-neutral-100 dark:border-neutral-500 dark:text-neutral-200 dark:hover:bg-neutral-600"
                                >
                                    Cancelar
                                </Link>

                                <button
                                    type="submit"
                                    disabled={details.processing}
                                    aria-busy={details.processing}
                                    className="btn btn-primary rounded-lg px-5 py-2.5 text-sm disabled:cursor-not-allowed disabled:opacity-70"
                                >
                                    {details.processing ? 'Guardando…' : 'Guardar cambios'}
                                </button>
                            </div>
                        </form>
                    </Card>
                </div>

                <div className="flex flex-col gap-6">
                    <Card header={<h6 className="mb-0 text-base font-semibold">Actividad</h6>}>
                        <dl className="grid gap-4">
                            <div>
                                <dt className="text-secondary-light text-sm">Último acceso</dt>
                                <dd className="mb-0 font-medium text-neutral-900 dark:text-white">
                                    {user.last_login_at
                                        ? DATE_FORMAT.format(new Date(user.last_login_at))
                                        : 'Nunca ha accedido'}
                                </dd>
                            </div>

                            <div>
                                <dt className="text-secondary-light text-sm">Cuenta creada</dt>
                                <dd className="mb-0 font-medium text-neutral-900 dark:text-white">
                                    {user.created_at
                                        ? DATE_FORMAT.format(new Date(user.created_at))
                                        : '—'}
                                </dd>
                            </div>

                            {user.must_change_password && (
                                <div>
                                    <Alert variant="danger">
                                        Tiene pendiente cambiar su contraseña en el próximo acceso.
                                    </Alert>
                                </div>
                            )}
                        </dl>
                    </Card>

                    {!isSelf && (
                        <Card header={<h6 className="mb-0 text-base font-semibold">Contraseña</h6>}>
                            <form onSubmit={submitPassword} noValidate>
                                {passwordSaved && (
                                    <Alert variant="success">
                                        Contraseña actualizada. Se han cerrado sus sesiones y deberá
                                        cambiarla al entrar.
                                    </Alert>
                                )}

                                <div className="grid gap-5">
                                    <PasswordField
                                        label="Contraseña nueva"
                                        name="password"
                                        value={password.data.password}
                                        onChange={(event) => {
                                            password.setData('password', event.target.value)
                                            setPasswordSaved(false)
                                        }}
                                        error={password.errors.password}
                                        autoComplete="new-password"
                                        required
                                    />

                                    <PasswordField
                                        label="Repite la contraseña"
                                        name="password_confirmation"
                                        value={password.data.password_confirmation}
                                        onChange={(event) =>
                                            password.setData('password_confirmation', event.target.value)
                                        }
                                        error={password.errors.password_confirmation}
                                        autoComplete="new-password"
                                        required
                                    />

                                    <PasswordRequirements policy={passwordPolicy} />

                                    <button
                                        type="submit"
                                        disabled={password.processing}
                                        aria-busy={password.processing}
                                        className="btn btn-outline-primary w-full justify-center rounded-lg px-5 py-2.5 text-sm disabled:cursor-not-allowed disabled:opacity-70"
                                    >
                                        {password.processing ? 'Guardando…' : 'Fijar contraseña nueva'}
                                    </button>
                                </div>
                            </form>
                        </Card>
                    )}
                </div>
            </div>
        </DashboardLayout>
    )
}

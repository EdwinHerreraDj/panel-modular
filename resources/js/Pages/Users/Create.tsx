import { Link, useForm } from '@inertiajs/react'
import type { FormEvent } from 'react'
import DashboardLayout from '@/Layouts/DashboardLayout'
import Card from '@/Components/UI/Card'
import Alert from '@/Components/UI/Alert'
import FormSection from '@/Components/UI/FormSection'
import TextField from '@/Components/UI/TextField'
import PasswordField from '@/Components/UI/PasswordField'
import RoleSelector from '@/Components/UI/RoleSelector'
import PasswordRequirements from '@/Components/UI/PasswordRequirements'
import type { PasswordPolicy, RoleName, RoleOption } from '@/types'

interface UsersCreateProps {
    roleOptions: RoleOption[]
    passwordPolicy: PasswordPolicy
}

interface CreateUserForm {
    name: string
    email: string
    role: RoleName | ''
    password: string
    password_confirmation: string
    [key: string]: string
}

export default function UsersCreate({ roleOptions, passwordPolicy }: UsersCreateProps) {
    const { data, setData, post, processing, errors, isDirty } = useForm<CreateUserForm>({
        name: '',
        email: '',
        role: 'autor',
        password: '',
        password_confirmation: '',
    })

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault()
        post(route('users.store'))
    }

    const hasErrors = Object.keys(errors).length > 0

    return (
        <DashboardLayout title="Nuevo usuario" breadcrumb="Usuarios">
            <div className="mx-auto w-full max-w-[860px]">
                <Card header={<h6 className="mb-0 text-base font-semibold">Nueva cuenta</h6>}>
                    <form onSubmit={submit} noValidate>
                        {hasErrors && (
                            <Alert variant="danger">
                                Revisa los campos marcados: hay datos que no son correctos.
                            </Alert>
                        )}

                        <div className="flex flex-col gap-6">
                            <FormSection
                                title="Datos de la persona"
                                description="El correo electrónico será su identificador para iniciar sesión."
                            >
                                <div className="grid gap-5 md:grid-cols-2">
                                    <TextField
                                        label="Nombre completo"
                                        name="name"
                                        value={data.name}
                                        onChange={(event) => setData('name', event.target.value)}
                                        error={errors.name}
                                        placeholder="Marta Ruiz"
                                        maxLength={150}
                                        autoComplete="off"
                                        autoFocus
                                        required
                                    />

                                    <TextField
                                        label="Correo electrónico"
                                        name="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(event) => setData('email', event.target.value)}
                                        error={errors.email}
                                        placeholder="marta@empresa.es"
                                        maxLength={190}
                                        autoComplete="off"
                                        required
                                    />
                                </div>
                            </FormSection>

                            <FormSection
                                title="Rol"
                                description="Determina qué puede hacer dentro del panel. Podrás cambiarlo más adelante."
                            >
                                <RoleSelector
                                    value={data.role}
                                    onChange={(role) => setData('role', role)}
                                    options={roleOptions}
                                    error={errors.role}
                                />
                            </FormSection>

                            <FormSection
                                title="Contraseña inicial"
                                description="Se la tendrás que comunicar por un canal seguro."
                            >
                                <div className="grid gap-6 lg:grid-cols-2">
                                    <div className="flex flex-col gap-5">
                                        <PasswordField
                                            label="Contraseña"
                                            name="password"
                                            value={data.password}
                                            onChange={(event) => setData('password', event.target.value)}
                                            error={errors.password}
                                            autoComplete="new-password"
                                            required
                                        />

                                        <PasswordField
                                            label="Repite la contraseña"
                                            name="password_confirmation"
                                            value={data.password_confirmation}
                                            onChange={(event) =>
                                                setData('password_confirmation', event.target.value)
                                            }
                                            error={errors.password_confirmation}
                                            autoComplete="new-password"
                                            required
                                        />
                                    </div>

                                    <PasswordRequirements policy={passwordPolicy} />
                                </div>

                                <Alert variant="info" className="mt-6 mb-0">
                                    Se le pedirá cambiarla la primera vez que entre, así que dejará de
                                    conocerla nadie más que ella.
                                </Alert>
                            </FormSection>
                        </div>

                        <div className="mt-8 flex flex-wrap items-center justify-end gap-3 border-t border-neutral-200 pt-6 dark:border-neutral-600">
                            {isDirty && (
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
                                disabled={processing}
                                aria-busy={processing}
                                className="btn btn-primary rounded-lg px-5 py-2.5 text-sm disabled:cursor-not-allowed disabled:opacity-70"
                            >
                                {processing ? 'Creando…' : 'Crear usuario'}
                            </button>
                        </div>
                    </form>
                </Card>
            </div>
        </DashboardLayout>
    )
}

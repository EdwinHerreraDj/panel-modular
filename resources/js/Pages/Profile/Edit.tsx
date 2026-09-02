import { Link, useForm } from '@inertiajs/react'
import type { FormEvent } from 'react'
import DashboardLayout from '@/Layouts/DashboardLayout'
import Card from '@/Components/UI/Card'
import Alert from '@/Components/UI/Alert'
import TextField from '@/Components/UI/TextField'
import StatusBadge from '@/Components/UI/StatusBadge'
import type { Tone } from '@/types'

interface ProfileData {
    name: string
    email: string
    role_label: string
    role_tone: Tone
    last_login_at: string | null
    password_changed_at: string | null
}

interface ProfileEditProps {
    profile: ProfileData
}

interface ProfileForm {
    name: string
    email: string
    [key: string]: string
}

const DATE_FORMAT = new Intl.DateTimeFormat('es-ES', { dateStyle: 'long', timeStyle: 'short' })

export default function ProfileEdit({ profile }: ProfileEditProps) {
    const { data, setData, put, processing, errors, isDirty } = useForm<ProfileForm>({
        name: profile.name,
        email: profile.email,
    })

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault()
        put(route('profile.update'))
    }

    return (
        <DashboardLayout title="Mi cuenta">
            <div className="grid gap-6 xl:grid-cols-3">
                <div className="xl:col-span-2">
                    <Card header={<h6 className="mb-0 text-base font-semibold">Mis datos</h6>}>
                        <form onSubmit={submit} noValidate>
                            {Object.keys(errors).length > 0 && (
                                <Alert variant="danger">
                                    Revisa los campos marcados: hay datos que no son correctos.
                                </Alert>
                            )}

                            <div className="grid gap-5 md:grid-cols-2">
                                <TextField
                                    label="Nombre completo"
                                    name="name"
                                    value={data.name}
                                    onChange={(event) => setData('name', event.target.value)}
                                    error={errors.name}
                                    maxLength={150}
                                    required
                                />

                                <TextField
                                    label="Correo electrónico"
                                    name="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(event) => setData('email', event.target.value)}
                                    error={errors.email}
                                    maxLength={190}
                                    required
                                />
                            </div>

                            <div className="mt-8 flex flex-wrap items-center justify-end gap-3">
                                {isDirty && (
                                    <p className="text-secondary-light me-auto mb-0 text-sm">
                                        Tienes cambios sin guardar.
                                    </p>
                                )}

                                <button
                                    type="submit"
                                    disabled={processing}
                                    aria-busy={processing}
                                    className="btn btn-primary rounded-lg px-5 py-2.5 text-sm disabled:cursor-not-allowed disabled:opacity-70"
                                >
                                    {processing ? 'Guardando…' : 'Guardar cambios'}
                                </button>
                            </div>
                        </form>
                    </Card>
                </div>

                <div className="flex flex-col gap-6">
                    <Card header={<h6 className="mb-0 text-base font-semibold">Mi acceso</h6>}>
                        <dl className="grid gap-4">
                            <div>
                                <dt className="text-secondary-light text-sm">Rol</dt>
                                <dd className="mb-0">
                                    <StatusBadge label={profile.role_label} tone={profile.role_tone} />
                                </dd>
                            </div>

                            <div>
                                <dt className="text-secondary-light text-sm">Último acceso</dt>
                                <dd className="mb-0 font-medium text-neutral-900 dark:text-white">
                                    {profile.last_login_at
                                        ? DATE_FORMAT.format(new Date(profile.last_login_at))
                                        : '—'}
                                </dd>
                            </div>

                            <div>
                                <dt className="text-secondary-light text-sm">Contraseña cambiada</dt>
                                <dd className="mb-0 font-medium text-neutral-900 dark:text-white">
                                    {profile.password_changed_at
                                        ? DATE_FORMAT.format(new Date(profile.password_changed_at))
                                        : 'Nunca'}
                                </dd>
                            </div>
                        </dl>

                        <div className="mt-6">
                            <Link
                                href={route('password.change')}
                                className="btn btn-outline-primary w-full justify-center rounded-lg px-5 py-2.5 text-sm"
                            >
                                Cambiar contraseña
                            </Link>
                        </div>
                    </Card>
                </div>
            </div>
        </DashboardLayout>
    )
}

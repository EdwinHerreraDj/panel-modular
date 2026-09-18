import { Head, Link, useForm, usePage } from '@inertiajs/react'
import type { FormEvent } from 'react'
import DashboardLayout from '@/Layouts/DashboardLayout'
import Card from '@/Components/UI/Card'
import PasswordField from '@/Components/UI/PasswordField'
import PasswordStrength from '@/Components/UI/PasswordStrength'
import type { PasswordPolicy } from '@/types'

interface ChangePasswordProps {
    /** true cuando el cambio es obligatorio y no se puede navegar a otra parte. */
    forced: boolean
    passwordPolicy: PasswordPolicy
    /** Cuándo se cambió por última vez, para dar contexto. */
    passwordChangedAt: string | null
}

interface ChangePasswordForm {
    current_password: string
    password: string
    password_confirmation: string
    [key: string]: string
}

const DATE_FORMAT = new Intl.DateTimeFormat('es-ES', { dateStyle: 'long' })

export default function ChangePassword({ forced, passwordPolicy, passwordChangedAt }: ChangePasswordProps) {
    const { app } = usePage().props

    const { data, setData, put, processing, errors, reset } = useForm<ChangePasswordForm>({
        current_password: '',
        password: '',
        password_confirmation: '',
    })

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault()

        put(route('password.change.update'), {
            onError: () => reset('current_password'),
        })
    }

    const form = (
        <form onSubmit={submit} noValidate className="flex flex-col gap-6">
            {/* Paso 1: identificarse */}
            <section>
                <div className="mb-4 flex items-center gap-3">
                    <span
                        className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-600 text-xs font-semibold text-white"
                        aria-hidden="true"
                    >
                        1
                    </span>
                    <div>
                        <h6 className="mb-0 text-sm font-semibold text-neutral-900 dark:text-white">
                            Confirma que eres tú
                        </h6>
                        <p className="text-secondary-light mb-0 text-xs">
                            Escribe la contraseña que usas ahora mismo.
                        </p>
                    </div>
                </div>

                <PasswordField
                    label="Contraseña actual"
                    name="current_password"
                    value={data.current_password}
                    onChange={(event) => setData('current_password', event.target.value)}
                    error={errors.current_password}
                    autoComplete="current-password"
                    autoFocus
                    required
                />
            </section>

            <hr className="border-neutral-200 dark:border-neutral-600" />

            {/* Paso 2: la nueva */}
            <section>
                <div className="mb-4 flex items-center gap-3">
                    <span
                        className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-600 text-xs font-semibold text-white"
                        aria-hidden="true"
                    >
                        2
                    </span>
                    <div>
                        <h6 className="mb-0 text-sm font-semibold text-neutral-900 dark:text-white">
                            Elige la contraseña nueva
                        </h6>
                        <p className="text-secondary-light mb-0 text-xs">
                            Los requisitos se marcan solos según escribes.
                        </p>
                    </div>
                </div>

                <div className="grid gap-5">
                    <PasswordField
                        label="Contraseña nueva"
                        name="password"
                        value={data.password}
                        onChange={(event) => setData('password', event.target.value)}
                        error={errors.password}
                        autoComplete="new-password"
                        required
                    />

                    <PasswordStrength
                        value={data.password}
                        policy={passwordPolicy}
                        confirmation={data.password_confirmation}
                    />

                    <PasswordField
                        label="Repite la contraseña nueva"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        onChange={(event) => setData('password_confirmation', event.target.value)}
                        error={errors.password_confirmation}
                        autoComplete="new-password"
                        required
                    />
                </div>
            </section>

            <div className="flex flex-wrap items-center gap-3 border-t border-neutral-200 pt-5 dark:border-neutral-600">
                <button
                    type="submit"
                    disabled={processing}
                    aria-busy={processing}
                    className={`btn btn-primary flex items-center justify-center gap-2 rounded-lg px-6 py-3 text-sm disabled:cursor-not-allowed disabled:opacity-70 ${forced ? 'w-full' : ''}`}
                >
                    {processing ? (
                        <>
                            <i className="ri-loader-4-line animate-spin" aria-hidden="true" />
                            Guardando…
                        </>
                    ) : (
                        <>
                            <i className="ri-lock-password-line" aria-hidden="true" />
                            Cambiar contraseña
                        </>
                    )}
                </button>

                {!forced && (
                    <Link
                        href={route('profile.edit')}
                        className="px-2 py-3 text-sm font-medium text-neutral-600 hover:underline dark:text-neutral-300"
                    >
                        Cancelar
                    </Link>
                )}
            </div>
        </form>
    )

    // Cuando el cambio es obligatorio se muestra a pantalla completa, sin menú
    // ni barra: no hay nada más que se pueda hacer hasta cambiarla.
    if (forced) {
        return (
            <>
                <Head title="Cambia tu contraseña" />

                <div className="flex min-h-[100vh] items-center justify-center bg-neutral-100 px-6 py-10 dark:bg-neutral-800">
                    <div className="w-full max-w-[560px]">
                        <div className="mb-6 text-center">
                            <img
                                src={app.logo ?? '/images/logo.png'}
                                alt={app.name}
                                className="mx-auto mb-6 dark:hidden"
                            />
                            <img
                                src={app.logo_dark ?? app.logo ?? '/images/logo-light.png'}
                                alt={app.name}
                                className="mx-auto mb-6 hidden dark:block"
                            />
                            <span
                                className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-warning-100 text-2xl text-warning-600 dark:bg-warning-600/20 dark:text-warning-400"
                                aria-hidden="true"
                            >
                                <i className="ri-shield-keyhole-line" />
                            </span>
                            <h4 className="mb-2">Cambia tu contraseña</h4>
                            <p className="text-secondary-light mx-auto mb-0 max-w-[420px]">
                                Tu contraseña la fijó un administrador, así que la conoce otra persona.
                                Elige una que solo sepas tú para poder entrar al panel.
                            </p>
                        </div>

                        <Card>{form}</Card>
                    </div>
                </div>
            </>
        )
    }

    return (
        <DashboardLayout title="Cambiar contraseña" breadcrumb="Mi cuenta">
            <div className="grid gap-6 lg:grid-cols-3">
                <div className="lg:col-span-2">
                    <Card
                        header={
                            <div>
                                <h6 className="mb-1 text-base font-semibold">Contraseña</h6>
                                <p className="text-secondary-light mb-0 text-sm">
                                    Al guardarla se cerrarán todas tus demás sesiones abiertas.
                                </p>
                            </div>
                        }
                    >
                        {form}
                    </Card>
                </div>

                <aside>
                    <Card header={<h6 className="mb-0 text-base font-semibold">Consejos</h6>}>
                        {passwordChangedAt && (
                            <p className="text-secondary-light mb-4 rounded-lg bg-neutral-100 px-3 py-2 text-sm dark:bg-neutral-700">
                                La cambiaste por última vez el{' '}
                                <span className="font-medium text-neutral-800 dark:text-neutral-100">
                                    {DATE_FORMAT.format(new Date(passwordChangedAt))}
                                </span>
                                .
                            </p>
                        )}

                        <ul className="text-secondary-light flex flex-col gap-3 text-sm">
                            <li className="flex items-start gap-2">
                                <i className="ri-lightbulb-line mt-px text-primary-600" aria-hidden="true" />
                                Una frase larga que recuerdes es más segura que una palabra corta con
                                símbolos raros.
                            </li>
                            <li className="flex items-start gap-2">
                                <i className="ri-close-circle-line mt-px text-danger-600" aria-hidden="true" />
                                No reutilices la del correo ni la de otras webs: si filtran una, caen todas.
                            </li>
                            <li className="flex items-start gap-2">
                                <i className="ri-key-2-line mt-px text-success-600" aria-hidden="true" />
                                Si te cuesta recordarla, guárdala en un gestor de contraseñas, nunca en un
                                papel junto al ordenador.
                            </li>
                        </ul>
                    </Card>
                </aside>
            </div>
        </DashboardLayout>
    )
}

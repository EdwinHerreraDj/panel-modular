import { Link, useForm } from '@inertiajs/react'
import type { FormEvent } from 'react'
import IconEmail from '~icons/mage/email'
import IconLock from '~icons/solar/lock-password-outline'
import AuthLayout from '@/Layouts/AuthLayout'
import Alert from '@/Components/UI/Alert'
import IconInput from '@/Components/UI/IconInput'
import PasswordInput from '@/Components/UI/PasswordInput'
import SubmitButton from '@/Components/UI/SubmitButton'

interface ResetPasswordProps {
    token: string
    email: string
}

interface ResetPasswordForm {
    token: string
    email: string
    password: string
    password_confirmation: string
    [key: string]: string
}

export default function ResetPassword({ token, email }: ResetPasswordProps) {
    const { data, setData, post, processing, errors, reset } = useForm<ResetPasswordForm>({
        token,
        email,
        password: '',
        password_confirmation: '',
    })

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault()

        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        })
    }

    return (
        <AuthLayout
            title="Restablecer contraseña"
            heading="Crea una contraseña nueva"
            description="Debe tener al menos 12 caracteres e incluir mayúsculas, minúsculas, números y símbolos."
            illustration="/images/auth/forgot-pass-img.png"
        >
            {/* El token caducado o el correo desconocido se notifican como error general. */}
            {errors.email && <Alert variant="danger">{errors.email}</Alert>}

            <form onSubmit={submit} noValidate>
                <div className="mb-4">
                    <IconInput
                        icon={<IconEmail />}
                        label="Correo electrónico"
                        type="email"
                        name="email"
                        value={data.email}
                        onChange={(event) => setData('email', event.target.value)}
                        placeholder="Correo electrónico"
                        autoComplete="username"
                        required
                    />
                </div>

                <div className="mb-4">
                    <PasswordInput
                        icon={<IconLock />}
                        label="Contraseña nueva"
                        name="password"
                        value={data.password}
                        onChange={(event) => setData('password', event.target.value)}
                        placeholder="Contraseña nueva"
                        autoComplete="new-password"
                        autoFocus
                        error={errors.password}
                        required
                    />
                </div>

                <div className="mb-4">
                    <PasswordInput
                        icon={<IconLock />}
                        label="Repite la contraseña"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        onChange={(event) => setData('password_confirmation', event.target.value)}
                        placeholder="Repite la contraseña"
                        autoComplete="new-password"
                        error={errors.password_confirmation}
                        required
                    />
                </div>

                <SubmitButton processing={processing} processingLabel="Guardando…">
                    Guardar contraseña
                </SubmitButton>

                <div className="text-center">
                    <Link href={route('login')} className="mt-6 font-bold text-primary-600 hover:underline">
                        Volver al inicio de sesión
                    </Link>
                </div>
            </form>
        </AuthLayout>
    )
}

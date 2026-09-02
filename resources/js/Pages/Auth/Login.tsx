import { Link, useForm } from '@inertiajs/react'
import type { FormEvent } from 'react'
import IconEmail from '~icons/mage/email'
import IconLock from '~icons/solar/lock-password-outline'
import AuthLayout from '@/Layouts/AuthLayout'
import Alert from '@/Components/UI/Alert'
import IconInput from '@/Components/UI/IconInput'
import PasswordInput from '@/Components/UI/PasswordInput'
import Checkbox from '@/Components/UI/Checkbox'
import SubmitButton from '@/Components/UI/SubmitButton'

interface LoginProps {
    canResetPassword: boolean
    status: string | null
}

interface LoginForm {
    email: string
    password: string
    remember: boolean
    [key: string]: string | boolean
}

export default function Login({ canResetPassword, status }: LoginProps) {
    const { data, setData, post, processing, errors, reset } = useForm<LoginForm>({
        email: '',
        password: '',
        remember: false,
    })

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault()

        post(route('login'), {
            onFinish: () => reset('password'),
        })
    }

    return (
        <AuthLayout
            title="Iniciar sesión"
            heading="Accede a tu cuenta"
            description="Introduce tus datos para entrar en el panel de gestión."
            illustration="/images/auth/auth-img.png"
            showLogo
        >
            {status && <Alert variant="success">{status}</Alert>}

            <form onSubmit={submit} noValidate>
                {/*
                    El error de credenciales llega en `errors.email` por convención
                    de Laravel, pero afecta a todo el formulario: se muestra arriba
                    y no bajo el campo de correo.
                */}
                {errors.email && <Alert variant="danger">{errors.email}</Alert>}

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
                        autoFocus
                        required
                    />
                </div>

                <div className="mb-5">
                    <PasswordInput
                        icon={<IconLock />}
                        label="Contraseña"
                        name="password"
                        value={data.password}
                        onChange={(event) => setData('password', event.target.value)}
                        placeholder="Contraseña"
                        autoComplete="current-password"
                        error={errors.password}
                        required
                    />
                </div>

                <div className="mt-7">
                    <div className="flex justify-between gap-2">
                        <Checkbox
                            label="Recordarme"
                            name="remember"
                            checked={data.remember}
                            onChange={(event) => setData('remember', event.target.checked)}
                        />

                        {canResetPassword && (
                            <Link
                                href={route('password.request')}
                                className="font-medium text-primary-600 hover:underline"
                            >
                                ¿Has olvidado tu contraseña?
                            </Link>
                        )}
                    </div>
                </div>

                <SubmitButton processing={processing} processingLabel="Accediendo…">
                    Iniciar sesión
                </SubmitButton>
            </form>

            <p className="text-secondary-light mt-8 text-center text-sm">
                ¿Necesitas una cuenta? Solicítala al administrador de tu organización.
            </p>
        </AuthLayout>
    )
}

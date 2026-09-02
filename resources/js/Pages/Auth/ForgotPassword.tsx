import { Link, useForm, usePage } from '@inertiajs/react'
import type { FormEvent } from 'react'
import IconEmail from '~icons/mage/email'
import AuthLayout from '@/Layouts/AuthLayout'
import Alert from '@/Components/UI/Alert'
import IconInput from '@/Components/UI/IconInput'
import SubmitButton from '@/Components/UI/SubmitButton'

interface ForgotPasswordForm {
    email: string
    [key: string]: string
}

export default function ForgotPassword() {
    const { flash } = usePage().props

    const { data, setData, post, processing, errors } = useForm<ForgotPasswordForm>({
        email: '',
    })

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault()
        post(route('password.email'))
    }

    return (
        <AuthLayout
            title="Recuperar contraseña"
            heading="¿Has olvidado tu contraseña?"
            description="Introduce el correo electrónico de tu cuenta y te enviaremos un enlace para crear una contraseña nueva."
            illustration="/images/auth/forgot-pass-img.png"
        >
            {flash.status && <Alert variant="success">{flash.status}</Alert>}

            <form onSubmit={submit} noValidate>
                <div className="mb-6">
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
                        error={errors.email}
                        required
                    />
                </div>

                <SubmitButton processing={processing} processingLabel="Enviando…">
                    Enviar enlace
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

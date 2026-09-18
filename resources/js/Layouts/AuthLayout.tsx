import { Head, usePage } from '@inertiajs/react'
import type { ReactNode } from 'react'
import Alert from '@/Components/UI/Alert'

interface AuthLayoutProps {
    /** Título de la pestaña del navegador. */
    title: string
    /** Encabezado visible del formulario. */
    heading: string
    description: string
    /** Ilustración de la columna izquierda (oculta por debajo de `lg`). */
    illustration: string
    /** Muestra el logotipo sobre el encabezado. */
    showLogo?: boolean
    children: ReactNode
}

/**
 * Estructura de las pantallas de acceso, calcada de sign-in.html /
 * forgot-password.html de la plantilla base: dos columnas, ilustración a la
 * izquierda y formulario centrado a la derecha.
 */
export default function AuthLayout({
    title,
    heading,
    description,
    illustration,
    showLogo = false,
    children,
}: AuthLayoutProps) {
    const { flash, app } = usePage().props

    // El de Configuración manda; si no hay, el de la plantilla.
    const logo = app.logo ?? '/images/logo.png'
    const logoOscuro = app.logo_dark ?? app.logo ?? '/images/logo-light.png'

    return (
        <>
            <Head title={title} />

            <section className="flex min-h-[100vh] flex-wrap bg-white dark:bg-dark-2">
                <div className="hidden lg:block lg:w-1/2">
                    <div className="flex h-full flex-col items-center justify-center">
                        <img src={illustration} alt="" aria-hidden="true" className="max-w-full" />
                    </div>
                </div>

                <div className="flex flex-col justify-center px-6 py-8 lg:w-1/2">
                    <div className="mx-auto w-full lg:max-w-[464px]">
                        <div>
                            {showLogo && (
                                <div className="mb-2.5 max-w-[290px]">
                                    <img src={logo} alt="" className="dark:hidden" />
                                    <img src={logoOscuro} alt="" className="hidden dark:block" />
                                </div>
                            )}
                            <h4 className="mb-3">{heading}</h4>
                            <p className="text-secondary-light mb-8 text-lg">{description}</p>
                        </div>

                        {flash.success && <Alert variant="success">{flash.success}</Alert>}
                        {flash.error && <Alert variant="danger">{flash.error}</Alert>}

                        {children}
                    </div>
                </div>
            </section>
        </>
    )
}

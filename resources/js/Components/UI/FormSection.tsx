import type { ReactNode } from 'react'

interface FormSectionProps {
    title: string
    description?: string
    children: ReactNode
}

/**
 * Bloque de un formulario largo, con su título y su separador.
 * Agrupar los campos evita el muro de inputs sin jerarquía.
 */
export default function FormSection({ title, description, children }: FormSectionProps) {
    return (
        <section className="border-t border-neutral-200 pt-6 first:border-t-0 first:pt-0 dark:border-neutral-600">
            <div className="mb-5">
                <h6 className="mb-1 text-base font-semibold">{title}</h6>
                {description && <p className="text-secondary-light mb-0 text-sm">{description}</p>}
            </div>

            {children}
        </section>
    )
}

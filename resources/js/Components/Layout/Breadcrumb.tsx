import { Link } from '@inertiajs/react'
import IconHome from '~icons/solar/home-smile-angle-outline'

interface BreadcrumbProps {
    title: string
    /** Última sección de la ruta; si se omite solo se muestra el panel. */
    current?: string
}

export default function Breadcrumb({ title, current }: BreadcrumbProps) {
    return (
        <div className="mb-6 flex flex-wrap items-center justify-between gap-2">
            <h6 className="mb-0 font-semibold dark:text-white">{title}</h6>

            <nav aria-label="Ruta de navegación">
                <ul className="flex items-center gap-[6px]">
                    <li className="font-medium">
                        <Link
                            href={route('dashboard')}
                            className="flex items-center gap-2 text-neutral-600 hover:text-primary-600 dark:text-white dark:hover:text-primary-600"
                        >
                            <IconHome className="icon text-lg" />
                            Panel
                        </Link>
                    </li>
                    {current && (
                        <>
                            <li className="text-neutral-600 dark:text-white">-</li>
                            <li className="font-medium text-neutral-600 dark:text-white" aria-current="page">
                                {current}
                            </li>
                        </>
                    )}
                </ul>
            </nav>
        </div>
    )
}

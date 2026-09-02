import { Head, usePage } from '@inertiajs/react'
import { useState, type ReactNode } from 'react'
import Alert from '@/Components/UI/Alert'
import Breadcrumb from '@/Components/Layout/Breadcrumb'
import Navbar from '@/Components/Layout/Navbar'
import Sidebar from '@/Components/Layout/Sidebar'

interface DashboardLayoutProps {
    title: string
    /** Sección actual mostrada en la ruta de navegación. */
    breadcrumb?: string
    children: ReactNode
}

/**
 * Estructura del área privada: menú lateral, barra superior, migas de pan y
 * pie, siguiendo el layout de la plantilla base.
 */
export default function DashboardLayout({ title, breadcrumb, children }: DashboardLayoutProps) {
    const { flash } = usePage().props
    const [collapsed, setCollapsed] = useState(false)
    const [mobileOpen, setMobileOpen] = useState(false)

    return (
        <>
            <Head title={title} />

            <a href="#contenido-principal" className="skip-link">
                Saltar al contenido principal
            </a>

            <Sidebar collapsed={collapsed} mobileOpen={mobileOpen} onClose={() => setMobileOpen(false)} />

            {/* Fondo oscurecido al abrir el menú en móvil. */}
            {mobileOpen && (
                <div
                    className="fixed inset-0 z-20 bg-neutral-900/50 xl:hidden"
                    onClick={() => setMobileOpen(false)}
                    aria-hidden="true"
                />
            )}

            <main className={`dashboard-main ${collapsed ? 'active' : ''}`}>
                <Navbar
                    collapsed={collapsed}
                    onToggleCollapsed={() => setCollapsed((current) => !current)}
                    onOpenMobileSidebar={() => setMobileOpen(true)}
                />

                <div className="dashboard-main-body" id="contenido-principal">
                    <Breadcrumb title={title} {...(breadcrumb ? { current: breadcrumb } : {})} />

                    {flash.success && <Alert variant="success">{flash.success}</Alert>}
                    {flash.error && <Alert variant="danger">{flash.error}</Alert>}

                    {children}
                </div>

                <footer className="d-footer mt-auto px-6 py-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="mb-0 text-neutral-600 dark:text-neutral-300">
                            &copy; {new Date().getFullYear()} Panel de Blogs
                        </p>
                    </div>
                </footer>
            </main>
        </>
    )
}

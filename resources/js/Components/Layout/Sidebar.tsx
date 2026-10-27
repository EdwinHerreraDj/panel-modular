import { Link, usePage } from '@inertiajs/react'
import { Fragment, type ReactNode } from 'react'
import IconCross from '~icons/radix-icons/cross-2'
import IconHome from '~icons/solar/home-smile-angle-outline'
import IconUsers from '~icons/solar/users-group-rounded-outline'
import IconSettings from '~icons/solar/settings-outline'
import IconFolder from '~icons/solar/folder-with-files-outline'
import IconTag from '~icons/solar/tag-outline'
import IconArticle from '~icons/solar/document-text-outline'
import IconGallery from '~icons/solar/gallery-outline'
import IconShield from '~icons/solar/shield-check-outline'
import IconPlug from '~icons/solar/plug-circle-outline'
import IconHistory from '~icons/solar/history-outline'
import IconRedirect from '~icons/solar/routing-2-outline'
import IconBox from '~icons/solar/box-minimalistic-outline'
import IconPuzzle from '~icons/solar/widget-add-outline'
import IconStock from '~icons/solar/archive-outline'
import IconSale from '~icons/solar/sale-outline'
import IconDefault from '~icons/solar/widget-outline'

interface SidebarProps {
    /** Modo compacto (solo iconos) en escritorio. */
    collapsed: boolean
    /** Panel desplegado sobre el contenido en móvil. */
    mobileOpen: boolean
    onClose: () => void
}

/**
 * Iconos disponibles para el menú.
 *
 * El servidor manda una clave, no un componente. Es lo que permite que los
 * iconos se sigan compilando a SVG en línea con `unplugin-icons`: si el nombre
 * llegara libre desde el servidor habría que resolverlo en tiempo de ejecución
 * contra una CDN, y eso está descartado en este proyecto.
 *
 * Un módulo que quiera un icono nuevo lo añade aquí.
 */
const icons: Record<string, ReactNode> = {
    home: <IconHome className="menu-icon" />,
    article: <IconArticle className="menu-icon" />,
    folder: <IconFolder className="menu-icon" />,
    tag: <IconTag className="menu-icon" />,
    redirect: <IconRedirect className="menu-icon" />,
    gallery: <IconGallery className="menu-icon" />,
    users: <IconUsers className="menu-icon" />,
    plug: <IconPlug className="menu-icon" />,
    shield: <IconShield className="menu-icon" />,
    history: <IconHistory className="menu-icon" />,
    settings: <IconSettings className="menu-icon" />,
    box: <IconBox className="menu-icon" />,
    puzzle: <IconPuzzle className="menu-icon" />,
    stock: <IconStock className="menu-icon" />,
    sale: <IconSale className="menu-icon" />,
}

/**
 * Menú lateral del área privada, con las clases `.sidebar` de la plantilla.
 *
 * No decide nada: las entradas, sus permisos y cuál está activa las compone el
 * servidor y llegan por las props compartidas.
 */
export default function Sidebar({ collapsed, mobileOpen, onClose }: SidebarProps) {
    const { menu, app } = usePage().props

    // El de Configuración manda; si no hay, el de la plantilla. Para el modo
    // oscuro vale el logotipo normal cuando no se ha subido uno específico.
    const logo = app.logo ?? '/images/logo.png'
    const logoOscuro = app.logo_dark ?? app.logo ?? '/images/logo-light.png'

    return (
        <aside className={`sidebar ${collapsed ? 'active' : ''} ${mobileOpen ? 'sidebar-open' : ''}`}>
            <button type="button" className="sidebar-close-btn !mt-4" onClick={onClose} aria-label="Cerrar menú">
                <IconCross />
            </button>

            <div>
                <Link href={route('dashboard')} className="sidebar-logo">
                    <img src={logo} alt="Inicio" className="light-logo" />
                    <img src={logoOscuro} alt="Inicio" className="dark-logo" />
                    <img src="/images/logo-icon.png" alt="Inicio" className="logo-icon" />
                </Link>
            </div>

            <div className="sidebar-menu-area">
                <ul className="sidebar-menu" id="sidebar-menu">
                    {menu.map((group) => (
                        <Fragment key={group.title ?? 'principal'}>
                            {group.title && <li className="sidebar-menu-group-title">{group.title}</li>}

                            {group.items.map((item) => (
                                <li key={item.href}>
                                    <Link
                                        href={item.href}
                                        className={item.active ? 'active-page' : ''}
                                        aria-current={item.active ? 'page' : undefined}
                                    >
                                        {icons[item.icon] ?? <IconDefault className="menu-icon" />}
                                        <span>{item.label}</span>
                                    </Link>
                                </li>
                            ))}
                        </Fragment>
                    ))}
                </ul>
            </div>
        </aside>
    )
}

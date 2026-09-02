import IconBars from '~icons/heroicons/bars-3-solid'
import IconArrowRight from '~icons/iconoir/arrow-right'
import ThemeToggle from '@/Components/UI/ThemeToggle'
import UserMenu from './UserMenu'

interface NavbarProps {
    collapsed: boolean
    onToggleCollapsed: () => void
    onOpenMobileSidebar: () => void
}

/**
 * Barra superior del área privada (`.navbar-header` de la plantilla).
 */
export default function Navbar({ collapsed, onToggleCollapsed, onOpenMobileSidebar }: NavbarProps) {
    return (
        <div className="navbar-header border-b border-neutral-200 dark:border-neutral-600">
            <div className="flex items-center justify-between">
                <div className="col-auto">
                    <div className="flex flex-wrap items-center gap-[16px]">
                        <button
                            type="button"
                            className={`sidebar-toggle ${collapsed ? 'active' : ''}`}
                            onClick={onToggleCollapsed}
                            aria-label={collapsed ? 'Expandir menú lateral' : 'Contraer menú lateral'}
                            aria-expanded={!collapsed}
                        >
                            <IconBars className="icon non-active" />
                            <IconArrowRight className="icon active" />
                        </button>

                        <button
                            type="button"
                            className="sidebar-mobile-toggle !leading-[0]"
                            onClick={onOpenMobileSidebar}
                            aria-label="Abrir menú lateral"
                        >
                            <IconBars className="icon !text-[30px]" />
                        </button>
                    </div>
                </div>

                <div className="col-auto">
                    <div className="flex flex-wrap items-center gap-3">
                        <ThemeToggle />
                        <UserMenu />
                    </div>
                </div>
            </div>
        </div>
    )
}

import type { Config as ZiggyConfig } from 'ziggy-js'

export type UserStatus = 'invited' | 'active' | 'suspended'

export type RoleName = 'superadministrador' | 'editor' | 'revisor' | 'autor' | 'lector'

/** Tonos de color de la plantilla usados en badges y avisos. */
export type Tone = 'success' | 'warning' | 'danger' | 'info' | 'neutral'

export interface AuthUser {
    id: number
    name: string
    email: string
    initials: string
    avatar_url: string | null
    is_super_admin: boolean
    role: RoleName | null
    role_label: string | null
    must_change_password: boolean
}

/** Opción de rol con su descripción, para los formularios. */
export interface RoleOption {
    value: RoleName
    label: string
    description: string
}

/** Imagen de la biblioteca, tal y como la devuelve el selector. */
export interface MediaPickerItem {
    id: number
    name: string
    alt_text: string | null
    thumbnail_url: string
    preview_url: string
    dimensions: string | null
    size: string
}

export type MediaProcessingStatus = 'pending' | 'done' | 'failed'

export interface MediaListItem {
    id: number
    name: string
    alt_text: string | null
    description: string | null
    url: string
    thumbnail_url: string
    preview_url: string
    size: string
    dimensions: string | null
    mime_type: string
    status: MediaProcessingStatus
    status_label: string
    status_tone: Tone
    usages_count: number
    deleted_at: string | null
    created_at: string | null
    can: {
        update: boolean
        delete: boolean
        restore: boolean
        purge: boolean
    }
}

/** Requisitos de una imagen subible, tal y como los envía el servidor. */
export interface ImageConstraint {
    formats: string
    max_kilobytes: number
    hint: string
}

/** Perfil en una red social, para los datos estructurados. */
export interface SocialProfile {
    network: string
    url: string
}

/** Requisitos de contraseña que se muestran en los formularios. */
export interface PasswordPolicy {
    min_length: number
    require_mixed_case: boolean
    require_numbers: boolean
    require_symbols: boolean
}

export interface UserListItem {
    id: number
    name: string
    email: string
    initials: string
    role: RoleName | null
    role_label: string
    role_tone: Tone
    status: UserStatus
    status_label: string
    must_change_password: boolean
    last_login_at: string | null
    deleted_at: string | null
    is_self: boolean
    can: {
        update: boolean
        delete: boolean
        restore: boolean
        resetPassword: boolean
    }
}

export interface FlashMessages {
    success: string | null
    error: string | null
    status: string | null
}

/** Una entrada del menú lateral, tal y como la compone el servidor. */
export interface MenuItem {
    label: string
    href: string
    /** Clave del icono; el Sidebar la resuelve contra su propio mapa. */
    icon: string
    active: boolean
}

/** Un grupo del menú. `title` a null es el grupo principal, sin encabezado. */
export interface MenuGroup {
    title: string | null
    items: MenuItem[]
}

/** Props compartidas por HandleInertiaRequests con todas las páginas. */
export interface SharedPageProps {
    app: { name: string }
    auth: { user: AuthUser | null; permissions: string[] }
    menu: MenuGroup[]
    flash: FlashMessages
    ziggy: ZiggyConfig & { location: string }
    errors: Record<string, string>
    [key: string]: unknown
}

/** Opción de un desplegable. */
export interface SelectOption {
    value: string
    label: string
}

/** Filtros de un listado, tal y como los devuelve el servidor. */
export interface ResourceFilterValues {
    search: string
    status: string
    trashed: boolean
    sort: string
    direction: 'asc' | 'desc'
    /** Filtro adicional propio del listado (por ejemplo, el rol). */
    extra?: string
}

/** Enlace de paginación devuelto por Laravel. */
export interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

/** Paginador de Laravel tal y como llega a través de Inertia. */
export interface Paginated<T> {
    data: T[]
    links: PaginationLink[]
    current_page: number
    from: number | null
    to: number | null
    last_page: number
    per_page: number
    total: number
}

export interface RowAbilities {
    update: boolean
    delete: boolean
    restore?: boolean
}

declare module '@inertiajs/core' {
    interface PageProps extends SharedPageProps {}
}

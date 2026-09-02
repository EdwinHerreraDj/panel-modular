import type { RowAbilities, Tone } from '@/types'

/**
 * Tipos del blog.
 *
 * Viven en el módulo y no en `resources/js/types`, que es el archivo que
 * comparten todas las pantallas: allí no pinta nada que sepa qué es un
 * artículo.
 */
export type ArticleStatus =
    | 'draft'
    | 'pending_review'
    | 'approved'
    | 'scheduled'
    | 'published'
    | 'archived'

export interface ArticleListItem {
    id: number
    title: string
    slug: string
    status: ArticleStatus
    status_label: string
    status_tone: Tone
    visibility: 'public' | 'unlisted'
    author_name: string | null
    categories: string[]
    published_at: string | null
    scheduled_for: string | null
    updated_at: string | null
    reading_minutes: number
    deleted_at: string | null
    is_mine: boolean
    can: RowAbilities
}

export interface CategoryListItem {
    id: number
    name: string
    slug: string
    description: string | null
    position: number
    is_slug_locked: boolean
    deleted_at: string | null
    can: RowAbilities
}

export interface TagListItem {
    id: number
    name: string
    slug: string
    is_slug_locked: boolean
    deleted_at: string | null
    can: RowAbilities
}

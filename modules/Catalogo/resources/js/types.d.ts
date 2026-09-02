import type { RowAbilities, Tone } from '@/types'

export type ProductAvailability = 'available' | 'low' | 'out_of_stock'

/**
 * Tipos del catálogo.
 *
 * Viven en el módulo: el archivo compartido del núcleo no tiene por qué saber
 * qué es un producto.
 */
export interface ProductListItem {
    id: number
    name: string
    slug: string
    reference: string | null
    summary: string | null
    /** Lo que se paga hoy, ya formateado. Null si el producto no enseña precio. */
    price_label: string | null
    /** El precio tachado; solo llega si el producto está rebajado. */
    previous_price_label: string | null
    discount: number | null
    availability: ProductAvailability
    availability_label: string
    availability_tone: Tone
    thumbnail_url: string | null
    is_visible: boolean
    position: number
    deleted_at: string | null
    can: RowAbilities
}

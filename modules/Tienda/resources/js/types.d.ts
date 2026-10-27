import type { RowAbilities, SelectOption, Tone } from '@/types'

/**
 * Tipos de la tienda.
 *
 * Viven en el módulo: el archivo compartido del núcleo no tiene por qué saber
 * qué es un artículo con variantes.
 */

export type StockStatus = 'available' | 'low' | 'out_of_stock'

export interface ShopProductListItem {
    id: number
    name: string
    sku: string | null
    brand: string | null
    category_label: string | null
    /** Lo que se paga hoy, ya formateado. Null si el artículo no enseña precio. */
    price_label: string | null
    /** El precio tachado; solo llega si hay una promoción en vigor. */
    previous_price_label: string | null
    discount: number | null
    /** Con variantes de precios distintos: el precio es «desde». */
    price_from: boolean
    has_variants: boolean
    variants_count: number
    track_stock: boolean
    stock: number
    stock_status: StockStatus
    stock_label: string
    stock_tone: Tone
    thumbnail_url: string | null
    is_visible: boolean
    is_featured: boolean
    position: number
    deleted_at: string | null
    can: RowAbilities
}

export interface GalleryImage {
    media_id: number
    alt: string
    /** Solo para pintar la miniatura; no se envía. */
    url: string | null
}

export interface ProductOptionValues {
    name: string
    values: string[]
}

export interface VariantRow {
    id: number | null
    values: string[]
    sku: string
    price: string
    stock: number
    /** Lo que había al abrir la ficha; si no se toca, no se reescribe. */
    stock_loaded: number | null
    is_active: boolean
    media_id: number | null
}

export interface ProductFormValues {
    name: string
    sku: string
    brand: string
    summary: string
    description: string
    category_id: string
    category_ids: number[]
    price: string
    track_stock: boolean
    stock: number
    stock_loaded: number | null
    low_stock_threshold: string
    is_visible: boolean
    is_featured: boolean
    position: number
    meta_title: string
    meta_description: string
    images: GalleryImage[]
    options: ProductOptionValues[]
    variants: VariantRow[]
}

/** Lo que comparten las pantallas de alta y edición. */
export interface ProductFormOptions {
    categoryOptions: SelectOption[]
    imageHint: string
    canManageStock: boolean
    defaultThreshold: number
    currency: string
}

export interface ShopCategoryRow {
    id: number
    name: string
    slug: string
    depth: number
    thumbnail_url: string | null
    products_count: number
    children_count: number
    is_visible: boolean
    /** Visible ella y todas las de encima: lo que de verdad sale en la web. */
    is_public: boolean
    position: number
    can: { update: boolean; delete: boolean }
}

export interface PromotionListItem {
    id: number
    name: string
    value_label: string
    scope_label: string
    starts_at: string | null
    ends_at: string | null
    status: { value: string; label: string; tone: Tone }
    can: { update: boolean; delete: boolean }
}

export interface StockVariantRow {
    id: number
    label: string
    sku: string | null
    stock: number
    is_active: boolean
    status_label: string
    status_tone: Tone
}

export interface StockProductRow {
    id: number
    name: string
    sku: string | null
    thumbnail_url: string | null
    is_visible: boolean
    stock: number
    threshold: number
    status: StockStatus
    status_label: string
    status_tone: Tone
    variants: StockVariantRow[]
}

export interface StockMovementRow {
    id: number
    date: string | null
    product_id: number
    product_name: string
    variant_label: string | null
    type_label: string
    quantity: number
    stock_after: number
    note: string | null
    user_name: string | null
}

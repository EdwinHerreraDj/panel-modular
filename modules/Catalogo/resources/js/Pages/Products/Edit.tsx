import DashboardLayout from '@/Layouts/DashboardLayout'
import ProductForm from '../../Components/ProductForm'

interface EditableProduct {
    id: number
    name: string
    slug: string
    reference: string | null
    summary: string | null
    description: string | null
    price: string
    sale_price: string
    availability: string
    image_media_id: number | null
    image_alt: string | null
    image_url: string | null
    is_visible: boolean
    position: number
    is_slug_locked: boolean
}

interface Props {
    product: EditableProduct
    availabilityOptions: { value: string; label: string }[]
    imageHint: string
}

export default function ProductEdit({ product, availabilityOptions, imageHint }: Props) {
    return (
        <DashboardLayout title={product.name} breadcrumb="Productos">
            <h5 className="mb-6 text-xl font-semibold">{product.name}</h5>

            <ProductForm
                values={{
                    name: product.name,
                    reference: product.reference ?? '',
                    summary: product.summary ?? '',
                    description: product.description ?? '',
                    price: product.price,
                    sale_price: product.sale_price,
                    availability: product.availability,
                    image_media_id: product.image_media_id,
                    image_alt: product.image_alt ?? '',
                    image_url: product.image_url,
                    is_visible: product.is_visible,
                    position: product.position,
                }}
                availabilityOptions={availabilityOptions}
                imageHint={imageHint}
                submitUrl={route('products.update', product.id)}
                submitLabel="Guardar cambios"
                slug={product.slug}
                slugLocked={product.is_slug_locked}
                slugUrl={route('products.slug', product.id)}
                cancelUrl={route('products.index')}
            />
        </DashboardLayout>
    )
}

import DashboardLayout from '@/Layouts/DashboardLayout'
import ProductForm from '../../Components/ProductForm'

interface Props {
    nextPosition: number
    availabilityOptions: { value: string; label: string }[]
    imageHint: string
}

export default function ProductCreate({ nextPosition, availabilityOptions, imageHint }: Props) {
    return (
        <DashboardLayout title="Nuevo producto" breadcrumb="Productos">
            <h5 className="mb-6 text-xl font-semibold">Nuevo producto</h5>

            <ProductForm
                values={{
                    name: '',
                    reference: '',
                    summary: '',
                    description: '',
                    price: '',
                    sale_price: '',
                    availability: 'available',
                    image_media_id: null,
                    image_alt: '',
                    image_url: null,
                    is_visible: false,
                    position: nextPosition,
                }}
                availabilityOptions={availabilityOptions}
                imageHint={imageHint}
                submitUrl={route('products.store')}
                submitLabel="Crear producto"
                cancelUrl={route('products.index')}
            />
        </DashboardLayout>
    )
}

import DashboardLayout from '@/Layouts/DashboardLayout'
import ProductForm from '../../Components/ProductForm'
import type { ProductFormOptions } from '../../types'

interface Props extends ProductFormOptions {
    nextPosition: number
}

export default function ShopProductCreate({ nextPosition, ...options }: Props) {
    return (
        <DashboardLayout title="Nuevo artículo" breadcrumb="Tienda">
            <h5 className="mb-6 text-xl font-semibold">Nuevo artículo</h5>

            <ProductForm
                {...options}
                values={{
                    name: '',
                    sku: '',
                    brand: '',
                    summary: '',
                    description: '',
                    category_id: '',
                    category_ids: [],
                    price: '',
                    track_stock: true,
                    stock: 0,
                    stock_loaded: null,
                    low_stock_threshold: '',
                    is_visible: false,
                    is_featured: false,
                    position: nextPosition,
                    meta_title: '',
                    meta_description: '',
                    images: [],
                    options: [],
                    variants: [],
                }}
                method="post"
                submitUrl={route('tienda.products.store')}
                submitLabel="Crear artículo"
            />
        </DashboardLayout>
    )
}

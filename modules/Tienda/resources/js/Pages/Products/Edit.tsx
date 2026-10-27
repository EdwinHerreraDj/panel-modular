import DashboardLayout from '@/Layouts/DashboardLayout'
import LinkButton from '@/Components/UI/LinkButton'
import ProductForm from '../../Components/ProductForm'
import type { ProductFormOptions, ProductFormValues } from '../../types'

interface Props extends ProductFormOptions {
    product: ProductFormValues & {
        id: number
        slug: string
        is_slug_locked: boolean
    }
    whatsappUrl: string | null
}

export default function ShopProductEdit({ product, whatsappUrl, ...options }: Props) {
    const { id, slug, is_slug_locked, ...values } = product

    return (
        <DashboardLayout title={product.name} breadcrumb="Tienda">
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <h5 className="mb-0 text-xl font-semibold">{product.name}</h5>

                <LinkButton href={route('tienda.stock.history', { product: id })} variant="outline">
                    Historial de existencias
                </LinkButton>
            </div>

            <ProductForm
                {...options}
                // Al guardar se vuelve a esta misma pantalla con los datos
                // nuevos. Si cambian las variantes o las existencias, la clave
                // obliga a montar el formulario otra vez: las variantes recién
                // creadas traen su id y el «stock al abrir» vuelve a ser real.
                key={JSON.stringify([values.stock, values.variants.map((variant) => [variant.id, variant.stock])])}
                values={values}
                method="put"
                submitUrl={route('tienda.products.update', id)}
                submitLabel="Guardar cambios"
                slug={slug}
                slugLocked={is_slug_locked}
                slugUrl={route('tienda.products.slug', id)}
                whatsappUrl={whatsappUrl}
            />
        </DashboardLayout>
    )
}

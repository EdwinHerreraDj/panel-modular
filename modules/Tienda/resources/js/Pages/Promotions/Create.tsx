import DashboardLayout from '@/Layouts/DashboardLayout'
import PromotionForm, { type PromotionFormOptions } from '../../Components/PromotionForm'

export default function PromotionCreate(options: PromotionFormOptions) {
    return (
        <DashboardLayout title="Nueva promoción" breadcrumb="Tienda">
            <h5 className="mb-6 text-xl font-semibold">Nueva promoción</h5>

            <PromotionForm
                {...options}
                values={{
                    name: '',
                    description: '',
                    type: 'percentage',
                    value: '',
                    scope: 'all',
                    category_ids: [],
                    product_ids: [],
                    starts_at: '',
                    ends_at: '',
                    is_active: true,
                }}
                method="post"
                submitUrl={route('tienda.promotions.store')}
                submitLabel="Crear promoción"
            />
        </DashboardLayout>
    )
}

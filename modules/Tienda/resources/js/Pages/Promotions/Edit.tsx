import DashboardLayout from '@/Layouts/DashboardLayout'
import PromotionForm, { type PromotionFormOptions, type PromotionFormValues } from '../../Components/PromotionForm'

interface Props extends PromotionFormOptions {
    promotion: PromotionFormValues & { id: number }
}

export default function PromotionEdit({ promotion, ...options }: Props) {
    const { id, ...values } = promotion

    return (
        <DashboardLayout title={promotion.name} breadcrumb="Tienda">
            <h5 className="mb-6 text-xl font-semibold">{promotion.name}</h5>

            <PromotionForm
                {...options}
                values={values}
                method="put"
                submitUrl={route('tienda.promotions.update', id)}
                submitLabel="Guardar cambios"
            />
        </DashboardLayout>
    )
}

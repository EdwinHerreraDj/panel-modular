import DashboardLayout from '@/Layouts/DashboardLayout'
import type { SelectOption } from '@/types'
import CategoryForm, { type CategoryFormValues } from '../../Components/CategoryForm'

interface Props {
    parentOptions: SelectOption[]
    category: CategoryFormValues & {
        id: number
        slug: string
        is_slug_locked: boolean
    }
}

export default function ShopCategoryEdit({ parentOptions, category }: Props) {
    const { id, slug, is_slug_locked, ...values } = category

    return (
        <DashboardLayout title={category.name} breadcrumb="Tienda">
            <h5 className="mb-6 text-xl font-semibold">{category.name}</h5>

            <CategoryForm
                values={values}
                parentOptions={parentOptions}
                method="put"
                submitUrl={route('tienda.categories.update', id)}
                submitLabel="Guardar cambios"
                slug={slug}
                slugLocked={is_slug_locked}
                slugUrl={route('tienda.categories.slug', id)}
            />
        </DashboardLayout>
    )
}

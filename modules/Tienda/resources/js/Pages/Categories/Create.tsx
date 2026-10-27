import DashboardLayout from '@/Layouts/DashboardLayout'
import type { SelectOption } from '@/types'
import CategoryForm from '../../Components/CategoryForm'

interface Props {
    parentOptions: SelectOption[]
    defaultParent: string
    nextPosition: number
}

export default function ShopCategoryCreate({ parentOptions, defaultParent, nextPosition }: Props) {
    return (
        <DashboardLayout title="Nueva categoría" breadcrumb="Tienda">
            <h5 className="mb-6 text-xl font-semibold">Nueva categoría</h5>

            <CategoryForm
                values={{
                    name: '',
                    parent_id: defaultParent,
                    description: '',
                    image_media_id: null,
                    image_url: null,
                    is_visible: true,
                    position: nextPosition,
                    meta_title: '',
                    meta_description: '',
                }}
                parentOptions={parentOptions}
                method="post"
                submitUrl={route('tienda.categories.store')}
                submitLabel="Crear categoría"
            />
        </DashboardLayout>
    )
}

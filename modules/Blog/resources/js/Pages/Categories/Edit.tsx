import DashboardLayout from '@/Layouts/DashboardLayout'
import Card from '@/Components/UI/Card'
import CategoryForm from '../../Components/CategoryForm'

interface EditableCategory {
    id: number
    name: string
    slug: string
    description: string | null
    meta_title: string | null
    meta_description: string | null
    position: number
    is_slug_locked: boolean
}

interface CategoriesEditProps {
    category: EditableCategory
}

export default function CategoriesEdit({ category }: CategoriesEditProps) {
    return (
        <DashboardLayout title={`Editar ${category.name}`} breadcrumb="Categorías">
            <div className="mx-auto w-full max-w-[820px]">
                <Card header={<h6 className="mb-0 text-base font-semibold">Editar categoría</h6>}>
                    <CategoryForm
                        initialValues={{
                            name: category.name,
                            description: category.description ?? '',
                            meta_title: category.meta_title ?? '',
                            meta_description: category.meta_description ?? '',
                            position: category.position,
                        }}
                        submit={{ method: 'put', url: route('categories.update', category.id) }}
                        submitLabel="Guardar cambios"
                        cancelUrl={route('categories.index')}
                        slug={{
                            value: category.slug,
                            locked: category.is_slug_locked,
                            regenerateUrl: route('categories.slug', category.id),
                        }}
                    />
                </Card>
            </div>
        </DashboardLayout>
    )
}

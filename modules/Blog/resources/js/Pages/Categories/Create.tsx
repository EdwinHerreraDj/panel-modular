import DashboardLayout from '@/Layouts/DashboardLayout'
import Card from '@/Components/UI/Card'
import CategoryForm from '../../Components/CategoryForm'

interface CategoriesCreateProps {
    nextPosition: number
}

export default function CategoriesCreate({ nextPosition }: CategoriesCreateProps) {
    return (
        <DashboardLayout title="Nueva categoría" breadcrumb="Categorías">
            <div className="mx-auto w-full max-w-[820px]">
                <Card header={<h6 className="mb-0 text-base font-semibold">Nueva categoría</h6>}>
                    <CategoryForm
                        initialValues={{
                            name: '',
                            description: '',
                            meta_title: '',
                            meta_description: '',
                            position: nextPosition,
                        }}
                        submit={{ method: 'post', url: route('categories.store') }}
                        submitLabel="Crear categoría"
                        cancelUrl={route('categories.index')}
                    />
                </Card>
            </div>
        </DashboardLayout>
    )
}

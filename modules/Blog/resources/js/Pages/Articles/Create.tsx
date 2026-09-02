import DashboardLayout from '@/Layouts/DashboardLayout'
import ArticleForm from '../../Components/ArticleForm'
import type { SelectOption } from '@/types'

interface ArticlesCreateProps {
    statusOptions: SelectOption[]
    visibilityOptions: SelectOption[]
    categoryOptions: SelectOption[]
    tagOptions: SelectOption[]
    relatedOptions: SelectOption[]
}

export default function ArticlesCreate({
    statusOptions,
    visibilityOptions,
    categoryOptions,
    tagOptions,
    relatedOptions,
}: ArticlesCreateProps) {
    return (
        <DashboardLayout title="Nuevo artículo" breadcrumb="Artículos">
            <ArticleForm
                initialValues={{
                    title: '',
                    excerpt: '',
                    content: '',
                    status: 'draft',
                    visibility: 'public',
                    published_at: '',
                    scheduled_for: '',
                    position: 0,
                    category_ids: [],
                    tag_ids: [],
                    related_ids: [],
                    featured_media_id: null,
                    featured_image_alt: '',
                    meta_title: '',
                    meta_description: '',
                    canonical_url: '',
                    og_title: '',
                    og_description: '',
                    is_indexable: true,
                }}
                statusOptions={statusOptions}
                visibilityOptions={visibilityOptions}
                categoryOptions={categoryOptions}
                tagOptions={tagOptions}
                relatedOptions={relatedOptions}
                submitUrl={route('articles.store')}
                submitLabel="Crear artículo"
            />
        </DashboardLayout>
    )
}

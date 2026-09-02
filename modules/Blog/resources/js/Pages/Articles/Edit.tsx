import DashboardLayout from '@/Layouts/DashboardLayout'
import ArticleForm, { type PendingAutosave } from '../../Components/ArticleForm'
import StatusBadge from '@/Components/UI/StatusBadge'
import type { SelectOption, Tone } from '@/types'

interface EditableArticle {
    id: number
    title: string
    slug: string
    excerpt: string | null
    content: string | null
    status: string
    status_label: string
    visibility: string
    published_at: string | null
    scheduled_for: string | null
    position: number
    reading_minutes: number
    category_ids: number[]
    tag_ids: number[]
    related_ids: number[]
    revisions_count: number
    featured_media_id: number | null
    featured_media: { id: number; name: string; url: string } | null
    featured_image_alt: string | null
    meta_title: string | null
    meta_description: string | null
    canonical_url: string | null
    og_title: string | null
    og_description: string | null
    is_indexable: boolean
    is_slug_locked: boolean
    author_name: string | null
    updated_at: string | null
}

interface ArticlesEditProps {
    article: EditableArticle
    statusOptions: SelectOption[]
    visibilityOptions: SelectOption[]
    categoryOptions: SelectOption[]
    tagOptions: SelectOption[]
    relatedOptions: SelectOption[]
    autosave: PendingAutosave | null
    autosaveSeconds: number
}

const STATUS_TONES: Record<string, Tone> = {
    draft: 'neutral',
    pending_review: 'warning',
    approved: 'info',
    scheduled: 'info',
    published: 'success',
    archived: 'danger',
}

const DATE_FORMAT = new Intl.DateTimeFormat('es-ES', { dateStyle: 'medium', timeStyle: 'short' })

export default function ArticlesEdit({
    article,
    statusOptions,
    visibilityOptions,
    categoryOptions,
    tagOptions,
    relatedOptions,
    autosave,
    autosaveSeconds,
}: ArticlesEditProps) {
    return (
        <DashboardLayout title={article.title} breadcrumb="Artículos">
            <div className="mb-6 flex flex-wrap items-center gap-3">
                <StatusBadge
                    label={article.status_label}
                    tone={STATUS_TONES[article.status] ?? 'neutral'}
                />

                {article.author_name && (
                    <span className="text-secondary-light text-sm">Autor: {article.author_name}</span>
                )}

                {article.updated_at && (
                    <span className="text-secondary-light text-sm">
                        Última modificación: {DATE_FORMAT.format(new Date(article.updated_at))}
                    </span>
                )}
            </div>

            <ArticleForm
                initialValues={{
                    title: article.title,
                    excerpt: article.excerpt ?? '',
                    content: article.content ?? '',
                    status: article.status,
                    visibility: article.visibility,
                    published_at: article.published_at ?? '',
                    scheduled_for: article.scheduled_for ?? '',
                    position: article.position,
                    category_ids: article.category_ids,
                    tag_ids: article.tag_ids,
                    related_ids: article.related_ids,
                    featured_media_id: article.featured_media_id,
                    featured_image_alt: article.featured_image_alt ?? '',
                    meta_title: article.meta_title ?? '',
                    meta_description: article.meta_description ?? '',
                    canonical_url: article.canonical_url ?? '',
                    og_title: article.og_title ?? '',
                    og_description: article.og_description ?? '',
                    is_indexable: article.is_indexable,
                }}
                statusOptions={statusOptions}
                visibilityOptions={visibilityOptions}
                categoryOptions={categoryOptions}
                tagOptions={tagOptions}
                relatedOptions={relatedOptions}
                submitUrl={route('articles.update', article.id)}
                submitLabel="Guardar cambios"
                articleId={article.id}
                autosaveSeconds={autosaveSeconds}
                pendingAutosave={autosave}
                revisionsCount={article.revisions_count}
                slug={{
                    value: article.slug,
                    locked: article.is_slug_locked,
                    regenerateUrl: route('articles.slug', article.id),
                }}
                initialFeaturedMedia={article.featured_media}
                readingMinutes={article.reading_minutes}
            />
        </DashboardLayout>
    )
}

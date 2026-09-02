import { EditorContent, useEditor, type Editor } from '@tiptap/react'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'
import Image from '@tiptap/extension-image'
import Placeholder from '@tiptap/extension-placeholder'
import Youtube from '@tiptap/extension-youtube'
import { useId, useState } from 'react'
import FieldError from '@/Components/UI/FieldError'
import Modal from '@/Components/UI/Modal'
import TextField from '@/Components/UI/TextField'

interface RichTextEditorProps {
    label: string
    value: string
    onChange: (html: string) => void
    error?: string | undefined
    placeholder?: string
}

interface ToolButton {
    icon: string
    title: string
    action: () => void
    isActive?: boolean
}

/**
 * Editor del contenido de los artículos.
 *
 * Solo ofrece los elementos que el servidor admite en su lista blanca: lo que
 * no está aquí tampoco sobreviviría a la sanitización. El HTML resultante se
 * vuelve a limpiar en el servidor de todas formas, porque la petición puede no
 * venir de este editor.
 */
export default function RichTextEditor({
    label,
    value,
    onChange,
    error,
    placeholder = 'Empieza a escribir el artículo…',
}: RichTextEditorProps) {
    const id = useId()
    const errorId = `${id}-error`

    const [linkDialogOpen, setLinkDialogOpen] = useState(false)
    const [linkUrl, setLinkUrl] = useState('')
    const [videoDialogOpen, setVideoDialogOpen] = useState(false)
    const [videoUrl, setVideoUrl] = useState('')

    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                // El h1 lo pone el título del artículo en la página pública.
                heading: { levels: [2, 3, 4] },
            }),
            Link.configure({
                openOnClick: false,
                autolink: true,
                protocols: ['http', 'https', 'mailto', 'tel'],
                HTMLAttributes: { rel: 'noopener noreferrer' },
            }),
            Image.configure({ inline: false }),
            Youtube.configure({ nocookie: true, modestBranding: true, width: 640, height: 360 }),
            Placeholder.configure({ placeholder }),
        ],
        content: value,
        onUpdate: ({ editor: instance }) => onChange(instance.getHTML()),
        editorProps: {
            attributes: {
                class: 'tiptap-content',
                'aria-labelledby': `${id}-label`,
            },
        },
    })

    if (!editor) {
        return null
    }

    function openLinkDialog(instance: Editor) {
        setLinkUrl(instance.getAttributes('link').href ?? '')
        setLinkDialogOpen(true)
    }

    function applyLink() {
        const url = linkUrl.trim()

        if (url === '') {
            editor?.chain().focus().extendMarkRange('link').unsetLink().run()
        } else {
            editor?.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
        }

        setLinkDialogOpen(false)
    }

    function applyVideo() {
        const url = videoUrl.trim()

        if (url !== '') {
            editor?.commands.setYoutubeVideo({ src: url })
        }

        setVideoUrl('')
        setVideoDialogOpen(false)
    }

    const groups: ToolButton[][] = [
        [
            {
                icon: 'ri-bold',
                title: 'Negrita',
                action: () => editor.chain().focus().toggleBold().run(),
                isActive: editor.isActive('bold'),
            },
            {
                icon: 'ri-italic',
                title: 'Cursiva',
                action: () => editor.chain().focus().toggleItalic().run(),
                isActive: editor.isActive('italic'),
            },
            {
                icon: 'ri-strikethrough',
                title: 'Tachado',
                action: () => editor.chain().focus().toggleStrike().run(),
                isActive: editor.isActive('strike'),
            },
        ],
        [
            {
                icon: 'ri-h-2',
                title: 'Encabezado 2',
                action: () => editor.chain().focus().toggleHeading({ level: 2 }).run(),
                isActive: editor.isActive('heading', { level: 2 }),
            },
            {
                icon: 'ri-h-3',
                title: 'Encabezado 3',
                action: () => editor.chain().focus().toggleHeading({ level: 3 }).run(),
                isActive: editor.isActive('heading', { level: 3 }),
            },
            {
                icon: 'ri-h-4',
                title: 'Encabezado 4',
                action: () => editor.chain().focus().toggleHeading({ level: 4 }).run(),
                isActive: editor.isActive('heading', { level: 4 }),
            },
        ],
        [
            {
                icon: 'ri-list-unordered',
                title: 'Lista con viñetas',
                action: () => editor.chain().focus().toggleBulletList().run(),
                isActive: editor.isActive('bulletList'),
            },
            {
                icon: 'ri-list-ordered',
                title: 'Lista numerada',
                action: () => editor.chain().focus().toggleOrderedList().run(),
                isActive: editor.isActive('orderedList'),
            },
            {
                icon: 'ri-double-quotes-l',
                title: 'Cita',
                action: () => editor.chain().focus().toggleBlockquote().run(),
                isActive: editor.isActive('blockquote'),
            },
            {
                icon: 'ri-code-box-line',
                title: 'Bloque de código',
                action: () => editor.chain().focus().toggleCodeBlock().run(),
                isActive: editor.isActive('codeBlock'),
            },
            {
                icon: 'ri-separator',
                title: 'Separador',
                action: () => editor.chain().focus().setHorizontalRule().run(),
            },
        ],
        [
            {
                icon: 'ri-link',
                title: 'Enlace',
                action: () => openLinkDialog(editor),
                isActive: editor.isActive('link'),
            },
            {
                icon: 'ri-video-line',
                title: 'Vídeo de YouTube',
                action: () => setVideoDialogOpen(true),
            },
        ],
        [
            {
                icon: 'ri-arrow-go-back-line',
                title: 'Deshacer',
                action: () => editor.chain().focus().undo().run(),
            },
            {
                icon: 'ri-arrow-go-forward-line',
                title: 'Rehacer',
                action: () => editor.chain().focus().redo().run(),
            },
        ],
    ]

    return (
        <div>
            <span className="form-label" id={`${id}-label`}>
                {label}
            </span>

            <div
                className={`overflow-hidden rounded-lg border ${
                    error ? 'border-danger-500' : 'border-neutral-300 dark:border-neutral-600'
                }`}
            >
                <div
                    className="flex flex-wrap items-center gap-1 border-b border-neutral-200 bg-neutral-50 px-2 py-2 dark:border-neutral-600 dark:bg-dark-1"
                    role="toolbar"
                    aria-label="Herramientas de edición"
                >
                    {groups.map((group, index) => (
                        <div key={index} className="flex items-center gap-1">
                            {index > 0 && (
                                <span
                                    className="mx-1 h-6 w-px bg-neutral-300 dark:bg-neutral-600"
                                    aria-hidden="true"
                                />
                            )}

                            {group.map((button) => (
                                <button
                                    key={button.title}
                                    type="button"
                                    onClick={button.action}
                                    title={button.title}
                                    aria-label={button.title}
                                    aria-pressed={button.isActive}
                                    className={`inline-flex h-8 w-8 items-center justify-center rounded text-lg transition ${
                                        button.isActive
                                            ? 'bg-primary-600 text-white'
                                            : 'text-neutral-600 hover:bg-neutral-200 dark:text-neutral-300 dark:hover:bg-neutral-600'
                                    }`}
                                >
                                    <i className={button.icon} aria-hidden="true" />
                                </button>
                            ))}
                        </div>
                    ))}
                </div>

                <EditorContent
                    editor={editor}
                    aria-describedby={error ? errorId : undefined}
                    className="bg-white dark:bg-dark-1"
                />
            </div>

            <FieldError id={errorId} message={error} />

            <Modal
                open={linkDialogOpen}
                onClose={() => setLinkDialogOpen(false)}
                title="Insertar enlace"
                footer={
                    <>
                        <button
                            type="button"
                            onClick={() => setLinkDialogOpen(false)}
                            className="btn rounded-lg border border-neutral-300 px-5 py-2.5 text-sm font-medium text-neutral-600 dark:border-neutral-500 dark:text-neutral-200"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            onClick={applyLink}
                            className="btn btn-primary rounded-lg px-5 py-2.5 text-sm"
                        >
                            Aplicar
                        </button>
                    </>
                }
            >
                <TextField
                    label="Dirección"
                    value={linkUrl}
                    onChange={(event) => setLinkUrl(event.target.value)}
                    placeholder="https://www.ejemplo.es"
                    hint="Déjalo vacío para quitar el enlace del texto seleccionado."
                />
            </Modal>

            <Modal
                open={videoDialogOpen}
                onClose={() => setVideoDialogOpen(false)}
                title="Insertar vídeo"
                footer={
                    <>
                        <button
                            type="button"
                            onClick={() => setVideoDialogOpen(false)}
                            className="btn rounded-lg border border-neutral-300 px-5 py-2.5 text-sm font-medium text-neutral-600 dark:border-neutral-500 dark:text-neutral-200"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            onClick={applyVideo}
                            className="btn btn-primary rounded-lg px-5 py-2.5 text-sm"
                        >
                            Insertar
                        </button>
                    </>
                }
            >
                <TextField
                    label="Enlace del vídeo"
                    value={videoUrl}
                    onChange={(event) => setVideoUrl(event.target.value)}
                    placeholder="https://www.youtube.com/watch?v=..."
                    hint="Solo se admiten vídeos de YouTube y Vimeo."
                />
            </Modal>
        </div>
    )
}

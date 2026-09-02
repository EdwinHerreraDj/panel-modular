import { useMemo, useState, type ReactNode } from 'react'
import DashboardLayout from '@/Layouts/DashboardLayout'
import Card from '@/Components/UI/Card'
import SearchInput from '@/Components/UI/SearchInput'
import EmptyState from '@/Components/UI/EmptyState'

type Block =
    | { type: 'text' | 'heading' | 'tip' | 'warning'; content: string }
    | { type: 'question'; content: [string, string] }
    | { type: 'table'; content: { headers: string[]; rows: string[][] } }

interface Section {
    key: string
    title: string
    icon: string
    blocks: Block[]
}

interface HelpIndexProps {
    sections: Section[]
    siteName: string
}

/**
 * Resalta lo que va entre asteriscos dobles.
 *
 * El manual se escribe una vez y se lee en dos sitios, así que lleva un mínimo
 * de marcado. Con negrita basta: meter un intérprete de Markdown entero para
 * esto sería desproporcionado.
 */
function withEmphasis(text: string): ReactNode {
    return text.split(/(\*\*[^*]+\*\*)/g).map((part, index) =>
        part.startsWith('**') && part.endsWith('**') ? (
            <strong key={index} className="font-semibold text-neutral-900 dark:text-white">
                {part.slice(2, -2)}
            </strong>
        ) : (
            part
        ),
    )
}

function BlockView({ block }: { block: Block }) {
    switch (block.type) {
        case 'heading':
            return (
                <h6 className="mt-6 mb-2 text-base font-semibold text-neutral-900 first:mt-0 dark:text-white">
                    {block.content}
                </h6>
            )

        case 'text':
            return <p className="text-secondary-light mb-3 text-sm leading-relaxed">{withEmphasis(block.content)}</p>

        case 'tip':
            return (
                <p className="mb-3 flex items-start gap-2 rounded-lg bg-info-100/60 px-3 py-2 text-sm text-info-800 dark:bg-info-600/10 dark:text-info-300">
                    <i className="ri-lightbulb-line mt-px shrink-0" aria-hidden="true" />
                    <span>{withEmphasis(block.content)}</span>
                </p>
            )

        case 'warning':
            return (
                <p className="mb-3 flex items-start gap-2 rounded-lg bg-warning-100/60 px-3 py-2 text-sm text-warning-800 dark:bg-warning-600/10 dark:text-warning-300">
                    <i className="ri-alert-line mt-px shrink-0" aria-hidden="true" />
                    <span>{withEmphasis(block.content)}</span>
                </p>
            )

        case 'question':
            return (
                <div className="mb-4 border-s-2 border-primary-300 ps-4 dark:border-primary-600">
                    <p className="mb-1 text-sm font-semibold text-neutral-900 dark:text-white">
                        {block.content[0]}
                    </p>
                    <p className="text-secondary-light mb-0 text-sm leading-relaxed">
                        {withEmphasis(block.content[1])}
                    </p>
                </div>
            )

        case 'table':
            return (
                <div className="mb-4 overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-neutral-200 dark:border-neutral-600">
                                {block.content.headers.map((header) => (
                                    <th key={header} className="pb-2 pe-4 text-start font-medium">
                                        {header}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {block.content.rows.map((row) => (
                                <tr
                                    key={row[0]}
                                    className="border-b border-neutral-100 last:border-0 dark:border-neutral-700"
                                >
                                    {row.map((cell, index) => (
                                        <td
                                            key={index}
                                            className={`py-2 pe-4 align-top ${
                                                index === 0
                                                    ? 'whitespace-nowrap font-medium text-neutral-900 dark:text-white'
                                                    : 'text-secondary-light'
                                            }`}
                                        >
                                            {cell}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )
    }
}

/**
 * Texto de un bloque, para poder buscar dentro.
 */
function blockText(block: Block): string {
    if (block.type === 'question') return block.content.join(' ')

    if (block.type === 'table') {
        return [...block.content.headers, ...block.content.rows.flat()].join(' ')
    }

    return block.content
}

export default function HelpIndex({ sections, siteName }: HelpIndexProps) {
    const [search, setSearch] = useState('')
    const [active, setActive] = useState(sections[0]?.key ?? '')

    const term = search.trim().toLowerCase()

    // Al buscar se enseñan todas las secciones que coinciden, no solo la
    // abierta: si no, buscar algo que está en otra parte no devuelve nada.
    const matching = useMemo(() => {
        if (term === '') return null

        return sections.filter(
            (section) =>
                section.title.toLowerCase().includes(term) ||
                section.blocks.some((block) => blockText(block).toLowerCase().includes(term)),
        )
    }, [sections, term])

    const visible = matching ?? sections.filter((section) => section.key === active)

    return (
        <DashboardLayout title="Ayuda">
            <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h5 className="mb-1 text-xl font-semibold">Cómo usar el panel de {siteName}</h5>
                    <p className="text-secondary-light mb-0 text-sm">
                        Lo que necesitas saber para gestionar el contenido de tu web.
                    </p>
                </div>

                <a
                    href={route('help.download')}
                    className="btn btn-outline-primary flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm"
                >
                    <i className="ri-download-2-line" aria-hidden="true" />
                    Descargar el manual
                </a>
            </div>

            <div className="grid gap-6 lg:grid-cols-4">
                {/* Índice */}
                <aside className="lg:col-span-1">
                    <div className="lg:sticky lg:top-24">
                        <div className="mb-4">
                            <SearchInput
                                value={search}
                                onChange={setSearch}
                                label="Buscar en la ayuda"
                                placeholder="Buscar…"
                            />
                        </div>

                        <nav aria-label="Secciones de la ayuda">
                            <ul className="flex flex-col gap-1">
                                {sections.map((section) => {
                                    const isActive = term === '' && section.key === active

                                    return (
                                        <li key={section.key}>
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    setSearch('')
                                                    setActive(section.key)
                                                }}
                                                aria-current={isActive ? 'true' : undefined}
                                                className={`flex w-full items-center gap-2 rounded-lg px-3 py-2 text-start text-sm transition ${
                                                    isActive
                                                        ? 'bg-primary-600 font-medium text-white'
                                                        : 'text-neutral-700 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-700'
                                                }`}
                                            >
                                                <i className={`${section.icon} text-lg`} aria-hidden="true" />
                                                {section.title}
                                            </button>
                                        </li>
                                    )
                                })}
                            </ul>
                        </nav>
                    </div>
                </aside>

                {/* Contenido */}
                <div className="grid content-start gap-6 lg:col-span-3">
                    {term !== '' && (
                        <p className="text-secondary-light mb-0 text-sm">
                            {visible.length === 0
                                ? `No hay nada sobre «${search}».`
                                : `${visible.length} sección(es) con «${search}».`}
                        </p>
                    )}

                    {visible.length === 0 ? (
                        <Card>
                            <EmptyState
                                title="No hemos encontrado nada"
                                description="Prueba con otra palabra. Si tu duda no está en el manual, escríbenos y la añadimos."
                                action={
                                    <button
                                        type="button"
                                        onClick={() => setSearch('')}
                                        className="text-sm font-medium text-primary-600 hover:underline"
                                    >
                                        Ver todas las secciones
                                    </button>
                                }
                            />
                        </Card>
                    ) : (
                        visible.map((section) => (
                            <Card
                                key={section.key}
                                header={
                                    <h6 className="mb-0 flex items-center gap-2 text-base font-semibold">
                                        <i className={`${section.icon} text-lg`} aria-hidden="true" />
                                        {section.title}
                                    </h6>
                                }
                            >
                                {section.blocks.map((block, index) => (
                                    <BlockView key={index} block={block} />
                                ))}
                            </Card>
                        ))
                    )}
                </div>
            </div>
        </DashboardLayout>
    )
}

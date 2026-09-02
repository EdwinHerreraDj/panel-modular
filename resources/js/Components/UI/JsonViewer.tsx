import { useMemo } from 'react'

interface JsonViewerProps {
    value: unknown
    /** Altura máxima antes de que aparezca la barra de desplazamiento. */
    maxHeight?: string
}

type Token = { text: string; kind: 'key' | 'string' | 'number' | 'boolean' | 'null' | 'plain' }

/**
 * Divide el JSON ya formateado en piezas coloreables.
 *
 * Se hace con una expresión regular sobre el texto y no recorriendo el objeto
 * porque así se conserva exactamente la indentación de `JSON.stringify`.
 */
function tokenize(json: string): Token[] {
    const pattern =
        /("(?:\\.|[^"\\])*"\s*:)|("(?:\\.|[^"\\])*")|(\b-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?\b)|(\btrue\b|\bfalse\b)|(\bnull\b)/g

    const tokens: Token[] = []
    let lastIndex = 0
    let match: RegExpExecArray | null

    while ((match = pattern.exec(json)) !== null) {
        if (match.index > lastIndex) {
            tokens.push({ text: json.slice(lastIndex, match.index), kind: 'plain' })
        }

        const kind: Token['kind'] = match[1]
            ? 'key'
            : match[2]
              ? 'string'
              : match[3]
                ? 'number'
                : match[4]
                  ? 'boolean'
                  : 'null'

        tokens.push({ text: match[0], kind })
        lastIndex = pattern.lastIndex
    }

    if (lastIndex < json.length) {
        tokens.push({ text: json.slice(lastIndex), kind: 'plain' })
    }

    return tokens
}

// El bloque tiene siempre fondo oscuro, así que los colores son fijos: no hay
// que resolver dos temas.
const COLORS: Record<Token['kind'], string> = {
    key: 'text-sky-300',
    string: 'text-emerald-300',
    number: 'text-amber-300',
    boolean: 'text-purple-300',
    null: 'text-neutral-500',
    plain: 'text-neutral-400',
}

/**
 * Muestra una respuesta JSON con formato y color.
 */
export default function JsonViewer({ value, maxHeight = '460px' }: JsonViewerProps) {
    const tokens = useMemo(() => {
        try {
            return tokenize(JSON.stringify(value, null, 2) ?? 'null')
        } catch {
            return [{ text: String(value), kind: 'plain' as const }]
        }
    }, [value])

    return (
        <pre
            className="overflow-auto rounded-lg bg-neutral-900 p-4 text-xs leading-relaxed dark:bg-black/40"
            style={{ maxHeight }}
        >
            <code>
                {tokens.map((token, index) => (
                    <span key={index} className={COLORS[token.kind]}>
                        {token.text}
                    </span>
                ))}
            </code>
        </pre>
    )
}

<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;

/**
 * Documentación de la API pensada para entregársela a otra persona.
 *
 * Se genera con los datos reales de esta instalación (dirección, límites,
 * nombre del blog) en lugar de con ejemplos genéricos: quien la reciba no tiene
 * que adivinar nada ni sustituir marcadores, salvo la clave.
 *
 * Sale en dos formatos: Markdown para leer y OpenAPI para importar en Postman,
 * Insomnia o cualquier cliente.
 */
class ApiDocumentation
{
    /**
     * Ejemplos de respuesta, indexados por endpoint.
     *
     * Se escriben a mano y no se generan de la base de datos porque la
     * documentación tiene que servir aunque el blog esté vacío. Cada endpoint
     * lleva el suyo dentro, así que un módulo documenta lo que aporta sin tocar
     * este archivo. Hay una prueba que comprueba que estas claves siguen siendo
     * las que devuelve la API.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function examples(): array
    {
        $examples = [];

        foreach (ApiCatalog::all() as $endpoint) {
            if ($endpoint->example !== []) {
                $examples[$endpoint->key] = $endpoint->example;
            }
        }

        return $examples;
    }

    /**
     * Errores que puede devolver la API, para que quien integre sepa qué
     * significan sin tener que probarlos.
     *
     * @return list<array{status: int, codigo: string, cuando: string, que_hacer: string}>
     */
    public static function errors(): array
    {
        return [
            [
                'status' => 401,
                'codigo' => 'clave_ausente',
                'cuando' => 'No se ha enviado la cabecera con la clave.',
                'que_hacer' => 'Comprueba que la variable de entorno con la clave está definida en el servidor.',
            ],
            [
                'status' => 401,
                'codigo' => 'clave_invalida',
                'cuando' => 'La clave no existe.',
                'que_hacer' => 'Puede estar mal copiada. Pide una nueva desde el panel.',
            ],
            [
                'status' => 403,
                'codigo' => 'clave_no_utilizable',
                'cuando' => 'La clave está revocada o ha caducado.',
                'que_hacer' => 'Pide una clave nueva: una revocada no se puede reactivar.',
            ],
            [
                'status' => 403,
                'codigo' => 'origen_no_permitido',
                'cuando' => 'La petición llega desde un dominio que no está autorizado en la clave.',
                'que_hacer' => 'Añade el dominio en el panel, escrito igual que lo envía el navegador.',
            ],
            [
                'status' => 404,
                'codigo' => 'no_encontrado',
                'cuando' => 'El artículo no existe, no está publicado o la dirección no es de la API.',
                'que_hacer' => 'Muestra tu página de «no encontrado». No distingas entre los tres casos.',
            ],
            [
                'status' => 429,
                'codigo' => 'demasiadas_peticiones',
                'cuando' => 'Se ha superado el límite por minuto.',
                'que_hacer' => 'Cachea las respuestas en tu web y reintenta pasado un minuto.',
            ],
            [
                'status' => 500,
                'codigo' => 'error_servidor',
                'cuando' => 'Ha fallado algo en el blog.',
                'que_hacer' => 'Sirve la última copia que tengas cacheada y avisa al administrador.',
            ],
        ];
    }

    /**
     * ¿La dirección configurada solo vale en esta máquina?
     *
     * Si es así, el documento no le sirve a nadie de fuera y hay que decirlo
     * bien claro antes de que se entregue con la dirección equivocada.
     */
    public static function addressIsLocal(): bool
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! is_string($host)) {
            return true;
        }

        return in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)
            || str_ends_with($host, '.test')
            || str_ends_with($host, '.local');
    }

    /**
     * Documentación completa en Markdown.
     */
    public static function markdown(): string
    {
        $base = ApiCatalog::url();
        $header = (string) config('api.header');
        $limit = (int) config('api.rate_limit');
        $notes = [];

        foreach (ApiCatalog::all() as $endpoint) {
            $notes[$endpoint->key] = $endpoint->notes;
        }
        $siteName = Setting::current()->site_name;
        $examples = self::examples();

        $lines = [];
        $add = function (string ...$text) use (&$lines): void {
            foreach ($text as $line) {
                $lines[] = $line;
            }
        };

        $add(
            '# API del blog de '.$siteName,
            '',
            'Documento para quien vaya a mostrar los artículos en la web pública.',
            'Todo lo que necesitas está aquí; lo único que hay que pedir aparte es la **clave de acceso**.',
            '',
            '---',
            '',
            '## Lo esencial',
            '',
            '| | |',
            '|---|---|',
            '| Dirección base | `'.$base.'` |',
            '| Versión | '.config('api.version').' |',
            '| Autenticación | Cabecera `'.$header.'` con la clave |',
            '| Formato | JSON, con las claves **en español** |',
            '| Operaciones | Solo lectura (`GET`). No se puede crear ni modificar nada |',
            '| Límite | '.$limit.' peticiones por minuto y clave |',
            '',
            '> La clave se entrega aparte y **solo se puede ver una vez**. Si se pierde, hay que generar otra',
            '> desde el panel: no es recuperable.',
            '',
        );

        if (self::addressIsLocal()) {
            $add(
                '> ⚠️ **La dirección de arriba es local y no funcionará fuera de este ordenador.** Está así',
                '> porque `APP_URL` todavía apunta al entorno de desarrollo. Antes de entregar este documento,',
                '> ponla con el dominio real y vuelve a descargarlo.',
                '',
            );
        }

        $add(
            '---',
            '',
            '## Autenticación',
            '',
            'Cada petición lleva la clave en una cabecera:',
            '',
            '```http',
            'GET '.$base.'/articulos',
            $header.': blog_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
            'Accept: application/json',
            '```',
            '',
            'También se acepta `Authorization: Bearer <clave>`, por si tu cliente HTTP ya lo hace por defecto.',
            '',
            '### Dónde guardar la clave',
            '',
            'En una **variable de entorno del servidor**, nunca en el código ni en el JavaScript que',
            'descarga el navegador. Si la clave viaja al navegador, cualquiera puede leerla desde las',
            'herramientas de desarrollo y usarla por su cuenta.',
            '',
            'Lo recomendable es **llamar a la API desde el servidor de tu web** (renderizado en servidor,',
            'generación estática o un proceso programado) y servir a tu visitante el HTML ya montado. Así la',
            'clave no sale de tu máquina y además te ahorras el CORS.',
            '',
            '---',
            '',
            '## Endpoints',
            '',
        );

        foreach (ApiCatalog::all() as $endpoint) {
            $endpoint = $endpoint->toArray() + ['notes' => $notes[$endpoint->key] ?? []];

            $add(
                '### '.$endpoint['title'],
                '',
                '```',
                'GET '.$base.'/'.$endpoint['path'],
                '```',
                '',
                $endpoint['description'],
                '',
                $endpoint['requires_key']
                    ? '**Requiere clave.**'
                    : '**No requiere clave**, para poder vigilar la API desde fuera sin exponer nada.',
                '',
            );

            if ($endpoint['parameters'] !== []) {
                $add('| Parámetro | Para qué sirve | Ejemplo |', '|---|---|---|');

                foreach ($endpoint['parameters'] as $parameter) {
                    $add('| `'.$parameter['name'].'` | '.$parameter['description'].' | '
                        .($parameter['example'] === '' ? '—' : '`'.$parameter['example'].'`').' |');
                }

                $add('');
            }

            foreach ($endpoint['notes'] as $note) {
                $add($note, '');
            }

            if (isset($examples[$endpoint['key']])) {
                $add(
                    'Respuesta:',
                    '',
                    '```json',
                    (string) json_encode(
                        $examples[$endpoint['key']],
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                    ),
                    '```',
                    '',
                );
            }
        }

        $add(
            '---',
            '',
            '## Qué se publica y qué no',
            '',
            'Es importante entenderlo para no construir páginas que luego se queden vacías:',
            '',
            '- Solo salen los artículos **publicados y con la fecha ya cumplida**. Un borrador o algo',
            '  programado para mañana no aparece, y tampoco responde si pides su dirección directamente.',
            '- Los artículos marcados como **«solo con enlace»** no salen en el listado, pero sí responden',
            '  si pides su dirección concreta. Existen justo para eso: compartirlos sin publicarlos.',
            '- El **listado no incluye el contenido** (`contenido_html`), solo el extracto. El contenido',
            '  completo llega al pedir un artículo concreto. Es para que la portada no descargue de más.',
            '- Del autor solo se expone el **nombre**. El correo no sale nunca.',
            '- Las **categorías y etiquetas vacías no aparecen**: si no tienen ningún artículo publicado, no',
            '  se listan. Así el menú de tu web no muestra secciones sin nada dentro.',
            '',
            '### El HTML del contenido',
            '',
            '`contenido_html` **ya viene limpio**: se sanea al guardarlo en el blog, con una lista blanca de',
            'etiquetas y permitiendo insertar vídeo solo de YouTube y Vimeo. Puedes insertarlo tal cual sin',
            'volver a sanearlo.',
            '',
            '---',
            '',
            '## Errores',
            '',
            'Siempre en JSON y con la misma forma:',
            '',
            '```json',
            '{ "error": { "codigo": "clave_invalida", "mensaje": "La clave de API no es válida." } }',
            '```',
            '',
            '| HTTP | Código | Cuándo pasa | Qué hacer |',
            '|---|---|---|---|',
        );

        foreach (self::errors() as $error) {
            $add('| '.$error['status'].' | `'.$error['codigo'].'` | '.$error['cuando'].' | '
                .$error['que_hacer'].' |');
        }

        $add(
            '',
            '---',
            '',
            '## CORS: llamar desde el navegador',
            '',
            'Si tu web llama a la API **desde el navegador**, hay que autorizar antes su dominio en el panel',
            'del blog. No se usa comodín: se responde con el dominio exacto y solo si está en la lista.',
            '',
            'El dominio se escribe **completo y sin nada detrás**, igual que lo envía el navegador:',
            '',
            '```',
            'Correcto:   https://www.tuweb.com',
            'Incorrecto: www.tuweb.com          (falta https://)',
            'Incorrecto: https://www.tuweb.com/blog   (sobra la ruta)',
            '```',
            '',
            'Recuerda que `https://tuweb.com` y `https://www.tuweb.com` son **dominios distintos** para el',
            'navegador: si tu web responde en los dos, añade los dos.',
            '',
            '> Aun así, lo mejor es no llamar desde el navegador: la clave quedaría a la vista.',
            '',
            '---',
            '',
            '## Límite de peticiones',
            '',
            'Son **'.$limit.' peticiones por minuto**, contadas por clave. Al pasarse, la API responde `429`.',
            '',
            'Con cachear las respuestas en tu web sobra: los artículos no cambian cada segundo. Unos pocos',
            'minutos de caché en los listados bastan y hacen tu web más rápida.',
            '',
            '---',
            '',
            '## Ejemplos',
            '',
            '### JavaScript (Node, Next.js, Astro…)',
            '',
            '```js',
            '// lib/blog.js',
            'const BASE = \''.$base.'\'',
            'const CLAVE = process.env.BLOG_API_KEY   // definida en el servidor, nunca en el cliente',
            '',
            'async function pedir(ruta) {',
            '  const respuesta = await fetch(`${BASE}${ruta}`, {',
            '    headers: { \''.$header.'\': CLAVE, Accept: \'application/json\' },',
            '    next: { revalidate: 300 },   // Next.js: cachea 5 minutos',
            '  })',
            '',
            '  if (respuesta.status === 404) return null',
            '',
            '  if (!respuesta.ok) {',
            '    const { error } = await respuesta.json()',
            '    throw new Error(`API del blog: ${error?.mensaje ?? respuesta.status}`)',
            '  }',
            '',
            '  return respuesta.json()',
            '}',
            '',
            'export const listarArticulos = (pagina = 1) =>',
            '  pedir(`/articulos?pagina=${pagina}&por_pagina=12`)',
            '',
            'export const verArticulo = (slug) => pedir(`/articulos/${encodeURIComponent(slug)}`)',
            '',
            'export const listarCategorias = () => pedir(\'/categorias\')',
            '```',
            '',
            'Pintar la portada:',
            '',
            '```jsx',
            'const { datos, paginacion } = await listarArticulos()',
            '',
            'return (',
            '  <ul>',
            '    {datos.map((articulo) => (',
            '      <li key={articulo.id}>',
            '        <a href={`/blog/${articulo.slug}`}>',
            '          {articulo.imagen_destacada && (',
            '            <img src={articulo.imagen_destacada.mediana} alt={articulo.imagen_destacada.alt} />',
            '          )}',
            '          <h2>{articulo.titulo}</h2>',
            '          <p>{articulo.extracto}</p>',
            '          <time dateTime={articulo.publicado_en}>',
            '            {new Date(articulo.publicado_en).toLocaleDateString(\'es-ES\')}',
            '          </time>',
            '        </a>',
            '      </li>',
            '    ))}',
            '  </ul>',
            ')',
            '```',
            '',
            'La página de un artículo, con su SEO:',
            '',
            '```jsx',
            'const resultado = await verArticulo(slug)',
            '',
            'if (!resultado) return notFound()   // no existe o no está publicado',
            '',
            'const { datos: articulo } = resultado',
            '',
            '// El HTML ya viene saneado del blog',
            '<article dangerouslySetInnerHTML={{ __html: articulo.contenido_html }} />',
            '',
            '// Y para las etiquetas de la cabecera:',
            '// <title>{articulo.seo.titulo}</title>',
            '// <meta name="description" content={articulo.seo.descripcion} />',
            '// {!articulo.seo.indexable && <meta name="robots" content="noindex" />}',
            '// {articulo.seo.canonica && <link rel="canonical" href={articulo.seo.canonica} />}',
            '```',
            '',
            '### PHP',
            '',
            '```php',
            '<?php',
            '',
            'function blog(string $ruta): ?array',
            '{',
            '    $ch = curl_init(\''.$base.'\'.$ruta);',
            '    curl_setopt_array($ch, [',
            '        CURLOPT_RETURNTRANSFER => true,',
            '        CURLOPT_HTTPHEADER => [\''.$header.': \'.getenv(\'BLOG_API_KEY\'), \'Accept: application/json\'],',
            '        CURLOPT_TIMEOUT => 10,',
            '    ]);',
            '',
            '    $cuerpo = curl_exec($ch);',
            '    $estado = curl_getinfo($ch, CURLINFO_HTTP_CODE);',
            '',
            '    if ($estado === 404) {',
            '        return null;',
            '    }',
            '',
            '    if ($estado !== 200) {',
            '        throw new RuntimeException(\'La API del blog ha devuelto \'.$estado);',
            '    }',
            '',
            '    return json_decode((string) $cuerpo, true);',
            '}',
            '',
            '$portada = blog(\'/articulos?por_pagina=6\');',
            '',
            'foreach ($portada[\'datos\'] as $articulo) {',
            '    echo \'<h2>\'.htmlspecialchars($articulo[\'titulo\']).\'</h2>\';',
            '}',
            '```',
            '',
            '### Comprobar a mano',
            '',
            '```bash',
            '# ¿Responde la API? (no necesita clave)',
            'curl '.$base.'/estado',
            '',
            '# Los últimos artículos',
            'curl -H "'.$header.': TU_CLAVE" "'.$base.'/articulos?por_pagina=3"',
            '```',
            '',
            '---',
            '',
            '## Antes de publicar',
            '',
            '- [ ] La clave está en una variable de entorno, no en el repositorio.',
            '- [ ] Las llamadas salen del servidor, no del navegador.',
            '- [ ] Si van desde el navegador, el dominio está autorizado en el panel.',
            '- [ ] Los listados están cacheados unos minutos.',
            '- [ ] Un `404` muestra tu página de «no encontrado», no un error.',
            '- [ ] Si la API falla, la web sigue en pie con lo cacheado.',
            '- [ ] El `slug` se usa tal cual llega para construir las direcciones.',
            '- [ ] `contenido_html` se inserta sin volver a sanearlo (ya viene limpio).',
            '',
            '---',
            '',
            '## Si algo va mal',
            '',
            'El administrador del blog tiene, en **Administración → Conexión web**, un probador que ejecuta',
            'cualquier endpoint y enseña la respuesta real. Es el sitio donde comprobar si el problema está',
            'en el blog o en la integración.',
            '',
            'Ahí mismo se ve si una clave está activa, cuántas peticiones lleva y desde qué dirección se usó',
            'por última vez.',
            '',
        );

        return implode("\n", $lines)."\n";
    }

    /**
     * Especificación OpenAPI 3.1, para importar en Postman o Insomnia.
     *
     * @return array<string, mixed>
     */
    public static function openapi(): array
    {
        $examples = self::examples();
        $header = (string) config('api.header');
        $paths = [];

        foreach (ApiCatalog::endpoints() as $endpoint) {
            $parameters = [];

            foreach ($endpoint['parameters'] as $parameter) {
                $inPath = str_contains($endpoint['path'], '{'.$parameter['name'].'}');

                $parameters[] = array_filter([
                    'name' => $parameter['name'],
                    'in' => $inPath ? 'path' : 'query',
                    'required' => $inPath,
                    'description' => $parameter['description'],
                    'schema' => ['type' => 'string'],
                    'example' => $parameter['example'] === '' ? null : $parameter['example'],
                ], fn (mixed $value): bool => $value !== null);
            }

            $responses = [
                '200' => [
                    'description' => 'Correcto',
                    'content' => ['application/json' => array_filter([
                        'example' => $examples[$endpoint['key']] ?? null,
                    ], fn (mixed $value): bool => $value !== null)],
                ],
            ];

            if ($endpoint['requires_key']) {
                $responses['401'] = ['description' => 'Falta la clave o no es válida'];
                $responses['403'] = ['description' => 'Clave revocada, caducada o dominio no permitido'];
                $responses['429'] = ['description' => 'Límite de peticiones superado'];
            }

            if (str_contains($endpoint['path'], '{slug}')) {
                $responses['404'] = ['description' => 'No hay ningún artículo publicado con esa dirección'];
            }

            $paths['/'.$endpoint['path']] = [
                'get' => array_filter([
                    'summary' => $endpoint['title'],
                    'description' => $endpoint['description'],
                    'operationId' => $endpoint['key'],
                    'security' => $endpoint['requires_key'] ? [['ClaveApi' => []]] : [],
                    'parameters' => $parameters === [] ? null : $parameters,
                    'responses' => $responses,
                ], fn (mixed $value): bool => $value !== null),
            ];
        }

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'API del blog de '.Setting::current()->site_name,
                'version' => (string) config('api.version'),
                'description' => 'API de solo lectura para mostrar los artículos del blog en la web '
                    .'pública. Las claves de JSON van en español.',
            ],
            'servers' => [['url' => ApiCatalog::url()]],
            'components' => [
                'securitySchemes' => [
                    'ClaveApi' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => $header,
                        'description' => 'Clave generada desde el panel del blog.',
                    ],
                ],
            ],
            'security' => [['ClaveApi' => []]],
            'paths' => $paths,
        ];
    }
}

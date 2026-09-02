# API del blog de Alminares Panel

Documento para quien vaya a mostrar los artículos en la web pública.
Todo lo que necesitas está aquí; lo único que hay que pedir aparte es la **clave de acceso**.

---

## Lo esencial

| | |
|---|---|
| Dirección base | `http://localhost:8000/api/v1` |
| Versión | 1.0 |
| Autenticación | Cabecera `X-API-Key` con la clave |
| Formato | JSON, con las claves **en español** |
| Operaciones | Solo lectura (`GET`). No se puede crear ni modificar nada |
| Límite | 120 peticiones por minuto y clave |

> La clave se entrega aparte y **solo se puede ver una vez**. Si se pierde, hay que generar otra
> desde el panel: no es recuperable.

> ⚠️ **La dirección de arriba es local y no funcionará fuera de este ordenador.** Está así
> porque `APP_URL` todavía apunta al entorno de desarrollo. Antes de entregar este documento,
> ponla con el dominio real y vuelve a descargarlo.

---

## Autenticación

Cada petición lleva la clave en una cabecera:

```http
GET http://localhost:8000/api/v1/articulos
X-API-Key: blog_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Accept: application/json
```

También se acepta `Authorization: Bearer <clave>`, por si tu cliente HTTP ya lo hace por defecto.

### Dónde guardar la clave

En una **variable de entorno del servidor**, nunca en el código ni en el JavaScript que
descarga el navegador. Si la clave viaja al navegador, cualquiera puede leerla desde las
herramientas de desarrollo y usarla por su cuenta.

Lo recomendable es **llamar a la API desde el servidor de tu web** (renderizado en servidor,
generación estática o un proceso programado) y servir a tu visitante el HTML ya montado. Así la
clave no sale de tu máquina y además te ahorras el CORS.

---

## Endpoints

### Estado de la API

```
GET http://localhost:8000/api/v1/estado
```

Comprueba que la API responde y cuántos artículos hay publicados. No pide clave, para poder vigilarla desde fuera.

**No requiere clave**, para poder vigilar la API desde fuera sin exponer nada.

Respuesta:

```json
{
    "estado": "ok",
    "version": "1.0",
    "articulos_publicados": 37,
    "hora": "2026-07-31T10:15:00+00:00"
}
```

### Datos del sitio

```
GET http://localhost:8000/api/v1/sitio
```

Nombre, lema, logotipos y SEO por defecto. Es lo que la web necesita para pintar la cabecera y el pie, tenga el contenido que tenga.

**Requiere clave.**

Respuesta:

```json
{
    "datos": {
        "nombre": "Alminares",
        "lema": "Novedades del sector",
        "url_publica": "https://www.ejemplo.com",
        "idioma": "es",
        "logo": "https://ejemplo.com/storage/settings/logo.png",
        "logo_oscuro": null,
        "favicon": "https://ejemplo.com/storage/settings/favicon.png",
        "seo": {
            "titulo": "Alminares",
            "descripcion": "Novedades, productos y consejos.",
            "plantilla_titulo": ":title | Alminares",
            "indexable": true,
            "og_imagen": "https://ejemplo.com/storage/settings/og.jpg",
            "twitter_card": "summary_large_image"
        },
        "organizacion": {
            "nombre": "Alminares S.L.",
            "redes": [
                {
                    "network": "instagram",
                    "url": "https://instagram.com/alminares"
                }
            ]
        }
    }
}
```

### Datos del blog (heredado)

```
GET http://localhost:8000/api/v1/blog
```

Lo mismo que `/sitio` más `articulos_por_pagina`. Existe solo para no romper las webs que se conectaron cuando esto era únicamente un blog. **En una integración nueva usa `/sitio`.**

**Requiere clave.**

El número de artículos por página también llega en cada listado, dentro de `paginacion.por_pagina`, así que una web nueva no necesita este endpoint.

Respuesta:

```json
{
    "datos": {
        "nombre": "Alminares",
        "lema": "Novedades del sector",
        "url_publica": "https://www.ejemplo.com",
        "idioma": "es",
        "articulos_por_pagina": 12,
        "logo": "https://ejemplo.com/storage/settings/logo.png",
        "logo_oscuro": null,
        "favicon": "https://ejemplo.com/storage/settings/favicon.png",
        "seo": {
            "titulo": "Alminares",
            "descripcion": "Novedades, productos y consejos.",
            "plantilla_titulo": ":title | Alminares",
            "indexable": true,
            "og_imagen": "https://ejemplo.com/storage/settings/og.jpg",
            "twitter_card": "summary_large_image"
        },
        "organizacion": {
            "nombre": "Alminares S.L.",
            "redes": [
                {
                    "network": "instagram",
                    "url": "https://instagram.com/alminares"
                }
            ]
        }
    }
}
```

### Listado de artículos

```
GET http://localhost:8000/api/v1/articulos
```

Artículos publicados, del más reciente al más antiguo. Solo sale lo que ya está publicado: un borrador o algo programado nunca aparece.

**Requiere clave.**

| Parámetro | Para qué sirve | Ejemplo |
|---|---|---|
| `pagina` | Número de página | `1` |
| `por_pagina` | Artículos por página (máximo 50) | `12` |
| `categoria` | Filtra por la dirección de una categoría | `noticias` |
| `etiqueta` | Filtra por la dirección de una etiqueta | `novedades` |
| `buscar` | Busca en título, extracto y contenido | `pizza` |

El máximo de `por_pagina` es **50**; por encima se recorta sin avisar.

Respuesta:

```json
{
    "datos": [
        {
            "id": 12,
            "titulo": "Cómo elegir la masa de tu pizza",
            "slug": "como-elegir-la-masa-de-tu-pizza",
            "extracto": "Fina, napolitana o de molde: en qué se diferencian y cuándo usar cada una.",
            "minutos_lectura": 4,
            "imagen_destacada": {
                "alt": "Masa de pizza recién estirada sobre harina",
                "miniatura": "https://ejemplo.com/storage/biblioteca/variantes/masa-thumbnail.jpg",
                "mediana": "https://ejemplo.com/storage/biblioteca/variantes/masa-medium.jpg",
                "grande": "https://ejemplo.com/storage/biblioteca/variantes/masa-large.jpg"
            },
            "autor": {
                "nombre": "Lucía Ramos"
            },
            "categorias": [
                {
                    "nombre": "Recetas",
                    "slug": "recetas"
                }
            ],
            "etiquetas": [
                {
                    "nombre": "Masa",
                    "slug": "masa"
                }
            ],
            "seo": {
                "titulo": "Cómo elegir la masa de tu pizza",
                "descripcion": "Fina, napolitana o de molde: en qué se diferencian.",
                "indexable": true,
                "og_titulo": "Cómo elegir la masa de tu pizza",
                "og_descripcion": "Fina, napolitana o de molde."
            },
            "publicado_en": "2026-07-14T09:00:00+00:00",
            "actualizado_en": "2026-07-16T11:32:10+00:00"
        }
    ],
    "paginacion": {
        "pagina": 1,
        "por_pagina": 12,
        "total": 37,
        "ultima_pagina": 4,
        "hay_mas": true
    }
}
```

### Un artículo

```
GET http://localhost:8000/api/v1/articulos/{slug}
```

El artículo completo con su contenido en HTML, ya limpio y seguro para insertar, además de sus relacionados.

**Requiere clave.**

| Parámetro | Para qué sirve | Ejemplo |
|---|---|---|
| `slug` | Dirección del artículo | — |

Respuesta:

```json
{
    "datos": {
        "id": 12,
        "titulo": "Cómo elegir la masa de tu pizza",
        "slug": "como-elegir-la-masa-de-tu-pizza",
        "extracto": "Fina, napolitana o de molde: en qué se diferencian y cuándo usar cada una.",
        "minutos_lectura": 4,
        "imagen_destacada": {
            "alt": "Masa de pizza recién estirada sobre harina",
            "miniatura": "https://ejemplo.com/storage/biblioteca/variantes/masa-thumbnail.jpg",
            "mediana": "https://ejemplo.com/storage/biblioteca/variantes/masa-medium.jpg",
            "grande": "https://ejemplo.com/storage/biblioteca/variantes/masa-large.jpg"
        },
        "autor": {
            "nombre": "Lucía Ramos"
        },
        "categorias": [
            {
                "nombre": "Recetas",
                "slug": "recetas"
            }
        ],
        "etiquetas": [
            {
                "nombre": "Masa",
                "slug": "masa"
            }
        ],
        "seo": {
            "titulo": "Cómo elegir la masa de tu pizza",
            "descripcion": "Fina, napolitana o de molde: en qué se diferencian.",
            "indexable": true,
            "og_titulo": "Cómo elegir la masa de tu pizza",
            "og_descripcion": "Fina, napolitana o de molde."
        },
        "publicado_en": "2026-07-14T09:00:00+00:00",
        "actualizado_en": "2026-07-16T11:32:10+00:00",
        "contenido_html": "<p>La masa es la base de todo…</p><h2>Masa fina</h2><p>…</p>"
    },
    "relacionados": []
}
```

### Categorías

```
GET http://localhost:8000/api/v1/categorias
```

Categorías que tienen al menos un artículo publicado, en orden de presentación. Para el menú de la web.

**Requiere clave.**

Respuesta:

```json
{
    "datos": [
        {
            "nombre": "Recetas",
            "slug": "recetas",
            "descripcion": "Todo lo que se cocina en casa.",
            "articulos": 14,
            "seo": {
                "titulo": "Recetas",
                "descripcion": "Todo lo que se cocina en casa."
            }
        }
    ]
}
```

### Etiquetas

```
GET http://localhost:8000/api/v1/etiquetas
```

Etiquetas en uso, de la más usada a la menos.

**Requiere clave.**

Respuesta:

```json
{
    "datos": [
        {
            "nombre": "Masa",
            "slug": "masa",
            "articulos": 6
        }
    ]
}
```

### Redirecciones

```
GET http://localhost:8000/api/v1/redirecciones
```

Direcciones antiguas que deben redirigir a las actuales, con un 301. Cárgalas al arrancar la web o cachéalas: van todas de una vez para no tener que preguntar en cada visita.

**Requiere clave.**

Respuesta:

```json
{
    "datos": [
        {
            "de": "como-elejir-la-masa",
            "a": "como-elegir-la-masa-de-tu-pizza",
            "codigo": 301,
            "externa": false
        }
    ],
    "total": 1
}
```

### Direcciones para el sitemap

```
GET http://localhost:8000/api/v1/sitemap
```

Todo lo publicado con su fecha de última modificación, para montar el sitemap.xml de la web. No devuelve XML: la estructura de direcciones la decide la web.

**Requiere clave.**

Respuesta:

```json
{
    "articulos": [
        {
            "slug": "como-elegir-la-masa-de-tu-pizza",
            "modificado_en": "2026-07-16T11:32:10+00:00",
            "publicado_en": "2026-07-14T09:00:00+00:00",
            "indexable": true
        }
    ],
    "categorias": [
        {
            "slug": "recetas",
            "modificado_en": "2026-07-10T08:00:00+00:00"
        }
    ],
    "etiquetas": [
        {
            "slug": "masa",
            "modificado_en": "2026-07-10T08:00:00+00:00"
        }
    ],
    "generado_en": "2026-07-31T10:15:00+00:00"
}
```

### Listado de productos

```
GET http://localhost:8000/api/v1/productos
```

Los productos marcados como visibles, en el orden en que se han colocado en el panel. Lo que no está visible no sale ni conociendo su dirección.

**Requiere clave.**

| Parámetro | Para qué sirve | Ejemplo |
|---|---|---|
| `pagina` | Número de página | `1` |
| `por_pagina` | Productos por página | `24` |
| `buscar` | Busca en nombre, referencia y resumen | `roble` |

Los campos vacíos no se envían: si un producto no tiene precio, no aparece `precio`.

`precio` es siempre lo que se paga hoy. Cuando el producto está rebajado llega además `precio_anterior`, que es el que hay que tachar, y `descuento_porcentaje`. Si no llega `precio_anterior`, no hay oferta.

`disponibilidad` vale `available`, `low` o `out_of_stock`. Un producto sin stock **sigue saliendo** en el listado: se marca, no se esconde.

Respuesta:

```json
{
    "datos": [
        {
            "id": 4,
            "nombre": "Silla de roble macizo",
            "slug": "silla-de-roble-macizo",
            "referencia": "SR-2043",
            "resumen": "Madera maciza, disponible en tres acabados.",
            "precio": 119,
            "precio_texto": "119,00 €",
            "precio_anterior": 149,
            "precio_anterior_texto": "149,00 €",
            "descuento_porcentaje": 20,
            "disponibilidad": "low",
            "disponibilidad_texto": "Pocas unidades",
            "imagen": {
                "alt": "Silla de roble vista de frente",
                "miniatura": "https://ejemplo.com/storage/biblioteca/variantes/silla-thumbnail.jpg",
                "mediana": "https://ejemplo.com/storage/biblioteca/variantes/silla-medium.jpg",
                "grande": "https://ejemplo.com/storage/biblioteca/variantes/silla-large.jpg"
            },
            "actualizado_en": "2026-08-19T10:12:00+00:00"
        }
    ],
    "paginacion": {
        "pagina": 1,
        "por_pagina": 24,
        "total": 37,
        "ultima_pagina": 2,
        "hay_mas": true
    }
}
```

### Un producto

```
GET http://localhost:8000/api/v1/productos/{slug}
```

La ficha completa, con la descripción en HTML ya limpio y seguro para insertar.

**Requiere clave.**

| Parámetro | Para qué sirve | Ejemplo |
|---|---|---|
| `slug` | Dirección del producto | — |

Respuesta:

```json
{
    "datos": {
        "id": 4,
        "nombre": "Silla de roble macizo",
        "slug": "silla-de-roble-macizo",
        "referencia": "SR-2043",
        "resumen": "Madera maciza, disponible en tres acabados.",
        "precio": 119,
        "precio_texto": "119,00 €",
        "precio_anterior": 149,
        "precio_anterior_texto": "149,00 €",
        "descuento_porcentaje": 20,
        "disponibilidad": "low",
        "disponibilidad_texto": "Pocas unidades",
        "imagen": {
            "alt": "Silla de roble vista de frente",
            "miniatura": "https://ejemplo.com/storage/biblioteca/variantes/silla-thumbnail.jpg",
            "mediana": "https://ejemplo.com/storage/biblioteca/variantes/silla-medium.jpg",
            "grande": "https://ejemplo.com/storage/biblioteca/variantes/silla-large.jpg"
        },
        "actualizado_en": "2026-08-19T10:12:00+00:00",
        "descripcion_html": "<p>Fabricada en roble macizo…</p>"
    }
}
```

---

## Qué se publica y qué no

Es importante entenderlo para no construir páginas que luego se queden vacías:

- Solo salen los artículos **publicados y con la fecha ya cumplida**. Un borrador o algo
  programado para mañana no aparece, y tampoco responde si pides su dirección directamente.
- Los artículos marcados como **«solo con enlace»** no salen en el listado, pero sí responden
  si pides su dirección concreta. Existen justo para eso: compartirlos sin publicarlos.
- El **listado no incluye el contenido** (`contenido_html`), solo el extracto. El contenido
  completo llega al pedir un artículo concreto. Es para que la portada no descargue de más.
- Del autor solo se expone el **nombre**. El correo no sale nunca.
- Las **categorías y etiquetas vacías no aparecen**: si no tienen ningún artículo publicado, no
  se listan. Así el menú de tu web no muestra secciones sin nada dentro.

### El HTML del contenido

`contenido_html` **ya viene limpio**: se sanea al guardarlo en el blog, con una lista blanca de
etiquetas y permitiendo insertar vídeo solo de YouTube y Vimeo. Puedes insertarlo tal cual sin
volver a sanearlo.

---

## Errores

Siempre en JSON y con la misma forma:

```json
{ "error": { "codigo": "clave_invalida", "mensaje": "La clave de API no es válida." } }
```

| HTTP | Código | Cuándo pasa | Qué hacer |
|---|---|---|---|
| 401 | `clave_ausente` | No se ha enviado la cabecera con la clave. | Comprueba que la variable de entorno con la clave está definida en el servidor. |
| 401 | `clave_invalida` | La clave no existe. | Puede estar mal copiada. Pide una nueva desde el panel. |
| 403 | `clave_no_utilizable` | La clave está revocada o ha caducado. | Pide una clave nueva: una revocada no se puede reactivar. |
| 403 | `origen_no_permitido` | La petición llega desde un dominio que no está autorizado en la clave. | Añade el dominio en el panel, escrito igual que lo envía el navegador. |
| 404 | `no_encontrado` | El artículo no existe, no está publicado o la dirección no es de la API. | Muestra tu página de «no encontrado». No distingas entre los tres casos. |
| 429 | `demasiadas_peticiones` | Se ha superado el límite por minuto. | Cachea las respuestas en tu web y reintenta pasado un minuto. |
| 500 | `error_servidor` | Ha fallado algo en el blog. | Sirve la última copia que tengas cacheada y avisa al administrador. |

---

## CORS: llamar desde el navegador

Si tu web llama a la API **desde el navegador**, hay que autorizar antes su dominio en el panel
del blog. No se usa comodín: se responde con el dominio exacto y solo si está en la lista.

El dominio se escribe **completo y sin nada detrás**, igual que lo envía el navegador:

```
Correcto:   https://www.tuweb.com
Incorrecto: www.tuweb.com          (falta https://)
Incorrecto: https://www.tuweb.com/blog   (sobra la ruta)
```

Recuerda que `https://tuweb.com` y `https://www.tuweb.com` son **dominios distintos** para el
navegador: si tu web responde en los dos, añade los dos.

> Aun así, lo mejor es no llamar desde el navegador: la clave quedaría a la vista.

---

## Límite de peticiones

Son **120 peticiones por minuto**, contadas por clave. Al pasarse, la API responde `429`.

Con cachear las respuestas en tu web sobra: los artículos no cambian cada segundo. Unos pocos
minutos de caché en los listados bastan y hacen tu web más rápida.

---

## Ejemplos

### JavaScript (Node, Next.js, Astro…)

```js
// lib/blog.js
const BASE = 'http://localhost:8000/api/v1'
const CLAVE = process.env.BLOG_API_KEY   // definida en el servidor, nunca en el cliente

async function pedir(ruta) {
  const respuesta = await fetch(`${BASE}${ruta}`, {
    headers: { 'X-API-Key': CLAVE, Accept: 'application/json' },
    next: { revalidate: 300 },   // Next.js: cachea 5 minutos
  })

  if (respuesta.status === 404) return null

  if (!respuesta.ok) {
    const { error } = await respuesta.json()
    throw new Error(`API del blog: ${error?.mensaje ?? respuesta.status}`)
  }

  return respuesta.json()
}

export const listarArticulos = (pagina = 1) =>
  pedir(`/articulos?pagina=${pagina}&por_pagina=12`)

export const verArticulo = (slug) => pedir(`/articulos/${encodeURIComponent(slug)}`)

export const listarCategorias = () => pedir('/categorias')
```

Pintar la portada:

```jsx
const { datos, paginacion } = await listarArticulos()

return (
  <ul>
    {datos.map((articulo) => (
      <li key={articulo.id}>
        <a href={`/blog/${articulo.slug}`}>
          {articulo.imagen_destacada && (
            <img src={articulo.imagen_destacada.mediana} alt={articulo.imagen_destacada.alt} />
          )}
          <h2>{articulo.titulo}</h2>
          <p>{articulo.extracto}</p>
          <time dateTime={articulo.publicado_en}>
            {new Date(articulo.publicado_en).toLocaleDateString('es-ES')}
          </time>
        </a>
      </li>
    ))}
  </ul>
)
```

La página de un artículo, con su SEO:

```jsx
const resultado = await verArticulo(slug)

if (!resultado) return notFound()   // no existe o no está publicado

const { datos: articulo } = resultado

// El HTML ya viene saneado del blog
<article dangerouslySetInnerHTML={{ __html: articulo.contenido_html }} />

// Y para las etiquetas de la cabecera:
// <title>{articulo.seo.titulo}</title>
// <meta name="description" content={articulo.seo.descripcion} />
// {!articulo.seo.indexable && <meta name="robots" content="noindex" />}
// {articulo.seo.canonica && <link rel="canonical" href={articulo.seo.canonica} />}
```

### PHP

```php
<?php

function blog(string $ruta): ?array
{
    $ch = curl_init('http://localhost:8000/api/v1'.$ruta);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['X-API-Key: '.getenv('BLOG_API_KEY'), 'Accept: application/json'],
        CURLOPT_TIMEOUT => 10,
    ]);

    $cuerpo = curl_exec($ch);
    $estado = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($estado === 404) {
        return null;
    }

    if ($estado !== 200) {
        throw new RuntimeException('La API del blog ha devuelto '.$estado);
    }

    return json_decode((string) $cuerpo, true);
}

$portada = blog('/articulos?por_pagina=6');

foreach ($portada['datos'] as $articulo) {
    echo '<h2>'.htmlspecialchars($articulo['titulo']).'</h2>';
}
```

### Comprobar a mano

```bash
# ¿Responde la API? (no necesita clave)
curl http://localhost:8000/api/v1/estado

# Los últimos artículos
curl -H "X-API-Key: TU_CLAVE" "http://localhost:8000/api/v1/articulos?por_pagina=3"
```

---

## Antes de publicar

- [ ] La clave está en una variable de entorno, no en el repositorio.
- [ ] Las llamadas salen del servidor, no del navegador.
- [ ] Si van desde el navegador, el dominio está autorizado en el panel.
- [ ] Los listados están cacheados unos minutos.
- [ ] Un `404` muestra tu página de «no encontrado», no un error.
- [ ] Si la API falla, la web sigue en pie con lo cacheado.
- [ ] El `slug` se usa tal cual llega para construir las direcciones.
- [ ] `contenido_html` se inserta sin volver a sanearlo (ya viene limpio).

---

## Si algo va mal

El administrador del blog tiene, en **Administración → Conexión web**, un probador que ejecuta
cualquier endpoint y enseña la respuesta real. Es el sitio donde comprobar si el problema está
en el blog o en la integración.

Ahí mismo se ve si una clave está activa, cuántas peticiones lleva y desde qué dirección se usó
por última vez.


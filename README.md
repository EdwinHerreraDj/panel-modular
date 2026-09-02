# Panel de Blogs

Área privada para gestionar el blog de una empresa. **Una instalación por empresa**:
desde el panel se administran los usuarios con sus roles y el contenido del blog, y
el superadministrador dispone además de la zona técnica (claves de API, dominios
permitidos para CORS y auditoría). La web pública consume el contenido mediante una
API REST versionada.

## Estado del proyecto

| Fase | Contenido | Estado |
|------|-----------|--------|
| 1 | Autenticación (acceso, cierre de sesión y recuperación de contraseña) | ✅ Completada |
| 2 | Usuarios, roles y permisos | ✅ Completada |
| 3 | Configuración del blog y SEO por defecto | ✅ Completada |
| 4 | Categorías y etiquetas | ✅ Completada |
| 5A | Artículos: núcleo del flujo editorial | ✅ Completada |
| 5B | Artículos: revisiones, autoguardado, duplicación y acciones masivas | ✅ Completada |
| 6 | Biblioteca multimedia | ✅ Completada |
| 7 | SEO y redirecciones | ✅ Completada |
| 8 | API pública, claves y CORS | ✅ Completada |
| 9 | Caché, seguridad y auditoría | ✅ Completada |
| 10 | Pruebas y documentación | ✅ Completada |
| 11 | Preparación para producción | ✅ Completada |

## Documentación

| Documento | Para quién |
|---|---|
| Este README | Quien mantiene el código |
| [docs/DESPLIEGUE.md](docs/DESPLIEGUE.md) | Quien instala el panel en un cliente |
| [docs/API-BLOG.md](docs/API-BLOG.md) | Quien construye la web pública |
| [docs/MANUAL.md](docs/MANUAL.md) | El cliente que va a escribir |
| [docs/MODULARIZAR.md](docs/MODULARIZAR.md) | Quien convierta esto en una plataforma modular |

Los tres últimos **se generan del código**, no se mantienen a mano:

```bash
php artisan api:documentar --openapi
php artisan panel:manual
```

El manual se ve además dentro del panel, en **Ayuda** (menú de usuario), y solo
enseña las secciones que esa persona puede aprovechar: a un autor no le sirve
leer cómo se dan de alta usuarios. Desde ahí se descarga para imprimirlo.

### Preparado para módulos

Cuatro tablas son **polimórficas a propósito**, para que el día que se añadan
otros tipos de contenido (un catálogo, servicios) no haya que migrar los datos
de cada cliente:

| Tabla | Columnas |
|---|---|
| `media_usages` | `usable_type` · `usable_id` |
| `activity_logs` | `subject_type` · `subject_id` |
| `revisions` | `revisionable_type` · `revisionable_id` |
| `redirects` | `redirectable_type` · `redirectable_id` |

Las rutas están repartidas siguiendo esa misma frontera: `routes/panel/nucleo.php`
y `routes/panel/blog.php`, con `routes/web.php` como índice.

El mapa completo de lo que falta por abrir está en
[docs/MODULARIZAR.md](docs/MODULARIZAR.md).

## Roles

| Rol | Alcance |
|---|---|
| **Superadministrador** | Todo, y en exclusiva: usuarios, claves de API, dominios CORS y auditoría |
| **Editor** | Crea, revisa, modifica, publica, programa y archiva cualquier artículo; gestiona categorías, etiquetas y medios |
| **Revisor** | Revisa, aprueba o rechaza contenido; no publica |
| **Autor** | Crea y modifica sus propios artículos y los envía a revisión; no publica |
| **Lector** | Solo consulta |

## Slugs

Los slugs **nunca se piden en los formularios**: se generan automáticamente a partir
del nombre o del título, quitando acentos y signos y resolviendo duplicados con un
sufijo numérico. Los registros de la papelera siguen reservando su slug mientras se
puedan restaurar.

Mientras el contenido no se usa, el slug sigue al nombre: así una errata recién
corregida no queda para siempre en la URL. En cuanto empieza a usarse se congela
(`is_slug_locked`) y deja de cambiar solo. Quien tenga permiso puede regenerarlo
mediante una acción explícita, que deja además una [redirección 301](#redirecciones)
desde la dirección anterior.

## Requisitos

- PHP **8.3+** con `pdo_mysql`, `gd`, `exif`, `fileinfo`, `intl`, `mbstring`, `zip`
- Composer 2
- Node.js 22+ y npm 10+
- MySQL 8+ / MariaDB

## Stack

| Componente | Versión |
|---|---|
| Laravel | 13 |
| Inertia (servidor / cliente) | 3.1 / 3.6 |
| React | 19 |
| TypeScript | 5.9 (modo estricto) |
| Vite | 8 |
| Tailwind CSS | 3.4 (el que usa la plantilla base) |
| Pest | 4 |
| Larastan | 3 (nivel 6) |

## Puesta en marcha

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
```

Crea las dos bases de datos y ajusta las credenciales en `.env`:

```sql
CREATE DATABASE area_privada_blog       CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE area_privada_blog_test  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
php artisan migrate
php artisan panel:crear-superadmin   # pide los datos de forma interactiva
npm run build                        # o `npm run dev` en desarrollo
php artisan serve
```

Alternativa solo para desarrollo: define `SEED_SUPER_ADMIN_PASSWORD` en `.env` y
ejecuta `php artisan db:seed`.

## Comandos habituales

| Comando | Qué hace |
|---|---|
| `npm run dev` | Servidor de Vite con recarga en caliente |
| `npm run build` | Comprueba los tipos y compila los assets |
| `npm run types` | Solo comprobación de tipos (`tsc --noEmit`) |
| `./vendor/bin/pest` | Pruebas automatizadas |
| `./vendor/bin/phpstan analyse` | Análisis estático (nivel 6) |
| `./vendor/bin/pint` | Formato del código PHP |
| `php artisan panel:instalar` | Prepara una instalación nueva de principio a fin |
| `php artisan panel:comprobar` | Revisa que el entorno esté listo para atender público |
| `php artisan panel:crear-superadmin` | Alta de un superadministrador |
| `php artisan api:documentar --openapi` | Regenera la documentación de la API en `docs/` |
| `php artisan panel:limpiar-registros` | Borra los registros de auditoría antiguos |
| `php artisan panel:manual` | Genera el manual del cliente en `docs/` |

## Diseño

El área privada reutiliza la plantilla **WowDash (Tailwind CSS)**. Sus recursos ya
están copiados dentro del proyecto:

- `resources/scss/` — SCSS original de la plantilla (sin modificar) más los
  añadidos propios en `resources/scss/app/`.
- `resources/vendor/remixicon/` — fuente de iconos `ri-*`, con el `@font-face`
  reducido a woff2/woff.
- `public/images/` — logotipos e ilustraciones de las pantallas de acceso.
- `tailwind.config.js` — paleta y breakpoints de la plantilla.

Los iconos de Iconify (`mage:`, `solar:`, `heroicons:`…) se compilan a SVG en línea
con `unplugin-icons`: no hay peticiones a ninguna CDN en tiempo de ejecución.

La carpeta `plantilla-base-diseño/` es **solo material de referencia**: la
aplicación compila, arranca y pasa las pruebas sin ella.

## Hosting compartido

Este panel se instala **una vez por cliente**, siempre en un subdominio y
siempre en alojamiento compartido. Eso condiciona el diseño entero: nada de
workers, nada de servicios externos, y cuidado con todo lo que crezca sin
límite.

### Instalar en un cliente

El procedimiento completo está en **[docs/DESPLIEGUE.md](docs/DESPLIEGUE.md)**:
subdominio, base de datos, `.env`, cron, copias de seguridad y qué hacer cuando
el alojamiento no deja cambiar la carpeta raíz o no admite enlaces simbólicos.

Dos comandos hacen el trabajo repetitivo:

```bash
php artisan panel:instalar     # clave, migraciones, roles, enlace, superadmin y caché
php artisan panel:comprobar    # ¿está lista para atender público?
```

`panel:comprobar` es la lista de comprobación convertida en código: PHP y sus
extensiones, memoria disponible, `APP_ENV` y `APP_DEBUG`, que `APP_URL` no sea
local, conexión y **hora** de la base de datos, migraciones pendientes,
permisos de escritura, el enlace `public/storage`, que el correo se envíe de
verdad, que la API exija clave y que haya un superadministrador activo. Cada
problema sale con la solución al lado, y el comando termina con error mientras
quede algo por resolver.

Conviene ejecutarlo después de cada actualización y cada vez que se toque el
`.env`.

### Zona horaria de la base de datos

`DB_TIMEZONE` fija la zona de la sesión de MySQL para que **coincida con la de
la aplicación**. Por defecto MySQL usa la del sistema, que en un compartido casi
nunca es UTC: eso deja dos relojes distintos y basta una consulta con `NOW()`
para que las fechas se desvíen horas.

> **Fíjala antes de la primera instalación.** Las columnas `TIMESTAMP` se
> convierten al leerlas usando esta zona: cambiarla con datos ya guardados
> desplazaría todas las fechas.

### Registros de auditoría

`auth_logs` y `activity_logs` crecen con cada visita al panel. `LOGS_RETENTION_DAYS`
(365 por defecto) marca cuánto se conservan; cero los guarda para siempre.

La poda la hace el comando `panel:limpiar-registros`, programado de madrugada. Si
el alojamiento no tiene cron, el middleware de mantenimiento la ejecuta como
mucho una vez al día, después de responder. Se borra por lotes para no bloquear
la tabla.

La aplicación está pensada para funcionar **sin workers en segundo plano**:

- `QUEUE_CONNECTION=sync` — todo se ejecuta durante la petición. No hay que dejar
  corriendo `php artisan queue:work`.
- Las variantes de imagen se generan al subir el archivo.
- La publicación programada funciona con cron o, si no lo hay, con la red de
  seguridad descrita más arriba.

El límite real al procesar imágenes **no es el peso del archivo, sino la memoria**:
una imagen ocupa unos 4 bytes por píxel al abrirla, así que una foto de 4000×4000
necesita unos 64 MB solo para empezar. Por eso:

- El máximo admitido son **4000×4000 px y 5 MB**.
- Antes de aceptar nada se comprueba que la imagen **quepa en la memoria disponible**
  ([ImageFitsInMemory](app/Rules/ImageFitsInMemory.php)) y, si no cabe, se rechaza con
  un aviso que explica qué hacer, en vez de morir con una pantalla en blanco.
- Si el alojamiento declara un `memory_limit` que en la práctica no sostiene, se puede
  bajar a mano con `MEDIA_MAX_PROCESSING_MEMORY=96M`.

## Arquitectura

```
app/
  Actions/Auth/         procesos de negocio (AttemptLogin)
  Console/Commands/     comandos de administración
  Enums/                UserStatus, AuthEvent
  Http/
    Controllers/Auth/   acceso, recuperación y restablecimiento
    Middleware/         SecurityHeaders, EnsureUserIsActive, HandleInertiaRequests
    Requests/Auth/      validación (mensajes en español)
  Models/               User, AuthLog
  Support/              PasswordRules
resources/
  js/Components/UI/     componentes reutilizables tipados
  js/Components/Layout/ sidebar, navbar, migas de pan, menú de usuario
  js/Layouts/           AuthLayout, DashboardLayout
  js/Pages/             páginas Inertia
  scss/                 estilos (plantilla + añadidos propios)
lang/es/                mensajes de validación y autenticación
```

## Seguridad implementada (fase 1)

- Contraseñas con `bcrypt` y política mínima de 12 caracteres con mayúsculas,
  minúsculas, números, símbolos y comprobación contra filtraciones conocidas.
- Limitación de intentos de acceso por combinación de correo + IP (5 por minuto)
  y limitación adicional por ruta.
- Regeneración del identificador de sesión tras el acceso e invalidación completa
  al cerrar sesión.
- Cookies `HttpOnly`, `SameSite=Lax` y sesión cifrada; `Secure` activable por
  entorno.
- Mensaje de error genérico e igual tiempo de respuesta exista o no la cuenta,
  para impedir la enumeración de usuarios.
- Cuentas suspendidas o sin activar bloqueadas al acceder y también durante una
  sesión ya iniciada.
- Cierre de todas las sesiones al restablecer la contraseña.
- Cabeceras de seguridad en todas las respuestas y HSTS activable en producción.
- Registro de auditoría en `auth_logs` de accesos, fallos, bloqueos, cierres de
  sesión y restablecimientos, sin guardar nunca datos sensibles.

## Rutas

| Método | URI | Nombre |
|---|---|---|
| GET | `/acceso` | `login` |
| POST | `/acceso` | — |
| GET | `/recuperar-contrasena` | `password.request` |
| POST | `/recuperar-contrasena` | `password.email` |
| GET | `/restablecer-contrasena/{token}` | `password.reset` |
| POST | `/restablecer-contrasena` | `password.store` |
| POST | `/salir` | `logout` |
| GET | `/panel` | `dashboard` |
| GET/PUT | `/cambiar-contrasena` | `password.change` |
| GET/PUT | `/mi-cuenta` | `profile.edit` · `profile.update` |
| GET | `/articulos` | `articles.index` |
| GET/POST | `/articulos/crear` · `/articulos` | `articles.create` · `articles.store` |
| GET/POST | `/articulos/{article}/editar` · `/articulos/{article}` | `articles.edit` · `articles.update` |
| POST | `/articulos/{article}/direccion` | `articles.slug` |
| POST | `/articulos/{article}/duplicar` | `articles.duplicate` |
| GET | `/articulos/{article}/vista-previa` | `articles.preview` |
| GET | `/articulos/{article}/versiones` | `articles.revisions.index` |
| GET/POST | `/articulos/{article}/versiones/{revision}` | `articles.revisions.show` · `...restore` |
| POST/DELETE | `/articulos/{article}/autoguardado` | `articles.autosave` · `...discard` |
| POST | `/articulos/acciones/estado` | `articles.bulk.status` |
| DELETE/POST | `/articulos/{article}` · `/articulos/{article}/restaurar` | `articles.destroy` · `articles.restore` |
| GET/POST | `/biblioteca` | `media.index` · `media.store` |
| GET/PUT/DELETE | `/biblioteca/{media}` | `media.show` · `media.update` · `media.destroy` |
| POST/DELETE | `/biblioteca/{media}/restaurar` · `/definitivo` | `media.restore` · `media.purge` |
| GET/POST | `/selector-imagenes` | `media.picker` · `media.picker.store` |
| GET | `/categorias` | `categories.index` |
| GET/POST | `/categorias/crear` · `/categorias` | `categories.create` · `categories.store` |
| GET/PUT | `/categorias/{category}/editar` · `/categorias/{category}` | `categories.edit` · `categories.update` |
| POST | `/categorias/{category}/direccion` | `categories.slug` |
| DELETE/POST | `/categorias/{category}` · `/categorias/{category}/restaurar` | `categories.destroy` · `categories.restore` |
| GET/POST/PUT | `/etiquetas` · `/etiquetas/{tag}` | `tags.index` · `tags.store` · `tags.update` |
| POST | `/etiquetas/{tag}/direccion` | `tags.slug` |
| DELETE/POST | `/etiquetas/{tag}` · `/etiquetas/{tag}/restaurar` | `tags.destroy` · `tags.restore` |
| GET/POST | `/configuracion` | `settings.edit` · `settings.update` |
| GET | `/usuarios` | `users.index` |
| GET/POST | `/usuarios/crear` · `/usuarios` | `users.create` · `users.store` |
| GET/PUT | `/usuarios/{user}/editar` · `/usuarios/{user}` | `users.edit` · `users.update` |
| PUT | `/usuarios/{user}/contrasena` | `users.password` |
| DELETE/POST | `/usuarios/{user}` · `/usuarios/{user}/restaurar` | `users.destroy` · `users.restore` |
| GET | `/registro-accesos` | `auth-logs.index` |
| GET | `/auditoria` | `activity-logs.index` |
| GET/POST/DELETE | `/redirecciones` · `/redirecciones/{redirect}` | `redirects.index` · `...store` · `...destroy` |
| GET/POST | `/conexion-web` · `/conexion-web/probar` | `api.connection` · `api.connection.run` |
| POST/DELETE | `/conexion-web/claves…` | `api-keys.store` · `...revoke` · `...destroy` |

No existe registro público: las cuentas las crea el superadministrador.

## API pública

Lo que consume la web que enseña los artículos. Es de **solo lectura**: desde
aquí no se crea ni se modifica nada.

| Método | URI | Clave | Devuelve |
|---|---|---|---|
| GET | `/api/v1/estado` | No | Si la API responde y cuántos artículos publicados hay |
| GET | `/api/v1/blog` | Sí | Nombre, lema, logotipos y SEO por defecto |
| GET | `/api/v1/articulos` | Sí | Listado paginado (`pagina`, `por_pagina`, `categoria`, `etiqueta`, `buscar`) |
| GET | `/api/v1/articulos/{slug}` | Sí | El artículo con su HTML y sus relacionados |
| GET | `/api/v1/categorias` | Sí | Categorías con al menos un artículo publicado |
| GET | `/api/v1/etiquetas` | Sí | Etiquetas en uso |
| GET | `/api/v1/redirecciones` | Sí | Direcciones antiguas y su 301 |
| GET | `/api/v1/sitemap` | Sí | Todo lo publicado con su última modificación |

Las claves de JSON van **en español**, igual que el panel.

**Qué sale y qué no.** Solo lo publicado y con fecha ya cumplida: un borrador o
algo programado no se filtra ni conociendo su dirección. Los artículos «solo con
enlace» no aparecen en el listado pero sí responden por su dirección, que es
justo para lo que existen. El correo del autor no se expone nunca.

**Claves.** Una por web. Se guarda su hash, nunca el texto: se enseña una sola
vez al crearla y, si se pierde, se genera otra. Cada una registra cuántas
peticiones lleva y cuándo se usó por última vez, así que se puede revocar la de
un consumidor concreto sin tocar a los demás.

**CORS.** No se usa comodín: se responde con el origen exacto solo si está
autorizado, en la clave o en `API_ALLOWED_ORIGINS`. El CORS que trae Laravel
está desactivado en `config/cors.php` a propósito — su valor por defecto es
`allowed_origins => ['*']` y sobrescribía el nuestro, dejando la API abierta a
cualquier web.

**Límite.** `API_RATE_LIMIT` peticiones por minuto, contadas **por clave** para
que dos webs no se gasten la cuota entre ellas. Al pasarse, la API responde 429.

Los errores salen siempre en JSON y en español, con `error.codigo` y
`error.mensaje`, aunque los lance el propio framework.

### Redirecciones

Cuando la dirección de un contenido **publicado** cambia, se guarda sola una
redirección 301 de la vieja a la nueva. Se sirven todas juntas en
`/api/v1/redirecciones` para que la web las cargue al arrancar o las cachee, en
vez de preguntar en cada visita.

Solo se guardan las direcciones que **llegaron a ser públicas**. Un borrador
renombrado cinco veces mientras se escribe no deja rastro: esas direcciones no
las vio nadie, y la lista se la descarga entera la web.

Tres cosas que parecen detalles y no lo son:

- **No se encadenan.** Si `a → b` y luego `b` pasa a `c`, queda `a → c`, no
  `a → b → c`. Un navegador solo sigue unos pocos saltos y cada uno cuesta una
  petición.
- **No se hacen bucles.** Si la dirección vuelve a un valor anterior, la
  redirección que salía de él se borra: si no, la web echaría al visitante del
  artículo que acaba de encontrar.
- **Se normalizan.** `/Blog/Mi-Post/` y `blog/mi-post` son la misma dirección, y
  se reconocen como tal al comparar y al validar que no esté repetida.

También se pueden añadir a mano, que es lo que hace falta al **traer un blog que
ya existía**: cada dirección vieja apuntando a la nueva, sin perder lo indexado.
El destino puede ser un artículo o una URL completa de otro sitio.

Cada redirección cuenta sus visitas, así que se ve cuáles siguen usándose y
cuáles se pueden retirar.

### Caché de las respuestas

`API_CACHE_SECONDS` (300 por defecto) guarda las respuestas para no repetir el
trabajo en cada visita. Cero la desactiva.

El almacén en compartido es la base de datos, que **no admite etiquetas**, así
que la invalidación va por **número de versión**: forma parte de cada clave, y
subirlo deja obsoleto todo lo guardado de golpe. Es atómico y no hay que
recorrer nada.

Se invalida sola al guardar, borrar o restaurar cualquier artículo, categoría,
etiqueta, archivo o la configuración. También hay un botón «Vaciar ahora» en la
pantalla de conexión.

Detalles que importan:

- Se guarda **solo el cuerpo**, nunca las cabeceras: la de CORS depende del
  dominio que pregunta, y servir a una web la que se calculó para otra sería un
  agujero.
- Va **después** de la clave, así que una clave revocada deja de servir en el
  acto y el contador de uso sigue siendo real aunque responda la caché.
- **Los errores no se cachean**: perpetuarlos más allá de su causa sería peor
  que recalcularlos.
- Las respuestas llevan `X-Cache: HIT|MISS`, y el probador del panel lo enseña.

### Documentación para quien haga la web

En [`docs/API-BLOG.md`](docs/API-BLOG.md) hay un documento completo para
entregárselo a quien vaya a integrar el blog: endpoints, ejemplos de respuesta,
tabla de errores con qué hacer en cada caso, CORS, límites, código de ejemplo en
JavaScript y PHP, y una lista de comprobación antes de publicar.

**No se mantiene a mano**: se genera desde el mismo catálogo que usa el panel, con
la dirección y los límites reales de la instalación.

```bash
php artisan api:documentar --openapi
```

Desde el panel se descarga en dos formatos: **Markdown** para leer y
**OpenAPI 3.1** para importar en Postman o Insomnia y tener las llamadas
preparadas.

Tres cosas cuidadas a propósito:

- La **clave nunca aparece** en el documento; se entrega aparte y por otro canal.
- Si `APP_URL` todavía apunta a `localhost`, el documento y el panel **avisan** de
  que esa dirección no sirve fuera de tu ordenador.
- Hay pruebas que comparan las claves JSON documentadas con las que devuelve la
  API de verdad, así que la documentación no puede quedarse desfasada en silencio.

### La pantalla «Conexión web»

En el panel, solo para quien puede gestionar claves. Reúne:

- **Estado en vivo**: llama de verdad al endpoint de estado al abrir la pantalla.
- **Probador**: ejecuta cualquier endpoint con sus parámetros y enseña el JSON
  real, el código HTTP, lo que ha tardado y lo que ocupa. Con opción de repetir
  cada 10 segundos para ver los datos cambiar. La petición se despacha dentro
  del servidor, no sale a la red: en un alojamiento compartido llamarse a uno
  mismo suele estar capado. Solo alcanza rutas del catálogo de la API.
- **Claves y dominios**: crear, revocar y borrar.
- **Cómo conectar la web**: la documentación descargable, los pasos y el código.

## Flujo editorial

| Estado | Quién puede dejarlo así |
|---|---|
| Borrador · Pendiente de revisión | Cualquiera que pueda editar el artículo |
| Aprobado | `articles.approve` (revisor, editor) |
| Programado | `articles.schedule` (editor) |
| Publicado · Archivado | `articles.publish` (editor) |

Las transiciones permitidas están declaradas en [ArticleStatus](app/Enums/ArticleStatus.php)
y se comprueban en el servidor: no basta con conocer la URL. El formulario solo ofrece
los estados alcanzables **con los permisos de quien lo está usando**.

Los artículos programados los publica el comando `articulos:publicar-programados`. Si
el alojamiento admite cron, basta con una entrada:

```
* * * * * cd /ruta/del/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

**Si no hay cron**, la programación sigue funcionando: un middleware comprueba lo
pendiente durante las visitas al panel, como mucho una vez por minuto y siempre
después de responder, así que no retrasa ninguna página. La contrapartida es que un
artículo programado se publica en cuanto alguien entra al panel pasada su hora, no
exactamente a esa hora. Con cron configurado se puede desactivar con
`ARTICLES_PUBLISH_ON_REQUEST=false`.

## Historial y guardado automático

Cada guardado deja una versión en el historial con el estado completo del artículo,
incluidas categorías, etiquetas y relacionados. Se conservan las últimas
`ARTICLES_MAX_REVISIONS` (30 por defecto).

Mientras se escribe, cada `ARTICLES_AUTOSAVE_SECONDS` (30 por defecto) se guarda una
**copia aparte**: el artículo no cambia hasta pulsar Guardar, así que una edición a
medias de algo ya publicado nunca llega a la web. Solo se conserva la última copia, y
al guardar de verdad se descarta. Al volver al editor se avisa de que hay una copia
pendiente y se ofrece recuperarla o descartarla.

**Restaurar una versión no cambia el estado de publicación.** Recuperar un texto
antiguo no debe despublicar ni publicar nada por su cuenta. Antes de sobrescribir se
guarda una versión del estado actual, y el contenido restaurado vuelve a pasar por el
sanitizador por si las reglas han cambiado desde que se guardó.

## Contenido y seguridad

El HTML del editor **nunca se guarda tal cual llega**. Se aplica una lista blanca
estricta ([config/sanitizer.php](config/sanitizer.php)) con
`symfony/html-sanitizer`: fuera scripts, manejadores de eventos en línea, esquemas
`javascript:` y `data:`, e iframes de cualquier dominio que no sea YouTube o Vimeo.
Los enlaces salen siempre con `rel="noopener noreferrer"`.

Cuando la limpieza deja un elemento inservible —un `<iframe>` sin origen o un `<a>`
sin destino— se retira también el envoltorio, conservando el texto: si no, quedarían
recuadros en blanco y enlaces muertos en la página publicada.

## Biblioteca multimedia

Las imágenes se suben una vez y se reutilizan: la imagen destacada de un artículo es
una referencia a la biblioteca, no un archivo propio.

De cada imagen se generan variantes de **300, 768 y 1400 px**, cada una en su formato
y en **WebP**, más un WebP del original. Nunca se agranda una imagen: si el original
es menor que la variante, esa variante no se genera y se sirve el original.

`media_usages` registra dónde se usa cada archivo. **No se puede eliminar algo que
está publicado**: el aviso dice exactamente en qué artículo y en qué papel se está
usando.

Hay tres niveles de borrado:

| Acción | Quién | Efecto |
|---|---|---|
| Enviar a la papelera | `media.delete` (editor, superadmin) | Borrado lógico; los archivos siguen en disco y se puede restaurar |
| Eliminar definitivamente | Superadministrador | Borra del disco el original y todas sus variantes. Irreversible |
| Vaciar la papelera | Superadministrador | Igual, para todo lo que haya en la papelera de una vez |

Al vaciar la papelera se **conservan** los archivos que hayan vuelto a usarse mientras
estaban descartados: es preferible dejar algo de sobra a romper una página publicada.

Las variantes se generan **durante la subida** (`MEDIA_QUEUE_CONNECTION=sync`): no
hace falta ningún worker. Ver más abajo las notas de hosting compartido.

Si alguna imagen se queda sin procesar —una importación, un error puntual— hay un
comando para ponerlas al día:

```bash
php artisan medios:procesar-pendientes            # solo las pendientes
php artisan medios:procesar-pendientes --fallidas # reintenta también las que fallaron
php artisan medios:procesar-pendientes --todas    # regenera toda la biblioteca
```

## Subida de imágenes

Las restricciones de cada tipo de imagen viven en [config/media.php](config/media.php):
formatos, peso y dimensiones mínimas y máximas. La validación se hace **sobre el
contenido real** del archivo (`finfo` y `getimagesize`), nunca sobre la extensión ni
sobre el `Content-Type` que envía el navegador, y se repite en el servicio de guardado
para que también proteja a los comandos y los trabajos en cola.

El nombre del archivo se genera siempre (ULID + extensión deducida del tipo detectado):
el nombre original se descarta por completo. **No se admite SVG** en ningún caso, porque
puede contener scripts y se serviría desde nuestro propio dominio.

Cambiar `MEDIA_DISK` en `.env` es todo lo necesario para pasar a S3: ningún punto del
código nombra el disco directamente.

## Gestión de usuarios

El superadministrador da de alta cada cuenta fijando una contraseña inicial. Como esa
contraseña la conoce otra persona, quien recibe la cuenta queda **retenido en la
pantalla de cambio de contraseña** hasta que la sustituye: ninguna otra ruta del panel
responde mientras tanto. Lo mismo ocurre cuando un administrador restablece la
contraseña de alguien, acción que además cierra todas sus sesiones abiertas.

Salvaguardas contra la escalada de privilegios y el bloqueo accidental:

- Nadie puede cambiar su propio rol ni su propio estado.
- Nadie puede enviarse a sí mismo a la papelera.
- La aplicación no puede quedarse sin ninguna cuenta de superadministrador activa.
- Las cuentas nunca se borran de forma definitiva: quedan en la papelera para
  conservar la trazabilidad de lo que hicieron.

# Mapa para convertir el panel en modular

Este documento no propone una arquitectura: describe **dónde están las costuras
del código actual**, con datos, para que quien haga la conversión no tenga que
descubrirlo.

El objetivo es que se puedan añadir módulos independientes (catálogo,
servicios, eventos…) activando en cada cliente solo los que necesite.

> Lo que aquí se llama «núcleo» es lo que no depende del tipo de contenido.
> «Blog» es lo que se convertirá en el primer módulo.

---

## Lo que ya está preparado

No hace falta tocarlo, y conviene saberlo antes de rediseñar nada:

| Pieza | Por qué ya sirve |
|---|---|
| `media_usages` | **Polimórfica** (`usable_type`/`usable_id`). La biblioteca funciona con cualquier modelo |
| `activity_logs` | **Polimórfica** (`subject_type`/`subject_id`). La auditoría también |
| `revisions` | **Polimórfica** (`revisionable_type`/`revisionable_id`). Historial y guardado automático valen para cualquier contenido |
| `redirects` | **Polimórfica** (`redirectable_type`/`redirectable_id`). Un producto cambia de dirección igual que un artículo |
| Autenticación, usuarios, roles y permisos | Sin nada del blog dentro |
| API: claves, CORS, caché, límite, probador, documentación | Sin nada del blog dentro |
| Sistema de diseño: 41 componentes React | Compartidos por todas las pantallas |
| Instalación y comprobación del entorno | Genéricos |

Las cuatro tablas polimórficas se dejaron así **a propósito y antes de la
primera instalación**: cambiarlas después obliga a migrar los datos de cada
cliente uno a uno.

## Cómo están repartidas las rutas

`routes/web.php` es solo un índice. La división ya sigue la frontera futura:

```
routes/
  web.php              índice: monta el grupo autenticado y carga los demás
  panel/nucleo.php     cuenta, ayuda, biblioteca, redirecciones, usuarios,
                       configuración, conexión web y auditoría
  panel/blog.php       artículos, categorías y etiquetas
```

Cuando el blog sea un módulo, `panel/blog.php` se muda con él y el núcleo no se
entera. Añadir un módulo será añadir su archivo al índice.

---

## Las cinco listas que siguen cerradas

Aquí está el trabajo. Son listas escritas a mano que deben pasar a componerse
sumando lo que aporta cada módulo activo.

### 1. Permisos — `app/Enums/Permission.php`

17 casos en un enum, que por definición no se puede extender. Los usan las
policies, el middleware `can:`, el sembrador y el selector de roles.

**Al abrirlo:** un registro que agregue los casos del enum del núcleo más los
que declare cada módulo, y que el sembrador y la interfaz consulten al registro
en lugar de al enum. Ojo con el **ámbito**: hoy un editor puede con todo el
contenido; con varios módulos, un cliente puede querer que alguien gestione el
catálogo pero no el blog. Merece la pena prefijar por módulo
(`catalogo.products.update`) desde el principio.

### 2. Menú — `resources/js/Components/Layout/Sidebar.tsx`

12 entradas escritas en el componente, con sus permisos y sus iconos.

**Al abrirlo:** el menú pasa a ser **datos**, compuestos en el servidor y
compartidos por `HandleInertiaRequests`. El componente solo lo pinta. Es un
cambio que mejora el código aunque nunca se modularice.

### 3. Catálogo de la API — `app/Support/ApiCatalog.php`

8 endpoints en un array. De ahí salen **el probador, la documentación
descargable y el OpenAPI**, así que abrirlo bien tiene premio: un módulo aporta
sus endpoints y aparecen solos en los tres sitios.

**No dupliques esta pieza por módulo.** Es lo que mantiene la documentación al
día sin mantenerla a mano.

### 4. Auditoría — `ActivityLogController::SUBJECTS`

11 clases con su nombre en español, para el filtro «Sobre qué». La tabla ya es
polimórfica: lo único cerrado es esta lista.

### 5. Panel de inicio — `DashboardController`

Las cifras y los avisos son del blog. Cada módulo debería aportar sus tarjetas y
sus avisos accionables, respetando lo que ya hace el controlador: **cada rol ve
lo suyo**.

---

## Lo que decidimos NO hacer aquí

**No se construyó el registro de módulos.** Es la decisión de diseño principal
de la conversión, y adelantarla en este repositorio habría chocado con lo que se
acuerde en el nuevo.

**No se separó la tabla `settings`.** Se estudió y no hace falta: sus columnas
(nombre del sitio, logotipos, SEO por defecto, redes) son de **sitio**, no de
blog. Un módulo que necesite ajustes propios puede añadir los suyos sin tocarla.

---

## Avisos para quien haga la conversión

**Las 439 pruebas son la red.** El criterio de que el corte es correcto:
convertir el blog en módulo **sin cambiar su comportamiento** y con las pruebas
en verde. Si para conseguirlo hay que tocar muchas pruebas, el corte está mal.

**Un módulo debe poder borrarse entero.** Si borrar su carpeta rompe algo fuera,
hay una fuga de acoplamiento.

**Nada de motores genéricos de tipos de contenido.** Un módulo tiene tablas
reales, modelo real y validación real. Un JSON con campos dentro se paga en
cuanto haya que ordenar o filtrar por uno de ellos.

**Lo que más va a costar no es el backend, es el frontend**: resolver páginas de
Inertia repartidas en carpetas de módulos y que Vite las compile. Abórdalo
pronto. Si eso no sale limpio, el resto del plan no sirve.

**Sigue mandando el alojamiento compartido**: sin workers, sin servicios
externos, y cuidado con lo que crezca sin límite. Ver [DESPLIEGUE.md](DESPLIEGUE.md).

Las trampas concretas de este código (el CORS de Laravel, `Route::dispatch`, la
configuración cacheada, `DB_TIMEZONE`, el `Gate::before`) están en el README.

# Instalar el panel en un cliente

Guía para poner el panel en un **subdominio** de un alojamiento **compartido**.
Es el mismo procedimiento para todos los clientes, así que conviene seguirlo tal
cual: lo que aquí se salta, se paga después.

Tiempo aproximado: **30 minutos** la primera vez, 10 cuando ya lo has hecho un
par de veces.

---

## Antes de empezar

Necesitas del alojamiento:

- **PHP 8.3 o superior**, con `pdo_mysql`, `gd`, `exif`, `fileinfo`, `mbstring` e `intl`
- **MySQL 8** o MariaDB
- Acceso por **SSH** (si no lo hay, mira [Sin acceso SSH](#sin-acceso-ssh))
- Poder crear un **subdominio** y apuntar su carpeta raíz
- **Certificado SSL** para el subdominio (Let's Encrypt vale)
- Datos de **SMTP** para enviar correo

Y del cliente:

- El dominio de la web pública que va a mostrar los artículos
- Nombre del blog y logotipo

> **Comprueba el `memory_limit` antes de nada.** Con menos de 128 MB las
> imágenes grandes no se pueden procesar. Suele ajustarse desde el panel del
> alojamiento.

---

## 1. El subdominio

Crea `blog.tucliente.com` (o `panel.tucliente.com`) y **apunta su carpeta raíz a
`public/`** del proyecto, no a la carpeta del proyecto.

Ejemplo, subiendo el proyecto a `~/paneles/tucliente`:

```
Subdominio:     blog.tucliente.com
Carpeta raíz:   /home/usuario/paneles/tucliente/public
```

Esto importa: si la raíz apunta al proyecto entero, quedan accesibles desde
internet el `.env`, la base del código y `storage/`. **Es la equivocación más
grave de toda la instalación.**

Si tu alojamiento no deja elegir la carpeta, mira
[Si la raíz no se puede cambiar](#si-la-raíz-no-se-puede-cambiar).

Activa el certificado SSL antes de continuar.

---

## 2. Subir el proyecto

Con Git:

```bash
cd ~/paneles
git clone <repositorio> tucliente
cd tucliente
```

Si el alojamiento no tiene Git, sube por SFTP **todo menos** `node_modules/`,
`vendor/`, `.env` y `.git/`.

### Dependencias

```bash
composer install --no-dev --optimize-autoloader
```

`--no-dev` deja fuera las herramientas de desarrollo: menos archivos y menos
superficie expuesta.

**Los assets se compilan en tu máquina, no en el servidor.** Un compartido no
suele tener Node y, si lo tiene, se queda sin memoria:

```bash
# En tu ordenador
npm run build
# y sube la carpeta public/build al servidor
```

---

## 3. La base de datos

Créala desde el panel del alojamiento con **utf8mb4**:

```
Nombre:   tucliente_blog
Usuario:  tucliente_blog
```

Guarda la contraseña: hace falta en el paso siguiente.

---

## 4. Configuración

```bash
cp .env.example .env
nano .env
```

Lo que **hay que cambiar sí o sí**:

```dotenv
APP_NAME="Blog de Tu Cliente"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://blog.tucliente.com

DB_DATABASE=tucliente_blog
DB_USERNAME=tucliente_blog
DB_PASSWORD=la-que-generaste

# Zona horaria: la misma en los dos sitios
APP_TIMEZONE=UTC
DB_TIMEZONE=+00:00

# Correo: sin esto nadie puede recuperar su contraseña
MAIL_MAILER=smtp
MAIL_HOST=smtp.tualojamiento.com
MAIL_PORT=587
MAIL_USERNAME=blog@tucliente.com
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=blog@tucliente.com
MAIL_FROM_NAME="Blog de Tu Cliente"

# Seguridad
SECURITY_HSTS_ENABLED=true
API_REQUIRE_KEY=true

# Dominios de la web pública, si va a llamar a la API desde el navegador
API_ALLOWED_ORIGINS=https://www.tucliente.com
```

> **`DB_TIMEZONE` se fija ahora y no se toca más.** Las fechas guardadas se
> interpretan con esa zona: cambiarla más adelante desplaza todo el historial.

---

## 5. Instalar

```bash
php artisan panel:instalar
```

Hace lo siguiente y te va contando:

1. Genera `APP_KEY`
2. Aplica las migraciones
3. Crea los roles y permisos
4. Enlaza `public/storage`
5. Te pide crear la cuenta de superadministrador
6. Cachea configuración, rutas y vistas
7. Lanza la comprobación final

Si algo falla, lo dice con la solución al lado.

### Permisos

Si el paso 4 se queja:

```bash
chmod -R 755 storage bootstrap/cache
```

---

## 6. Comprobar

```bash
php artisan panel:comprobar
```

Tiene que salir **todo en verde**. Este comando es la lista de comprobación:
si dice que algo falta, falta de verdad.

Repítelo después de cada cambio en el `.env` (recordando `php artisan config:cache`).

---

## 7. Publicación programada

Si el alojamiento admite cron, añade **una sola entrada**:

```cron
* * * * * cd /home/usuario/paneles/tucliente && php artisan schedule:run >> /dev/null 2>&1
```

Con eso se publica lo programado al minuto y se podan los registros de noche.

**Si no hay cron, no pasa nada**: la aplicación lleva una red de seguridad que
hace lo mismo después de responder a una visita. La diferencia es que lo
programado se publica cuando alguien entra al panel o la web pide artículos, no
al minuto exacto.

---

## 8. Conectar la web pública

En el panel, **Administración → Conexión web**:

1. **Descarga la documentación** y envíasela a quien haga la web.
2. **Crea una clave** con el nombre de la web. Si va a llamar a la API desde el
   navegador, añade su dominio.
3. **Envía la clave por otro canal**, nunca dentro del documento.
4. Usa el **probador** para confirmar que la API devuelve lo que debe.

---

## 9. Entregar al cliente

- Dirección del panel y su cuenta
- Recuérdale que **cambie la contraseña** al entrar: la que le diste la conoces tú
- Explícale que los artículos tardan unos minutos en verse en la web, por la caché

---

## Casos particulares

### Si la raíz no se puede cambiar

Algunos alojamientos fijan la carpeta del subdominio y no dejan apuntarla a
`public/`. Entonces:

1. Sube el proyecto a `~/paneles/tucliente` (fuera de la carpeta pública).
2. Copia el contenido de `public/` a la carpeta del subdominio.
3. Edita el `index.php` que has copiado y corrige las dos rutas:

```php
require __DIR__.'/../../paneles/tucliente/vendor/autoload.php';

$app = require_once __DIR__.'/../../paneles/tucliente/bootstrap/app.php';
```

4. Sube también el `.htaccess` de `public/`.

**No dejes el proyecto dentro de la carpeta pública** y te ahorras esto.

### Si no se pueden crear enlaces

`php artisan storage:link` necesita enlaces simbólicos, y algunos compartidos
los tienen capados. Prueba a crearlo a mano:

```bash
ln -s ~/paneles/tucliente/storage/app/public ~/paneles/tucliente/public/storage
```

Si tampoco, cambia dónde se guardan los archivos para que caigan ya dentro de
`public/`:

```dotenv
MEDIA_DISK=public_directo
```

y añade el disco en `config/filesystems.php`:

```php
'public_directo' => [
    'driver' => 'local',
    'root' => public_path('storage'),
    'url' => env('APP_URL').'/storage',
    'visibility' => 'public',
    'throw' => false,
],
```

Menos limpio, pero funciona. **No copies los archivos a mano**: se quedan
desactualizados en cuanto se sube una imagen nueva.

### Sin acceso SSH

Se puede, pero es incómodo. Necesitas ejecutar comandos de Artisan y no vas a
poder. Opciones, de mejor a peor:

1. **Terminal del panel del alojamiento** (cPanel y Plesk suelen traerla).
2. **Cambiar de plan**: casi todos tienen SSH en el nivel siguiente.
3. Ejecutar las migraciones desde tu máquina apuntando a la base de datos
   remota, si el alojamiento admite conexiones externas.

No inventes una ruta web que ejecute Artisan: acabaría siendo una puerta abierta.

---

## Actualizar una instalación

```bash
cd ~/paneles/tucliente
php artisan down                    # avisa a quien esté dentro

git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan up
php artisan panel:comprobar
```

Sube antes `public/build` recompilado desde tu máquina.

**Haz copia de la base de datos antes de migrar.** Siempre.

---

## Copias de seguridad

Dos cosas que respaldar:

| Qué | Dónde | Cada cuánto |
|---|---|---|
| Base de datos | La copia del panel del alojamiento, o `mysqldump` | Diaria |
| `storage/app/public` | Las imágenes de la biblioteca | Semanal |

```bash
mysqldump -u tucliente_blog -p tucliente_blog | gzip > copia-$(date +%F).sql.gz
```

Lo demás está en el repositorio y se recupera de ahí.

> Comprueba alguna vez que la copia **se puede restaurar**. Una copia que nunca
> se ha probado no es una copia.

---

## Si algo va mal

**Página en blanco.** `APP_DEBUG` está en `false`, así que el error está en
`storage/logs/laravel.log`. Míralo ahí antes de tocar nada.

**«500» después de actualizar.** Casi siempre la caché de configuración vieja:

```bash
php artisan config:clear && php artisan config:cache
```

**Las imágenes no se ven.** Falta `public/storage`. Lo dice
`php artisan panel:comprobar`.

**Las fechas están desplazadas.** `DB_TIMEZONE` no coincide con `APP_TIMEZONE`.
Lo dice la comprobación, en «Hora».

**La web pública no recibe nada.** Mira el probador de **Conexión web**: dice si
el problema está aquí o en la integración. Comprueba también que la clave no
esté revocada.

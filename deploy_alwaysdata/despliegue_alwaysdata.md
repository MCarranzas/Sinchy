# Despliegue de SINCHI WAYRA en Alwaysdata Free

Guía concreta para publicar la SPA y la API en **Alwaysdata Free** durante una
prueba de 1 a 2 semanas, sin IP pública, sin tarjeta de crédito y sin depender
de una PC encendida.

## Resultado esperado

- URL única: `https://CUENTA.alwaysdata.net`
- SPA Vue servida desde la raíz.
- API Laravel en `/api`.
- Sanctum y la SPA comparten el mismo origen.
- PostgreSQL 17 en Alwaysdata.
- Acceso por SSH/SFTP, Composer y tareas programadas.

> **Alcance:** el plan Free es para uso personal o sin fines de lucro y no
> permite dominio propio. No usar datos reales de clientes ni información
> fiscal sensible durante la prueba.

---

## 1. Datos que hay que anotar

Antes de empezar, completar esta tabla. Los nombres de base de datos y usuario
llevan el prefijo de la cuenta.

| Dato | Ejemplo | Valor real |
|---|---|---|
| Cuenta de Alwaysdata | `sinchy` | |
| URL del sitio | `https://sinchy.alwaysdata.net` | |
| Host SSH/SFTP | `ssh-sinchy.alwaysdata.net` | |
| Usuario SSH/SFTP | `sinchy` | |
| Host PostgreSQL | `postgresql-sinchy.alwaysdata.net` | |
| Puerto PostgreSQL | `5432` | |
| Base de datos | `sinchy_sinchy` | |
| Usuario PostgreSQL | `sinchy_sinchy` | |
| Contraseña PostgreSQL | *(la genera el panel)* | |

La ruta absoluta de la cuenta en el servidor es `/home/CUENTA`. En el panel se
puede ver como `~/`.

---

## 2. Crear la cuenta, el acceso SSH y el sitio

### 2.1 Cuenta

1. Crear la cuenta en <https://www.alwaysdata.com/> con el plan **Free**.
2. Confirmar el correo de verificación.
3. Elegir un nombre de cuenta corto, por ejemplo `sinchy`. Ese nombre define
   el subdominio `sinchy.alwaysdata.net` y los nombres de la base.
4. No hace falta agregar método de pago para el plan Free.

### 2.2 Acceso SSH

En el panel:

1. Ir a **Remote access > SSH**.
2. Verificar que exista el usuario de la cuenta. Si no existe, crearlo.
3. Definir o cambiar la contraseña.
4. Anotar el host: `ssh-CUENTA.alwaysdata.net`, puerto `22`.

Probar desde Windows PowerShell:

```powershell
ssh CUENTA@ssh-CUENTA.alwaysdata.net
```

La primera vez hay que aceptar la huella del servidor. Si se prefiere, también
se puede usar SFTP con FileZilla:

- Host: `ssh-CUENTA.alwaysdata.net`
- Puerto: `22`
- Usuario: `CUENTA`
- Contraseña: la del usuario SSH

### 2.3 Crear las carpetas del proyecto

Ya dentro del servidor:

```bash
mkdir -p ~/sinchy-api/public
mkdir -p ~/backups/sinchy
```

La carpeta `public` se crea antes que el sitio porque el panel de Alwaysdata no
crea el directorio raíz automáticamente.

### 2.4 Configurar el sitio

En el panel, ir a **Web > Sites**:

1. Si ya existe un sitio para `CUENTA.alwaysdata.net`, editarlo. Si no, crear
   uno nuevo con esa dirección.
2. Configuración:
   - **Address:** `CUENTA.alwaysdata.net`
   - **Type:** `PHP`
   - **Root directory:** `/home/CUENTA/sinchy-api/public`
   - **PHP version:** `8.4` (el proyecto fija PHP 8.4.26 en Composer)
3. Guardar.
4. En la sección SSL del sitio, activar **Let's Encrypt** para
   `CUENTA.alwaysdata.net` y forzar HTTPS.

> **Importante:** la raíz del sitio debe ser `sinchy-api/public`, nunca
> `sinchy-api`. Si se apunta a la raíz del proyecto, `.env`, `vendor` y los
> archivos de configuración quedan accesibles por HTTP.

### 2.5 Verificar PHP y extensiones

Por SSH:

```bash
php -v
which php
php -m
```

Debe ser PHP 8.4. Si `php` apunta a otra versión, cambiarla en
**Environment > PHP** o usar el binario de PHP 8.4 que indique el panel
(por ejemplo `php8.4`) en todos los comandos. Para Composer, si hace falta,
ejecutarlo con ese mismo binario: `php8.4 "$(which composer)" ...`.

Extensiones necesarias:

```text
pdo_pgsql, pgsql, mbstring, openssl, bcmath, gd, zip, intl, opcache
```

Si falta alguna, habilitarla en **Environment > PHP** (Extensions) y reiniciar
el sitio. No hace falta Redis, Docker ni workers de cola.

Alwaysdata suele dejar `memory_limit=256M` y `max_execution_time=120`. Se puede
verificar con `php -i | grep -E 'memory_limit|max_execution_time'`. Si aparece
menos de 256M, subirlo en **Environment > PHP**.

---

## 3. Crear PostgreSQL 17

En el panel:

1. Ir a **Databases > PostgreSQL > Add a database**.
2. Completar:
   - **Name:** `sinchy` (el panel lo muestra como `CUENTA_sinchy`)
   - **Version:** `17`
   - **Encoding:** `UTF8`
   - **Locale/Collation:** `en_US.UTF-8` o `C.UTF-8` si no aparece español
   - **User:** crear o asignar uno con **todos los privilegios** sobre esa base
   - **Password:** generar una contraseña larga y guardarla
3. Guardar y anotar los nombres completos que muestra el panel.

Verificar la conexión por SSH:

```bash
psql \
  -h postgresql-CUENTA.alwaysdata.net \
  -p 5432 \
  -U CUENTA_sinchy \
  -d CUENTA_sinchy \
  -W \
  -c "select version();"
```

Se debe ver `PostgreSQL 17.x`.

> Usar el puerto **5432** para migraciones. El puerto **5433** es pgBouncer y
> puede dar problemas con DDL, `CREATE TYPE` y migraciones.

Si `psql` no está disponible en la shell, se puede usar phpPgAdmin desde el
panel. Lo que importa es que Laravel pueda conectarse.

---

## 4. Preparar el paquete en Windows

### 4.1 Compilar la SPA

Requiere Node.js 22+ y npm.

```powershell
cd C:\MarioC\webs_figma\Sinchy\sinchy-web
npm ci
npm run build
```

Comprobar que exista:

```powershell
Test-Path .\dist\index.html
```

La SPA usa rutas relativas (`/api/v1`, `/sanctum/csrf-cookie`), así que no hay
que cambiar `VITE_*` para producción: compilada con `base: "/"` funciona en la
raíz del dominio.

### 4.2 Crear los archivos comprimidos

En PowerShell:

```powershell
$src = "C:\MarioC\webs_figma\Sinchy"
$out = "$env:TEMP\sinchy-deploy"

Remove-Item -Recurse -Force $out -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Force $out | Out-Null

# Backend: sin vendor, sin .env, sin logs ni cachés locales
tar -czf "$out\sinchy-api.tar.gz" -C $src `
  --exclude="sinchy-api/vendor" `
  --exclude="sinchy-api/node_modules" `
  --exclude="sinchy-api/.git" `
  --exclude="sinchy-api/.env" `
  --exclude="sinchy-api/usuarios_prueba.txt" `
  --exclude="sinchy-api/storage/logs/*" `
  --exclude="sinchy-api/storage/framework/cache/*" `
  --exclude="sinchy-api/storage/framework/sessions/*" `
  --exclude="sinchy-api/storage/framework/views/*" `
  --exclude="sinchy-api/bootstrap/cache/*.php" `
  --exclude="sinchy-api/public/hot" `
  --exclude="sinchy-api/public/.htaccess" `
  --exclude="sinchy-api/public/index.html" `
  --exclude="sinchy-api/public/assets/*" `
  sinchy-api

# Frontend compilado
tar -czf "$out\sinchy-web-dist.tar.gz" -C "$src\sinchy-web\dist" .
```

El paquete del backend excluye `public/.htaccess`, `public/index.html` y
`public/assets/` para que una actualización del backend no pise la SPA ni las
reglas de Apache ya instaladas. Esos archivos se suben aparte en los pasos 5.1
y 5.2.

Si el `tar` de Windows no acepta alguna exclusión, se puede usar 7-Zip o subir
por SFTP excluyendo manualmente `vendor`, `node_modules`, `.env`,
`usuarios_prueba.txt`, `.git`, `storage/logs`, las cachés de
`storage/framework`, `public/.htaccess`, `public/index.html` y
`public/assets/`.

### 4.3 Subir los archivos

```powershell
$cuenta = "TU_CUENTA"

scp "$out\sinchy-api.tar.gz" "${cuenta}@ssh-${cuenta}.alwaysdata.net:~/"
scp "$out\sinchy-web-dist.tar.gz" "${cuenta}@ssh-${cuenta}.alwaysdata.net:~/"
```

También se pueden subir con FileZilla a la carpeta raíz `/home/CUENTA`.

Si se abre otra consola de PowerShell, volver a definir `$cuenta` antes de
usar los comandos siguientes.

---

## 5. Desplegar en el servidor

### 5.1 Extraer

```bash
cd ~
tar -xzf sinchy-api.tar.gz
tar -xzf sinchy-web-dist.tar.gz -C ~/sinchy-api/public
rm sinchy-api.tar.gz sinchy-web-dist.tar.gz
```

Comprobar:

```bash
ls -la ~/sinchy-api
ls -la ~/sinchy-api/public
```

Deben existir, entre otros:

```text
~/sinchy-api/artisan
~/sinchy-api/composer.json
~/sinchy-api/public/index.php
~/sinchy-api/public/index.html
~/sinchy-api/public/assets/
```

### 5.2 Subir el `.htaccess` de producción

El archivo del repositorio
[`deploy_alwaysdata/apache.htaccess`](apache.htaccess) contiene las reglas para
que la SPA y la API compartan origen. Subirlo como:

```text
~/sinchy-api/public/.htaccess
```

Desde PowerShell:

```powershell
scp "C:\MarioC\webs_figma\Sinchy\deploy_alwaysdata\apache.htaccess" `
  "${cuenta}@ssh-${cuenta}.alwaysdata.net:~/sinchy-api/public/.htaccess"
```

Reglas que aplica:

- `/api/*`, `/sanctum/*` y `/up` van a `index.php` (Laravel).
- Los archivos reales (`assets`, `favicon`, etc.) se sirven directamente.
- Las rutas de Vue Router que no son archivos devuelven `index.html`.
- La raíz entrega `index.html`.

Guardar el archivo en UTF-8 **sin BOM**. Un BOM al inicio puede hacer que
Apache interprete mal la primera directiva.

### 5.3 Crear el `.env`

Subir la plantilla:

```powershell
scp "C:\MarioC\webs_figma\Sinchy\deploy_alwaysdata\.env.alwaysdata.example" `
  "${cuenta}@ssh-${cuenta}.alwaysdata.net:~/sinchy-api/.env"
```

Editarla en el servidor:

```bash
nano ~/sinchy-api/.env
```

Reemplazar en todo el archivo:

- `CUENTA` por el nombre real de la cuenta.
- `CAMBIAR_ESTA_CLAVE` por la contraseña real de PostgreSQL.

Valores que deben quedar claros:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://CUENTA.alwaysdata.net

DB_HOST=postgresql-CUENTA.alwaysdata.net
DB_PORT=5432
DB_DATABASE=CUENTA_sinchy
DB_USERNAME=CUENTA_sinchy
DB_PASSWORD="LA_CONTRASENA_REAL"

SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=null

CORS_ALLOWED_ORIGINS=https://CUENTA.alwaysdata.net
SANCTUM_STATEFUL_DOMAINS=CUENTA.alwaysdata.net
FRONTEND_URL=https://CUENTA.alwaysdata.net

TRUST_PROXIES=
```

Notas:

- `SANCTUM_STATEFUL_DOMAINS` va **sin** `https://` y **sin** barra final.
- `CORS_ALLOWED_ORIGINS` y `APP_URL` sí llevan `https://`.
- `SESSION_DOMAIN=null` deja la cookie en el host exacto.
- Si la contraseña de PostgreSQL contiene `#`, espacios o comillas, mantenerla
  entre comillas dobles y escaparla según corresponda.
- `TRUST_PROXIES` se deja vacío. Solo llenarlo con la IP de un proxy si el
  proveedor lo indica; nunca usar `*`.
- Crear el directorio de respaldos:

```bash
mkdir -p ~/backups/sinchy
chmod 600 ~/sinchy-api/.env
grep -n '^DB_PASSWORD=.*CAMBIAR_ESTA_CLAVE' ~/sinchy-api/.env
```

El último comando no debe devolver nada. Si devuelve la línea
`DB_PASSWORD`, todavía no se reemplazó la contraseña.

### 5.4 Verificar `pg_dump`

```bash
which pg_dump
pg_dump --version
```

Debe ser de PostgreSQL 17 para que pueda leer la base 17. Si la ruta no es la
del `PATH`, ajustar `PG_DUMP_PATH` en `.env` con la ruta completa. Si no existe
`pg_dump`, se puede omitir el respaldo propio y usar los respaldos diarios de
Alwaysdata durante la prueba.

---

## 6. Instalar dependencias y preparar Laravel

Todo se ejecuta dentro de `~/sinchy-api`:

```bash
cd ~/sinchy-api
composer --version
composer install --no-dev --optimize-autoloader
composer check-platform-reqs
```

Si `check-platform-reqs` falla, habilitar la extensión que falta en el panel de
PHP antes de continuar.

Si Composer agota la memoria del plan Free (256 MB):

```bash
COMPOSER_MEMORY_LIMIT=-1 composer install --no-dev --optimize-autoloader
```

Si aun así falla, instalar `vendor` localmente y subirlo:

```powershell
cd C:\MarioC\webs_figma\Sinchy\sinchy-api
composer install --no-dev --optimize-autoloader
tar -czf "$env:TEMP\sinchy-deploy\vendor.tar.gz" vendor
scp "$env:TEMP\sinchy-deploy\vendor.tar.gz" `
  "${cuenta}@ssh-${cuenta}.alwaysdata.net:~/"
```

En el servidor:

```bash
cd ~
rm -rf ~/sinchy-api/vendor
tar -xzf vendor.tar.gz -C ~/sinchy-api
rm vendor.tar.gz
```

En ese caso no ejecutes `composer install` de nuevo en el servidor. Después, en
la PC local se puede restaurar el entorno de desarrollo con:

```powershell
cd C:\MarioC\webs_figma\Sinchy\sinchy-api
composer install
```

Continuar en el servidor:

```bash
cd ~/sinchy-api
php artisan config:clear
php artisan key:generate --force

mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chmod -R u+rwX storage bootstrap/cache

php artisan migrate --force
php artisan db:seed --force
```

> **Importante:** con `APP_ENV=production`, los seeders generan contraseñas
> aleatorias para `admin`, `tecnico`, `tecnico2`, `revisor` y `gerente`. La
> salida de `db:seed` las muestra una sola vez. Copiarlas y guardarlas en un
> lugar seguro. En el primer login, `admin` estará obligado a cambiarla.
>
> Este `db:seed` es deliberado para la prueba temporal: crea usuarios y datos
> de demostración. En un entorno con datos reales no se debe ejecutar sin
> revisar antes qué sembradores corren.

Verificar el entorno:

```bash
php artisan sinchiwayra:revisar-produccion
```

Debe terminar con `Entorno apto para produccion.` Si hay un `FALLO`, corregir
el `.env` antes de continuar.

Optimizar para producción:

```bash
php artisan optimize
```

Si se cambia el `.env` después de esto:

```bash
php artisan optimize:clear
php artisan optimize
```

---

## 7. Verificar el despliegue

### 7.1 Health check

Abrir en el navegador:

```text
https://CUENTA.alwaysdata.net/api/health
```

Debe devolver un JSON con `"database":"ok"` (y el estado general de la API).

También se puede probar por consola:

```bash
curl -i https://CUENTA.alwaysdata.net/api/health
```

### 7.2 SPA y rutas profundas

Abrir:

```text
https://CUENTA.alwaysdata.net/
https://CUENTA.alwaysdata.net/login
```

La segunda URL debe cargar la SPA, no un 404. Eso confirma que el fallback de
`.htaccess` funciona.

### 7.3 Login

1. Entrar con el usuario `admin` y la contraseña aleatoria que imprimió el
   seeder.
2. Cambiar la contraseña cuando la aplicación lo pida.
3. Verificar que la cookie de sesión sea `Secure` e `HttpOnly` en las
   herramientas de desarrollo del navegador.
4. Probar logout y volver a entrar.

### 7.4 Prueba funcional mínima

- Crear una liquidación.
- Enviarla a revisión.
- Revisarla y aprobarla con los usuarios correspondientes.
- Descargar un PDF y un Excel.
- Abrir reportes y cambiar el periodo.
- Verificar que no haya errores en la consola del navegador.

### 7.5 Logs si algo falla

```bash
tail -n 100 ~/sinchy-api/storage/logs/laravel.log
```

Los logs de Apache/PHP también están disponibles desde el panel o en
`~/admin/logs/`.

Errores típicos:

- `500` después de subir: permisos de `storage`/`bootstrap/cache`, `.env`
  incompleto o `APP_KEY` vacía.
- `419` al iniciar sesión: `SANCTUM_STATEFUL_DOMAINS` mal escrito, SSL todavía
  inactivo o caché de configuración vieja.
- El login redirige a `localhost`: `APP_URL`/`FRONTEND_URL` siguen apuntando al
  entorno local o hay una caché de configuración vieja. Ejecutar
  `php artisan optimize:clear` y revisar el `.env`.
- `404` en `/login`: `.htaccess` no se aplicó o la raíz del sitio no es
  `public`.
- `500` en todo el sitio después de subir `.htaccess`: revisar el log de
  Apache. Si dice `Options not allowed here`, quitar la línea
  `Options -MultiViews -Indexes` y recargar.
- `could not find driver`: falta `pdo_pgsql` en PHP.
- `SQLSTATE[08006]`: host, puerto, base o contraseña incorrectos.
- `permission denied for schema public`: el usuario de PostgreSQL no tiene todos
  los privilegios; corregirlo en **Databases > PostgreSQL** y volver a migrar.
- `SQLSTATE[42P07]` al restaurar: ya existen tablas; restaurar sobre una base
  vacía o usar `--clean --if-exists`.

---

## 8. Backups y tareas programadas (opcional)

Alwaysdata ya hace respaldos diarios de archivos, bases de datos y correos en
`/home/CUENTA/admin/backup`. En el plan **Free se conservan solo 3 días**, así
que para una prueba de 1 a 2 semanas conviene tener además un respaldo propio.
Los respaldos de Alwaysdata no descuentan de la cuota de disco; los propios sí.
Si se quiere un respaldo propio:

1. Verificar `pg_dump` como se indicó en 5.4.
2. Probar el comando manualmente:

```bash
cd ~/sinchy-api
php artisan sinchiwayra:respaldar
ls -lh ~/backups/sinchy
```

3. En el panel, ir a **Advanced > Scheduled tasks** y crear una tarea tipo
   **Command**. Usar la ruta real de PHP que devuelve `which php`.

   - **Value:** el comando, sin el horario delante:
     ```text
     /usr/bin/php8.4 /home/CUENTA/sinchy-api/artisan sinchiwayra:respaldar >> /home/CUENTA/sinchy-api/storage/logs/respaldo.log 2>&1
     ```
   - **Frequency:** todos los días a las 02:30.
   - Línea crontab equivalente (solo como referencia):
     ```text
     30 2 * * * /usr/bin/php8.4 /home/CUENTA/sinchy-api/artisan sinchiwayra:respaldar >> /home/CUENTA/sinchy-api/storage/logs/respaldo.log 2>&1
     ```

Alternativa: ejecutar el planificador de Laravel cada minuto y dejar que
`routes/console.php` decida la hora.

- **Value:**
  ```text
  /usr/bin/php8.4 /home/CUENTA/sinchy-api/artisan schedule:run >> /home/CUENTA/sinchy-api/storage/logs/scheduler.log 2>&1
  ```
- **Frequency:** cada minuto.
- Línea crontab equivalente:
  ```text
  * * * * * /usr/bin/php8.4 /home/CUENTA/sinchy-api/artisan schedule:run >> /home/CUENTA/sinchy-api/storage/logs/scheduler.log 2>&1
  ```

Los logs de las tareas programadas quedan en `~/admin/logs/jobs/`. El respaldo
se guarda en `RESPALDO_DIRECTORIO` y se purga según
`RESPALDO_DIAS_RETENCION`.

---

## 9. Actualizar una versión ya desplegada

### 9.1 Frontend

```powershell
cd C:\MarioC\webs_figma\Sinchy\sinchy-web
npm ci
npm run build
tar -czf "$env:TEMP\sinchy-deploy\sinchy-web-dist.tar.gz" -C .\dist .
scp "$env:TEMP\sinchy-deploy\sinchy-web-dist.tar.gz" `
  "${cuenta}@ssh-${cuenta}.alwaysdata.net:~/"
```

En el servidor:

```bash
tar -xzf ~/sinchy-web-dist.tar.gz -C ~/sinchy-api/public
```

### 9.2 Backend

Subir solo los archivos modificados o un nuevo `sinchy-api.tar.gz` sin `.env`
ni `vendor`, extraerlo encima y ejecutar:

```bash
cd ~/sinchy-api
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
```

No reemplazar el `.env` del servidor con el de desarrollo. Si cambió
`composer.lock`, hay que ejecutar Composer; si solo cambió código, no es
imprescindible.

Para una actualización delicada:

```bash
php artisan down
# subir y migrar
php artisan up
```

---

## 10. Importar los datos locales (opcional)

Para una prueba limpia se recomienda `migrate --force` + `db:seed --force`. Si
se quieren conservar las liquidaciones locales:

1. Generar el dump con PostgreSQL 17 local:

```powershell
pg_dump -h 127.0.0.1 -U sinchy -d sinchy -Fc -f "$env:TEMP\sinchy.dump"
scp "$env:TEMP\sinchy.dump" "${cuenta}@ssh-${cuenta}.alwaysdata.net:~/"
```

2. Restaurar sobre una base **vacía** (sin haber corrido migraciones):

```bash
pg_restore \
  -h postgresql-CUENTA.alwaysdata.net \
  -p 5432 \
  -U CUENTA_sinchy \
  -d CUENTA_sinchy \
  --no-owner \
  --clean --if-exists \
  ~/sinchy.dump
```

3. Verificar que la tabla `migrations` haya quedado restaurada y ejecutar:

```bash
cd ~/sinchy-api
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
```

> El dump local contiene usuarios de desarrollo con contraseñas conocidas. No
> usar ese dump si el sitio va a quedar accesible con datos reales. Para una
> prueba, cambiar las contraseñas después de restaurar.

---

## 11. Contraseñas conocidas para la prueba (opcional)

Con `APP_ENV=production` las contraseñas son aleatorias. Si se necesitan las
credenciales conocidas de desarrollo para comodidad de los testers, se pueden
cambiar **solo en el entorno de prueba**:

```bash
cd ~/sinchy-api

php artisan tinker --execute="\App\Models\User::where('username','admin')->update(['password'=>bcrypt('Admin123'),'debe_cambiar_password'=>false]);"
```

Para los usuarios de ejemplo, repetir el patrón con `tecnico`, `tecnico2`,
`revisor` y `gerente`. No hacer esto en un entorno con datos reales ni publicar
las credenciales.

---

## 12. Límites del plan Free y qué hacer si se queda corto

| Límite | Efecto |
|---|---|
| 256 MB de RAM | Composer o una exportación grande pueden fallar por memoria |
| 1/4 de CPU | Las exportaciones y los reportes pueden ser lentos |
| 1 GB de disco | Los respaldos y logs deben rotarse |
| Respaldos de 3 días | Suficiente para una prueba corta, pero conviene un dump propio |
| 50 conexiones PostgreSQL | Suficiente para pruebas; no para mucha concurrencia |
| Sin Redis ni workers 24/7 | La app no usa colas ni workers, así que no es un problema |
| Sin Docker | El despliegue es nativo con PHP/Apache |
| Sin dominio propio | Se usa `CUENTA.alwaysdata.net` |
| Plan personal/sin fines de lucro | No usar para producción comercial ni datos reales |

Si una exportación Excel/PDF falla por memoria:

1. Revisar `storage/logs/laravel.log`.
2. Probar con un periodo más corto.
3. Si el error es de tiempo, subir `max_execution_time` en **Environment > PHP**
   (por defecto 120 segundos) o reducir el rango.
4. Si es imprescindible, pasar temporalmente a un plan pago con más RAM.

---

## 13. Checklist final

- [ ] El sitio apunta a `/home/CUENTA/sinchy-api/public`.
- [ ] HTTPS activo y `APP_URL` con `https://`.
- [ ] `public/index.html` y `public/assets/` existen.
- [ ] `public/.htaccess` es el de `deploy_alwaysdata/apache.htaccess`.
- [ ] `.env` tiene `APP_ENV=production` y `APP_DEBUG=false`.
- [ ] `APP_KEY` generada.
- [ ] `DB_*` con los nombres prefijados de Alwaysdata.
- [ ] `SANCTUM_STATEFUL_DOMAINS` sin esquema.
- [ ] `CORS_ALLOWED_ORIGINS` con esquema.
- [ ] `SESSION_SECURE_COOKIE=true`.
- [ ] Migraciones y seeders ejecutados.
- [ ] `sinchiwayra:revisar-produccion` en verde.
- [ ] `/api/health` responde con la base en `ok`.
- [ ] Login, logout, roles y cambio de contraseña funcionan.
- [ ] `/login` directo no da 404.
- [ ] PDF y Excel funcionan.
- [ ] Logs sin errores repetidos.
- [ ] Respaldos verificados o respaldo diario de Alwaysdata confirmado.

# Despliegue en Railway (imagen única: SPA + API en el mismo origen)

Esta guía asume que ya existe en el repositorio:

- `Dockerfile` (raíz): compila la SPA con Node 24 y la sirve junto a la API con
  Nginx + PHP-FPM 8.4, todo en el mismo puerto.
- `docker/nginx.conf.template`, `docker/start.sh`, `docker/php.ini`.
- `.dockerignore`: impide que el `.env` real, `vendor/` y `node_modules/`
  entren a la imagen.
- `railway.toml`: le dice a Railway que construya con el Dockerfile.

**Regla que manda:** la SPA y la API deben salir del **mismo origen** (la
autenticación es por cookie de sesión, Sanctum modo SPA). Por eso **un solo
servicio** sirve las dos; nunca despliegues el frontend como un servicio aparte.

---

## 1. Antes de empezar

Verifica que el código esté en GitHub (rama `main`) e incluye los archivos
nuevos (`Dockerfile`, `docker/`, `railway.toml`, `.dockerignore`,
`.gitattributes`). El `.env` **no** se sube (ya está en `.gitignore`).

Genera la `APP_KEY` una sola vez, desde tu máquina:

```bash
cd epcoro-api
php artisan key:generate --show
```

Copia el valor completo (`base64:...`) para pegarlo como variable en Railway.
**No** la regeneres en cada despliegue: invalidaría las sesiones existentes.

---

## 2. Crear el proyecto en Railway

1. Entra a https://railway.com y vincula tu cuenta de GitHub.
2. Revisa el plan: el plan gratuito ya no existe; hay **crédito de prueba**
   (USD 5 por 30 días) y luego USD 1/mes. No cambies a Hobby si quieres seguir
   en USD 0.
3. **New Project → Deploy from GitHub repo** y elige `MCarranzas/epcoro`.
4. En el servicio, abre **Settings**:
   - **Root Directory**: dejar en la **raíz del repositorio** (vacío / `/`),
     NO `epcoro-api`. Si lo cambias, el Dockerfile no encuentra `epcoro-web/`.
   - Railway detecta el `Dockerfile` y lo usa automáticamente.

## 3. Agregar PostgreSQL

1. En el canvas: **+ New → Database → PostgreSQL**.
2. Railway expone `PGHOST`, `PGPORT`, `PGUSER`, `PGPASSWORD`, `PGDATABASE`.
3. Mantén la base **privada** (no habilites TCP Proxy público).

## 4. Variables del servicio (uno solo, el del Dockerfile)

Abre el servicio, pestaña **Variables**, y agrega:

| Variable | Valor |
|---|---|
| `APP_NAME` | `SINCHI WAYRA` |
| `APP_ENV` | `production` |
| `APP_KEY` | `base64:...` (la que generaste) |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://TU-DOMINIO.up.railway.app` (ver paso 5) |
| `APP_TIMEZONE` | `America/La_Paz` |
| `APP_LOCALE` | `es` |
| `APP_MAINTENANCE_DRIVER` | `file` |
| `APP_MAINTENANCE_STORE` | `database` |
| `BCRYPT_ROUNDS` | `12` |
| `LOG_CHANNEL` | `stack` |
| `LOG_STACK` | `stderr` |
| `LOG_LEVEL` | `warning` |
| `DB_CONNECTION` | `pgsql` |
| `DB_HOST` | `${{Postgres.PGHOST}}` |
| `DB_PORT` | `${{Postgres.PGPORT}}` |
| `DB_DATABASE` | `${{Postgres.PGDATABASE}}` |
| `DB_USERNAME` | `${{Postgres.PGUSER}}` |
| `DB_PASSWORD` | `${{Postgres.PGPASSWORD}}` |
| `SESSION_DRIVER` | `database` |
| `SESSION_LIFETIME` | `480` |
| `SESSION_ENCRYPT` | `true` |
| `SESSION_SECURE_COOKIE` | `true` |
| `SESSION_DOMAIN` | `null` |
| `SESSION_SAME_SITE` | `lax` |
| `BROADCAST_CONNECTION` | `log` |
| `FILESYSTEM_DISK` | `local` |
| `QUEUE_CONNECTION` | `sync` |
| `CACHE_STORE` | `database` |
| `MAIL_MAILER` | `log` |
| `CORS_ALLOWED_ORIGINS` | `https://TU-DOMINIO.up.railway.app` |
| `SANCTUM_STATEFUL_DOMAINS` | `TU-DOMINIO.up.railway.app` (SIN `https://`) |
| `TRUST_PROXIES` | `*` |
| `FRONTEND_URL` | `https://TU-DOMINIO.up.railway.app` |

Notas:

- `${{Postgres.PGHOST}}` etc. usan el **nombre exacto del servicio Postgres**.
  Si lo renombraste, cambia el prefijo.
- `SANCTUM_STATEFUL_DOMAINS` va **sin** esquema y **sin** puerto.
- `TRUST_PROXIES=*` es la excepción justificada para un PaaS: el contenedor no
  es alcanzable desde Internet salvo por el proxy del proveedor, así que nadie
  puede falsificar `X-Forwarded-For`. (Ver `puesta_produccion.txt`, 7.6.)
- Con `LOG_STACK=stderr` los logs salen al panel de Railway. El canal de
  auditoría sigue escribiendo a archivo y **se pierde** en cada reinicio (disco
  efímero): para auditoría persistente hace falta disco (ver Opción A/C del
  documento de producción).

## 5. Generar el dominio y cerrar las variables dependientes

1. En el servicio: **Settings → Networking → Generate Domain**.
2. Copia la URL HTTPS (por ejemplo `https://epcoro-production.up.railway.app`).
3. Actualiza `APP_URL`, `CORS_ALLOWED_ORIGINS` y `FRONTEND_URL` con esa URL, y
   `SANCTUM_STATEFUL_DOMAINS` con solo el host.
4. Vuelve a desplegar (Railway redespliega al guardar variables).

> Alternativa: usar `${{RAILWAY_PUBLIC_DOMAIN}}` en esas variables para no
> copiar el dominio a mano. Si Railway no resuelve la referencia, hazlo manual.

## 6. Primer arranque y datos base

El contenedor, al arrancar (`docker/start.sh`), hace con `set -e`:

1. Genera `nginx.conf` con el `$PORT`.
2. `config:cache`, `route:cache`, `view:cache`.
3. `migrate --force` (con reintentos hasta conectar con la base).
4. Si `SEED=1`: corre los seeders base (Anexo F) — **incluido `AdminSeeder`**,
   que en producción imprime por consola una **contraseña aleatoria** de una
   sola vez. Mírala en los **logs del deploy** y guárdala.
5. Arranca PHP-FPM y Nginx.

Para sembrar la primera vez: agrega la variable `SEED=1`, despliega, copia la
contraseña del admin desde los logs y **luego borra `SEED`**.

## 7. Verificación

- `https://TU-DOMINIO/up` → responde 200 (health check de Laravel).
- `https://TU-DOMINIO/api/health` → JSON que confirma la base de datos.
- Abre la raíz y entra con el usuario `admin` y la contraseña de los logs.
- Recorre una liquidación, descarga un PDF y un Excel.
- Cierra sesión y confirma que una URL privada redirige al login.

Si algo falla, revisa los **Deploy Logs** (build) y los logs del contenedor.

---

## Errores frecuentes y su causa

| Síntoma | Causa probable |
|---|---|
| Build falla en `apk add` | Versión de Alpine distinta. Revisa el nombre de los paquetes (`postgresql-dev`, `libjpeg-turbo-dev`, ...) en los logs. |
| Build falla en `npm ci` | `package-lock.json` desincronizado respecto a `package.json`. Correr `npm install` en `epcoro-web/` y volver a commitear el lock. |
| `SQLSTATE: could not connect` | `DB_*` mal referenciadas o el servicio Postgres no está activo. |
| Login "entra y me devuelve al login" | `SANCTUM_STATEFUL_DOMAINS` no incluye el host real (o quedó con `localhost`). |
| Error 500 al login ("Session store not set") | Falta el dominio de la API en `SANCTUM_STATEFUL_DOMAINS`. |
| `419` en el login | Cookie `XSRF-TOKEN` no viaja: HTTPS roto o `SESSION_SECURE_COOKIE`/proxy mal configurado. |
| Cálculo falla tras "instalar todo bien" | Falta `bcmath` en PHP (esta imagen ya lo incluye). |
| `route:cache` falla por closure | Verificado que funciona en Laravel 13; si falla, quita esa línea de `docker/start.sh`. |

## Sobre el costo

Railway ya no tiene plan gratuito. Con el crédito de prueba agotado, servicios
detenidos. Si el objetivo es **gratis de verdad**, revisa las alternativas de
`puesta_produccion.txt` (sección 6): el **mismo `Dockerfile` sirve en Render
Free + Neon**, o se adapta a **Alwaysdata Free** (PHP + PostgreSQL + cron).

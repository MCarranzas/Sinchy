# Despliegue en VPS con Coolify (imagen única: SPA + API en el mismo origen)

Alternativa a Railway. Un VPS propio (Hetzner, DigitalOcean, Contabo…) con
[Coolify](https://coolify.io), un PaaS auto-hospedado que hace despliegue desde
Git, HTTPS automático y bases de datos, sin pagar cuota de plataforma.

Esta guía asume que ya existen en el repositorio:

- `Dockerfile` (raíz): compila la SPA con Node 24 y sirve SPA + API con Nginx +
  PHP-FPM 8.4 en el mismo puerto.
- `docker/nginx.conf.template`, `docker/start.sh`, `docker/php.ini`.
- `.dockerignore`.
- `docker-compose.prod.yml` y `deploy_vps/.env.prod.example` (incluidos).

**Regla que manda:** la SPA y la API salen del **mismo origen** (cookie de sesión,
Sanctum modo SPA). Por eso **un solo servicio** sirve las dos; nunca despliegues
el frontend aparte. Ver `puesta_produccion.txt`, sección 3.2.

---

## 1. Elegir y crear el VPS

Un VPS con **2 GB de RAM y 2 vCPU** va cómodo (el build de Node + PHP + Postgres
pide aire; con 1 GB conviene agregar swap). Referencias: Hetzner CX22 (~€4/mes),
DigitalOcean Droplet 2 GB (~$12/mes), Contabo, Vultr.

- Sistema operativo: **Ubuntu 24.04 LTS** (o Debian 12).
- Abre el firewall para `22` (SSH), `80`, `443` (web/TLS) y `8000` (panel de
  Coolify). Con UFW en el servidor:

  ```bash
  sudo ufw allow 22,80,443,8000/tcp && sudo ufw enable
  ```

## 2. Apuntar el DNS

Crea un registro **A** de tu dominio hacia la IP del VPS, por ejemplo
`epcoro.tudominio.com → 123.123.123.123`. Espera la propagación (míralo con
`dig +short epcoro.tudominio.com`). Coolify emitirá el certificado TLS de Let's
Encrypt solo si el DNS ya resuelve.

## 3. Instalar Coolify

En el VPS:

```bash
curl -fsSL https://cdn.coollabs.io/coolify/install.sh | sudo bash
```

Cuando termina, abre `http://IP-DEL-VPS:8000` y **crea la cuenta de
administrador**. Recomendado enseguida: en **Settings → Instance Domain** pon
`https://coolify.tudominio.com` (otro registro A) para servir el panel por HTTPS.

## 4. Crear la base de datos PostgreSQL

1. Crea un **Project** (por ejemplo `epcoro`) y dentro un **Environment**
   (`production`).
2. **+ New → Database → PostgreSQL**.
3. Elige versión **17**, define nombre, usuario y una **contraseña fuerte**
   (guárdala). Deja el puerto público desactivado (la app la alcanza por la red
   interna de Docker).
4. Pulsa **Deploy**. Cuando esté sana, copia del panel de la base sus datos de
   **conexión interna**: host (`DB_HOST`), puerto (`5432`), base, usuario y
   contraseña.

## 5. Crear la aplicación

1. **+ New → Application → Public Repository** (o Private si el repo es privado;
   conecta la GitHub App).
2. Repositorio `https://github.com/MCarranzas/Sinchy`, rama **`main`**.
3. **Build Pack: `Dockerfile`** (NO Nixpacks).
4. **Base Directory**: raíz del repo (vacío / `/`). El `Dockerfile` de la raíz
   necesita ver `epcoro-web/` y `epcoro-api/` juntos.
5. **Ports Exposes**: `8080`.
6. **Domains**: `https://epcoro.tudominio.com`. Coolify asigna el dominio al
   servicio y pide el certificado automáticamente (Traefik).
7. **Health Check**: ruta `/up`, puerto `8080`.
8. **Persistent Storage** (para que sobrevivan logs de auditoría y subidas):
   agrega un volumen cuyo *Destination Path* sea `/var/www/api/storage`.

## 6. Variables de entorno

En la app: **Environment Variables**. Pega estas (mismos valores que la guía de
Railway, sección 4):

| Variable | Valor |
|---|---|
| `APP_NAME` | `SINCHI WAYRA` |
| `APP_ENV` | `production` |
| `APP_KEY` | `base64:...` (genérala una sola vez, ver abajo) |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://epcoro.tudominio.com` |
| `APP_TIMEZONE` | `America/La_Paz` |
| `APP_LOCALE` | `es` |
| `LOG_CHANNEL` | `stack` |
| `LOG_STACK` | `stderr` |
| `LOG_LEVEL` | `warning` |
| `DB_CONNECTION` | `pgsql` |
| `DB_HOST` | host interno de la base (paso 4) |
| `DB_PORT` | `5432` |
| `DB_DATABASE` | `epcoro` (o el que definiste) |
| `DB_USERNAME` | usuario de la base |
| `DB_PASSWORD` | contraseña de la base |
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
| `CORS_ALLOWED_ORIGINS` | `https://epcoro.tudominio.com` |
| `SANCTUM_STATEFUL_DOMAINS` | `epcoro.tudominio.com` (solo el host) |
| `TRUST_PROXIES` | `*` |

Genera la `APP_KEY` una sola vez, desde tu máquina:

```bash
cd epcoro-api
php artisan key:generate --show
```

Copia el valor completo (`base64:...`). **No** la regeneres en cada despliegue:
invalidaría las sesiones existentes.

> `TRUST_PROXIES=*` es correcto aquí: el contenedor solo es alcanzable a través
> del Traefik de Coolify, que termina el TLS; nadie puede falsificar
> `X-Forwarded-For`. Ver `puesta_produccion.txt`, 7.6.

## 7. Primer despliegue y datos base

Agrega `SEED=1` (y `DEMO_SEED=1` si es un entorno de **vitrina/demo**), pulsa
**Deploy**, y sigue el arranque en **Logs**. Al arrancar, `docker/start.sh`:

1. Genera la config de Nginx para `$PORT` (`8080`).
2. `config:cache`, `route:cache`, `view:cache`.
3. `migrate --force` (con reintentos hasta que la base responda).
4. Si `SEED=1`: seeders base (Anexo F) — **incluido `AdminSeeder`**, que imprime
   la **contraseña aleatoria** del `admin` **una sola vez**. Cópiala de los logs.
5. Si `DEMO_SEED=1`: `DatosDemoSeeder` (ver `deploy_railway/despliegue_railway.md`,
   sección 6.1 y el Anexo F).

Después del primer arranque **borra `SEED` y `DEMO_SEED`** (o ponlos en `0`) para
no re-sembrar en cada reinicio.

## 8. Verificación

- `https://epcoro.tudominio.com/up` → 200 (health check de Laravel).
- `https://epcoro.tudominio.com/api/health` → JSON que confirma la base.
- Entra con `admin` y la contraseña de los logs (o con los usuarios de demo).
- Recorre una liquidación, descarga un PDF y un Excel.
- Cierra sesión y confirma que una URL privada redirige al login.

## 9. Actualizaciones y respaldos

- **Actualizar**: `git push` a `main`, luego **Deploy** en Coolify (opcionalmente
  activa *Auto Deploy* con webhook de GitHub).
- **Respaldos de la base**: Coolify permite *Scheduled Backups* del recurso
  PostgreSQL hacia S3-compatible. Configúralo; un VPS no trae respaldo por sí
  solo.
- **Certificado TLS**: Coolify/Traefik lo renueva solo. No toques Let's Encrypt
  a mano.

---

## Errores frecuentes

| Síntoma | Causa probable |
|---|---|
| El dominio no responde / sin HTTPS | El DNS aún no apunta al VPS, o faltan los puertos 80/443 en el firewall. |
| Login "entra y me devuelve al login" | `SANCTUM_STATEFUL_DOMAINS` no incluye el host real, o `APP_URL` no coincide. |
| `419` en el login | Cookie `XSRF-TOKEN` no viaja: TLS roto, `SESSION_SECURE_COOKIE` o proxy mal configurado. |
| `SQLSTATE: could not connect` | `DB_HOST`/credenciales mal copiadas del recurso PostgreSQL, o la base no está sana. |
| Build se queda sin memoria | VPS de 1 GB. Sube a 2 GB o agrega swap. |
| `route:cache` falla por closure | Verificado que funciona en Laravel 13; si falla, comenta esa línea de `docker/start.sh`. |

## Costo

VPS Hetzner CX22 ≈ **€4/mes** + dominio. Coolify es gratis (auto-hospedado). No
hay cuota de plataforma ni cobro por uso: el costo es fijo y predecible.

---

## Alternativa sin Coolify: Docker Compose directo

Si no quieres el panel de Coolify, el mismo `docker-compose.prod.yml` corre solo
en el VPS con Docker Compose y un proxy TLS delante (Caddy o Traefik).

1. Instala Docker y el plugin Compose en el VPS.
2. Clona el repo y crea el archivo de variables:

   ```bash
   git clone https://github.com/MCarranzas/Sinchy.git && cd Sinchy
   cp deploy_vps/.env.prod.example .env.prod
   # edita .env.prod: APP_KEY, APP_URL, SANCTUM_DOMAIN, DB_* y, para el primer
   # arranque, SEED=1 (y DEMO_SEED=1 si es demo)
   ```

3. En `docker-compose.prod.yml`, descomenta el bloque `ports` del servicio `api`
   (`127.0.0.1:8080:8080`) para publicarlo solo en loopback.
4. Levanta el stack:

   ```bash
   docker compose --env-file .env.prod -f docker-compose.prod.yml up -d --build
   ```

5. Pon delante un proxy TLS. Con Caddy basta un `Caddyfile`:

   ```caddy
   epcoro.tudominio.com {
       reverse_proxy 127.0.0.1:8080
   }
   ```

   y `caddy run` (o el contenedor oficial `caddy`). Caddy emite y renueva el
   certificado de Let's Encrypt automáticamente.

Para actualizar: `git pull` y repite el `docker compose ... up -d --build`.

# Despliegue en Render (plan FREE, sin tarjeta)

Opción gratuita para demos cortas. El plan **Free de Render no pide tarjeta** y
soporta **web services con Docker** + **PostgreSQL**, así que el sistema se
despliega con el **mismo `Dockerfile`** de la raíz: la SPA y la API salen del
mismo origen (requisito de Sanctum, ver `puesta_produccion.txt`, 3.2).

Limites del plan Free (a tener en cuenta):

- El **web service duerme** a los 15 min sin tráfico y despierta en ~1 min.
- **PostgreSQL free: 1 GB y expira a los 30 días** (de sobra para una semana).
- **Sin disco persistente**: los logs de auditoría se pierden en cada reinicio.
- Render dice: *"No usar en producción real"*. Para la demo de una semana, OK.

Todo se define en `render.yaml` (Blueprint). No hace falta tocar el código.

---

## 1. Genera la `APP_KEY`

Desde tu máquina, una sola vez:

```bash
cd epcoro-api
php artisan key:generate --show
```

Copia el valor completo (`base64:...`); lo pegas en el paso 3.

## 2. Crea el proyecto (Blueprint)

1. Entra a https://render.com y crea la cuenta con GitHub (no pide tarjeta).
2. **New → Blueprint** y conecta el repositorio `MCarranzas/epcoro` (rama
   `main`). Render lee `render.yaml` y muestra lo que va a crear: un **web
   service** (`epcoro`, con tu Dockerfile) y una **base PostgreSQL** (`epcoro-db`),
   ambos en plan Free.

> **Importante:** el proyecto es **solo PostgreSQL** (usa ENUM nativo, `ilike` y
> `TO_CHAR`; ver `puesta_produccion.txt`, 4.2). Si el servicio web se crea **sin**
> la base enlazada (`New → Web Service` a mano), Laravel cae a SQLite y el
> arranque muere con `near "TYPE": syntax error`. Debe existir la base y sus
> variables `DB_*`. Si ya creaste el web service a mano, sigue la sección 2.1.

### 2.1 Enlazar PostgreSQL a un servicio ya creado

Si tu web service ya existe y no aparece `DB_HOST` en sus variables:

1. **New → PostgreSQL**: nombre `epcoro-db`, mismo **region** (`ohio`), plan
   **Free**. Anota el **Internal Database URL** o los datos (host, puerto, base,
   usuario, contraseña).
2. En el web service, **Environment → Environment Variables**, agrega (o corrige):

   | Variable | Valor |
   |---|---|
   | `DB_CONNECTION` | `pgsql` |
   | `DB_HOST` | el host interno de la base (o `fromDatabase`) |
   | `DB_PORT` | `5432` |
   | `DB_DATABASE` | `epcoro` |
   | `DB_USERNAME` | `epcoro` |
   | `DB_PASSWORD` | la contraseña de la base |

3. Guarda: Render redespliega. En los logs de arranque debe verse
   `[start] DB_CONNECTION=pgsql DB_HOST=...`. Si ves `DB_HOST=<sin definir>`, la
   base aún no está enlazada (el `start.sh` aborta a propósito con un mensaje
   claro).

## 3. Completa las variables y aplica

Al aplicar el Blueprint, Render pide solo las variables marcadas `sync: false`:

| Variable | Valor |
|---|---|
| `APP_KEY` | `base64:...` (la del paso 1) |
| `APP_URL` | provisional, p. ej. `https://epcoro.onrender.com` |
| `CORS_ALLOWED_ORIGINS` | provisional, `https://epcoro.onrender.com` |
| `SANCTUM_STATEFUL_DOMAINS` | provisional, `epcoro.onrender.com` (solo el host, sin https) |

El resto (y las `DB_*`) las completa el Blueprint solo: enlaza la base al
servicio con `fromDatabase`. Pulsa **Apply** y espera el primer build.

> **Zona horaria.** El Blueprint fija `APP_TIMEZONE=America/La_Paz` y la
> conexión de PostgreSQL hereda esa zona (`DB_TIMEZONE` por defecto es
> `APP_TIMEZONE`). No lo cambies: el Postgres de Render corre en UTC y, si la
> sesión no se alinea con la app, los vencimientos y los bloqueos de cuenta se
> leen desplazados 4 h (el bloqueo "no funciona").

## 4. Corrige la URL real

1. Cuando el deploy termine, copia la URL del servicio: normalmente
   `https://epcoro.onrender.com`, pero Render agrega un sufijo si el nombre ya
   está tomado (p. ej. `epcoro-a1b2.onrender.com`).
2. En **Environment**, corrige las tres variables del paso 3 con la URL real:
   - `APP_URL` → `https://<tu-url>.onrender.com`
   - `CORS_ALLOWED_ORIGINS` → `https://<tu-url>.onrender.com`
   - `SANCTUM_STATEFUL_DOMAINS` → `<tu-url>.onrender.com` (solo el host)
3. Guarda: Render redespliega al guardar variables.

## 5. Primer arranque y datos base

Agrega `SEED=1` (y `DEMO_SEED=1` si es un entorno de **vitrina/demo**) y
despliega. Al arrancar, `docker/start.sh`:

1. Genera la config de Nginx para `$PORT` (Render lo inyecta).
2. `config:cache`, `route:cache`, `view:cache`.
3. `migrate --force` (con reintentos hasta que la base responda).
4. Si `SEED=1`: seeders base (Anexo F) — **incluido `AdminSeeder`**, que imprime
   la **contraseña aleatoria** del `admin` **una sola vez**. Cópiala de los logs.
5. Si `DEMO_SEED=1`: `DatosDemoSeeder` (usuarios de demo, tipos de cambio,
   cotizaciones, compradores, vendedores y áreas). Ver
   `deploy_railway/despliegue_railway.md`, sección 6.1.

Después **vuelve `SEED` y `DEMO_SEED` a `0`** para no re-sembrar en cada reinicio.

## 6. Verificación

- `https://<tu-url>.onrender.com/up` → 200 (health check de Laravel).
- `https://<tu-url>.onrender.com/api/health` → JSON con `"database":"ok"`.
- Entra con `admin` (clave de los logs) o con los usuarios de demo.
- Recorre una liquidación, descarga un PDF y un Excel.

> La primera petición tras 15 min sin tráfico tarda ~1 min (cold start del plan
> Free). Es normal.

---

## Errores frecuentes

| Síntoma | Causa probable |
|---|---|
| Build falla en `apk add` / `npm ci` | Revisa los logs del build. `npm ci` requiere `package-lock.json` sincronizado. |
| Login "entra y me devuelve al login" | `SANCTUM_STATEFUL_DOMAINS`/`APP_URL` no coinciden con la URL real (paso 4). |
| `419` en el login | `SESSION_SECURE_COOKIE=true` sin HTTPS (Render da HTTPS por defecto, no debería pasar). |
| `SQLSTATE: could not connect` | El Blueprint no enlazó la base: revisa que existan `DB_HOST`/`DB_PASSWORD`. |
| `Connection: sqlite` o `near "TYPE": syntax error` | El servicio no tiene la base PostgreSQL enlazada (por eso Laravel cae a SQLite). Abre **Environment** y confirma que existan `DB_CONNECTION=pgsql` y `DB_HOST`; si faltan, el servicio no se creó desde el Blueprint. Enlaza la base a mano (sección 2.1) o bórralo y recréalo con **New → Blueprint**. |
| El bloqueo por intentos fallidos no funciona (o vence 4 h antes/después) | La sesión de PostgreSQL no usa la zona de la app. Confirma `APP_TIMEZONE=America/La_Paz` en **Environment**; la conexión fija `DB_TIMEZONE` a partir de ella. No pongas el Postgres en otra zona. |
| Primera petición muy lenta (~1 min) | Es el *cold start* del plan Free. |
| A las semanas la base "desaparece" | El free Postgres expira a los 30 días; para 1 semana no aplica. |

## Costo

**$0**, sin tarjeta. Para algo más permanente, migra al **VPS + Coolify**
(`deploy_vps/despliegue_vps.md`).

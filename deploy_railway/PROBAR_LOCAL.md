# Probar la imagen en LOCAL antes de subirla (WSL2 + Docker)

Sirve para verificar que el `Dockerfile` construye y que la imagen sirve la SPA y
la API en el mismo origen, **antes** de gastar crédito en Railway.

Solo necesitas Docker para esto: **el código de la aplicación no cambia**, ni
tampoco la forma de desplegar en Railway.

> ¿Vale la pena instalarlo? Docker no es obligatorio: Railway te da los mismos
> logs de build y un fallo cuesta minutos de crédito, no dinero. Instálalo solo
> si prefieres iterar rápido desde tu PC. Si no, ve directo a Railway.

---

## 1. Habilitar WSL2 (Windows) — requiere ADMINISTRADOR y reinicio

Tu máquina: Windows 11 (build 26200) 64 bits, **WSL no instalado**, 113 GB
libres en C:.

En PowerShell **como administrador**:

```powershell
wsl --install -d Ubuntu
```

Reinicia. Al volver, Ubuntu se configura sola (te pide usuario y contraseña).

Comprueba:

```powershell
wsl --status
wsl -l -v          # Ubuntu debe aparecer con "Running"
```

## 2. Instalar Docker Engine dentro de Ubuntu

Abre la terminal de **Ubuntu** (no PowerShell):

```bash
sudo apt update
sudo apt install -y docker.io docker-compose-v2
sudo service docker start
sudo usermod -aG docker $USER

# cierra y vuelve a abrir la terminal de Ubuntu para que tome el grupo
docker version
docker compose version
```

> Si `docker-compose-v2` no está en tu versión, usa
> `sudo apt install -y docker-compose` y escribe `docker-compose` en lugar de
> `docker compose` en los comandos de abajo.
>
> Alternativa gráfica: **Docker Desktop for Windows** con backend WSL2. Si la
> usas, el resto de pasos son iguales.

## 3. Copiar el repo al disco de Linux (importante: velocidad)

Montar el disco de Windows en `/mnt/c` funciona, pero compilar allí es lento.
Copia primero:

```bash
cp -r /mnt/c/MarioC/webs_figma/sinchy ~/sinchy
cd ~/sinchy
```

(Si haces cambios en Windows, vuelve a copiar o usa `rsync -a --delete`.)

## 4. Generar la APP_KEY (desde Windows, donde ya tienes PHP)

```powershell
cd C:\MarioC\webs_figma\sinchy\sinchy-api
php artisan key:generate --show
```

Copia el valor `base64:...`.

## 5. Construir y arrancar

En Ubuntu:

```bash
cd ~/sinchy
APP_KEY="base64:PEGA_AQUI_TU_CLAVE" SEED=1 \
  docker compose -f docker-compose.local.yml up --build
```

El primer build tarda varios minutos (descarga imágenes, compila extensiones PHP,
instala Composer y compila la SPA). Es normal.

- `SEED=1` siembra los datos base la primera vez (Anexo F).
- En el **segundo** arranque quita `SEED=1`, o la repetición de los seeders
  puede abortar el contenedor.

## 6. Qué probar

| URL | Esperado |
|---|---|
| http://localhost:8080/up | 200 (health de Laravel) |
| http://localhost:8080/api/health | JSON confirmando la base de datos |
| http://localhost:8080 | Carga la SPA (login) |

**Usuario:** `admin`. La contraseña es aleatoria y se imprime **una sola vez**
en los logs del contenedor `api` (busca `AdminSeeder` / "contraseña").

Recorre una liquidación, descarga un PDF y un Excel, y confirma que la sesión
aguanta (esa es la prueba del mismo origen + cookie).

## 7. Si algo falla

| Síntoma | Qué mirar |
|---|---|
| `npm ci` falla | `package-lock.json` desincronizado de `package.json`. En Windows: `cd sinchy-web && npm install`, y commitea el lock. |
| `apk add` falla | Nombre de paquete según la versión de Alpine. El log dice cuál. |
| Contenedor arranca pero todo da 500 | `clear_env = no` no llegó a PHP-FPM: revisa los logs del arranque. |
| `SQLSTATE: could not connect` | El servicio `db` no estaba listo o las credenciales `DB_*` no coinciden. |
| Login entra y sale | `SANCTUM_STATEFUL_DOMAINS` no incluye el host. |
| Contenedor muere al sembrar | Quita `SEED=1` y arranca de nuevo (los seeders ya corrieron). |

## 8. Limpieza

```bash
docker compose -f docker-compose.local.yml down -v
```

`down -v` borra también el volumen de la base de prueba.

---

## Diferencias entre esta prueba local y Railway

| Tema | Local | Railway |
|---|---|---|
| HTTPS | No hay | Sí (dominio `.up.railway.app`) |
| `SESSION_SECURE_COOKIE` | `false` | `true` |
| Base de datos | Contenedor `postgres:17` en la misma red | Servicio `Postgres` de Railway |
| Variables | `docker-compose.local.yml` | Panel de variables de Railway |
| `TRUST_PROXIES` | `*` (igual) | `*` (excepción justificada, 7.6) |

Es decir: **lo que verifiques aquí se traslada**, salvo lo de HTTPS y las
variables del proveedor, que se configuran en el paso 4 de la guía de Railway
(`deploy_railway/despliegue_railway.md`).

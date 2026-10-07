# epcoro-web

SPA del sistema EPCORO (captura de liquidaciones de aportes). Frontend de la
FASE 7: sesion, permisos y base sobre la que se construyen los modulos de la
FASE 8 en adelante.

Stack: Vue 3 (`<script setup>`), TypeScript, Vite 8, Tailwind v4, Pinia,
vue-router 5 y axios.

## Como correrlo

Necesita el backend levantado en el puerto 8000:

```bash
cd epcoro-api
php artisan serve --host=127.0.0.1 --port=8000

cd epcoro-web
npm install
npm run dev        # http://localhost:5173
npm run build      # vue-tsc + vite build
npm run preview    # sirve dist/
```

## Proxy de Vite

En desarrollo el navegador solo habla con `localhost:5173`. `vite.config.ts`
proxea dos rutas hacia `http://localhost:8000`:

- `/api` — la API versionada `/api/v1`.
- `/sanctum` — `GET /sanctum/csrf-cookie`, que es la cookie CSRF que axios
  pide antes del login.

Si `/sanctum` no esta en el proxy, el dev server devuelve el `index.html` en
su lugar, la SPA nunca recibe `XSRF-TOKEN` y el backend responde **419** en
cualquier POST. Con la SPA en produccion (mismo origen) esto no aplica.

## Como autentica

Cookie de sesion de Sanctum en modo SPA (no bearer token):

1. `GET /sanctum/csrf-cookie` obtiene `XSRF-TOKEN`.
2. `POST /api/v1/auth/login` con header `X-XSRF-TOKEN`.
3. El guard del router llama `GET /api/v1/auth/me`; un 401 manda a
   `/login?redirect=...`.
4. `stores/auth.ts` guarda el usuario y expone `puede(permiso)`; el menu de
   `navigation/menu.ts` se filtra por esos permisos.

Conexiones: `src/api/axios.ts` (`baseURL: '/api/v1'`, `withCredentials`).

## Estructura

```
src/
  api/axios.ts          cliente y token CSRF
  components/AppIcon.vue iconos del sistema
  layouts/              AuthLayout (login) y MainLayout (sidebar + drawer)
  navigation/menu.ts    menu por permisos
  router/               rutas y guards (sesion, permiso, 403/404)
  stores/auth.ts        sesion, permisos, login/logout
  types/auth.ts         tipos de la API de auth
  views/                Login, CambioClave, Dashboard, EnConstruccion, 403, 404
```

Las rutas de los modulos todavia apuntan a `EnConstruccionView`: la FASE 7
solo cubre base y login.

## Cambio de contrasena obligatorio

El backend levanta `debe_cambiar_password` cuando la clave vigente fue
generada o acaba de cambiarse. Mientras este en true, el guard del router no
deja pasar a ninguna ruta salvo `/cambio-clave`, que llama
`POST /api/v1/auth/change-password` y baja la bandera en el store.

## Faltantes declarados

- Verificacion visual del menu por rol y del drawer en movil (manual).

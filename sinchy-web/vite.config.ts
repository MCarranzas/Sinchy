import { readFileSync } from 'node:fs'
import { fileURLToPath, URL } from 'node:url'

import tailwindcss from '@tailwindcss/vite'
import vue from '@vitejs/plugin-vue'
import { defineConfig, loadEnv } from 'vite'

const pkg = JSON.parse(
  readFileSync(new URL('./package.json', import.meta.url), 'utf-8'),
) as { version: string }

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  // El destino del proxy SOLO afecta el servidor de desarrollo: en produccion
  // el sitio es estatico y el servidor web enruta /api y /sanctum al backend.
  // Se lee del entorno para no tener que editar este archivo al cambiar de
  // maquina; por defecto apunta al `php artisan serve` local.
  const env = loadEnv(mode, process.cwd(), '')
  const apiTarget = env.VITE_API_PROXY_TARGET || 'http://localhost:8000'

  return {
    plugins: [vue(), tailwindcss()],
    define: {
      __APP_VERSION__: JSON.stringify(pkg.version),
    },
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
      },
    },
    server: {
      port: 5173,
      proxy: {
        '/api': {
          target: apiTarget,
          changeOrigin: true,
          // El backend confia en el proxy (TRUST_PROXIES) y lee la IP real del
          // cliente de X-Forwarded-For: sin xfwd sesiones_registro.ip guardaria
          // la del proxy (127.0.0.1).
          xfwd: true,
        },
        // Sanctum SPA no vive bajo /api: sin esto el pedido de la cookie CSRF
        // cae en el fallback del dev server y devuelve el index.html, el login
        // nunca recibe XSRF-TOKEN y el backend responde 419.
        '/sanctum': {
          target: apiTarget,
          changeOrigin: true,
          xfwd: true,
        },
      },
    },
  }
})
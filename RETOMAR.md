# EPCORO - dónde lo dejamos (punto de reanudación)

Documento para retomar el trabajo en otra máquina. El **entorno** (versiones,
instalación, PATH, bases) está en `pasos_otra_maquina.txt`; este archivo habla
del **estado del proyecto** y de lo que sigue.

- Fecha de este estado: 2026-10-07
- Rama: `master`
- Bitácora: `pasos.txt` (**protocolo ligero** desde 2026-10-07: solo
  decisiones, bugs raros y verificaciones de cierre de fase; ver README.md
  sección "Proceso"). Las entradas que sí se escriben conservan el formato
  `[N] fecha hora - FASE` con OBJETIVO / COMANDO / RESULTADO / ESTADO.

---

## 1. Estado del repositorio

Últimos commits (los cuatro ya están hechos):

```
8c93d17 FASE 6: API CRUD de liquidaciones, reportes y tests
4bd490c FASE 5: servicio de cálculo, numerador y transiciones de estado
4fd6849 FASE 4: base de datos de negocio, policy y factories
1b4b956 Commit inicial: FASE 0-3 completadas
```

**La FASE 7 está terminada en código pero SIN COMMITEAR.** `git status` en la
máquina original:

```
 M README.md                                              (tabla de estados)
 M epcoro-api/app/Http/Requests/ChangePasswordRequest.php (mensajes en español)
 M pasos.txt                                              (entradas [107]-[113])
?? epcoro-web/                                            (SPA completa, nueva)
?? pasos_otra_maquina.txt                                 (guía de entorno, nueva)
?? RETOMAR.md                                             (este archivo)
```

**Primer paso al retomar: hacer el commit de FASE 7** (el usuario lo aprobó):
`epcoro-web/`, el fix del proxy, los dos README, `pasos.txt` y el
`ChangePasswordRequest`. No commitear nada más.

---

## 2. Qué hay en la FASE 7 (hecho)

| Ítem | Estado |
|---|---|
| 7.1 template vue-ts | hecho |
| 7.2 deps (vue-router, pinia, axios, tailwind v4, @vueuse) | hecho |
| 7.3 proxy `/api` -> :8000 | hecho |
| 7.3b proxy **`/sanctum`** -> :8000 | hecho (arreglo de esta sesión, ver §3) |
| 7.4 `src/api/axios.ts` baseURL `/api/v1`, withCredentials, interceptor 401 | hecho |
| 7.5 `stores/auth.ts` login / logout / cargarSesion / puede() | hecho |
| 7.6 router con guard: sesión + permiso + **cambio obligatorio** | hecho |
| 7.7 AuthLayout y MainLayout (sidebar responsive) | hecho |
| 7.8 LoginView (errores, loading, validación) | hecho |
| 7.9 menú por permisos + versión | hecho |
| 7.10 retirar el prototipo `py -m http.server 8000` | hecho: no quedó ningún resto |
| extra | `views/CambioClaveView.vue` + ruta `/cambio-clave` (cambio obligatorio) |

Todo el catálogo de módulos (liquidaciones, revisión, reportes, parámetros,
catálogos, usuarios) apunta a `EnConstruccionView`: eso es FASE 8 en adelante.

---

## 3. El bug que se encontró y se arregló (leer antes de tocar el proxy)

El criterio de aceptación de la FASE 7 ("login real contra la API") **fallaba
aunque el código estuviera bien**:

1. `GET /sanctum/csrf-cookie` a través del dev server devolvía el
   `index.html` de la SPA (el proxy de Vite solo mapeaba `/api`), así que el
   navegador **nunca recibía `XSRF-TOKEN`**.
2. Con `Origin: http://localhost:5173` (peticion stateful, que es lo que manda
   el navegador), el `POST /auth/login` respondía **419**.

Arreglo: `epcoro-web/vite.config.ts` ahora proxya `/api` **y `/sanctum`**.
Verificado end-to-end por el proxy: csrf-cookie 204 -> login 200 -> me 200 ->
parametros 200 -> logout 200 -> me 401.

Si algún día se ve 419 otra vez, es que falta ese mapeo (o se cambió el
`Origin` de la SPA en producción).

---

## 4. Verificaciones en verde (última corrida)

- `npm run build` en `epcoro-web`: vue-tsc + vite, 102 módulos, OK.
- `php artisan test`: **127 passed (699 assertions)**.
- `vendor\bin\pint --test`: passed.
- `vendor\bin\phpstan analyse`: [OK] No errors.
- Cambio de contraseña probado por HTTP con los 4 casos (clave actual mala,
  sin números, corta, y el exitoso que limpia `debe_cambiar_password`).

---

## 5. Comprobación manual pendiente (único pendiente de la FASE 7)

Requiere navegador, no se puede automatizar desde aquí:

```
Terminal 1:  cd epcoro-api  && php artisan serve --host=127.0.0.1 --port=8000
Terminal 2:  cd epcoro-web  && npm run dev
Abrir:       http://localhost:5173
```

1. **Cambio obligatorio**: entrar con `admin` / `Epcoro2026dev1`. La bandera
   `debe_cambiar_password` está puesta a propósito, así que debe abrir la
   pantalla `/cambio-clave` y **no debe dejar navegar a ninguna otra ruta**
   hasta cambiar la clave. Probar una clave sin números (debe marcar el campo)
   y después una válida.
2. **Menú por rol**: con el admin se ven Liquidaciones, Revisión, Reportes,
   Parámetros, Catálogos y Usuarios; crear un `tecnico` y verificar que solo
   vea lo suyo (o mirar `/parametros` sin permiso: debe dar 403 / NoAutorizado).
3. **Drawer móvil**: F12 -> responsive, la barra se vuelve menú lateral
   deslizante.

> La clave `Epcoro2026dev1` es **solo de desarrollo local**. Regenerarla con el
> procedimiento del paso `[110]` de `pasos.txt` (aleatoria, se muestra una vez)
> antes de cualquier cosa que no sea esta máquina. No subir claves a producción.

---

## 6. Qué sigue: FASE 8 - módulo de captura

Checklist en `instrucciones.txt` (línea ~1433). En resumen: formulario por
secciones (transacción, comprador, vendedor, procedencia, técnico),
`SelectorUbicacion.vue` en cascada de 4 selects, `ResumenCalculo.vue` con
cálculo en vivo, wizard para celular, validaciones con vee-validate espejo del
backend, lista con filtros + paginación por URL, detalle con PDF/Excel.

- Criterio: crear una liquidación desde el celular y desde el escritorio con
  los mismos resultados que el Excel de referencia (`docs/hoja.xlsx`).
- Antes de empezar: registrar la entrada de apertura de FASE 8 en `pasos.txt`.

---

## 7. Reglas que no se rompen

- **Bitácora ligera**: en `pasos.txt` solo se registran **decisiones**,
  **bugs raros** con su arreglo y **verificaciones** que cambian el estado
  de una fase. Los comandos rutinarios (`npm install`, docs, auditorías de
  partida) ya no se anotan. Las entradas que sí se escriben conservan el
  formato `OBJETIVO / COMANDO / RESULTADO / ESTADO`.
- **Verificaciones en bloque**: `pint` + `phpstan` al cerrar fase; tests
  solo al tocar lógica de negocio; `npm run build` solo antes de un commit.
  Detalle en `README.md`, sección "Proceso".
- **Un commit por fase**, solo cuando el usuario lo confirma.
- Versiones y trampas del entorno: `pasos_otra_maquina.txt` y los propios
  comentarios de las FASES 0-6 en `pasos.txt`.
- Backend en `/api/v1` con auth por cookie Sanctum; el alias `/auth` es
  deprecado y se conserva por compatibilidad.

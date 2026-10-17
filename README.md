# SINCHI WAYRA

Sistema de liquidaciones de mineral para SINCHI WAYRA, Bolivia.

El sistema reemplaza el formulario `hoja.xlsx` que hoy se completa a mano y se
archiva en Excel. La prioridad de diseño es que **el sistema cuadre con el
Excel oficial al centavo**, porque ese documento es la referencia que revisa la
Gerencia.

## Estructura

```
epcoro/
|-- epcoro-api/       API REST en Laravel 13 + PostgreSQL 17
|-- epcoro-web/       SPA en Vue 3 + Vite + Tailwind
|-- docs/
|   |-- hoja.xlsx                     formulario oficial (fuente de verdad)
|   |-- diagrama_sist_epcoro.drawio   flujo de estados y roles
|   |-- diagrama-erd.md               entidad-relacion (deriva de las migraciones)
|   |-- catalogo-campos.md            celda Excel <-> columna BD <-> campo UI
|   `-- reglas-negocio.md             formulas y maquina de estados
|-- instrucciones.txt  especificacion tecnica v1.2
`-- pasos.txt          bitacora de todos los comandos ejecutados
```

## Documentos que mandan

| Documento | Para que sirve |
|---|---|
| `instrucciones.txt` | Especificacion tecnica completa v1.2. Correcciones C1-C9 y decisiones D1-D4. |
| `docs/hoja.xlsx` | El formulario real. Todos los casos de prueba salen de aca. |
| `docs/diagrama_sist_epcoro.drawio` | Roles y flujo de estados. |
| `docs/reglas-negocio.md` | Decisiones D1-D4 del negocio y redondeo verificado. |
| `epcoro-api/docs/api.md` | Contrato de la API `/api/v1` (endpoints, estados, recursos, errores). |
| `pasos.txt` | Bitacora. **Solo decisiones, bugs raros y verificaciones (ver Proceso).** |

## Proceso (protocolo ligero)

El costo de proceso no debe superar al de codigo. Vigente desde 2026-10-07.

**Bitacora (`pasos.txt`)**: se anota SOLO lo que vale la pena guardar:

- una **decision** tomada (no las que ya vienen en `instrucciones.txt`)
- un **bug raro** y su arreglo (ej: el 419 del proxy `/sanctum`)
- un **resultado de verificacion** que cambia el estado de una fase

No se anotan comandos rutinarios: `npm install`, ediciones de documentacion,
auditorias de partida, instalaciones exitosas. Las entradas que si se
escriben conservan el formato `[N] fecha hora - FASE` con
`OBJETIVO / COMANDO / RESULTADO / ESTADO`.

**Verificaciones en bloque, no tras cada paso:**

| Check | Cuando correrlo |
|---|---|
| `vendor\bin\pint` + `phpstan` | Una vez al **cerrar fase** (o en guardado del editor) |
| `php artisan test` | Solo al tocar **logica de negocio** (calculo, estados, validaciones) |
| `npm run build` | Solo **antes de un commit** |
| Documentacion (README, ERD, `api.md`) | Solo **al cerrar fase** |

**No se toca:** un commit por fase, los tests y la regla del half-up,
`RETOMAR.md`.

## La regla mas importante del proyecto

El calculo monetario **no redondea en pasos intermedios**. Se calcula con
precision decimal completa y se redondea half-up a 2 decimales **una sola vez,
al final**, sobre los montos en bolivianos.

```
peso_fino     = peso_gramos * ley_pagable
usd_gramo     = cotizacion_usd_oz_t / 31.1035
bruto_exacto  = usd_gramo * tipo_cambio * peso_fino
aporte        = aplica_caja_salud ? bruto_exacto * 0.018 : 0     (decision D4)
deducciones   = aporte                          (no hay descuentos variables)
bruto         = halfUp(bruto_exacto, 2)
total_deducc  = halfUp(deducciones, 2)
neto          = halfUp(bruto_exacto - deducciones, 2)
```

Los cuatro montos en bolivianos se redondean **una sola vez cada uno**, y
cada uno desde su valor exacto. En particular el neto se resta **antes** de
redondear: `bruto_ya_redondeado - aporte_ya_redondeado` seria un segundo
redondeo, que la regla prohíbe. En los casos ORO las dos formas coinciden;
en el caso de la decisión D4 dan `52166.29` (la correcta) y `52166.30`.

Si se redondea a 6 decimales en el medio, el resultado da `1 072 018.83` en vez
de `1 072 018.84`. Un centavo. El Excel oficial tiene el numero sin redondear.

El aporte se calcula sobre el bruto **sin redondear** (decision D4). En el caso
ORO da lo mismo, pero en un muestreo de 3 000 brutos las dos reglas difieren en
15 casos, siempre por un centavo.

Para el half-up, `bcadd($x, '0', 2)` **no sirve**: trunca. Usar el helper
`halfUp()` de la seccion 4.3 de `instrucciones.txt`, verificado contra 17 casos
limite.

Los montos calculados **se persisten** y los reportes **no los recalculan**.
Ver seccion 4.3 de `instrucciones.txt`.

## Casos de prueba ORO

Con peso `1944.53` g, ley `0.9302`, cotizacion `2648.57` USD/OzT y tipo de
cambio `6.96`:

| Caso | Caja de salud | Bruto | Aporte | Neto |
|---|---|---|---|---|
| ORO-A (`C20 = NO`, replica del Excel) | no | 1 072 018.84 | 0.00 | **1 072 018.84** |
| ORO-B (`C20 = SI`) | si | 1 072 018.84 | 19 296.34 | **1 052 722.50** |

## Entorno de desarrollo

- PHP 8.4.26 en `C:\tools\php84` (Laravel 13 exige 8.3+)
- Composer 2.9.7
- PostgreSQL 17.6 nativo, servicio `postgresql-x64-17`, puerto `5432`
- Node.js 24 / npm 11
- Base: `epcoro` - rol `epcoro` / `epcoro_secret` (**solo desarrollo**)
- Zona horaria: `America/La_Paz`

XAMPP sigue instalado y sus Apache y MySQL funcionan; su PHP 8.2 quedo atras en
el PATH y no se usa para este proyecto.

## Instalacion (otro desarrollador)

Requisitos: **PHP 8.3+** con las extensiones `pdo_pgsql`, `pgsql`, `mbstring`,
`openssl`, `bcmath`, `gd`; **Composer 2**; **PostgreSQL 17**; **Node.js 22+** y
**npm**. En Windows, el cliente de PostgreSQL (`pg_dump`) debe estar en el
`PATH` para los respaldos.

### Backend (`epcoro-api/`)

```bash
cd epcoro-api
composer install
copy .env.example .env            # bash: cp .env.example .env
php artisan key:generate
```

Editar `.env` y apuntar la base de datos:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=epcoro
DB_USERNAME=epcoro
DB_PASSWORD=epcoro_secret
```

Crear la base y sembrarla (esquema, catalogos, parametros y 4 usuarios de
ejemplo):

```bash
php artisan migrate --seed
php artisan serve                 # http://localhost:8000
```

El seeding deja las cuatro cuentas de ejemplo con las contrasenas conocidas de
`usuarios_prueba.txt`: `admin`/`Admin123`, `tecnico`/`Tecnico123`,
`revisor`/`Revisor123` y `gerente`/`Gerente123`. Son **solo para desarrollo**:
fuera de `local`/`testing` los seeders generan una aleatoria que se muestra una
sola vez. Ninguna de estas cuentas debe existir en produccion.

### Frontend (`epcoro-web/`)

```bash
cd epcoro-web
npm ci
copy .env.example .env.local      # opcional: solo cambia el proxy de Vite
npm run dev                       # http://localhost:5173
```

En desarrollo, Vite proxea `/api` y `/sanctum` al backend
(`VITE_API_PROXY_TARGET`, por defecto `http://localhost:8000`). La app siempre
llama a rutas relativas: en produccion es Nginx quien enruta al backend.

### Verificacion (antes de dar por bueno un cambio)

```bash
cd epcoro-api
composer check                    # pint + phpstan + php artisan test
cd ../epcoro-web
npm run build                     # vue-tsc + vite build
```

### Produccion (resumen)

```bash
# backend
cp .env.production.example .env   # y completar los valores reales
php artisan key:generate
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan sinchiwayra:revisar-produccion   # falla si APP_DEBUG=true, CORS/Sanctum
                                        # en localhost, base sin clave, etc.
php artisan config:cache route:cache view:cache

# frontend
npm ci && npm run build           # el dist/ lo sirve Nginx

# respaldos diarios (programador de tareas del sistema)
php artisan schedule:work         # o un cron: * * * * * php artisan schedule:run
php artisan sinchiwayra:respaldar      # respaldo manual inmediato (pg_dump -Fc)
```

La plantilla `.env.production.example` marca los valores obligatorios
(`APP_ENV=production`, `APP_DEBUG=false`, HTTPS, sin `localhost`, respaldos).
El respaldo diario (`sinchiwayra:respaldar`) queda programado en
`routes/console.php` a las 02:30 y purga los que superan la retencion de
`config/respaldo.php`.

### Despliegue en un servidor

La app se despliega como **una imagen unica** (SPA + API en el mismo origen, por
Sanctum). Hay dos guias paso a paso:

- `deploy_vps/despliegue_vps.md` - **VPS propio con Coolify** (o Docker Compose
  directo con Caddy). Usa `docker-compose.prod.yml` y
  `deploy_vps/.env.prod.example`. Costo fijo bajo (~€4-12/mes).
- `deploy_railway/despliegue_railway.md` - Railway (ya no tiene plan gratuito).

## Estado

| Fase | Estado |
|---|---|
| 0 - Reglas de negocio | completa para la FASE 4: D1-D4 resueltas; quedan 3 puntos que no bloquean |
| 1 - Entorno | completa salvo 1.6 (extensiones de VS Code) |
| 2 - Esqueleto del backend | completa |
| 3 - Autenticacion y roles | completa: login por cookie Sanctum, roles Spatie, bloqueo y 23 tests en verde |
| 4 - Esquema de base de datos | completa: 15 migraciones, ENUM de estados, catalogos sembrados, 13 factories, `LiquidacionPolicy` y 45 tests en verde |
| 5 - Servicio de calculo | completa (salvo 5.4.8, que necesita la API): calculo puro, parametros, numerador, maquina de estados y 87 tests en verde |
| 6 - API CRUD de liquidaciones | completa: `/api/v1` (auth, catalogos, parametros, liquidaciones, PDF/Excel, transiciones, observaciones, historial y reportes), 127 tests en verde, pint y phpstan limpios. Ver `epcoro-api/docs/api.md` |
| 7 - Frontend base y login | completa: SPA vue-ts con axios `/api/v1`, store auth, guard de sesion/permiso/cambio obligatorio, layouts, LoginView, pantalla de cambio de contrasena y menu por permisos. Login y cambio de contrasena verificados end-to-end por el proxy de Vite (`/api` + `/sanctum`). Queda solo la comprobacion visual en navegador |
| 8 - Captura de liquidacion | completa (8.1-8.12): planilla de captura reutilizada por crear/editar, wizard responsive de 5 pasos, validacion vee-validate espejo de `StoreLiquidacionRequest`, calculo en vivo espejo de `CalculoLiquidacionService` (BigInt, 1 solo redondeo half-up), lista con filtros y paginacion por URL (incluye municipio y vendedor con autocompletado), detalle con PDF/Excel e historial. El comprador se autocompleta desde su tabla propia (`compradores`, `GET /catalogos/compradores`, FK `liquidaciones.comprador_id`). Build verde y smoke test E2E via proxy. Queda solo la comprobacion visual en navegador |
| 9 - Flujo de estados y permisos | completa (9.1-9.7): barra de estado con `transiciones_disponibles`, modal de transicion (motivo obligatorio en rechazado/anulado), timeline de historial, edicion condicionada a auth+propiedad+estado, banda `GET /api/v1/bandeja` con pestanas por rol (tambien el item "Mi bandeja" en el menu y la ruta `/bandeja`), seeder de 4 usuarios de ejemplo, fix de paginacion (`page`, no `pagina`) y 153 tests en verde (26 nuevos). El backend ya cortaba por estado (422) antes que por permiso (403) |
| 10 - Reportes | completa (10.1-10.6): pantalla de reportes con rango quincenal/mensual/anual y por URL (`?fecha_desde=&fecha_hasta=`), tarjetas de resumen (n, pesos, bruto, aportes, deducciones y neto), tablas agrupadas por estado/municipio/area minera/presentacion/mes (grupos `por_area_minera` y `por_mes` agregados al resumen del backend), graficos Chart.js (dona por estado, evolucion mensual del neto, top municipios) y exportacion a Excel/PDF reutilizando los parametros del periodo |
| 11 - Calidad, seguridad y auditoria | completa (11.1-11.9): canal de log `auditoria` con registro de login/logout/fallos, cambios de estado, parametros, exportaciones y respaldos (`App\Support\Auditoria`); respaldo diario con `pg_dump` y purga por retencion (`sinchiwayra:respaldar`); preflight de produccion (`sinchiwayra:revisar-produccion`); `.env.production.example` y proxy de Vite por entorno; scripts `composer check` (pint + phpstan + tests); esta guia de instalacion; 190 tests en verde |

Cambios posteriores a la FASE 11 (2026-10-12): se retiro por completo la
feature "deducciones variables" (la unica deduccion es `ALICUOTA_APORTE`
1,8 % por D1; ya no existe `liquidacion_deducciones`) y el comprador paso a
tener tabla propia (`compradores`) con FK `liquidaciones.comprador_id`
(reemplaza `comprador_persona_id`); su autocompletado sale del catalogo
`compradores`, no de `parametros_generales`. Detalle en `pasos.txt` [122].

Tambien se agregaron los mensajes de validacion en espanol
(`epcoro-api/lang/es/`): las reglas genericas (required, regex, exists, decimal,
etc.) ya no caen al ingles de respaldo y los campos muestran su nombre legible
via el bloque `attributes`. Fijado con un test de candado en `pasos.txt` [123].

Comprobacion (`epcoro-api/`):

```bash
php artisan migrate:fresh --seed   # crea todo sin errores
php artisan migrate:rollback       # revierte todo
php artisan test                   # 190 tests / 932 assertions
vendor\bin\pint --test
vendor\bin\phpstan analyse
```

Detalle exacto y cronologico en `pasos.txt`.
# EPCORO

Sistema de liquidaciones de mineral para la Empresa Estatal de Producción y
Comercialización del Oro (EPCORO), Bolivia.

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
deducciones   = aporte + deducciones_variables                   (exacto)
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
| 8 - Captura de liquidacion | completa (8.1-8.12): planilla de captura reutilizada por crear/editar, wizard responsive de 5 pasos, validacion vee-validate espejo de `StoreLiquidacionRequest`, calculo en vivo espejo de `CalculoLiquidacionService` (BigInt, 1 solo redondeo half-up), lista con filtros y paginacion por URL, detalle con PDF/Excel e historial. Build verde y smoke test E2E via proxy (login, catalogos, alta rapida, crear `EP-BCB-1`, detalle, historial, PDF/Excel, edicion con recalculo). Queda la comprobacion visual en navegador y 2 filtros del listado (municipio/vendedor) postergados |
| 9 a 12 - Modulos, reportes UI, produccion | pendiente |

Comprobacion (`epcoro-api/`):

```bash
php artisan migrate:fresh --seed   # crea todo sin errores
php artisan migrate:rollback       # revierte todo
php artisan test                   # 127 tests / 699 assertions
vendor\bin\pint --test
vendor\bin\phpstan analyse
```

Detalle exacto y cronologico en `pasos.txt`.
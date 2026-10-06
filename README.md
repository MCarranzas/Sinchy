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
|   |-- diagrama-erd.md               entidad-relacion (se crea en FASE 4)
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
| `pasos.txt` | Bitacora. **Toda orden ejecutada se anota aca.** |

## La regla mas importante del proyecto

El calculo monetario **no redondea en pasos intermedios**. Se calcula con
precision decimal completa y se redondea half-up a 2 decimales **una sola vez,
al final**, sobre los montos en bolivianos.

```
peso_fino  = peso_gramos * ley_pagable
usd_gramo  = cotizacion_usd_oz_t / 31.1035
bruto_exacto = usd_gramo * tipo_cambio * peso_fino
bruto      = halfUp(bruto_exacto, 2)
aporte     = halfUp(bruto_exacto * 0.018, 2)   (si aplica, sobre el EXACTO)
neto       = bruto - deducciones
```

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
| 4 - Esquema de base de datos | pendiente |
| 5 a 12 - Calculo, API, frontend, reportes, produccion | pendiente |

Detalle exacto y cronologico en `pasos.txt`.
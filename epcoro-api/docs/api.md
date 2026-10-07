# API Epcoro - `/api/v1`

Documentacion de la API de liquidaciones de mineral oro (seccion 6 del plan de
desarrollo). Todas las rutas estan bajo el prefijo `/api/v1` y exigen sesion
(`auth:sanctum`) salvo `POST /auth/login`.

Formato de los cuerpos: JSON. Las fechas viajan como `YYYY-MM-DD`
(`fecha_transaccion`, parametros de filtro) o ISO-8601 con zona horaria
(campos `fecha_*` de auditoria).

## Respuestas

Los errores usan el formato de Laravel:

- `401`: no autenticado.
- `403`: autenticado pero sin permiso (`{"message": "This action is unauthorized."}`).
- `422`: regla de negocio o validacion. Los errores de negocio llevan un
  mensaje en `message`; los de formulario, el objeto `errors` estandar.
- `404`: recurso inexistente.

Orden de controles (documentado en cada controller): primero la maquina de
estados (regla de dominio, `422`), despues el permiso (`403`). Por eso una
liquidacion aprobada que nadie puede editar responde `422` y no `403`.

### Estados (`estado`, `estado_label`)

| valor          | etiqueta      |
|----------------|---------------|
| `creado`       | Creado        |
| `en_revision`  | En revision   |
| `revisado`     | Revisado      |
| `aprobado`     | Aprobado      |
| `rechazado`    | Rechazado     |
| `en_correccion`| En correccion |
| `anulado`      | Anulado       |
| `eliminado`    | Eliminado     |

### Maquina de estados (transiciones)

```
creado        --enviar---->    en_revision
creado        --eliminar-->   eliminado        (solo el autor, antes de enviar)
en_revision   --aprobar-----> revisado
en_revision   --rechazar----> rechazado
en_revision   --corregir-----> en_correccion
en_correccion --reenviar-----> en_revision
en_correccion --eliminar-----> eliminado       (solo el autor)
revisado      --aprobar-----> aprobado
revisado      --rechazar----> rechazado
{cualquier no final} --anular--> anulado
```

`aprobar` = aprobacion final (GERENTE). `anular` (GERENTE) y `rechazar`
(REVISOR) operan sobre estados revisados y posteriores. `eliminar` solo lo usa
el autor de la liquidacion y solo en `creado`/`en_correccion`.

## Sesion

### `POST /api/v1/auth/login`

- Body: `{ "usuario": string, "clave": string }`.
- Devuelve el usuario autenticado (mismo formato que `/auth/me`).

### `POST /api/v1/auth/logout`

Cierra la sesion. `204` sin cuerpo.

### `GET /api/v1/auth/me`

Devuelve el usuario de la sesion:

```json
{
  "data": {
    "id": 1,
    "nombre_completo": "ANA FLORES",
    "username": "aflores",
    "roles": ["tecnico"],
    "permisos": ["liquidaciones.crear", "liquidaciones.ver", "..."]
  }
}
```

### `POST /api/v1/auth/change-password`

- Body: `{ "clave_actual": string, "clave_nueva": string }`.

## Catalogos

Todos los `GET` devuelven `{ "data": [...] }` con listas pequeñas (ordenadas,
sin paginar).

| Ruta                                          | Contenido                                              |
|-----------------------------------------------|--------------------------------------------------------|
| `GET /catalogos/departamentos`                | Departamentos                                          |
| `GET /catalogos/provincias?departamento_id=`  | Provincias (filtrar por departamento)                  |
| `GET /catalogos/municipios?provincia_id=`     | Municipios (incluye `codigo`)                          |
| `GET /catalogos/localidades?municipio_id=`    | Localidades                                            |
| `GET /catalogos/areas-mineras`                | Areas mineras (`id`, `nombre`, `titular`, `codigo_ajam_patente`, ubicacion en cascada) |
| `GET /catalogos/personas?q=`                  | Personas vendedoras (`id`, nombres, `ci`, `telefono`)  |
| `GET /catalogos/presentaciones-mineral`       | Presentaciones (`id`, `nombre`)                        |
| `GET /cotizaciones`                           | Cotizaciones quincenales en vigencia                   |

### `POST /catalogos/personas`

Registra un vendedor nuevo.

- Body: `{ "nombres": string, "apellidos": string, "ci": string, "telefono"?: string }`.
- `ci` debe ser `^\d{1,10}$`; la forma `4.530.001` se rechaza con `422`.

## Parametros

### `GET /api/v1/parametros`

```json
{
  "data": [
    { "clave": "TIPO_CAMBIO_DEFECTO", "valor": "6.9100", "tipo": "decimal",
      "descripcion": "...", "actualizado_en": "2026-01-01T00:00:00-04:00" }
  ]
}
```

### `PUT /api/v1/parametros`

Solo admin (`parametros.gestionar`). Actualiza en mapa `{clave: valor}`; solo
se tocan claves que ya existen y los valores no numericos se rechazan
(incluida la notacion cientifica `1e3`).

- Body: `{ "parametros": { "TIPO_CAMBIO_DEFECTO": "6.9500" } }`.

## Liquidaciones

Todas las rutas de liquidacion devuelven el recurso `LiquidacionResource`
(grupos `insumos`, `calculo`, `comprador`, `vendedor`, `procedencia`,
`presentacion`, `deducciones`, `auditoria`). Los montos son **strings**, nunca
`float` (un float devuelve 1072018.83 donde la hoja dice 1072018.84).

```json
{
  "data": {
    "id": 1,
    "numero_documento": "EP-BCB-1",
    "codigo_formulario": "FORM-EP-BCB-1/2026",
    "correlativo": 1,
    "ejercicio": 2026,
    "fecha_transaccion": "2026-01-15",
    "estado": "creado",
    "estado_label": "Creado",
    "transiciones_disponibles": ["en_revision", "eliminado"],
    "insumos": {
      "peso_gramos": "1049.97",
      "ley_pagable": "0.9302",
      "tipo_cambio": "6.9100",
      "cotizacion_usd_oz_t": "2203.1735",
      "cotizacion_id": null,
      "aplica_caja_salud": true,
      "gramos_por_onza_troy": "31.103500"
    },
    "calculo": {
      "peso_fino_gramos": "976.6355",
      "valor_usd_gramo": "70.833620",
      "valor_bruto_bs": "1000.25",
      "aporte_caja_salud_bs": "18.00",
      "total_deducciones_bs": "120.00",
      "valor_neto_bs": "862.25"
    },
    "comprador": { "persona_id": null, "razon_social": "...", "nim": "...", "nit": "..." },
    "vendedor": { "persona_id": 2, "nombre": "ANA FLORES", "ci": "77532791" },
    "procedencia": {
      "area_minera_id": 1, "nombre_area_minera": "...", "titular_derecho_minero": "...",
      "codigo_ajam_patente": "...",
      "departamento": { "id": 1, "nombre": "..." },
      "provincia": { "id": 1, "nombre": "..." },
      "municipio": { "id": 1, "nombre": "...", "codigo": "..." },
      "localidad": { "id": 1, "nombre": "..." }
    },
    "presentacion": { "id": 1, "nombre": "ORO FUNDIDO" },
    "observaciones": null,
    "deducciones": [
      { "id": 1, "concepto": "APORTE CAJA DE SALUD - LEY 649", "tipo": "alicuota",
        "alicuota": "0.018000", "base_monto": "1000.25", "monto": "18.00", "obligatorio": true }
    ],
    "auditoria": {
      "registrado_por": { "id": 1, "nombre_completo": "...", "username": "..." },
      "fecha_registro": "...", "fecha_revision": null, "fecha_aprobacion": null,
      "fecha_rechazo": null, "motivo_rechazo": null, "fecha_anulacion": null,
      "motivo_anulacion": null
    }
  }
}
```

`transiciones_disponibles` es la lista de `estado_destino` que el usuario de la
sesion puede disparar (para dibujar botones); la seguridad real vive en la
Policy.

### `GET /api/v1/liquidaciones`

Listado paginado. Filtros (todos opcionales):

| Parametro        | Descripcion                                        |
|------------------|----------------------------------------------------|
| `estado`         | Uno de los valores del estado                       |
| `fecha_desde`    | `YYYY-MM-DD` (incluye)                             |
| `fecha_hasta`    | `YYYY-MM-DD` (incluye)                             |
| `municipio_id`   | Municipio de procedencia                            |
| `area_minera_id` | Area minera de procedencia                         |
| `vendedor_id`    | Id de la persona vendedora                         |
| `registrado_por` | Id del usuario que registro                        |
| `q`              | Busqueda libre                                     |
| `orden`          | `fecha`, `numero`, `neto`, `bruto` (con `-` para desc) |
| `page`           | Numero de pagina (estandar Laravel: los `links` del paginador ya apuntan a `?page=N`) |
| `por_pagina`     | 15 por defecto, maximo 100                          |

Respuesta: `{ "data": [...], "links": {...}, "meta": { "current_page", "per_page", "total", "last_page", ... } }`. (`pagina` se acepta como alias de `page` por compatibilidad.)

Los tecnicos ven solo lo propio (`registrado_por` = ellos); los que tienen
`liquidaciones.ver_todas` ven todo. Las eliminadas no aparecen.

### `POST /api/v1/liquidaciones`

Creacion (permiso `liquidaciones.crear`, solo tecnico). Sobre los cinutos de
insumos se recalcula el calculo en el backend (nunca se confia en el frontend).

- `numero_documento`: si no viene o viene vacio, el sistema lo asigna
  (`EP-BCB-<n>` con correlativo). Un numero externo va con `correlativo: null`
  y genera `codigo_formulario = FORM-<numero>/<año>`.
- `fecha_transaccion` determina el ejercicio.
- Reglas de formato (C5): `vendedor_ci` `^\d{1,10}$`; `comprador_nim`
  `^[0-9\-]{5,30}$`; `comprador_nit` `^\d{1,20}$`; `codigo_municipio`
  `^\d{1,10}$`; `codigo_ajam_patente` `^\d{1,20}$`. `ley_pagable` entre 0 y 1
  (`lte:1` excluido en 0), montos `decimal:0,2` y tipo/cotizacion `decimal:0,4`.
- `deducciones`: lista de `{concepto, monto}`. El aporte de caja de salud
  (1.8% sobre el bruto) se agrega solo si `aplica_caja_salud: true` y su monto
  es distinto de `0.00`.
- Se escribe la bitacora inicial (`null -> creado`) en la misma transaccion.
- `201` con el recurso.

### `GET /api/v1/liquidaciones/{id}`

Detalle. `200` con el recurso.

### `PUT /api/v1/liquidaciones/{id}`

Edicion PARCIAL (permiso `liquidaciones.editar`, solo el autor y solo en
estados editables): solo los campos enviados se modifican y se recalcula.
Campos validos: los mismos que en la creacion mas `observaciones`.

El numero asignado por el sistema (con `correlativo`) es inmutable: cambiarlo
responde `422` con el error en `numero_documento`. El numero externo si se
puede corregir. `200` con el recurso.

### `DELETE /api/v1/liquidaciones/{id}`

Borrado logico que dispara `estado -> eliminado` por la maquina de estados.
Solo el autor y solo en `creado`/`en_correccion`; en cualquier otro estado
responde `422`. `204` sin cuerpo.

### `GET /api/v1/liquidaciones/{id}/pdf`

El formulario oficial (vista). `200` `application/pdf`.

### `GET /api/v1/liquidaciones/{id}/excel`

El formulario oficial en Excel. `200`
`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`.

### `POST /api/v1/liquidaciones/{id}/transiciones`

Aplica una transicion de estado (seccion 6.7). Validaciones en orden fijo:
primero el **estado** (`422` si el actual no admite el destino o no es
editable) y recien despues el **permiso** (`403`): un no-tecnico que intenta
editar lo editar-lo-ajeno recibe 403, pero sobre una liquidacion aprobada
recibe 422 por estado.

- Body: `{ "estado_destino": "aprobado", "comentario"?: string, "motivo"?: string }`.
- `motivo` es obligatorio para `rechazado` (asentado como `motivo_rechazo`)
  y para `anulado` (asentado como `motivo_anulacion`).
- `200` con `{ "message": "Transicion aplicada: en_revision -> rechazado.",
  "data": <recurso> }`.

Tambien controla internos: `fecha_revision`, `fecha_aprobacion`, `fecha_rechazo`
con su motivo y `fecha_anulacion` con el motivo de anulacion.

### `POST /api/v1/liquidaciones/{id}/observaciones`

Comentario en el flujo (permiso `liquidaciones.comentar`, revisor).

- Body: `{ "comentario": string }`.
- `201` con `{ "data": { "id", "texto", "usuario", "registrado_en" } }`.

### `GET /api/v1/liquidaciones/{id}/historial`

Bitacora de cambios de estado, en orden cronologico (primera transicion primero):

```json
{ "data": [
  { "id": 2, "estado_anterior": "creado", "estado_nuevo": "en_revision",
    "comentario": null, "usuario": { "id": 1, "nombre_completo": "..." },
    "fecha": "2026-01-15T10:00:00-04:00" }
] }
```

`fecha` sigue el orden de los registros (`id`), mas reciente al final.

### `GET /api/v1/bandeja`

Conteos de las colas del flujo para el usuario de la sesion (permiso: estar
autenticado). El backend pone en **cero** la cola que el rol no puede tocar,
asi la UI esconde la pestana sin duplicar logica de permisos:

```json
{ "data": {
  "mis_liquidaciones": 6,
  "por_revisar": 2,
  "por_aprobar": 1
} }
```

- `mis_liquidaciones`: las que el usuario registro (propiedad; siempre).
- `por_revisar`: estado `en_revision`, solo si el rol tiene
  `liquidaciones.revisar` o `liquidaciones.solicitar_correccion`.
- `por_aprobar`: estado `revisado`, solo si el rol tiene `liquidaciones.aprobar`.

Las listas de cada cola se piden al mismo `GET /api/v1/liquidaciones`:
`?registrado_por=<id>` para mis liquidaciones y `?estado=en_revision` /
`?estado=revisado` para las otras dos.

## Reportes

Requieren permiso `reportes.ver` (gerente, admin). El rango se acepta como
`desde`/`hasta` o `fecha_desde`/`fecha_hasta` (dos nombres para el mismo
parametro), en `YYYY-MM-DD`. Los totales suman columnas ya calculadas por el
servicio; nunca se recalculan en el reporte. El estado `eliminado` se excluye.

### `GET /api/v1/reportes/resumen`

```json
{
  "data": {
    "rango": { "desde": "2026-01-01", "hasta": "2026-12-31" },
    "totales": {
      "liquidaciones": 10,
      "peso_total": "5432.10",
      "peso_fino_total": "4800.5555",
      "bruto_total": "482950.00",
      "aporte_total": "8693.10",
      "deducciones_total": "12032.50",
      "neto_total": "470917.50"
    },
    "por_estado": { "grupo": "estado", "filas": [
      { "valor": "aprobado", "liquidaciones": 3, "bruto_total": "90000.00", "neto_total": "88000.00" }
    ]},
    "por_municipio": { "grupo": "municipio", "filas": [] },
    "por_mineral": { "grupo": "presentacion", "filas": [] }
  }
}
```

### `GET /api/v1/reportes/liquidaciones`

Detalle paginado de las liquidaciones del periodo (permite comprobar de que se
compone el resumen). Mismos parametros de filtro que el listado mas `desplegado`.

### `GET /api/v1/reportes/exportar?formato=xlsx|pdf`

Exporta el listado del periodo. `xlsx` por defecto.
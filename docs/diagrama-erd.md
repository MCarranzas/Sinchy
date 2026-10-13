# Diagrama ERD — SINCHI WAYRA (FASE 4)

Fuente: las migraciones de `epcoro-api/database/migrations/2026_10_06_*.php`.
El diagrama se deriva del esquema real, no de un dibujo aparte: si cambia una
migración, cambia este archivo.

Tipos abreviados a propósito (sin precisión) para que el bloque sea legible;
la precisión exacta de cada `numeric` está en su migración.

```mermaid
erDiagram
    users ||--o{ liquidaciones : "registra"
    users ||--o{ liquidacion_observaciones : "comenta"
    users ||--o{ liquidacion_estado_historial : "firmo"

    cotizaciones_quincenales ||--o{ liquidaciones : "cotizacion del dia"
    personas ||--o{ liquidaciones : "vendedor"
    compradores ||--o{ liquidaciones : "comprador"
    areas_mineras ||--o{ liquidaciones : "procedencia"
    presentaciones_mineral ||--o{ liquidaciones : "tipo de mineral"
    departamentos ||--o{ liquidaciones : "procedencia"
    provincias ||--o{ liquidaciones : "procedencia"
    municipios ||--o{ liquidaciones : "procedencia"
    localidades ||--o{ liquidaciones : "procedencia"

    liquidaciones ||--o{ liquidacion_observaciones : "conversacion"
    liquidaciones ||--o{ liquidacion_estado_historial : "bitacora"

    departamentos ||--o{ provincias : "agrupa"
    provincias ||--o{ municipios : "agrupa"
    municipios ||--o{ localidades : "agrupa"

    departamentos ||--o{ areas_mineras : "ubicacion"
    provincias ||--o{ areas_mineras : "ubicacion"
    municipios ||--o{ areas_mineras : "ubicacion"
    localidades ||--o{ areas_mineras : "ubicacion"

    users {
        bigint id PK
        varchar username UK
        varchar nombre_completo
        bool activo
        int intentos_fallidos
        timestamp bloqueado_hasta
    }

    departamentos {
        bigint id PK
        varchar codigo UK
        varchar nombre UK
    }

    provincias {
        bigint id PK
        bigint departamento_id FK
        varchar codigo
        varchar nombre
    }

    municipios {
        bigint id PK
        bigint provincia_id FK
        varchar codigo
        varchar nombre
    }

    localidades {
        bigint id PK
        bigint municipio_id FK
        varchar codigo
        varchar nombre
    }

    personas {
        bigint id PK
        varchar nombres
        varchar apellidos
        varchar nombre_completo
        varchar ci UK
        varchar nit UK
        varchar tipo_documento
        bool activo
    }

    compradores {
        bigint id PK
        varchar razon_social
        varchar nim
        varchar nit
        bool activo
    }

    areas_mineras {
        bigint id PK
        varchar nombre_area_minera
        varchar titular_derecho_minero
        varchar codigo_ajam_patente
        bigint departamento_id FK
        bigint provincia_id FK
        bigint municipio_id FK
        bigint localidad_id FK
        bool activo
    }

    presentaciones_mineral {
        bigint id PK
        varchar nombre UK
    }

    parametros_generales {
        varchar clave PK
        varchar valor
        varchar tipo
        text descripcion
    }

    cotizaciones_quincenales {
        bigint id PK
        smallint ejercicio
        smallint quincena
        date fecha_inicio
        date fecha_fin
        numeric valor_usd_oz_t
        varchar fuente
    }

    correlativos {
        varchar tipo PK
        smallint ejercicio PK
        bigint ultimo
    }

    liquidaciones {
        bigint id PK
        varchar numero_documento UK
        varchar codigo_formulario
        bigint correlativo UK
        smallint ejercicio
        date fecha_transaccion
        timestamp fecha_registro
        bigint usuario_registro_id FK
        enum estado
        timestamp fecha_revision
        bigint usuario_revision_id FK
        timestamp fecha_aprobacion
        bigint usuario_aprobacion_id FK
        timestamp fecha_rechazo
        bigint usuario_rechazo_id FK
        text motivo_rechazo
        timestamp fecha_anulacion
        bigint usuario_anulacion_id FK
        text motivo_anulacion
        text observaciones
        numeric tipo_cambio
        bigint cotizacion_id FK
        numeric cotizacion_usd_oz_t
        bigint comprador_id FK
        varchar comprador_razon_social
        varchar comprador_nim
        varchar comprador_nit
        bool aplica_caja_salud
        bigint vendedor_persona_id FK
        varchar vendedor_nombre
        varchar vendedor_ci
        bigint area_minera_id FK
        varchar nombre_area_minera
        varchar titular_derecho_minero
        varchar codigo_ajam_patente
        bigint departamento_id FK
        varchar departamento_nombre
        bigint provincia_id FK
        varchar provincia_nombre
        bigint municipio_id FK
        varchar municipio_nombre
        varchar codigo_municipio
        bigint localidad_id FK
        varchar localidad_nombre
        bigint presentacion_mineral_id FK
        varchar presentacion_nombre
        numeric peso_gramos
        numeric ley_pagable
        numeric gramos_por_onza_troy
        numeric peso_fino_gramos
        numeric valor_usd_gramo
        numeric valor_bruto_bs
        numeric aporte_caja_salud_bs
        numeric total_deducciones_bs
        numeric valor_neto_bs
    }

    liquidacion_observaciones {
        bigint id PK
        bigint liquidacion_id FK
        bigint usuario_id FK
        varchar tipo
        text texto
        timestamp created_at
    }

    liquidacion_estado_historial {
        bigint id PK
        bigint liquidacion_id FK
        enum estado_anterior
        enum estado_nuevo
        bigint usuario_id FK
        varchar usuario_nombre
        text comentario
        timestamp created_at
    }
```

## Notas que el diagrama no puede decir

1. **Snapshot (columnas `*_nombre`).** `liquidaciones` guarda el nombre junto
   a la FK: `departamento_id` **y** `departamento_nombre`, `area_minera_id`
   **y** `nombre_area_minera`, `vendedor_persona_id` **y** `vendedor_nombre`,
   `comprador_id` **y** `comprador_razon_social`.
   La FK da integridad referencial; el snapshot congela lo que se imprimió
   ese día. Si mañana corrigen el nombre de un municipio, los PDF históricos
   no cambian. Lo mismo en `liquidacion_estado_historial.usuario_nombre`.

2. **Montos calculados persistidos (T3).** `peso_fino_gramos`,
   `valor_usd_gramo`, `valor_bruto_bs`, `aporte_caja_salud_bs`,
   `total_deducciones_bs` y `valor_neto_bs` se guardan y los reportes **no
   los recalculan**: el Excel oficial es la referencia.

3. **ENUM `liquidacion_estado`.** Creado con `DB::statement`, no con
   `$table->enum()`, porque se quiere el tipo nativo de PostgreSQL:
   `creado`, `en_revision`, `revisado`, `aprobado`, `rechazado`,
   `en_correccion`, `anulado`, `eliminado`. Se usa en `liquidaciones.estado`
   y en `liquidacion_estado_historial.estado_anterior/estado_nuevo`.

4. **PKs especiales.** `parametros_generales.clave` es la clave primaria (no
   hay `id`) y `correlativos` es compuesta por `(tipo, ejercicio)`.

5. **Una sola deducción (D1).** Existe `liquidaciones.aplica_caja_salud` y el
   `total_deducciones_bs` guarda ese único monto. **No existe** la tabla
   `liquidacion_deducciones` (la feature de deducciones variables se eliminó)
   ni `aplica_aporte_solidario` / `aporte_solidario_bs`: por D1 el aporte es
   único (1,8 %).

6. **Vista `v_liquidaciones_detalle`.** No aparece en el diagrama porque es
   una vista, no una tabla: aporta los JOIN de `users` (registrado por) para
   que los reportes no los repitan.

7. **Roles y permisos.** Tablas de la FASE 1 (`roles`, `permissions`,
   `model_has_roles`, `model_has_permissions`, `role_has_permissions`), fuera
   del alcance de negocio: un usuario tiene exactamente un rol.

## Cómo verificar que el diagrama sigue siendo cierto

```bash
psql -U epcoro -d epcoro -c "\d liquidaciones"
php artisan test --filter=TablasDeModelosTest
```

# Reglas de negocio de SINCHI WAYRA

Decisiones confirmadas por el usuario de negocio el 2026-10-05. Las dos
preguntas que bloqueaban el diseño de la base de datos (FASE 4) quedaron
resueltas ese mismo día: la nomenclatura del aporte (D1) y la anulación de una
liquidación aprobada (D2).

Si algo aquí contradice `hoja.xlsx`, manda `hoja.xlsx` para el cálculo y este
documento para el flujo. Si algo contradice `instrucciones.txt`, manda este
documento: `instrucciones.txt` se actualizó a v1.2 en la misma fecha.

---

## D1. El aporte es uno solo, del 1.8 %, gobernado por un interruptor

**Decisión.** La celda SI/NO del formulario controla una única deducción: si dice
SI se aplica el 1.8 %, si dice NO se aplica 0.

**Por qué.** `hoja.xlsx` tiene exactamente una fila de aporte,
`CAJA DE SALUD APORTE 1,8 %`, controlada por el SI/NO de la celda C20. No existe
ninguna segunda deducción.

**Consecuencias técnicas.**

- Una sola tasa: `ALICUOTA_APORTE = 0.018`.
- Un solo interruptor por liquidación: `aplica_caja_salud` (boolean).
- **No se crea** un parámetro `ALICUOTA_APORTE_SOLIDARIO` aparte. La corrección
  C4 de `instrucciones.txt` queda resuelta y en desuso.
- **No se crea** la columna `aplica_aporte_solidario` prevista en el plan v1.0.

**RESUELTO el 2026-10-05 (nomenclatura).** El usuario de negocio confirmó que el
aporte solidario **es siempre el 1.8 %**. No hay una segunda deducción del 1 %:
la diferencia entre el nombre del negocio y el del Excel era solo el nombre, no
el porcentaje.

Consecuencia: la FASE 4 se construye con UNA sola deducción. No se agrega columna,
ni parámetro `ALICUOTA_APORTE_SOLIDARIO`, ni una segunda fila en el reporte. El
nombre en el sistema es el del Excel, `aporte_caja_salud`, porque `hoja.xlsx` es
el documento que se revisa en la auditoría; el negocio lo llama "aporte
solidario" y ambos se refieren a lo mismo.

Este punto queda CERRADO y ya no bloquea la FASE 4.

---

## D2. Una liquidación aprobada no se corrige jamás

**Decisión.** No hay corrección de una liquidación_APROBADA.

**Qué significa en el sistema.**

- En estado `aprobado` el registro es **de solo lectura**. No hay `PUT` ni
  `PATCH` ni `DELETE`, sin importar el rol. Ni siquiera el administrador.
- `aprobado` **no tiene salida**: ni a `anulado` ni a ningún otro estado. Si una
  liquidación aprobada resultara estar mal, no se toca: se deja así y se registra
  el problema por fuera del sistema.
- Todo cambio de estado queda en `liquidacion_estado_historial` con usuario,
  estado anterior, estado nuevo, comentario y fecha.

**RESUELTO el 2026-10-05.** El usuario de negocio confirmó: una liquidación
aprobada **no se puede anular, solo rechazar**. O sea que `anulado` es
inalcanzable desde `aprobado`, y la única forma de salir de una liquidación con
problemas es el **rechazo**, que ocurre antes de la aprobación. Este punto queda
CERRADO y ya no bloquea la FASE 4.

**Consecuencia sobre `anulado`.** El estado `anulado` sigue existiendo, pero
únicamente para liquidaciones que **todavía no fueron aprobadas**. Anular es
cancelar un trámite en curso; no es la salida de una aprobación. Por eso
`fecha_anulacion` y `usuario_anulacion_id` quedan en el esquema.

**RESUELTO el 2026-10-12 (rechazo terminal + devolución).** El usuario de negocio
confirmó dos cosas que conviven en la máquina de estados:

1. **Una liquidación rechazada es final**: no se corrige y no se anula. Un
   rechazo se registra con `motivo_rechazo` (obligatorio) y el expediente queda
   cerrado, lo mismo que una aprobada.
2. **La devolución es una acción aparte del rechazo**, con observación
   obligatoria, y no cierra el expediente:
   - el **revisor** devuelve al técnico (`en_revision` → `en_correccion`) para
     que corrija y reenvíe (`en_correccion` → `en_revision`);
   - el **gerente** devuelve al revisor (`revisado` → `en_revision`) para que
     corrija su revisión y la marque de nuevo como revisada.

   El estado `en_correccion` vuelve a ser alcanzable, pero **solo por la
   devolución del revisor**, nunca desde el rechazo.

**Ambigüedad que había que cerrar.** `instrucciones.txt` v1.0 definía un estado
`en_correccion` y un estado `eliminado`. Con la decisión actual:

- `en_correccion` solo se alcanza desde `en_revision` por la devolución del
  revisor. El rechazo sigue siendo terminal y no hay reenvío a corrección.
- `eliminado` (borrado lógico) solo se permite desde `creado`. Borrar una
  liquidación en revisión o aprobada por la puerta de atrás anularía la decisión
  D2.

**Máquina de estados.** La fila `rechazado` está CONFIRMADA por el usuario de
negocio (terminal, sin salida); el resto de la tabla proviene de la tabla
histórica, con las dos aristas de devolución agregadas el 2026-10-12. Si alguna
no es la correcta, hay que corregirla antes de implementar las transiciones:

| Desde | Hacia |
|---|---|
| `creado` | `en_revision`, `anulado`, `eliminado` |
| `en_revision` | `revisado`, `rechazado`, `anulado`, `en_correccion` (devolver al técnico) |
| `revisado` | `aprobado`, `rechazado`, `anulado`, `en_revision` (devolver al revisor) |
| `rechazado` | — (terminal, inmutable) CONFIRMADO el 2026-10-12 |
| `en_correccion` | `en_revision`, `anulado` (vive: solo se alcanza por devolución) |
| `aprobado` | — (terminal, inmutable) CONFIRMADO |

---

## D3. Visibilidad según quién la registró

**Decisión.**

- El **administrador** ve todas las liquidaciones.
- Quienes no son administrador ven las suyas.
- **Para aprobar no hay restricción**: un aprobador ve cualquier liquidación
  registrada, aunque no sea suya.

**Matriz de visibilidad.**

| Rol | Liquidaciones que ve |
|---|---|
| `admin` | todas |
| `gerente` (aprobador) | todas |
| `revisor` | todas las que no ha registrado él, **más** las propias |
| `tecnico` | solo las suyas |

**Implementación.** Un `LiquidacionPolicy::viewAny` decide la visibilidad, y el
listado filtra por `usuario_registro_id = auth()->id()` para quien no tenga
acceso global. La columna `usuario_registro_id` ya está en el diseño.

**Pendiente de confirmar.** La decisión dice "un aprobador puede ver cualquier
liquidación registrada". El diagrama define cuatro roles y solo `gerente` es el
aprobador. Se asumió que `revisor` también ve todas las que no ha registrado él,
porque de lo contrario no podría revisar el trabajo de otros y el paso de
revisión sería imposible. Si la intención era que el revisor solo viera las suyas,
hay que decirlo.

---

## D4. El aporte se calcula sobre el bruto sin redondear

**Decisión.** `aporte = bruto_exacto × 0.018`, y el resultado se redondea a 2
decimales una sola vez. No se usa el bruto ya redondeado como base.

**Consecuencias técnicas.**

- El servicio de cálculo conserva el bruto con precisión completa hasta el
  último paso.
- El aporte se redondea al final, nunca antes.

**Verificado con aritmética decimal de 30 dígitos** (el 2026-10-05):

| Regla | Aporte | Neto |
|---|---|---|
| sobre el bruto exacto (la elegida) | 19 296.34 | 1 052 722.50 |
| sobre el bruto redondeado | 19 296.34 | 1 052 722.50 |

En este caso coinciden, y ambos coinciden con el Excel. Pero **no siempre**: en
un muestreo de 3 000 brutos, las dos reglas difieren en 15 casos (0.5 %), siempre
por un centavo. Ejemplos:

| Bruto | Aporte sobre el exacto | Aporte sobre el redondeado |
|---|---|---|
| 53 122.496411 | 956.20 | 956.21 |
| 56 309.723178 | 1 013.58 | 1 013.57 |
| 120 335.275943 | 2 166.03 | 2 166.04 |

Por eso la decisión importa y hay que dejar un test que la fije.

---

## Regla de redondeo (consolidada, verificada por ejecución)

El cálculo no redondea en ningún paso intermedio. Se redondea half-up a 2
decimales **una sola vez, al final**, solo sobre montos en bolivianos.

**Advertencia técnica verificada el 2026-10-05 al probar esto:** la forma
"natural" de redondear en PHP está mal. `bcadd($x, '0', 2)` **trunca** en lugar de
redondear:

```
bcadd('1072018.8360554035', '0', 2)  ->  1072018.83   <-- MAL
```

Con esa expresión el bruto da 83 y la liquidación no cuadra con el Excel. Y
`bcdiv($x, $y, $n)` tampoco sirve: trunca con un dígito de guarda, así que
`bcdiv('100.505', '100', 2)` devuelve `1.00` en vez de `1.01`.

La forma correcta, verificada contra 17 casos límite:

```php
function halfUp(string $valor, int $decimales = 2, int $escala = 30): string
{
    $factor = bcpow('10', (string) $decimales, 0);

    return bcdiv(
        bcadd(bcmul($valor, $factor, $escala), '0.5', 0),
        $factor,
        $decimales
    );
}
```

Desplazar, sumar media unidad **en el dominio ya desplazado** (0.5, no 0.005),
truncar a escala entera con `bcadd` y recién ahí dividir, cuando la división ya
es exacta.

Casos límite comprobados: `1.005 → 1.01`, `2.675 → 2.68`, `0.005 → 0.01`,
`1072018.835 → 1072018.84`, `1072018.834 → 1072018.83`, `999999.995 → 1000000.00`,
`1.015 → 1.02` (half-up, no "banker's rounding").

## Casos de prueba ORO (confirman contra `hoja.xlsx`)

Insumos: peso `1944.53` g, ley `0.9302`, cotización `2648.57` USD/OzT, tipo de
cambio `6.96`.

| Caso | Interruptor | Bruto | Aporte | Neto |
|---|---|---|---|---|
| ORO-A | NO | 1 072 018.84 | 0.00 | **1 072 018.84** |
| ORO-B | SI | 1 072 018.84 | 19 296.34 | **1 052 722.50** |

ORO-A es la réplica exacta de la fila real del Excel (C20 = NO).

---

## Abierto: qué queda por confirmar

**Cerrado el 2026-10-05** (ya no bloquean la FASE 4):

1. ~~**Nomenclatura del aporte** (D1)~~ → **CERRADO.** El aporte solidario es
   siempre el 1.8 %. Es una sola deducción, no dos.
4. ~~**Quién anula, y si una aprobada se puede anular** (D2)~~ → **CERRADO.**
   Una liquidación aprobada no se puede anular, solo rechazar. `aprobado` es
   terminal y `anulado` es inalcanzable desde ahí.

**Cerrado el 2026-10-12**:

6. ~~**Máquina de estados: rechazado → en_correccion**~~ → **CERRADO.** El
   usuario de negocio confirmó que **el rechazo es final**: una rechazada no se
   corrige ni se anula, `rechazado` es terminal. A la vez confirmó la
   **devolución** como acción aparte: el revisor devuelve al técnico
   (`en_revision` → `en_correccion`) y el gerente devuelve al revisor
   (`revisado` → `en_revision`), las dos con observación obligatoria. Queda
   implementado en `EstadoLiquidacion.php`.

**Sigue abierto:**

2. **Visibilidad del revisor** (D3): ¿ve todas las liquidaciones ajenas o solo
   las que están en revisión? Hoy está implementado como "ve todas". No cambia
   el esquema, es una Policy.
5. **Vía de corrección para una aprobada mal cargada**: si el error es grave
   (un peso mal escrito), ¿se hace una liquidación nueva que referencie a la
   anterior, o se acepta el error? Hoy no existe ninguna de las dos. Con la
   decisión de que `aprobado` es terminal, esta vía es la única salida posible
   para un error grave, así que conviene resolverla antes de construir la FASE 5.

Nota: el punto 2 no cambia el esquema de la base de datos, se puede resolver
durante la FASE 4. El punto 5 sí afecta el modelo, pero no bloquea: si no se
decide, la FASE 4 se construye sin la tabla de referencias y se agrega despues.
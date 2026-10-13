# Plan de pruebas - SINCHI WAYRA

Guia para probar los **flujos** y los **roles** del sistema de liquidaciones de
mineral. Sirve tanto para una prueba manual por la interfaz como para ubicar la
prueba automatizada que ya cubre cada caso.

- Backend: `epcoro-api/` (Laravel 13 + PostgreSQL 17), API en `/api/v1`.
- Frontend: `epcoro-web/` (Vue 3 + Vite), arranca en `http://localhost:5173`.
- Referencia de negocio: `instrucciones.txt` (secciones 4.1, 4.2, 4.3 y FASES
  8-11) y `docs/reglas-negocio.md` (decisiones D1-D4).
- Contrato de la API: `epcoro-api/docs/api.md`.

> Los IDs de caso de este plan (`AUTH-01`, `FLU-03`, ...) son estables; use una
> copia de la tabla de la seccion 11 para registrar cada corrida.

---

## 1. Objetivo y alcance

Verificar que:

1. Cada **rol** ve y puede hacer exactamente lo que le corresponde, y nada mas.
2. El **flujo de estados** de una liquidacion se recorre completo y respeta la
   maquina de estados (estados validos, terminales inmutables, motivos).
3. La **separacion de responsabilidades** se cumple (nadie aprueba lo propio).
4. El **calculo** coincide con el Excel oficial al centavo.
5. Los **reportes** cuadran con las liquidaciones del periodo.
6. La **seguridad y auditoria** de la FASE 11 funcionan (sesion, bloqueo,
   log de acciones sensibles, respaldo y revision de entorno).

Fuera de alcance: rendimiento, accesibilidad, y las pantallas de
`Parametros`, `Catalogos` y `Usuarios`, que siguen como vistas provisionales
("En construccion"). Esas funciones se prueban por API (seccion 6.7).

---

## 2. Entorno de pruebas

### 2.1 Arranque

```powershell
# Terminal 1 - backend
Set-Location epcoro-api
php artisan migrate:fresh --seed      # esquema + catalogos + parametros + usuarios
php artisan serve --host=127.0.0.1 --port=8000

# Terminal 2 - frontend
Set-Location epcoro-web
npm run dev                            # http://localhost:5173
```

`migrate:fresh --seed` deja las cuatro cuentas con las contrasenas conocidas de
`usuarios_prueba.txt`: `admin`/`Admin123`, `tecnico`/`Tecnico123`,
`revisor`/`Revisor123` y `gerente`/`Gerente123`. Fuera de `local`/`testing` el
seeder genera una aleatoria que se muestra **una sola vez**.

### 2.2 Restablecer credenciales de prueba (si hiciera falta)

El seeder ya deja las claves conocidas, asi que normalmente no hay que hacer
nada. Si se cambiaron desde la interfaz y hay que volver a dejarlas:

```powershell
Set-Location epcoro-api
php artisan tinker
```

```php
$claves = ['admin' => 'Admin123', 'tecnico' => 'Tecnico123',
           'revisor' => 'Revisor123', 'gerente' => 'Gerente123'];

App\Models\User::whereIn('username', array_keys($claves))
    ->get()
    ->each(fn ($u) => $u->forceFill([
        'password' => $claves[$u->username],
        'activo' => true,
        'debe_cambiar_password' => false,
        'intentos_fallidos' => 0,
        'bloqueado_hasta' => null,
    ])->save());
```

A partir de aqui: usuario `tecnico` / clave `Tecnico123`, etc.

### 2.3 Datos base que el seeder NO trae

El seeder deja geografia, presentaciones, parametros y usuarios. **No** crea
areas mineras, cotizaciones ni vendedores. Antes de capturar, cree un area
minera (el formulario solo permite elegir areas existentes):

```php
use App\Models\{AreaMinera, Localidad};

$loc  = Localidad::query()->first();
$mun  = $loc->municipio;
$prov = $mun->provincia;

AreaMinera::firstOrCreate(
    ['nombre_area_minera' => 'AREA DEMO'],
    [
        'titular_derecho_minero' => 'COOPERATIVA MINERA DEMO',
        'codigo_ajam_patente' => '123456',
        'departamento_id' => $prov->departamento_id,
        'provincia_id' => $prov->id,
        'municipio_id' => $mun->id,
        'localidad_id' => $loc->id,
        'activo' => true,
    ],
);
```

El **vendedor** si se puede crear desde el formulario (autocompletado de
personas con alta rapida).

### 2.4 Verificacion rapida de que el backend responde

```powershell
Invoke-RestMethod http://127.0.0.1:8000/api/health
# status = ok, checks.database = ok
```

---

## 3. Roles y permisos

### 3.1 Los cuatro roles

| Rol | Para que existe | Puede (en una frase) |
|---|---|---|
| **tecnico** | Captura las liquidaciones | Crea, edita **lo suyo** mientras esta `creado` o `en_correccion`, lo envia a revision y lo borra antes de enviarlo |
| **revisor** | Oficina de Liquidaciones | Ve todas, revisa (`en_revision` -> `revisado`), rechaza con motivo y comenta |
| **gerente** | Aprobador | Ve todas, **aprueba** (`revisado` -> `aprobado`), rechaza y **anula**; ve reportes |
| **admin** | Administracion | Usuarios, roles, catalogos y parametros; ve todas; anula y elimina; ve reportes |

### 3.2 Matriz permiso x rol

Origen: `RolPermisosSeeder` (seccion 4.2 de `instrucciones.txt`).

| Permiso | tecnico | revisor | gerente | admin |
|---|:--:|:--:|:--:|:--:|
| `liquidaciones.crear` | X | | | |
| `liquidaciones.ver` (propias) | X | | | |
| `liquidaciones.ver_propias` | X | | | |
| `liquidaciones.ver_todas` | | X | X | X |
| `liquidaciones.editar` | X | | | |
| `liquidaciones.eliminar` | | | | X |
| `liquidaciones.enviar_revision` | X | | | |
| `liquidaciones.revisar` | | X | | |
| `liquidaciones.rechazar` | | X | X | |
| `liquidaciones.aprobar` | | | X | |
| `liquidaciones.anular` | | | X | X |
| `liquidaciones.comentar` | | X | | |
| `reportes.ver` | | | X | X |
| `usuarios.gestionar` | | | | X |
| `roles.gestionar` | | | | X |
| `catalogos.gestionar` | | | | X |
| `parametros.gestionar` | | | | X |

### 3.3 Que ve cada rol en el menu

| Item de menu | tecnico | revisor | gerente | admin |
|---|:--:|:--:|:--:|:--:|
| Inicio | X | X | X | X |
| Liquidaciones | X | X | X | X |
| Nueva liquidacion | X | | | |
| Mi bandeja | X | X | X | X |
| Reportes | | | X | X |
| Parametros | | | | X |
| Catalogos | | | | X |
| Usuarios | | | | X |

Entrar por URL a un modulo sin permiso (p. ej. el tecnico a `/parametros`)
lleva a la pantalla **No autorizado** (`/403`).

---

## 4. Visibilidad y propiedad (decision D3)

| Rol | Listado (`GET /liquidaciones`) | Detalle ajeno |
|---|---|---|
| tecnico | Solo sus propias liquidaciones (`usuario_registro_id` = el) | 403 |
| revisor | Todas | 200 |
| gerente | Todas | 200 |
| admin | Todas | 200 |

Las liquidaciones en estado `eliminado` no aparecen en el listado.

---

## 5. Maquina de estados

### 5.1 Transiciones validas

```
creado         --enviar_revision--> en_revision
creado         --eliminar---------> eliminado        (solo el autor)
en_revision    --revisar----------> revisado         (revisor)
en_revision    --rechazar---------> rechazado        (revisor/gerente, con motivo)
en_revision    --devolver_tecnico-> en_correccion    (revisor, con observacion)
en_correccion  --enviar_revision--> en_revision      (el autor corrige y reenvia)
revisado       --aprobar----------> aprobado         (gerente, NUNCA lo propio)
revisado       --rechazar---------> rechazado        (revisor/gerente, con motivo)
revisado       --devolver_revisor-> en_revision      (gerente, con observacion)
revisado       --anular-----------> anulado          (gerente/admin, con motivo)
```

### 5.2 Estados terminales (sin salida)

`aprobado`, `rechazado`, `anulado` y `eliminado`. En particular, **una
liquidacion `aprobado` no se edita, no se anula y no se borra para ningun rol**,
el admin incluido (decision D2), y **una `rechazado` tampoco**: se rechaza con
motivo y queda cerrada (decision del negocio 2026-10-12). El estado
`en_correccion` vuelve a estar vivo y solo se alcanza por la **devolucion del
revisor** (`en_revision -> en_correccion`), nunca desde un rechazo.

### 5.3 Reglas que hay que recordar

- **Edicion abierta** en `creado` y en `en_correccion` (el tecnico corrige lo
  que el revisor le devuelve), y solo el autor.
- **Devolver = accion aparte del rechazo**, con `comentario` (observacion)
  OBLIGATORIO: `liquidaciones.devolver_tecnico` (revisor) y
  `liquidaciones.devolver_revisor` (gerente). La devolucion no cierra el
  expediente; el rechazo si.
- **`eliminado` solo se alcanza desde `creado`** (borrado logico del autor
  antes de enviar). No desde `en_correccion` (aunque `docs/api.md` lo mencione).
- **Motivo obligatorio** al pasar a `rechazado` (queda como `motivo_rechazo`) y
  a `anulado` (queda como `motivo_anulacion`).
- **Orden de controles en la API**: primero el **estado** (`422`), despues el
  **permiso** (`403`). Ej.: editar una liquidacion `aprobado` da `422` aunque
  quien lo intente no tenga `liquidaciones.editar`.

### 5.4 Matriz rol x transicion (resultado esperado)

| Origen -> Destino | tecnico | revisor | gerente | admin | Aplica si es propia | Sin motivo/observacion |
|---|:--:|:--:|:--:|:--:|---|---|
| creado -> en_revision | 200 (autor) | 403 | 403 | 403 | solo el autor | - |
| creado -> eliminado | 200 (autor) / 403 (ajena) | 403 | 403 | 403 | solo el autor | - |
| en_revision -> revisado | 422 (estado) | 200 | 403 | 403 | revisor si, en la suya o ajena | - |
| en_revision -> rechazado | 422 | 200 | 200 | 403 | - | **422** |
| en_revision -> en_correccion (devolver al tecnico) | 403 | 200 | 403 | 403 | - | **422** (sin observacion) |
| revisado -> en_revision (devolver al revisor) | 403 | 403 | 200 | 403 | - | **422** (sin observacion) |
| revisado -> aprobado | 403 | 403 | 200 (ajena) | 403 | gerente **no** la propia (403) | - |
| revisado -> rechazado | 422 | 200 | 200 | 403 | - | **422** |
| creado/en_revision/revisado -> anulado | 422 | 403 | 200 | 200 | - | **422** |
| aprobado -> (cualquiera) | 422 | 422 | 422 | 422 | - | - |
| rechazado -> (cualquiera) | 422 | 422 | 422 | 422 | - | - |

(La matriz completa de 27 casos esta automatizada en
`tests/Feature/FlujoPermisosTest.php`.)

---

## 6. Casos de prueba por flujo

Convencion: **Pre** = precondiciones, **Pasos**, **Esperado** = resultado
observable. "Autor" = el usuario que creo la liquidacion.

### 6.1 Autenticacion y sesion

**AUTH-01 - Login correcto.** Pre: usuario activo.
Pasos: abrir `http://localhost:5173`, entrar con `tecnico` / `Prueba1234`.
Esperado: entra a Inicio; el menu muestra solo Inicio, Liquidaciones, Nueva
liquidacion, Mi bandeja. (Si `debe_cambiar_password` esta activo, va a
Cambio de contrasena y no puede navegar a otra ruta.)

**AUTH-02 - Credenciales erroneas.**
Pasos: usuario valido + clave mala.
Esperado: mensaje "Las credenciales no son correctas." (no revela si el usuario
existe).

**AUTH-03 - Cambio de contrasena obligatorio.**
Pre: forzar `debe_cambiar_password = true` en `admin`.
Pasos: loguearse.
Esperado: unica ruta permitida `/cambio-clave`; cualquier URL redirige ahi.
Probar clave corta o sin numeros (marca el campo) y una valida (limpia la
bandera y deja navegar).

**AUTH-04 - Bloqueo por intentos fallidos.**
Pasos: 5 intentos fallidos seguidos con `revisor`.
Esperado: 6.o intento responde **429** con `minutos_restantes` (config:
`AUTH_INTENTOS_MAXIMOS=5`, `AUTH_MINUTOS_BLOQUEO=15`).

**AUTH-05 - Cerrar sesion.**
Pasos: boton de salir.
Esperado: vuelve a Login; `GET /auth/me` responde 401. La sesion del servidor
queda invalidada.

**AUTH-06 - Rate limit del login.**
Pasos: mas de 6 intentos por minuto (config `AUTH_THROTTLE_LOGIN=6,1`).
Esperado: 429 por throttling, independiente del contador por usuario.

### 6.2 Captura (tecnico)

Sigue pendiente solo la comprobación visual del autocompletado del comprador en http://localhost:5173.

**CAP-01 - Alta de vendedor rapida.** Pre: AreaMinera "AREA DEMO".
Pasos: Nueva liquidacion -> seccion Vendedor -> buscar un CI inexistente -> alta
rapida con nombres, apellidos y CI.
Esperado: el vendedor queda seleccionado. Un CI con formato `4.530.001` se
rechaza (422).

**CAP-02 - Calculo en vivo.** Pre: area y vendedor.
Pasos: peso `1944.53`, ley `0.9302`, cotizacion `2648.57` USD/OzT, tipo de
cambio `6.96`, presentacion "ORO".
Esperado: el resumen en vivo coincide con la tabla de la seccion 8 (ORO-A).

**CAP-03 - Guardar.** Pre: formulario completo.
Pasos: Guardar.
Esperado: 201; la liquidacion queda `creado` con `numero_documento` `EP-BCB-<n>`
asignado por el sistema y una fila inicial (`null -> creado`) en el historial.

**CAP-04 - Edicion.** Pre: liquidacion `creado` del tecnico autor.
Pasos: editar un campo, guardar.
Esperado: recalcula y persiste. Cambiar `numero_documento` asignado por el
sistema responde 422.

**CAP-05 - PDF y Excel.** Pasos: desde el detalle, descargar PDF y Excel.
Esperado: PDF (`application/pdf`) y XLSX
(`application/vnd.openxmlformats-...sheet`) con el formulario oficial.

**CAP-06 - Validacion de formato (C5).** Probar CI `^\d{1,10}$`, NIM
`^[0-9\-]{5,30}$`, NIT `^\d{1,20}$`, ley entre 0 y 1, montos `decimal:0,2`.
Esperado: cada valor invalido da 422 con el error en su campo.

### 6.3 Flujo completo feliz (tecnico -> revisor -> gerente)

**FLU-01 - Camino aprobado.**
Pre: tecnico con una liquidacion `creado`.
Pasos:
1. tecnico: envia a revision (`creado -> en_revision`).
2. revisor: revisa (`en_revision -> revisado`).
3. gerente: aprueba (`revisado -> aprobado`).
Esperado: en cada paso el detalle vuelve con el nuevo estado y el historial
suma la fila correspondiente con usuario y fecha. Al final, `aprobado` no
ofrece ningun boton de transicion (terminal).

**FLU-02 - Rechazo del revisor.**
Pasos: tecnico envia (`-> en_revision`) -> revisor rechaza con motivo
(`-> rechazado`).
Esperado: el `motivo_rechazo` queda registrado y visible; `rechazado` es
terminal, no ofrece ningun boton de transicion y la edicion queda cerrada.

**FLU-02b - Devolucion del revisor al tecnico.**
Pasos: tecnico envia (`-> en_revision`) -> revisor "Devolver al tecnico" sin
observacion -> error 422 en `comentario` -> con la observacion `-> en_correccion`
-> el tecnico corrige (edicion abierta) y reenvia `-> en_revision` -> el revisor
revisa `-> revisado`.
Esperado: la observacion queda en el historial (campo `comentario`), no hay
`motivo_rechazo` y la liquidacion sigue viva. El boton de etiqueta "Rechazar"
no es el mismo que "Devolver al tecnico".

**FLU-03 - Rechazo del gerente.**
Pasos: `revisado -> rechazado` (gerente, con motivo).
Esperado: el `motivo_rechazo` lo firma el gerente; `rechazado` es terminal,
igual que en FLU-02.

**FLU-03b - Devolucion del gerente al revisor.**
Pasos: `revisado` -> gerente "Devolver al revisor" sin observacion -> error 422
en `comentario` -> con la observacion `-> en_revision` -> el revisor re-revisa
`-> revisado` -> el gerente aprueba `-> aprobado`.
Esperado: la observacion queda en el historial y la fecha de la primera
revision (`fecha_revision`) NO se reescribe: el rastro de la re-entrada esta
solo en el historial.

**FLU-04 - Anulacion.**
Pasos: con una liquidacion `en_revision` (o `creado`/`revisado`), el gerente
(o admin) anula con motivo.
Esperado: `anulado` (terminal), `motivo_anulacion` registrado. Intentar anular
una `aprobado` o una `rechazado` da 422.

**FLU-05 - Eliminacion.**
Pasos: tecnico autor borra su liquidacion `creado`.
Esperado: 204; estado `eliminado`; desaparece del listado. Borrar una ajena da
403; una en estado no permitido da 422.

### 6.4 Visibilidad y separacion de responsabilidades

**VIS-01 - El tecnico solo ve lo suyo.** Pre: una liquidacion del tecnico y
otra del revisor.
Esperado: el tecnico ve una sola; abrir la ajena por URL -> No autorizado/403.

**VIS-02 - Revisor/gerente/admin ven todas.**

**VIS-03 - Separacion de responsabilidades.** Pre: una liquidacion `revisado`
cuyo autor es el propio gerente.
Esperado: el gerente **no** puede aprobarla (403); si es ajena, si (200).

**VIS-04 - Mi bandeja por rol.** Pre: 1 `en_revision` y 1 `revisado`.
Esperado:
- tecnico: `por_revisar=0`, `por_aprobar=0`, `mis_liquidaciones>0`.
- revisor: `por_revisar>=1`, `por_aprobar=0`.
- gerente: `por_aprobar>=1`, `por_revisar=0`.

### 6.5 Reportes

**REP-01 - Resumen cuadra.** Pre: liquidaciones del periodo.
Pasos: Reportes -> rango Quincenal/Mensual/Anual.
Esperado: los totales (n, peso, peso fino, bruto, aportes, deducciones, neto)
coinciden con la suma de las liquidaciones listadas; `eliminado` no cuenta.

**REP-02 - Grupos y graficos.** Esperado: por estado, municipio, area minera,
presentacion y mes, con dona por estado y evolucion mensual del neto.

**REP-03 - Exportacion.** Esperado: Excel y PDF del periodo con los mismos
filtros.

### 6.6 Auditoria (FASE 11.5)

**AUD-01 - Registro de acciones sensibles.**
Pasos: hacer login, una transicion, una exportacion y un respaldo; abrir
`epcoro-api/storage/logs/auditoria.log` (o `php artisan pail`).
Esperado: una linea por accion (`login`, `logout`, `login_fallido`,
`cuenta_bloqueada`, `password_cambiada`, `transicion`, `parametros`,
`exportacion`, `respaldo`) con usuario, IP y user-agent.

### 6.7 Administracion (por API; la UI sigue provisional)

**ADM-01 - Parametros (solo admin).** `PUT /api/v1/parametros` con
`{"parametros":{"TIPO_CAMBIO_DEFECTO":"6.9500"}}`.
Esperado: 200 solo admin; clave inexistente o valor no numerico (incluida
`1e3`) -> 422; otro rol -> 403.

**ADM-02 - Alta de persona.** `POST /api/v1/catalogos/personas` con CI valido.
Esperado: 201; CI duplicado o formato invalido -> 422.

### 6.8 Puesta en marcha / respaldo (FASE 11.4 y 11.6)

**OPS-01 - Respaldo.** `php artisan sinchiwayra:respaldar --sin-retener`.
Esperado: crea `sinchiwayra-<fecha>.dump`; si `pg_dump` no esta, termina con error
sin dejar archivo a medias. La purga borra solo `sinchiwayra-*.dump` vencidos.

**OPS-02 - Preflight de produccion.** `php artisan sinchiwayra:revisar-produccion`.
Esperado: en desarrollo marca en rojo `APP_ENV`, `APP_DEBUG`, CORS/Sanctum en
localhost, etc. (codigo distinto de cero). Con `.env` de produccion correcto,
todo verde.

---

## 7. Codigos de error esperados

| Codigo | Cuando |
|---|---|
| `401` | Sin sesion / sesion expirada |
| `403` | Autenticado sin permiso (`{"message": "This action is unauthorized."}`) |
| `404` | Recurso inexistente |
| `419` | CSRF caducado (en el proxy: falta el mapeo de `/sanctum`) |
| `422` | Regla de negocio/validacion (estado invalido, motivo faltante, formato) |
| `429` | Bloqueo por intentos fallidos o rate limit del login |
| `503` | `GET /health` con la base caida (`status: degraded`) |

Regla de oro: **estado antes que permiso**; una accion sobre un estado no
permitido responde `422`, no `403`.

---

## 8. Casos de calculo con valores del Excel

Con peso `1944.53` g, ley `0.9302`, cotizacion `2648.57` USD/OzT y tipo de
cambio `6.96` (un solo redondeo half-up al final sobre cada monto en Bs):

| Caso | Caja de salud | Bruto (Bs) | Aporte (Bs) | Deducciones (Bs) | Neto (Bs) |
|---|---|---|---|---|---|
| ORO-A (`C20 = NO`) | no | 1.072.018,84 | 0,00 | 0,00 | **1.072.018,84** |
| ORO-B (`C20 = SI`) | si | 1.072.018,84 | 19.296,34 | 19.296,34 | **1.052.722,50** |

**CAL-01.** Reproducir ambos casos y comparar. La regla prohibe redondear en
pasos intermedios y prohibe restar montos ya redondeados: el neto se calcula
desde el valor exacto.

---

## 9. Pruebas automatizadas

Antes de dar por cerrada una corrida manual, correr la suite:

```powershell
Set-Location epcoro-api
composer check                         # pint + phpstan + php artisan test
```

Suites que cubren este plan:

| Suite | Cubre |
|---|---|
| `tests/Feature/FlujoPermisosTest.php` | Matriz rol x transicion x propiedad (seccion 5.4), bandeja, orden 422/403 |
| `tests/Feature/TransicionEstadoTest.php` | Maquina de estados completa, estados terminales, auditoria de transiciones |
| `tests/Feature/LiquidacionApiTest.php` | CRUD, calculo, edicion, borrado, historial |
| `tests/Unit/CalculoLiquidacionTest.php` y `tests/Unit/DecimalTest.php` | Calculo y redondeo half-up |
| `tests/Feature/ReportesYCatalogosApiTest.php` | Resumen, grupos y catalogos |
| `tests/Feature/AuthTestTest.php` | Login, bloqueo, cambio de contrasena |
| `tests/Feature/AuditoriaTest.php` | Log de acciones sensibles |
| `tests/Unit/RespaldosTest.php` / `tests/Feature/ConsolaDeProduccionTest.php` | Respaldo y preflight |

Filtro puntual:

```powershell
php artisan test --filter=FlujoPermisosTest
```

---

## 10. Checklist de aceptacion

- [ ] Los 4 roles entran y ven exactamente su menu (seccion 3.3).
- [ ] El tecnico solo accede a lo propio; revisor/gerente/admin ven todo.
- [ ] Flujo feliz completo llega a `aprobado` y esta queda inmutable.
- [ ] Rechazo con motivo funciona de punta a punta y deja la liquidacion cerrada.
- [ ] La devolucion (revisor->tecnico y gerente->revisor) exige observacion y no cierra el expediente.
- [ ] Anulacion y eliminacion respetan estados y autoridad.
- [ ] Un gerente no aprueba su propia liquidacion.
- [ ] Los montos ORO-A y ORO-B coinciden con el Excel.
- [ ] Los reportes cuadran con el listado del periodo.
- [ ] 422 para estado/formato, 403 para permiso, 401 sin sesion, 429 bloqueo.
- [ ] `auditoria.log` registra login/transicion/exportacion/respaldo.
- [ ] `composer check` en verde (pint, phpstan, tests).

---

## 11. Registro de ejecucion (plantilla)

| Caso | Fecha | Responsable | Resultado (OK/FALLA) | Evidencia / nota |
|---|---|---|---|---|
| AUTH-01 | | | | |
| CAP-02 | | | | |
| FLU-01 | | | | |
| FLU-02 | | | | |
| FLU-02b | | | | |
| FLU-03 | | | | |
| FLU-03b | | | | |
| FLU-04 | | | | |
| VIS-01 | | | | |
| VIS-03 | | | | |
| REP-01 | | | | |
| CAL-01 | | | | |
| OPS-01 | | | | |

<?php

namespace Tests\Feature;

use App\Models\AreaMinera;
use App\Models\Liquidacion;
use App\Models\LiquidacionEstadoHistorial;
use App\Models\Persona;
use App\Models\PresentacionMineral;
use App\Models\User;
use Database\Seeders\ParametrosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

/**
 * FASE 6.10: el CRUD de liquidaciones tal como lo usa el frontend.
 *
 * Cada caso es una regla de instrucciones.txt, no una invencion:
 *
 *  - el calculo se persiste tal cual salio del servicio (T3);
 *  - C5: los identificadores van como digitos con regex (un NIT no es un
 *    numero);
 *  - T1: ley_pagable es fraccion y un 93.02 da 422 a proposito;
 *  - el numero lo asigna el sistema si no llega, y el que llega tiene que
 *    ser unico (5.3);
 *  - las transiciones responden 422 por ESTADO antes de mirar permisos, y
 *    403 solo cuando el estado y el permiso chocan (D2);
 *  - la rama rechazado -> en_correccion es la unica puerta de vuelta a la
 *    edicion, y la recorre el autor (4.1).
 *
 * El alta rapida de la persona (N5) y los reportes van en
 * ReportesYPermisosApiTest.
 */
class LiquidacionApiTest extends AuthTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Los totales del formulario (gramos por onza, alicuota) viven en
        // parametros_generales; sin ellos la captura de una con caja de
        // salud no tiene con que calcular el aporte.
        $this->seed(ParametrosSeeder::class);
    }

    // ---------------------------------------------------------- seguridad

    public function test_sin_sesion_los_recursos_responden_401(): void
    {
        $this->withHeaders($this->cabecerasStateful())
            ->getJson('/api/v1/liquidaciones')
            ->assertUnauthorized();
    }

    public function test_la_ruta_viva_ahora_es_v1_y_el_aliase_legacy_sigue_habilitado(): void
    {
        $usuario = User::factory()->conRol('tecnico')->create();

        // La URL nueva versionada, la que el frontend real debe usar.
        $this->withHeaders($this->cabecerasStateful())
            ->postJson('/api/v1/auth/login', $this->credenciales($usuario))
            ->assertOk()
            ->assertJsonPath('usuario.username', $usuario->username);

        // El aliase viejo se mantiene como deprecado para no romper el
        // cliente que aun lo usa.
        $this->withHeaders($this->cabecerasStateful())
            ->postJson('/api/auth/login', $this->credenciales($usuario))
            ->assertOk();
    }

    // ------------------------------------------------------------- alta

    public function test_el_tecnico_registra_una_liquidacion_y_el_calculo_se_persiste_tal_cual_salio_del_servicio(): void
    {
        $tecnico = $this->tecnico();

        $respuesta = $this->registrar($tecnico, [
            'deducciones' => [
                ['concepto' => 'DESCUENTO TRANSPORTE', 'monto' => '120.00'],
            ],
        ], true);

        $respuesta->assertCreated()
            ->assertJsonPath('message', 'Liquidacion creada correctamente.')
            ->assertJsonPath('data.estado', 'creado')
            ->assertJsonPath('data.estado_label', 'Creado')
            ->assertJsonPath('data.ejercicio', 2026)
            ->assertJsonPath('data.fecha_transaccion', '2026-10-01')
            ->assertJsonPath('data.auditoria.registrado_por.username', $tecnico->username);

        $numero = (string) $respuesta->json('data.numero_documento');

        // Correlativo del ejercicio y codigo de formulario derivados (5.3).
        $this->assertMatchesRegularExpression('/^EP-BCB-\d+$/', $numero);
        $this->assertSame(1, $respuesta->json('data.correlativo'));
        $this->assertSame('FORM-'.$numero.'/2026', $respuesta->json('data.codigo_formulario'));

        // T3: la fila guardada es exactamente la salida del servicio, que
        // es la misma formula que la hoja oficial.
        $fila = Liquidacion::query()->findOrFail($respuesta->json('data.id'));
        $esperado = $this->calculoEsperado(conCajaDeSalud: true, deducciones: ['120.00']);

        $this->assertSame($esperado['peso_fino_gramos'], $fila->peso_fino_gramos);
        $this->assertSame($esperado['valor_usd_gramo'], $fila->valor_usd_gramo);
        $this->assertSame($esperado['valor_bruto_bs'], $fila->valor_bruto_bs);
        $this->assertSame($esperado['aporte_caja_salud_bs'], $fila->aporte_caja_salud_bs);
        $this->assertSame($esperado['total_deducciones_bs'], $fila->total_deducciones_bs);
        $this->assertSame($esperado['valor_neto_bs'], $fila->valor_neto_bs);

        // El bloque calculo del recurso es el mismo, y los montos viajan
        // como string (nada de float en el camino de vuelta).
        $this->assertSame($esperado['valor_neto_bs'], $respuesta->json('data.calculo.valor_neto_bs'));
        $this->assertIsString($respuesta->json('data.calculo.valor_bruto_bs'));

        // El tecnico la ve con la transicion que le toca.
        $this->assertContains('en_revision', $respuesta->json('data.transiciones_disponibles'));
    }

    public function test_el_detalle_de_deducciones_queda_persistido_con_el_aporte_y_las_variables(): void
    {
        $tecnico = $this->tecnico();

        $respuesta = $this->registrar($tecnico, [
            'deducciones' => [
                ['concepto' => 'DESCUENTO TRANSPORTE', 'monto' => '120.00'],
            ],
        ], true);

        $filas = $respuesta->json('data.deducciones');

        // Primero el aporte obligatorio (ley 649), despues la variable.
        $this->assertSame(2, count($filas));
        $this->assertSame('APORTE CAJA DE SALUD - LEY 649', $filas[0]['concepto']);
        $this->assertSame('alicuota', $filas[0]['tipo']);
        $this->assertSame($respuesta->json('data.calculo.valor_bruto_bs'), $filas[0]['base_monto']);
        $this->assertSame($respuesta->json('data.calculo.aporte_caja_salud_bs'), $filas[0]['monto']);
        $this->assertSame('DESCUENTO TRANSPORTE', $filas[1]['concepto']);
        $this->assertSame('descuento', $filas[1]['tipo']);
        $this->assertSame('120.00', $filas[1]['monto']);
    }

    public function test_el_numero_externo_pasa_con_correlativo_nulo_y_codigo_derivado(): void
    {
        $tecnico = $this->tecnico();

        $respuesta = $this->registrar($tecnico, [
            'numero_documento' => 'EP-BCB-99',
        ]);

        $respuesta->assertCreated();
        $this->assertNull($respuesta->json('data.correlativo'));
        $this->assertSame('FORM-EP-BCB-99/2026', $respuesta->json('data.codigo_formulario'));
    }

    public function test_el_numero_duplicado_responde_422_con_el_error_en_su_campo(): void
    {
        $tecnico = $this->tecnico();

        $this->registrar($tecnico, ['numero_documento' => 'EP-BCB-55'])->assertCreated();

        $this->registrar($tecnico, ['numero_documento' => 'EP-BCB-55'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('numero_documento');
    }

    public function test_el_formato_del_numero_y_de_los_identificadores_sigue_c5(): void
    {
        $tecnico = $this->tecnico();

        $this->registrar($tecnico, ['numero_documento' => 'no-es-un-numero'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('numero_documento');

        // CI con puntos: identificador partido, lo corta la regex.
        $this->registrar($tecnico, ['vendedor_ci' => '4.530.001'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('vendedor_ci');

        // NIT en notacion cientifica, que es como un entero largo se va.
        $this->registrar($tecnico, ['comprador_nit' => '1.0E+15'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('comprador_nit');

        // El NIM admite digitos y guiones (02-2388-05), y pasa.
        $this->registrar($tecnico, ['comprador_nim' => '02-2388-05'])->assertCreated();
    }

    public function test_la_ley_pagable_es_fraccion_y_un_93_02_se_rechaza_a_proposito(): void
    {
        $tecnico = $this->tecnico();

        // T1: 93.02 es un error de captura, no un valor posible.
        $this->registrar($tecnico, ['ley_pagable' => '93.02'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ley_pagable');

        // La fraccion valida pasa y el cadmio no bloquea (0.0009).
        $this->registrar($tecnico, ['ley_pagable' => '0.0009'])->assertCreated();
        $this->registrar($tecnico, ['ley_pagable' => '0.9302'])->assertCreated();
    }

    public function test_la_ubicacion_tiene_que_ser_la_del_area_minera(): void
    {
        $tecnico = $this->tecnico();
        $otroMunicipio = AreaMinera::factory()->create()->municipio_id;

        $this->registrar($tecnico, ['municipio_id' => $otroMunicipio])
            ->assertStatus(422)
            ->assertJsonValidationErrors('municipio_id');
    }

    // ------------------------------------------------------------- lectura

    public function test_el_tecnico_en_el_listado_solo_ve_las_suyas(): void
    {
        $tecnico = $this->tecnico();
        $otroTecnico = $this->tecnico();

        $propia = $this->liquidacionDe($tecnico);
        $ajena = $this->liquidacionDe($otroTecnico);

        $respuesta = $this->identificarseComo($tecnico, 'web')
            ->getJson('/api/v1/liquidaciones');

        // meta.total == 1 ya prueba el filtro: la ajena no entro en la
        // respuesta. assertJsonMissing([...]) no sirve aqui porque el id puede
        // aparecer anidado (un usuario, una persona...) sin ser una fila.
        $respuesta->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $propia->id)
            ->assertJsonCount(1, 'data');
    }

    public function test_filtros_estado_fechas_y_texto_combinados(): void
    {
        $gerente = $this->gerente();
        $tecnico = $this->tecnico();

        $dentro = $this->liquidacionDe($tecnico, [
            'fecha_transaccion' => '2026-05-10',
            'comprador_razon_social' => 'EMPRESA DE PRUEBA DELTA',
        ]);
        $fuera = $this->liquidacionDe($tecnico, [
            'fecha_transaccion' => '2025-01-01',
            'estado' => 'aprobado',
        ]);

        $respuesta = $this->identificarseComo($gerente, 'web')->getJson(
            '/api/v1/liquidaciones?estado=creado&fecha_desde=2026-01-01&fecha_hasta=2026-12-31&q=delta'
        );

        $respuesta->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $dentro->id)
            ->assertJsonCount(1, 'data');
    }

    public function test_un_estado_desconocido_en_el_filtro_responde_422_con_los_validos(): void
    {
        $gerente = $this->gerente();

        $this->identificarseComo($gerente, 'web')
            ->getJson('/api/v1/liquidaciones?estado=transformada')
            ->assertStatus(422)
            ->assertJsonValidationErrors('estado');
    }

    public function test_el_tecnico_ve_el_detalle_de_una_propia_y_el_recurso_carga_sus_transiciones(): void
    {
        $tecnico = $this->tecnico();
        $liquidacion = $this->liquidacionDe($tecnico);

        $respuesta = $this->identificarseComo($tecnico, 'web')
            ->getJson('/api/v1/liquidaciones/'.$liquidacion->id);

        $respuesta->assertOk()
            ->assertJsonPath('data.id', $liquidacion->id)
            ->assertJsonPath('data.estado', 'creado')
            ->assertJsonPath('data.procedencia.municipio.nombre', $liquidacion->municipio_nombre)
            ->assertJsonPath('data.vendedor.nombre', $liquidacion->vendedor_nombre);
    }

    public function test_el_tecnico_recibe_403_con_un_detalle_ajeno(): void
    {
        $tecnico = $this->tecnico();
        $ajena = $this->liquidacionDe($this->tecnico());

        $this->identificarseComo($tecnico, 'web')
            ->getJson('/api/v1/liquidaciones/'.$ajena->id)
            ->assertForbidden();
    }

    // ------------------------------------------------------------- edicion

    public function test_el_tecnico_corrige_una_creada_propia_y_se_recalculan_los_montos(): void
    {
        $tecnico = $this->tecnico();
        $liquidacion = $this->liquidacionDe($tecnico);

        $respuesta = $this->identificarseComo($tecnico, 'web')
            ->putJson('/api/v1/liquidaciones/'.$liquidacion->id, [
                'peso_gramos' => '500.25',
                'observaciones' => 'Peso corregido por báscula.',
            ]);

        $respuesta->assertOk()
            ->assertJsonPath('message', 'Liquidacion actualizada correctamente.')
            ->assertJsonPath('data.insumos.peso_gramos', '500.25')
            ->assertJsonPath('data.observaciones', 'Peso corregido por báscula.');

        // El nueo bruto partio del peso corregido y no de un snapshot viejo.
        $esperado = $this->calculoEsperado(
            peso: '500.25',
            tipoCambio: (string) $liquidacion->tipo_cambio,
            cotizacion: (string) $liquidacion->cotizacion_usd_oz_t,
            ley: (string) $liquidacion->ley_pagable,
        );

        $this->assertSame($esperado['valor_bruto_bs'], $respuesta->json('data.calculo.valor_bruto_bs'));
        $this->assertSame($esperado['valor_neto_bs'], $respuesta->json('data.calculo.valor_neto_bs'));
    }

    public function test_el_numero_con_correlativo_no_se_cambia_pero_el_externo_si(): void
    {
        $tecnico = $this->tecnico();
        // El numero lo asigno el numerador (correlativo con valor): es
        // inmutable y el cambio tiene que responder 422 con el error en el campo.
        $delSistema = $this->liquidacionDe($tecnico, [
            'correlativo' => 1,
            'numero_documento' => 'EP-BCB-1',
        ]);

        $this->identificarseComo($tecnico, 'web')
            ->putJson('/api/v1/liquidaciones/'.$delSistema->id, ['numero_documento' => 'EP-BCB-777'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('numero_documento');
    }

    public function test_el_numero_externo_se_puede_corregir_en_creado(): void
    {
        $tecnico = $this->tecnico();
        $externa = $this->registrar($tecnico, ['numero_documento' => 'EP-BCB-88'])->json('data.id');

        $this->identificarseComo($tecnico, 'web')
            ->putJson('/api/v1/liquidaciones/'.$externa, ['numero_documento' => 'EP-BCB-89'])
            ->assertOk()
            ->assertJsonPath('data.numero_documento', 'EP-BCB-89')
            ->assertJsonPath('data.codigo_formulario', 'FORM-EP-BCB-89/2026');
    }

    public function test_editar_una_aprobada_responde_422_antes_de_mirar_permisos(): void
    {
        $tecnico = $this->tecnico();
        $aprobada = $this->liquidacionDe($tecnico, ['estado' => 'aprobado']);

        // El tecnico tiene el permiso de editar: el corte lo hace el estado,
        // y por eso es un 422 y no un 403 (el frontend pinta solo lectura).
        $this->identificarseComo($tecnico, 'web')
            ->putJson('/api/v1/liquidaciones/'.$aprobada->id, ['observaciones' => 'nada'])
            ->assertStatus(422);
    }

    public function test_editar_la_ajena_de_otro_tecnico_responde_403(): void
    {
        $tecnico = $this->tecnico();
        $ajena = $this->liquidacionDe($this->tecnico());

        $this->identificarseComo($tecnico, 'web')
            ->putJson('/api/v1/liquidaciones/'.$ajena->id, ['peso_gramos' => '9.99'])
            ->assertForbidden();
    }

    public function test_borrar_en_creado_es_borrado_logico_y_en_revision_es_422(): void
    {
        $tecnico = $this->tecnico();
        $creada = $this->liquidacionDe($tecnico);
        $enRevision = $this->liquidacionDe($tecnico, ['estado' => 'en_revision']);

        $this->identificarseComo($tecnico, 'web')
            ->deleteJson('/api/v1/liquidaciones/'.$creada->id)
            ->assertNoContent();

        $this->assertSame('eliminado', $creada->fresh()->estado->value);

        $this->identificarseComo($tecnico, 'web')
            ->deleteJson('/api/v1/liquidaciones/'.$enRevision->id)
            ->assertStatus(422);
    }

    // -------------------------------------------------------- transiciones

    public function test_el_tecnico_envia_una_propia_a_revision_y_queda_en_el_historial(): void
    {
        $tecnico = $this->tecnico();
        $liquidacion = $this->liquidacionDe($tecnico);

        $respuesta = $this->identificarseComo($tecnico, 'web')
            ->postJson('/api/v1/liquidaciones/'.$liquidacion->id.'/transiciones', [
                'estado_destino' => 'en_revision',
            ]);

        $respuesta->assertOk()
            ->assertJsonPath('data.estado', 'en_revision');

        $this->assertSame('en_revision', $liquidacion->fresh()->estado->value);
        $this->assertSame(1, $liquidacion->historial()->count());

        /** @var LiquidacionEstadoHistorial $primera */
        $primera = $liquidacion->historial()->first();
        $this->assertSame('creado', $primera->estado_anterior?->value);
        $this->assertSame('en_revision', $primera->estado_nuevo->value);
        $this->assertSame($tecnico->id, $primera->usuario_id);
    }

    public function test_un_estado_impropio_corta_con_422_antes_que_el_permiso(): void
    {
        $revisor = $this->revisor();
        $creada = $this->liquidacionDe($this->tecnico());

        // El revisor tiene permiso para revisar; el estado no esta en
        // revision. Corta el estado: 422, el mismo idioma que el frontend
        // necesita (la fila se ve como solo lectura).
        $this->identificarseComo($revisor, 'web')
            ->postJson('/api/v1/liquidaciones/'.$creada->id.'/transiciones', [
                'estado_destino' => 'revisado',
            ])
            ->assertStatus(422);

        $this->assertSame('creado', $creada->fresh()->estado->value);
    }

    public function test_el_tecnico_que_querria_aprobar_recibe_403(): void
    {
        $tecnico = $this->tecnico();
        $ajena = $this->liquidacionDe($this->tecnico(), ['estado' => 'revisado']);

        // Estado valido, permiso que no existe en el rol: 403.
        $this->identificarseComo($tecnico, 'web')
            ->postJson('/api/v1/liquidaciones/'.$ajena->id.'/transiciones', [
                'estado_destino' => 'aprobado',
            ])
            ->assertForbidden();
    }

    public function test_la_anulacion_exige_motivo_y_el_estado_valido(): void
    {
        $gerente = $this->gerente();
        $enRevision = $this->liquidacionDe($this->tecnico(), ['estado' => 'en_revision']);

        $this->identificarseComo($gerente, 'web')
            ->postJson('/api/v1/liquidaciones/'.$enRevision->id.'/transiciones', [
                'estado_destino' => 'anulado',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('motivo');

        $this->identificarseComo($gerente, 'web')
            ->postJson('/api/v1/liquidaciones/'.$enRevision->id.'/transiciones', [
                'estado_destino' => 'anulado',
                'motivo' => 'Balanza del patio en reparacion.',
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'anulado');

        $this->assertSame('Balanza del patio en reparacion.', $enRevision->fresh()->motivo_anulacion);
    }

    public function test_la_rama_rechazado_reenvia_a_correccion_y_el_autor_recalcula(): void
    {
        $tecnico = $this->tecnico();
        $revisor = $this->revisor();

        $liquidacion = $this->liquidacionDe($tecnico);

        // publico el flujo completo: captura -> revision -> solicitud de
        // correccion -> correccion -> reenvio.
        $this->identificarseComo($tecnico, 'web')
            ->postJson('/api/v1/liquidaciones/'.$liquidacion->id.'/transiciones', ['estado_destino' => 'en_revision'])
            ->assertOk();

        $this->identificarseComo($revisor, 'web')
            ->postJson('/api/v1/liquidaciones/'.$liquidacion->id.'/transiciones', [
                'estado_destino' => 'rechazado',
                'motivo' => 'La cota del peso no coincide con la pesada.',
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'rechazado');

        $this->identificarseComo($tecnico, 'web')
            ->postJson('/api/v1/liquidaciones/'.$liquidacion->id.'/transiciones', ['estado_destino' => 'en_correccion'])
            ->assertOk()
            ->assertJsonPath('data.estado', 'en_correccion');

        // y en en_correccion la edicion vuelve a estar abierta (4.1).
        $corregida = $this->identificarseComo($tecnico, 'web')
            ->putJson('/api/v1/liquidaciones/'.$liquidacion->id, ['peso_gramos' => '900.00'])
            ->assertOk()
            ->json('data');

        $this->assertSame('900.00', $corregida['insumos']['peso_gramos']);
    }

    // ------------------------------------------------------------- flujo

    public function test_solo_el_revisor_observa_y_la_observacion_no_mueve_el_estado(): void
    {
        $revisor = $this->revisor();
        $tecnico = $this->tecnico();
        $liquidacion = $this->liquidacionDe($revisor, ['estado' => 'en_revision']);

        $respuesta = $this->identificarseComo($revisor, 'web')
            ->postJson('/api/v1/liquidaciones/'.$liquidacion->id.'/observaciones', [
                'comentario' => 'El mineral traia tierra; muestrear de nuevo.',
            ]);

        $respuesta->assertCreated()
            ->assertJsonPath('data.texto', 'El mineral traia tierra; muestrear de nuevo.');

        $this->assertSame('en_revision', $liquidacion->fresh()->estado->value);

        $this->identificarseComo($tecnico, 'web')
            ->postJson('/api/v1/liquidaciones/'.$liquidacion->id.'/observaciones', [
                'comentario' => 'Yo no deberia poder observarlo.',
            ])
            ->assertForbidden();
    }

    // ----------------------------------------------------------- descargas

    public function test_pdf_y_excel_de_una_liquidacion_se_descargan_sin_recalcular(): void
    {
        $tecnico = $this->tecnico();
        $liquidacion = $this->liquidacionDe($tecnico);

        $pdf = $this->identificarseComo($tecnico, 'web')
            ->getJson('/api/v1/liquidaciones/'.$liquidacion->id.'/pdf');

        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', $pdf->headers->get('Content-Type'));

        $excel = $this->identificarseComo($tecnico, 'web')
            ->getJson('/api/v1/liquidaciones/'.$liquidacion->id.'/excel');

        $excel->assertOk();
        $this->assertStringContainsString('spreadsheetml', $excel->headers->get('Content-Type'));
    }

    // --------------------------------------------------------- utilidades

    private function tecnico(): User
    {
        return User::factory()->conRol('tecnico')->create();
    }

    private function revisor(): User
    {
        return User::factory()->conRol('revisor')->create();
    }

    private function gerente(): User
    {
        return User::factory()->conRol('gerente')->create();
    }

    /**
     * Actua como $usuario para la proxima peticion HTTP.
     *
     * No es `actingAs()` pelado: el guard de Sanctum queda cacheado en el
     * contenedor entre peticiones del MISMO test y, sin reiniciarlo, la
     * segunda peticion sigue con el usuario de la primera (un tecnioco
     * logueado no vuelve a ser revisor ni con otro actingAs). `forgetGuards()`
     * tira los guards y recien entonces se identifica al nuevo actor.
     */
    private function identificarseComo(User $usuario, string $guard = 'web'): static
    {
        app('auth')->forgetGuards();
        $this->actingAs($usuario, $guard);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function liquidacionDe(User $autor, array $atributos = []): Liquidacion
    {
        return Liquidacion::factory()->create(array_merge(
            ['usuario_registro_id' => $autor->id],
            $atributos,
        ));
    }

    /**
     * POST /api/v1/liquidaciones con el payload valido de captura, y con
     * los sobreescribibles de $datos reemplazados.
     *
     * @param  array<string, mixed>  $datos
     */
    private function registrar(User $usuario, array $datos = [], bool $cajaDeSalud = false): TestResponse
    {
        return $this->identificarseComo($usuario, 'web')
            ->postJson('/api/v1/liquidaciones', array_merge($this->payload($cajaDeSalud), $datos));
    }

    /**
     * Los 22 campos que el formulario manda, coherentes entre si: la
     * ubicacion sale del area y la persona del vendedor ya existe.
     *
     * @return array<string, mixed>
     */
    private function payload(bool $cajaDeSalud = false): array
    {
        $area = AreaMinera::factory()->create();
        $vendedor = Persona::factory()->create();

        return [
            'fecha_transaccion' => '2026-10-01',
            'tipo_cambio' => '6.9100',
            'cotizacion_usd_oz_t' => '2900.0000',
            'aplica_caja_salud' => $cajaDeSalud,
            'comprador_razon_social' => 'EMPRESA ESTATAL DE PRODUCCION Y COMERCIALIZACION DEL ORO - EPCORO',
            'comprador_nim' => '02-2388-05',
            'comprador_nit' => '1006965023',
            'vendedor_persona_id' => $vendedor->id,
            'vendedor_ci' => (string) $vendedor->ci,
            'area_minera_id' => $area->id,
            'departamento_id' => $area->departamento_id,
            'provincia_id' => $area->provincia_id,
            'municipio_id' => $area->municipio_id,
            'localidad_id' => $area->localidad_id,
            'presentacion_mineral_id' => PresentacionMineral::factory()->create()->id,
            'peso_gramos' => '1000.00',
            'ley_pagable' => '0.9302',
        ];
    }

    /**
     * Los seis calculados tal como los produce el servicio: el mismo orden
     * de operaciones y el mismo redondeo (half-up) que la hoja. El test
     * verifica que el controlador persista EXACTAMENTE esto (T3).
     *
     * @param  array<int, string>  $deducciones
     * @return array<string, string>
     */
    private function calculoEsperado(
        string $peso = '1000.00',
        bool $conCajaDeSalud = false,
        array $deducciones = [],
        string $tipoCambio = '6.9100',
        string $cotizacion = '2900.0000',
        string $ley = '0.9302',
    ): array {
        $gramos = '31.1035';
        $escala = 20;

        $pesoFino = bcmul($peso, $ley, $escala);
        $valorUsdGramo = bcdiv($cotizacion, $gramos, $escala);
        $brutoExacto = bcmul(bcmul($valorUsdGramo, $tipoCambio, $escala), $pesoFino, $escala);

        $aporteExacto = $conCajaDeSalud ? bcmul($brutoExacto, '0.018', $escala) : '0';

        $totalExacto = $aporteExacto;

        foreach ($deducciones as $monto) {
            $totalExacto = bcadd($totalExacto, $monto, $escala);
        }

        $netoExacto = bcsub($brutoExacto, $totalExacto, $escala);

        return [
            'peso_fino_gramos' => $this->conDecimales($pesoFino, 4),
            'valor_usd_gramo' => $this->conDecimales($valorUsdGramo, 6),
            'valor_bruto_bs' => $this->moneda($brutoExacto),
            'aporte_caja_salud_bs' => $this->moneda($aporteExacto),
            'total_deducciones_bs' => $this->moneda($totalExacto),
            'valor_neto_bs' => $this->moneda($netoExacto),
        ];
    }

    /** Redondeo half-up: mitad hacia arriba, igual que suffix.decimal. */
    private function moneda(string $exacto): string
    {
        $factor = bcpow('10', '2', 0);

        return bcdiv(bcadd(bcmul($exacto, $factor, 30), '0.5', 0), $factor, 2);
    }

    /**
     * Redondeo a la escala de la columna (numeric 14,4 / 12,6). La tabla
     * guarda el resultado del servicio y PostgreSQL redondea al persistir, o
     * sea que el valor que lee el test es el decimal redondeado, no el de
     * precision completa del servicio.
     */
    private function conDecimales(string $exacto, int $escala): string
    {
        // bcmath TRUNCA, no redondea (93.2370955 -> 93.237095), y un DECIMAL
        // de MySQL redondea half-up. `+0.5` y truncar replica ese redondeo.
        $factor = bcpow('10', (string) $escala, 0);
        $negativo = bccomp($exacto, '0') < 0;
        $absoluto = $negativo ? bcsub('0', $exacto, 20) : $exacto;
        $escalado = bcadd(bcmul($absoluto, $factor, 20), '0.5', 0);
        $redondeado = bcdiv($escalado, $factor, $escala);

        return $negativo ? bcsub('0', $redondeado, $escala) : $redondeado;
    }
}

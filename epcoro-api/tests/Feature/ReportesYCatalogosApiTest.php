<?php

namespace Tests\Feature;

use App\Models\Liquidacion;
use App\Models\Municipio;
use App\Models\ParametroGeneral;
use App\Models\TipoCambio;
use App\Models\User;
use App\Services\TransicionEstadoService;
use Database\Seeders\ParametrosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * FASE 6: reportes (4.6 + 6.8), parametros (3.3 + 6.6) y catalogos.
 *
 * El reporte es un ESPEJO del estado de la base: los totales salen de
 * SUMAR columnas que el servicio ya calculo y redondeo. Si la hoja no
 * cuadra, el problema esta en otra parte; aqui solo se verifica que la
 * suma coincida y que el permiso `reportes.ver` sea quien abra la puerta.
 */
class ReportesYCatalogosApiTest extends AuthTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ParametrosSeeder::class);
    }

    // ----------------------------------------------------------- reportes

    public function test_el_gerente_ve_el_resumen_y_los_totales_coinciden_con_la_suma(): void
    {
        $gerente = $this->gerente();
        $tecnico = $this->tecnico();

        // La aprobada cuenta y se agrupa; la eliminada NO es un expediente
        // real; la de fuera del rango no entra al periodo pedido.
        $aprobada = Liquidacion::factory()->create([
            'estado' => 'aprobado',
            'fecha_transaccion' => '2026-05-10',
            'peso_gramos' => '900.00',
            'peso_fino_gramos' => '837.1800',
            'valor_bruto_bs' => '1000.25',
            'aporte_caja_salud_bs' => '18.00',
            'total_deducciones_bs' => '120.00',
            'valor_neto_bs' => '862.25',
        ]);
        Liquidacion::factory()->create([
            'estado' => 'eliminado',
            'fecha_transaccion' => '2026-05-11',
            'valor_bruto_bs' => '999999.00',
            'valor_neto_bs' => '999999.00',
        ]);
        Liquidacion::factory()->create([
            'estado' => 'aprobado',
            'fecha_transaccion' => '2025-01-01',
            'valor_bruto_bs' => '777.00',
            'valor_neto_bs' => '777.00',
        ]);

        $respuesta = $this->actingAs($gerente, 'web')
            ->getJson('/api/v1/reportes/resumen?desde=2026-01-01&hasta=2026-12-31');

        $respuesta->assertOk()
            ->assertJsonPath('data.rango.desde', '2026-01-01')
            ->assertJsonPath('data.totales.liquidaciones', 1)
            ->assertJsonPath('data.totales.peso_fino_total', '837.1800')
            ->assertJsonPath('data.totales.bruto_total', '1000.25')
            ->assertJsonPath('data.totales.deducciones_total', '120.00')
            ->assertJsonPath('data.totales.neto_total', '862.25')
            ->assertJsonPath('data.por_estado.grupo', 'estado')
            ->assertJsonPath('data.por_estado.filas.0.valor', 'aprobado')
            ->assertJsonPath('data.por_estado.filas.0.liquidaciones', 1)
            ->assertJsonPath('data.por_estado.filas.0.bruto_total', '1000.25')
            ->assertJsonPath('data.por_estado.filas.0.neto_total', '862.25')
            ->assertJsonPath('data.por_municipio.grupo', 'municipio')
            ->assertJsonPath('data.por_municipio.filas.0.valor', $aprobada->municipio_nombre)
            ->assertJsonPath('data.por_area_minera.grupo', 'area_minera')
            ->assertJsonPath('data.por_area_minera.filas.0.valor', $aprobada->nombre_area_minera)
            ->assertJsonPath('data.por_mes.grupo', 'mes')
            ->assertJsonPath('data.por_mes.filas.0.valor', '2026-05');
    }

    public function test_el_tecnico_sin_permiso_de_reportes_recibe_403(): void
    {
        $tecnico = $this->tecnico();

        $this->actingAs($tecnico, 'web')
            ->getJson('/api/v1/reportes/resumen')
            ->assertForbidden();

        $this->actingAs($tecnico, 'web')
            ->getJson('/api/v1/reportes/exportar?formato=xlsx')
            ->assertForbidden();
    }

    public function test_un_rango_mal_formado_o_invertido_se_rechaza(): void
    {
        $gerente = $this->gerente();

        $this->actingAs($gerente, 'web')
            ->getJson('/api/v1/reportes/resumen?desde=10-05-2026')
            ->assertStatus(422)
            ->assertJsonValidationErrors('fecha_desde');

        $this->actingAs($gerente, 'web')
            ->getJson('/api/v1/reportes/resumen?desde=2026-12-31&hasta=2026-01-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors('fecha_hasta');
    }

    public function test_el_detalle_del_reporte_filtra_por_estado(): void
    {
        $gerente = $this->gerente();
        $tecnico = $this->tecnico();

        $aprobada = Liquidacion::factory()->aprobada()->create();
        Liquidacion::factory()->create(['estado' => 'creado']);
        Liquidacion::factory()->create(['estado' => 'eliminado']);

        $respuesta = $this->actingAs($gerente, 'web')
            ->getJson('/api/v1/reportes/liquidaciones?estado=aprobado');

        $respuesta->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $aprobada->id);
    }

    public function test_exportar_el_reporte_a_xlsx_y_a_pdf(): void
    {
        $gerente = $this->gerente();
        Liquidacion::factory()->create(['estado' => 'aprobado']);

        $xlsx = $this->actingAs($gerente, 'web')
            ->getJson('/api/v1/reportes/exportar?formato=xlsx');

        $xlsx->assertOk();
        $this->assertStringContainsString('spreadsheetml', $xlsx->headers->get('Content-Type'));

        $pdf = $this->actingAs($gerente, 'web')
            ->getJson('/api/v1/reportes/exportar?formato=pdf');

        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', $pdf->headers->get('Content-Type'));
    }

    public function test_un_formato_de_exportacion_desconocido_responde_422(): void
    {
        $gerente = $this->gerente();

        $this->actingAs($gerente, 'web')
            ->getJson('/api/v1/reportes/exportar?formato=avi')
            ->assertStatus(422)
            ->assertJsonValidationErrors('formato');
    }

    // ---------------------------------------------------------- parametros

    public function test_leer_parametros_es_para_cualquier_autenticado(): void
    {
        $tecnico = $this->tecnico();

        $this->actingAs($tecnico, 'web')
            ->getJson('/api/v1/parametros')
            ->assertOk()
            ->assertJsonPath('data.0.clave', 'ALICUOTA_APORTE');
    }

    public function test_escribir_parametros_es_solo_para_el_administrador(): void
    {
        $tecnico = $this->tecnico();

        $this->actingAs($tecnico, 'web')
            ->putJson('/api/v1/parametros', [
                'parametros' => ['ALICUOTA_APORTE' => '0.019'],
            ])
            ->assertForbidden();
    }

    public function test_el_administrador_actualiza_solo_claves_que_existen(): void
    {
        $admin = $this->admin();

        $respuesta = $this->actingAs($admin, 'web')
            ->putJson('/api/v1/parametros', [
                'parametros' => [
                    'ALICUOTA_APORTE' => '0.0180',
                    'GRAMOS_POR_ONZA_TROY' => '31.1036',
                ],
            ]);

        $respuesta->assertOk()
            ->assertJsonPath('message', 'Parametros actualizados correctamente.');

        $this->assertSame('0.0180', ParametroGeneral::query()->find('ALICUOTA_APORTE')?->valor);
        $this->assertSame('31.1036', ParametroGeneral::query()->find('GRAMOS_POR_ONZA_TROY')?->valor);
    }

    public function test_una_clave_inventada_o_con_notacion_cientifica_no_pasa(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'web')
            ->putJson('/api/v1/parametros', [
                'parametros' => ['MULTIPLICADOR_MAGICO' => '999'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('parametros');

        $this->actingAs($admin, 'web')
            ->putJson('/api/v1/parametros', [
                'parametros' => ['ALICUOTA_APORTE' => '1.8E-2'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('parametros.ALICUOTA_APORTE');
    }

    // ----------------------------------------------------------- catalogos

    public function test_el_tecnico_crea_una_persona_y_el_mismo_ci_no_duplica(): void
    {
        $tecnico = $this->tecnico();

        $nuevo = $this->actingAs($tecnico, 'web')
            ->postJson('/api/v1/catalogos/personas', [
                'nombres' => 'JOSE LUIS',
                'apellidos' => 'QUISPE MAMANI',
                'ci' => '4530012',
                'nit' => '1006965099',
            ]);

        $nuevo->assertCreated();
        $id = $nuevo->json('data.id');

        // El vendedor puede venir del Excel con el mismo CI: la segunda vez
        // se devuelve la misma persona, nunca se crea un duplicado (N5).
        $repetido = $this->actingAs($tecnico, 'web')
            ->postJson('/api/v1/catalogos/personas', [
                'nombres' => 'JOSE LUIS',
                'apellidos' => 'QUISPE MAMANI',
                'ci' => '4530012',
            ]);

        $repetido->assertOk();
        $this->assertSame($id, $repetido->json('data.id'));
    }

    public function test_el_ci_en_formato_invalido_no_se_registra(): void
    {
        $tecnico = $this->tecnico();

        $this->actingAs($tecnico, 'web')
            ->postJson('/api/v1/catalogos/personas', [
                'nombres' => 'ANA',
                'apellidos' => 'FLORES',
                'ci' => '4.530.001',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ci');
    }

    public function test_el_historial_y_las_cotizaciones_responden(): void
    {
        $gerente = $this->gerente();
        $tecnico = $this->tecnico();
        $liquidacion = Liquidacion::factory()->create();

        // La bitacora inicial (creado) la escribe el registro, que aqui no
        // paso; se registra como hace el controlador para que el historial
        // tenga su primera entrada.
        app(TransicionEstadoService::class)->registrarInicio($liquidacion, $tecnico);

        $this->actingAs($gerente, 'web')
            ->getJson('/api/v1/liquidaciones/'.$liquidacion->id.'/historial')
            ->assertOk()
            ->assertJsonPath('data.0.estado_nuevo', 'creado');

        $this->actingAs($tecnico, 'web')
            ->getJson('/api/v1/cotizaciones')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_el_buscador_de_municipios_responde_a_q_con_su_ubicacion(): void
    {
        $tecnico = $this->tecnico();

        // Dos municipios con nombre distinto: la busqueda tiene que devolver
        // solo el que coincide y, para el filtro del Index, su provincia y su
        // departamento (el autocompletado los muestra como detalle).
        $elegido = Municipio::factory()->create();
        Municipio::factory()->create();

        $respuesta = $this->actingAs($tecnico, 'web')
            ->getJson('/api/v1/catalogos/municipios?q='.rawurlencode($elegido->nombre));

        $respuesta->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $elegido->id)
            ->assertJsonPath('data.0.nombre', $elegido->nombre)
            ->assertJsonPath('data.0.provincia_nombre', $elegido->provincia->nombre)
            ->assertJsonPath('data.0.departamento_nombre', $elegido->provincia->departamento->nombre);

        // Sin q la cascada sigue sirviendo la lista completa y no recorta.
        $cascada = $this->actingAs($tecnico, 'web')
            ->getJson('/api/v1/catalogos/municipios?provincia_id='.$elegido->provincia_id)
            ->assertOk();

        $this->assertTrue($cascada->json('data.0.id') === $elegido->id);
    }

    public function test_el_tipo_de_cambio_del_dia_se_consulta_por_fecha(): void
    {
        $tecnico = $this->tecnico();
        TipoCambio::factory()->create(['fecha' => '2026-10-01', 'valor' => '6.9100', 'fuente' => 'BCB']);

        $this->actingAs($tecnico, 'web')
            ->getJson('/api/v1/catalogos/tipo-cambio?fecha=2026-10-01')
            ->assertOk()
            ->assertJsonPath('data.fecha', '2026-10-01')
            ->assertJsonPath('data.valor', '6.9100')
            ->assertJsonPath('data.fuente', 'BCB');

        // Un dia sin valor cargado responde null: el formulario lo muestra
        // como aviso en vez de inventar un tipo de cambio.
        $this->actingAs($tecnico, 'web')
            ->getJson('/api/v1/catalogos/tipo-cambio?fecha=2026-10-02')
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->actingAs($tecnico, 'web')
            ->getJson('/api/v1/catalogos/tipo-cambio?fecha=01-10-2026')
            ->assertStatus(422)
            ->assertJsonValidationErrors('fecha');
    }

    // --------------------------------------------------------- utilidades

    private function tecnico(): User
    {
        return User::factory()->conRol('tecnico')->create();
    }

    private function gerente(): User
    {
        return User::factory()->conRol('gerente')->create();
    }

    private function admin(): User
    {
        return User::factory()->conRol('admin')->create();
    }
}

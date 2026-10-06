<?php

namespace Tests\Feature;

use App\Contracts\LectorParametros;
use App\Models\ParametroGeneral;
use App\Repositories\ParametroRepository;
use App\Services\CalculoLiquidacionService;
use Database\Seeders\ParametrosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La capa de lectura de parametros (seccion 5.2).
 *
 * No hay nada de HTTP aqui: es la fila de `parametros_generales` y el
 * contrato de un solo metodo que el servicio de calculo consume. El test que
 * mas importa es el segundo: la ausencia de cache es una decision, no un
 * descuido, y un cache bien intencionado -que hoy no invalida- romperia el
 * aporte de una liquidacion capturada despues de corregir la alicuota.
 */
final class ParametroRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_lee_el_valor_guardado_y_devuelve_null_para_una_clave_desconocida(): void
    {
        $parametro = ParametroGeneral::query()->create([
            'clave' => 'GRAMOS_POR_ONZA_TROY',
            'valor' => '31.1035',
            'tipo' => 'decimal',
            'descripcion' => 'Divisor de la formula del valor bruto.',
        ]);

        $repositorio = new ParametroRepository;

        $this->assertSame('31.1035', $repositorio->valor($parametro->clave));
        $this->assertNull($repositorio->valor('ESTE_PARAMETRO_NO_EXISTE'));
    }

    /**
     * Cada lectura ve la tabla como esta en ese momento: sin cache y sin
     * memoria entre llamadas.
     */
    public function test_no_cachea_la_tabla_entre_llamadas(): void
    {
        $parametro = ParametroGeneral::query()->create([
            'clave' => 'ALICUOTA_APORTE',
            'valor' => '0.018',
            'tipo' => 'decimal',
            'descripcion' => 'Aporte de caja de salud 1,8 %.',
        ]);

        $repositorio = new ParametroRepository;

        $this->assertSame('0.018', $repositorio->valor('ALICUOTA_APORTE'));

        // Un administrador corrige la alicuota entre dos calculos. El segundo
        // calculo tiene que ver el valor nuevo, aunque sea la MISMA instancia
        // del repositorio.
        $parametro->update(['valor' => '0.019']);

        $this->assertSame('0.019', $repositorio->valor('ALICUOTA_APORTE'));
        $this->assertSame('0.019', (new ParametroRepository)->valor('ALICUOTA_APORTE'));
    }

    /**
     * La union de 5.1 + 5.2: el servicio calculado con los parametros
     * REALES del seeder, no con los de un stub.
     *
     * El caso es el ORO de la seccion 1: si alguien cambia la alicuota, borra
     * una fila o le pone notacion cientifica a los parametros, los numeros
     * dejan de cuadrar con la hoja y este test lo dice en terminos de
     * dinero.
     *
     * Se pide por el contenedor a proposito: si desaparece el binding de
     * LectorParametros en AppServiceProvider, la resolucion falla aqui y no
     * dentro de un controlador de la FASE 6.
     */
    public function test_el_calculo_con_los_parametros_del_seeder_reproduce_la_hoja(): void
    {
        $this->seed(ParametrosSeeder::class);

        $servicio = app(CalculoLiquidacionService::class);

        $oroB = $servicio->calcular([
            'peso_gramos' => '1944.53',
            'ley_pagable' => '0.9302',
            'cotizacion_usd_oz_t' => '2648.57',
            'tipo_cambio' => '6.96',
            'aplica_caja_salud' => true,
        ]);

        $this->assertSame('31.1035', $oroB['gramos_por_onza_troy']);
        $this->assertSame('1072018.84', $oroB['valor_bruto_bs']);
        $this->assertSame('19296.34', $oroB['aporte_caja_salud_bs']);
        $this->assertSame('1052722.50', $oroB['valor_neto_bs']);

        $oroA = $servicio->calcular([
            'peso_gramos' => '1944.53',
            'ley_pagable' => '0.9302',
            'cotizacion_usd_oz_t' => '2648.57',
            'tipo_cambio' => '6.96',
            'aplica_caja_salud' => false,
        ]);

        $this->assertSame('0.00', $oroA['aporte_caja_salud_bs']);
        $this->assertSame('1072018.84', $oroA['valor_neto_bs']);
    }

    /**
     * La D1 del seeder: existe un UNICO alicuota, la del 1,8 %. Sembrar la
     * segunda -que la correccion C4 elimino- volveria a la maquina de
     * deducciones que el negocio rechazo.
     */
    public function test_el_seeder_no_reintroduce_la_segunda_deduccion(): void
    {
        $this->seed(ParametrosSeeder::class);

        $repositorio = new ParametroRepository;

        $this->assertSame('0.018', $repositorio->valor('ALICUOTA_APORTE'));
        $this->assertNull($repositorio->valor('ALICUOTA_APORTE_SOLIDARIO'));
        $this->assertNull($repositorio->valor('ALICUOTA_SINDICAL'));
    }

    public function test_el_repositorio_satisface_el_contrato_que_el_servicio_exige(): void
    {
        $this->assertInstanceOf(
            LectorParametros::class,
            new ParametroRepository,
        );
    }
}

<?php

namespace Tests\Feature;

use App\Services\NumeradorDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * El numerador (secciones 4.4 y 5.3).
 *
 * El riesgo real no es el formato del numero -es el de la hoja oficial- sino
 * la carrera de dos capturas simultaneas: dos liquidaciones no pueden quedar
 * con el mismo numero_documento, y un fallo a mitad de la creacion no puede
 * comerse un correlativo. Ambos se prueban contra la base, no contra la
 * memoria, que es exactamente donde el servicio dice que vive el contador.
 */
final class NumeradorDocumentoTest extends TestCase
{
    use RefreshDatabase;

    private const TIPO = 'liquidacion';

    public function test_el_primer_documento_del_ejercicio_es_el_uno(): void
    {
        $asignado = (new NumeradorDocumento)->asignar(self::TIPO, 2024);

        $this->assertSame(1, $asignado['correlativo']);
        $this->assertSame('EP-BCB-1', $asignado['numero_documento']);
        $this->assertSame('FORM-EP-BCB-1/2024', $asignado['codigo_formulario']);
    }

    public function test_los_siguientes_llegan_correlativos_y_en_orden(): void
    {
        $numerador = new NumeradorDocumento;

        $this->assertSame('EP-BCB-1', $numerador->asignar(self::TIPO, 2024)['numero_documento']);
        $this->assertSame('EP-BCB-2', $numerador->asignar(self::TIPO, 2024)['numero_documento']);
        $this->assertSame('EP-BCB-3', $numerador->asignar(self::TIPO, 2024)['numero_documento']);

        $this->assertDatabaseHas('correlativos', [
            'tipo' => self::TIPO,
            'ejercicio' => 2024,
            'ultimo' => 3,
        ]);
    }

    /**
     * El contador es (tipo, ejercicio): cada ejercicio arranca solo, sin
     * arrastrar el acumulado del anterior.
     */
    public function test_cada_ejercicio_arranca_en_el_uno(): void
    {
        $numerador = new NumeradorDocumento;

        $numerador->asignar(self::TIPO, 2024);
        $numerador->asignar(self::TIPO, 2024);

        $this->assertSame(1, $numerador->asignar(self::TIPO, 2025)['correlativo']);
        $this->assertSame(3, $numerador->asignar(self::TIPO, 2024)['correlativo']);
    }

    /**
     * Retoma un contador que ya venia de la hoja: 722 entregados, el
     * siguiente es 723, que es el numero de la seccion 1.
     */
    public function test_retoma_un_contador_preexistente(): void
    {
        DB::table('correlativos')->insert([
            'tipo' => self::TIPO,
            'ejercicio' => 2024,
            'ultimo' => 722,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $asignado = (new NumeradorDocumento)->asignar(self::TIPO, 2024);

        $this->assertSame(723, $asignado['correlativo']);
        $this->assertSame('EP-BCB-723', $asignado['numero_documento']);
        $this->assertSame('FORM-EP-BCB-723/2024', $asignado['codigo_formulario']);
    }

    /**
     * El contador se incrementa DENTRO de la transaccion: si la creacion de
     * la liquidacion falla despues de pedir el numero, el correlativo vuelve
     * atras y no queda un hueco. No es el escenario mas grave -un hueco no
     * duplica nada- pero es el que distingue un contador transaccional de un
     * "leer, sumar y guardar" que se come numeros en cada error.
     */
    public function test_si_la_transaccion_falla_el_correlativo_no_avanza(): void
    {
        $numerador = new NumeradorDocumento;

        try {
            DB::transaction(function () use ($numerador): void {
                $numerador->asignar(self::TIPO, 2024);

                throw new \RuntimeException('falla simulada al crear la fila');
            });
        } catch (\RuntimeException) {
            // El fallo es el escenario: lo que importa es el contador.
        }

        $this->assertDatabaseMissing('correlativos', ['tipo' => self::TIPO, 'ejercicio' => 2024]);

        $this->assertSame(1, $numerador->asignar(self::TIPO, 2024)['correlativo']);
    }

    /**
     * Dos peticiones simultaneas no pueden entregar el mismo numero. El
     * SELECT ... FOR UPDATE no se puede provocar de forma deterministica en
     * un solo proceso, pero si se puede probar que el contador esta en la
     * base y no en memoria: dos instancias distintas del servicio leen la
     * misma fila y no se pisan.
     */
    public function test_dos_instancias_del_servicio_no_dan_el_mismo_numero(): void
    {
        $primera = (new NumeradorDocumento)->asignar(self::TIPO, 2024);
        $segunda = (new NumeradorDocumento)->asignar(self::TIPO, 2024);

        $this->assertSame('EP-BCB-1', $primera['numero_documento']);
        $this->assertSame('EP-BCB-2', $segunda['numero_documento']);
        $this->assertNotSame($primera['numero_documento'], $segunda['numero_documento']);
    }

    public function test_un_tipo_desconocido_se_rechaza_y_no_toca_el_contador(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tipo de documento desconocido: "nota". Registrado: liquidacion.');

        (new NumeradorDocumento)->asignar('nota', 2024);
    }

    /**
     * El codigo del formulario se arma sin tocar la base: sirve cuando el
     * numero lo trajo el usuario desde el Excel y no hubo correlativo.
     */
    public function test_el_codigo_del_formulario_es_puro(): void
    {
        $this->assertSame(
            'FORM-EP-BCB-723/2024',
            NumeradorDocumento::codigoFormulario('EP-BCB-723', 2024),
        );
        $this->assertSame(
            'FORM-EP-BCB-12/2026',
            NumeradorDocumento::codigoFormulario('EP-BCB-12', 2026),
        );
    }
}

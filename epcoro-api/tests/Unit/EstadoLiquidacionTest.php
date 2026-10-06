<?php

namespace Tests\Unit;

use App\Enums\EstadoLiquidacion;
use PHPUnit\Framework\TestCase;

/**
 * La maquina de estados de D2, sin base de datos ni aplicacion levantada: es
 * una tabla de constantes, asi que el test es literalmente la tabla del
 * documento comparada valor por valor.
 *
 * Si este test falla, cambio la maquina de estados sin querer: esa es la
 * unica razon por la que se rompe.
 */
class EstadoLiquidacionTest extends TestCase
{
    /**
     * La tabla completa de docs/reglas-negocio.md, de donde salio la
     * constante TRANSICIONES.
     *
     * @var array<string, array<string>>
     */
    private const TABLA = [
        'creado' => ['en_revision', 'anulado', 'eliminado'],
        'en_revision' => ['revisado', 'rechazado', 'anulado'],
        'revisado' => ['aprobado', 'rechazado', 'anulado'],
        'rechazado' => ['en_correccion', 'anulado'],
        'en_correccion' => ['en_revision', 'anulado'],
        'aprobado' => [],
        'anulado' => [],
        'eliminado' => [],
    ];

    public function test_cada_estado_tiene_exactamente_las_transiciones_de_la_tabla_d2(): void
    {
        foreach (self::TABLA as $desde => $esperadas) {
            $estado = EstadoLiquidacion::from($desde);

            $obtenidas = array_map(
                fn (EstadoLiquidacion $destino): string => $destino->value,
                $estado->transiciones(),
            );

            sort($esperadas);
            sort($obtenidas);

            $this->assertSame($esperadas, $obtenidas, 'Transiciones de '.$desde);
        }
    }

    public function test_aprobado_es_terminal_e_inmutable(): void
    {
        $aprobado = EstadoLiquidacion::Aprobado;

        $this->assertTrue($aprobado->esTerminal());
        $this->assertSame([], $aprobado->transiciones());

        // D2: desde aprobado no se sale NI HACIA UNO NUEVO. Si alguna vez
        // anadir una transicion hacia aprobado, este assert la atrapa.
        foreach (EstadoLiquidacion::cases() as $destino) {
            $this->assertFalse(
                $aprobado->puedeTransicionA($destino),
                'aprobado no debe poder llegar a '.$destino->value,
            );
        }
    }

    public function test_anulado_y_eliminado_tambien_son_terminales(): void
    {
        foreach ([EstadoLiquidacion::Anulado, EstadoLiquidacion::Eliminado] as $terminal) {
            $this->assertTrue($terminal->esTerminal());
            $this->assertSame([], $terminal->transiciones());
        }
    }

    public function test_un_estado_no_transiciona_si_mismo(): void
    {
        foreach (EstadoLiquidacion::cases() as $estado) {
            $this->assertFalse($estado->puedeTransicionA($estado));
        }
    }

    public function test_solo_creado_y_en_correccion_son_editables(): void
    {
        $editables = array_map(
            fn (EstadoLiquidacion $estado): string => $estado->value,
            array_filter(
                EstadoLiquidacion::cases(),
                fn (EstadoLiquidacion $estado): bool => $estado->esEditable(),
            ),
        );

        sort($editables);

        $this->assertSame(['creado', 'en_correccion'], $editables);
    }

    public function test_las_cadenas_son_las_del_enum_de_postgresql(): void
    {
        $valores = array_map(
            fn (EstadoLiquidacion $estado): string => $estado->value,
            EstadoLiquidacion::cases(),
        );

        $this->assertSame([
            'creado', 'en_revision', 'revisado', 'aprobado',
            'rechazado', 'en_correccion', 'anulado', 'eliminado',
        ], $valores);
    }
}

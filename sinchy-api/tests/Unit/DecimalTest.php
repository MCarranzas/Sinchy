<?php

namespace Tests\Unit;

use App\Support\Decimal;
use PHPUnit\Framework\TestCase;

/**
 * Los 17 casos limite de halfUp (correccion C9, seccion 4.3).
 *
 * Este test existe para que un "arreglo" bien intencionado -bcadd($x,'0',2),
 * que trunca, o round((float)$x,2), que reintroduce la perdida de precision
 * binaria- caiga en el acto. El caso 1072018.8360554035 es el mismo que casi
 * se llevo el ORO-A: con truncamiento da 1072018.83 y la liquidacion deja de
 * cuadrar con el Excel oficial.
 */
final class DecimalTest extends TestCase
{
    /**
     * Pares y no mapa: con la entrada como clave, PHP convierte '0' y '123'
     * en enteros y se pierde que son cadenas, que es como llegan desde la
     * hoja.
     *
     * @var array<int, array{entrada: string, salida: string}>
     */
    private const CASOS = [
        ['entrada' => '1.005', 'salida' => '1.01'],
        ['entrada' => '1.004', 'salida' => '1.00'],
        ['entrada' => '1.015', 'salida' => '1.02'],  // half-up, NO banker's rounding: es lo que hace ROUND() de Excel
        ['entrada' => '2.675', 'salida' => '2.68'],
        ['entrada' => '2.674', 'salida' => '2.67'],
        ['entrada' => '2.685', 'salida' => '2.69'],
        ['entrada' => '0.005', 'salida' => '0.01'],
        ['entrada' => '0.004', 'salida' => '0.00'],
        ['entrada' => '0.015', 'salida' => '0.02'],
        ['entrada' => '1072018.8360554035', 'salida' => '1072018.84'],
        ['entrada' => '1072018.835', 'salida' => '1072018.84'],
        ['entrada' => '1072018.834', 'salida' => '1072018.83'],
        ['entrada' => '999999.995', 'salida' => '1000000.00'],
        ['entrada' => '0.999', 'salida' => '1.00'],
        ['entrada' => '2.999', 'salida' => '3.00'],
        ['entrada' => '100', 'salida' => '100.00'],
        ['entrada' => '0.1', 'salida' => '0.10'],
    ];

    public function test_los_casos_limite_del_documento(): void
    {
        foreach (self::CASOS as $caso) {
            $this->assertSame(
                $caso['salida'],
                Decimal::halfUp($caso['entrada']),
                "halfUp({$caso['entrada']}) debio dar {$caso['salida']}",
            );
        }
    }

    /**
     * El bug concreto que casi entra: truncar no es redondear.
     */
    public function test_truncar_con_bcadd_no_es_redondear(): void
    {
        $this->assertSame('1072018.83', bcadd('1072018.8360554035', '0', 2));
        $this->assertSame('1072018.84', Decimal::halfUp('1072018.8360554035'));

        // bcdiv trunca con digito de guarda: 100.505 / 100 da 1.00 y no 1.01.
        $this->assertSame('1.00', bcdiv('100.505', '100', 2));
        $this->assertSame('1.01', Decimal::halfUp(bcdiv('100.505', '100', 20)));
    }

    public function test_redondea_tambien_a_otras_cantidades_de_decimales(): void
    {
        // Las columnas de auditoria: peso_fino numeric(14,4) y
        // valor_usd_gramo numeric(12,6), que PostgreSQL redondea igual.
        $this->assertSame('1808.8018', Decimal::halfUp('1808.801806', 4));
        $this->assertSame('85.153439', Decimal::halfUp('85.153439323548796', 6));
    }

    /**
     * normalizar() es el otro lado de la moneda: el producto sale de bcmath
     * con la escala completa y viaja sin los ceros sobrantes.
     *
     * @var array<int, array{entrada: string, salida: string}>
     */
    private const NORMALIZABLES = [
        ['entrada' => '1808.80180600000000000000', 'salida' => '1808.801806'],
        ['entrada' => '85.15343932354879675920', 'salida' => '85.1534393235487967592'],
        ['entrada' => '100.000', 'salida' => '100'],
        ['entrada' => '0.000', 'salida' => '0'],
        ['entrada' => '123', 'salida' => '123'],
        ['entrada' => '0', 'salida' => '0'],
    ];

    public function test_normalizar_quita_los_ceros_sobrantes(): void
    {
        foreach (self::NORMALIZABLES as $caso) {
            $this->assertSame(
                $caso['salida'],
                Decimal::normalizar($caso['entrada']),
                "normalizar({$caso['entrada']}) debio dar {$caso['salida']}",
            );
        }
    }
}

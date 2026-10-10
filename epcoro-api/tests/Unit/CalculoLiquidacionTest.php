<?php

namespace Tests\Unit;

use App\Contracts\LectorParametros;
use App\Exceptions\CalculoInvalidoException;
use App\Exceptions\ParametroInvalidoException;
use App\Services\CalculoLiquidacionService;
use App\Support\Decimal;
use PHPUnit\Framework\TestCase;

/**
 * El test mas importante del proyecto (seccion 5.4): los numeros de
 * hoja.xlsx, calculados por el servicio y comparados como string.
 *
 * Nunca se comparan floats: 1072018.84 no es representable en binario y un
 * assertEquals con float seria fragil en el peor lugar posible, que es donde
 * se decide si una liquidacion vale un centavo mas o menos.
 *
 * No levanta la aplicacion ni toca la base de datos: el lector de parametros
 * es un stub en memoria, porque el servicio solo recibe numeros y devuelve
 * numeros.
 */
final class CalculoLiquidacionTest extends TestCase
{
    /**
     * Insumos del caso ORO de la seccion 1: la fila real de la hoja.
     *
     * @var array<string, string>
     */
    private const ORO = [
        'peso_gramos' => '1944.53',
        'ley_pagable' => '0.9302',
        'cotizacion_usd_oz_t' => '2648.57',
        'tipo_cambio' => '6.96',
    ];

    /**
     * Los dos brutos de la decision D4 y los insumos que los producen.
     *
     * La regla solo se ve en un bruto cuyo centavo depende de la tercera
     * decimal, y esos brutos se eligen primero: 53122.496411 y 120335.275943
     * son los de docs/reglas-negocio.md. Los insumos de abajo son los que la
     * formula pone en esa misma ventana, con cotizacion 2648.57 y tipo de
     * cambio 6.96: el bruto exacto queda en 53122.4954... y 120335.2751...,
     * es decir redondea a 53122.50 y 120335.28. Los aportes que salen son
     * exactamente los de la tabla: 956.20/956.21 y 2166.03/2166.04.
     *
     * @var array<int, array{peso: string, ley: string, bruto: string, aporte_exacto: string, aporte_sobre_redondeado: string}>
     */
    private const CASOS_D4 = [
        [
            'peso' => '89.70',
            'ley' => '0.999251',
            'bruto' => '53122.50',
            'aporte_exacto' => '956.20',
            'aporte_sobre_redondeado' => '956.21',
        ],
        [
            'peso' => '204.49',
            'ley' => '0.992909',
            'bruto' => '120335.28',
            'aporte_exacto' => '2166.03',
            'aporte_sobre_redondeado' => '2166.04',
        ],
    ];

    public function test_oro_a_replica_exacta_de_la_hoja(): void
    {
        $r = $this->calcular($this->oro(['aplica_caja_salud' => false]));

        $this->assertSame('1808.801806', $r['peso_fino_gramos']);
        $this->assertSame('1072018.84', $r['valor_bruto_bs']);
        $this->assertSame('0.00', $r['aporte_caja_salud_bs']);
        $this->assertSame('0.00', $r['total_deducciones_bs']);
        $this->assertSame('1072018.84', $r['valor_neto_bs']);
        $this->assertSame('31.1035', $r['gramos_por_onza_troy']);

        // Lo que PostgreSQL guarda en las columnas de auditoria: numeric(14,4)
        // y numeric(12,6) redondean igual que halfUp. Son referencia, jamas
        // insumo (caso ORO-C de abajo).
        $this->assertSame('1808.8018', Decimal::halfUp($r['peso_fino_gramos'], 4));
        $this->assertSame('85.153439', Decimal::halfUp($r['valor_usd_gramo'], 6));
    }

    public function test_oro_b_con_caja_de_salud_encendida(): void
    {
        $r = $this->calcular($this->oro(['aplica_caja_salud' => true]));

        $this->assertSame('1072018.84', $r['valor_bruto_bs']);
        $this->assertSame('19296.34', $r['aporte_caja_salud_bs']);
        $this->assertSame('19296.34', $r['total_deducciones_bs']);
        $this->assertSame('1052722.50', $r['valor_neto_bs']);
    }

    /**
     * Correccion C2: multiplicar de vuelta las columnas ya persistidas da un
     * centavo menos que el valor guardado. Si alguien "optimiza" el servicio
     * para calcular desde la BD -o un reporte recalcula en vez de leer- este
     * test lo detecta, porque es el que documenta por que no se hace.
     */
    public function test_oro_c_no_se_vuelve_a_multiplicar_lo_persistido(): void
    {
        $brutoRecalculado = Decimal::halfUp(
            bcmul(bcmul('1808.8018', '85.153439', Decimal::ESCALA), '6.96', Decimal::ESCALA),
        );

        $this->assertSame('1072018.83', $brutoRecalculado);

        $r = $this->calcular($this->oro(['aplica_caja_salud' => false]));

        $this->assertSame('1072018.84', $r['valor_bruto_bs']);
        $this->assertNotSame($r['valor_bruto_bs'], $brutoRecalculado);
    }

    public function test_ley_pagable_uno_da_el_peso_fino_igual_al_peso(): void
    {
        $r = $this->calcular($this->oro([
            'ley_pagable' => '1.00',
            'aplica_caja_salud' => false,
        ]));

        $this->assertSame('1944.53', $r['peso_fino_gramos']);
    }

    public function test_caja_de_salud_apagada_da_aporte_cero(): void
    {
        $r = $this->calcular($this->oro(['aplica_caja_salud' => false]));

        $this->assertSame('0.00', $r['aporte_caja_salud_bs']);
        $this->assertSame('0.00', $r['total_deducciones_bs']);
        // Sin deducciones, neto y bruto son el mismo numero.
        $this->assertSame($r['valor_bruto_bs'], $r['valor_neto_bs']);
    }

    /**
     * Paso 6 de la formula: las deducciones variables se suman exactas y se
     * redondean una sola vez, al final.
     *
     * Aqui la resta encaja al centavo porque 15.50 esta exactamente
     * representado: restar un monto ya cuadrado desplaza el halfUp y no lo
     * cruza.
     */
    public function test_las_deducciones_variables_se_suman_al_total(): void
    {
        $r = $this->calcular($this->oro([
            'aplica_caja_salud' => false,
            'deducciones' => ['10.00', '5.50'],
        ]));

        $this->assertSame('0.00', $r['aporte_caja_salud_bs']);
        $this->assertSame('15.50', $r['total_deducciones_bs']);
        $this->assertSame(
            bcsub($r['valor_bruto_bs'], '15.50', 2),
            $r['valor_neto_bs'],
        );
    }

    /**
     * Correccion C1 (seccion 5.4.4): el resultado no depende de redondear en
     * pasos intermedios. Con ley 0.930255 y peso 1944.535 el bruto sale
     * IDENTICO por las dos vias:
     *
     *   via 1: bcmath a escala 20, sin redondeo intermedio (el servicio)
     *   via 2: el mismo bruto redondeado a 6 decimales y recien despues a 2
     *
     * Y la via que SI desplaza el centavo -redondear el valor_usd_gramo a 6
     * decimales, como hacia la regla vieja- da otro resultado, que es
     * justamente la que el servicio no usa.
     *
     * El peso con tres decimales es deliberado: este es un caso de redondeo,
     * no una fila que se persista (la columna es numeric(14,2)).
     */
    public function test_el_resultado_no_depende_de_redondeos_intermedios(): void
    {
        $peso = '1944.535';
        $ley = '0.930255';
        $cotizacion = '2648.57';
        $tipoCambio = '6.96';

        $brutoExacto = bcmul(
            bcmul(bcdiv($cotizacion, '31.1035', Decimal::ESCALA), $tipoCambio, Decimal::ESCALA),
            bcmul($peso, $ley, Decimal::ESCALA),
            Decimal::ESCALA,
        );

        $r = $this->calcular([
            'peso_gramos' => $peso,
            'ley_pagable' => $ley,
            'cotizacion_usd_oz_t' => $cotizacion,
            'tipo_cambio' => $tipoCambio,
            'aplica_caja_salud' => false,
        ]);

        $this->assertSame('1072084.98', Decimal::halfUp($brutoExacto));
        $this->assertSame('1072084.98', $r['valor_bruto_bs'], 'via 1: escala 20');
        $this->assertSame(
            $r['valor_bruto_bs'],
            Decimal::halfUp(Decimal::halfUp($brutoExacto, 6)),
            'via 2: redondeando a 6 decimales',
        );

        $valorUsdRedondeado = Decimal::halfUp(bcdiv($cotizacion, '31.1035', Decimal::ESCALA), 6);
        $brutoConUsdRedondeado = Decimal::halfUp(
            bcmul(
                bcmul($valorUsdRedondeado, $tipoCambio, Decimal::ESCALA),
                bcmul($peso, $ley, Decimal::ESCALA),
                Decimal::ESCALA,
            ),
        );

        $this->assertSame('1072084.97', $brutoConUsdRedondeado);
        $this->assertNotSame($r['valor_bruto_bs'], $brutoConUsdRedondeado);
    }

    /**
     * Decision D4 (seccion 5.4.6): el aporte sale del bruto SIN redondear.
     * En el caso ORO las dos reglas coinciden, asi que este es el unico test
     * que protege la decision.
     */
    public function test_el_aporte_se_calcula_sobre_el_bruto_exacto(): void
    {
        foreach (self::CASOS_D4 as $caso) {
            $r = $this->calcular($this->oro([
                'peso_gramos' => $caso['peso'],
                'ley_pagable' => $caso['ley'],
                'aplica_caja_salud' => true,
            ]));

            $this->assertSame($caso['bruto'], $r['valor_bruto_bs']);
            $this->assertSame(
                $caso['aporte_exacto'],
                $r['aporte_caja_salud_bs'],
                'el aporte se calculo sobre el bruto exacto (D4)',
            );

            // El camino prohibido, calculado aqui a proposito: base = bruto
            // ya redondeado. En estos dos casos da un centavo distinto.
            $aporteSobreRedondeado = Decimal::halfUp(
                bcmul($caso['bruto'], '0.018', Decimal::ESCALA),
            );

            $this->assertSame($caso['aporte_sobre_redondeado'], $aporteSobreRedondeado);
            $this->assertNotSame($r['aporte_caja_salud_bs'], $aporteSobreRedondeado);
        }
    }

    /**
     * La otra cara de la regla de redondeo: el NETO tambien se redondea una
     * sola vez, desde su valor exacto (seccion 4.3, punto b), que es lo que
     * hace la celda de la hoja.
     *
     * Por eso bruto - aporte NO es el neto persistido en este caso:
     * 53122.50 - 956.20 = 52166.30, y el neto es 52166.29. Restando los dos
     * montos ya redondeados se estaria redondeando dos veces, que es lo que
     * la regla prohibe. En los casos ORO las dos vias coinciden, por eso este
     * test es el unico que fija la interpretacion.
     */
    public function test_el_neto_se_redondea_una_sola_vez_desde_el_valor_exacto(): void
    {
        $caso = self::CASOS_D4[0];

        $r = $this->calcular($this->oro([
            'peso_gramos' => $caso['peso'],
            'ley_pagable' => $caso['ley'],
            'aplica_caja_salud' => true,
        ]));

        $this->assertSame('52166.29', $r['valor_neto_bs']);
        $this->assertNotSame(
            bcsub($r['valor_bruto_bs'], $r['aporte_caja_salud_bs'], 2),
            $r['valor_neto_bs'],
        );
    }

    public function test_peso_cero_o_negativo_se_rechaza(): void
    {
        foreach (['0', '-1', '0.00'] as $peso) {
            $e = $this->rechazado($this->oro([
                'peso_gramos' => $peso,
                'aplica_caja_salud' => false,
            ]));

            $this->assertSame('El peso en gramos debe ser mayor que cero.', $e->getMessage());
        }
    }

    /**
     * Trampa T1: la ley es fraccion. 93.02 en vez de 0.9302 es un error de
     * captura y el corte lte:1 lo rechaza en lugar de entregar un peso fino
     * cien veces mayor.
     */
    public function test_ley_mayor_que_uno_se_rechaza(): void
    {
        foreach (['93.02', '1.000001', '0'] as $ley) {
            $e = $this->rechazado($this->oro([
                'ley_pagable' => $ley,
                'aplica_caja_salud' => false,
            ]));

            $this->assertStringContainsString('0.9302', $e->getMessage());
        }
    }

    public function test_cotizacion_y_tipo_de_cambio_en_cero_se_rechazan(): void
    {
        $cotizacion = $this->rechazado($this->oro([
            'cotizacion_usd_oz_t' => '0',
            'aplica_caja_salud' => false,
        ]));
        $this->assertStringContainsString('cotizacion', $cotizacion->getMessage());

        $tipoCambio = $this->rechazado($this->oro([
            'tipo_cambio' => '0',
            'aplica_caja_salud' => false,
        ]));
        $this->assertStringContainsString('tipo de cambio', $tipoCambio->getMessage());
    }

    public function test_un_campo_no_numerico_se_rechaza(): void
    {
        foreach (['mil novecientos', '', '1e3', null, true] as $peso) {
            $e = $this->rechazado($this->oro([
                'peso_gramos' => $peso,
                'aplica_caja_salud' => false,
            ]));

            $this->assertStringContainsString('peso_gramos', $e->getMessage());
        }
    }

    public function test_el_interruptor_de_caja_de_salud_tiene_que_ser_booleano(): void
    {
        $e = $this->rechazado($this->oro(['aplica_caja_salud' => 'SI']));

        $this->assertStringContainsString('aplica_caja_salud', $e->getMessage());
    }

    public function test_una_deduccion_negativa_o_no_numerica_se_rechaza(): void
    {
        foreach ([['-1.00'], ['1e3'], ['abc']] as $deducciones) {
            $e = $this->rechazado($this->oro([
                'aplica_caja_salud' => false,
                'deducciones' => $deducciones,
            ]));

            $this->assertStringContainsString('deduccion', $e->getMessage());
        }
    }

    /**
     * Un parametro faltante o basura no es un error del cliente: es la tabla
     * de parametros mal cargada, y por eso la excepcion no es de negocio.
     */
    public function test_un_parametro_faltante_o_basura_es_error_de_configuracion(): void
    {
        $sinDivisor = new CalculoLiquidacionService(
            $this->lector([CalculoLiquidacionService::CLAVE_ALICUOTA_APORTE => '0.018']),
        );

        try {
            $sinDivisor->calcular($this->oro(['aplica_caja_salud' => false]));
            $this->fail('Faltaba GRAMOS_POR_ONZA_TROY');
        } catch (ParametroInvalidoException $e) {
            $this->assertStringContainsString('GRAMOS_POR_ONZA_TROY', $e->getMessage());
        }

        $alicuotaBasura = new CalculoLiquidacionService(
            $this->lector([
                CalculoLiquidacionService::CLAVE_GRAMOS_POR_ONZA_TROY => '31.1035',
                CalculoLiquidacionService::CLAVE_ALICUOTA_APORTE => 'uno coma ocho',
            ]),
        );

        try {
            $alicuotaBasura->calcular($this->oro(['aplica_caja_salud' => false]));
            $this->fail('ALICUOTA_APORTE no era numerico');
        } catch (ParametroInvalidoException $e) {
            $this->assertStringContainsString('ALICUOTA_APORTE', $e->getMessage());
        }
    }

    /**
     * Los insumos tambien llegan como int y float desde JSON, no como string
     * forzado. El cast de PHP imprime la repr mas corta que coincide con el
     * literal, asi que calcular con numeros tiene que dar EXACTAMENTE lo mismo
     * que calcular con sus strings: la formula no distingue como llego el dato.
     * Tambien cubre las deducciones como float (10.5) e int (5).
     */
    public function test_los_insumos_int_y_float_producen_lo_mismo_que_sus_strings(): void
    {
        $numeros = [
            'peso_gramos' => 1944.53,
            'ley_pagable' => 0.9302,
            'cotizacion_usd_oz_t' => 2648.57,
            'tipo_cambio' => 6.96,
            'aplica_caja_salud' => true,
            'deducciones' => [10.5, 5],
        ];

        $desdeNumeros = $this->calcular($numeros);

        $strings = array_map(
            fn (mixed $v): mixed => is_array($v) ? array_map('strval', $v) : (is_bool($v) ? $v : (string) $v),
            $numeros,
        );

        $this->assertSame($desdeNumeros, $this->calcular($strings));
    }

    /**
     * NAN e infinito no son numeros usables: el servicio los descarta en
     * texto() antes de asomarse a bcmath, porque un peso NAN dejaria un bruto
     * NAN que PostgreSQL ni siquiera puede persistir.
     */
    public function test_nan_e_infinito_se_rechazan_como_no_numericos(): void
    {
        foreach ([NAN, INF, -INF] as $peso) {
            $e = $this->rechazado($this->oro([
                'peso_gramos' => $peso,
                'aplica_caja_salud' => false,
            ]));

            $this->assertStringContainsString('peso_gramos', $e->getMessage());
        }
    }

    /**
     * Los insumos vienen de un JSON, donde " 1944.53 " es tan valido como
     * "1944.53": el servicio recorta y el resultado no se corre un centavo.
     */
    public function test_el_espacio_alrededor_de_un_insumo_no_descuadra(): void
    {
        $r = $this->calcular($this->oro([
            'peso_gramos' => ' 1944.53 ',
            'cotizacion_usd_oz_t' => ' 2648.57 ',
            'aplica_caja_salud' => false,
        ]));

        $this->assertSame('1072018.84', $r['valor_bruto_bs']);
    }

    /**
     * deducciones es opcional, pero si viene tiene que ser una LISTA: un
     * string, un numero o un booleano son errores de serializacion, no una
     * deduccion de cero. (null no llega aqui: el ?? [] lo convierte en lista
     * vacia, que es justamente "sin deducciones".)
     */
    public function test_deducciones_sin_ser_lista_se_rechazan(): void
    {
        foreach (['10.00', 10.0, true] as $deducciones) {
            $e = $this->rechazado($this->oro([
                'aplica_caja_salud' => false,
                'deducciones' => $deducciones,
            ]));

            $this->assertSame('deducciones debe ser una lista de montos.', $e->getMessage());
        }
    }

    /**
     * GRAMOS_POR_ONZA_TROY en cero o negativo no es un 31.1035 extraño: es
     * una division por cero. Error de configuracion (ParametroInvalidoException),
     * igual que un parametro faltante.
     */
    public function test_el_divisor_en_cero_o_negativo_es_un_parametro_invalido(): void
    {
        foreach (['0', '-31.1035'] as $divisor) {
            $servicio = new CalculoLiquidacionService(
                $this->lector([
                    CalculoLiquidacionService::CLAVE_GRAMOS_POR_ONZA_TROY => $divisor,
                    CalculoLiquidacionService::CLAVE_ALICUOTA_APORTE => '0.018',
                ]),
            );

            try {
                $servicio->calcular($this->oro(['aplica_caja_salud' => false]));
                $this->fail("GRAMOS_POR_ONZA_TROY={$divisor} debio rechazarse");
            } catch (ParametroInvalidoException $e) {
                $this->assertStringContainsString('GRAMOS_POR_ONZA_TROY', $e->getMessage());
                $this->assertStringContainsString('fuera de rango', $e->getMessage());
            }
        }
    }

    /**
     * La alicuota es una FRACCION entre 0 y 1, excluidos los bordes (como la
     * ley): 0, 1 y 1.5 son imposibles y el corte >=1 atrapa un '1.8' que
     * multiplicaria el aporte cien veces.
     */
    public function test_la_alicuota_fuera_del_rango_fraccion_es_un_parametro_invalido(): void
    {
        foreach (['0', '1', '1.50', '-0.01'] as $alicuota) {
            $servicio = new CalculoLiquidacionService(
                $this->lector([
                    CalculoLiquidacionService::CLAVE_GRAMOS_POR_ONZA_TROY => '31.1035',
                    CalculoLiquidacionService::CLAVE_ALICUOTA_APORTE => $alicuota,
                ]),
            );

            try {
                $servicio->calcular($this->oro(['aplica_caja_salud' => true]));
                $this->fail("ALICUOTA_APORTE={$alicuota} debio rechazarse");
            } catch (ParametroInvalidoException $e) {
                $this->assertStringContainsString('ALICUOTA_APORTE', $e->getMessage());
            }
        }
    }

    /**
     * Los parametros tambien vienen de la tabla y pueden traer espacios; se
     * recortan igual que los insumos y el divisor que se persiste es el
     * recortado, no el crudo.
     */
    public function test_los_parametros_tambien_se_limpian_de_espacios(): void
    {
        $servicio = new CalculoLiquidacionService(
            $this->lector([
                CalculoLiquidacionService::CLAVE_GRAMOS_POR_ONZA_TROY => ' 31.1035',
                CalculoLiquidacionService::CLAVE_ALICUOTA_APORTE => '0.018 ',
            ]),
        );

        $r = $servicio->calcular($this->oro(['aplica_caja_salud' => false]));

        $this->assertSame('31.1035', $r['gramos_por_onza_troy']);
        $this->assertSame('1072018.84', $r['valor_bruto_bs']);
    }

    /**
     * Los insumos del caso ORO con las sobrescrituras del caso concreto.
     *
     * @param array<string, mixed> $sobrescritura
     * @return array<string, mixed>
     */
    private function oro(array $sobrescritura): array
    {
        return array_merge(self::ORO, $sobrescritura);
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, string>
     */
    private function calcular(array $datos): array
    {
        return (new CalculoLiquidacionService($this->lector()))->calcular($datos);
    }

    /**
     * @param array<string, mixed> $datos
     */
    private function rechazado(array $datos): CalculoInvalidoException
    {
        try {
            $this->calcular($datos);
        } catch (CalculoInvalidoException $e) {
            return $e;
        }

        return $this->fail('El servicio debio rechazar estos datos');
    }

    /**
     * Sin argumentos trae los dos parametros del ParametrosSeeder. Con
     * argumentos trae exactamente esos, para poder simular un parametro
     * faltante (se quita la clave, no se pone vacia).
     *
     * @param array<string, string>|null $parametros
     */
    private function lector(?array $parametros = null): LectorParametros
    {
        $parametros ??= [
            CalculoLiquidacionService::CLAVE_GRAMOS_POR_ONZA_TROY => '31.1035',
            CalculoLiquidacionService::CLAVE_ALICUOTA_APORTE => '0.018',
        ];

        return new class($parametros) implements LectorParametros
        {
            /** @param array<string, string> $valores */
            public function __construct(private readonly array $valores) {}

            public function valor(string $clave): ?string
            {
                return $this->valores[$clave] ?? null;
            }
        };
    }
}

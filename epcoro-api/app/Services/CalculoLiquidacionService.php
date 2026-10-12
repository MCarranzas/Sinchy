<?php

namespace App\Services;

use App\Contracts\LectorParametros;
use App\Exceptions\CalculoInvalidoException;
use App\Exceptions\ParametroInvalidoException;
use App\Support\Decimal;

/**
 * El corazon del sistema (seccion 4.3): la formula de hoja.xlsx en bcmath.
 *
 *     peso_fino = peso_gramos * ley_pagable
 *     usd_gramo = cotizacion_usd_oz_t / gramos_por_onza_troy
 *     bruto     = usd_gramo * tipo_cambio * peso_fino
 *     aporte    = aplica_caja_salud ? bruto_EXACTO * ALICUOTA_APORTE : 0
 *     neto      = bruto - aporte
 *
 * Tres reglas que este archivo tiene que seguir SIEMPRE, porque las tres son
 * bugs que casi entran (C1, C2 y D4):
 *
 *  1. UN solo redondeo, half-up a 2 decimales, y al FINAL, sobre los montos
 *     en bolivianos (bruto, aporte, total de deducciones y neto). Los pasos
 *     intermedios se calculan a escala 20. Redondear el valor_usd_gramo a 6
 *     decimales cuesta un centavo: 1072018.83 en vez de 1072018.84.
 *  2. El aporte se calcula sobre el bruto EXACTO (D4), jamas sobre el bruto
 *     ya redondeado. En el caso ORO los dos caminos coinciden, pero en un
 *     muestreo de 3000 brutos difieren en 15 casos, siempre por un centavo.
 *  3. El servicio no vuelve a leer sus propias columnas (C2): devuelve los
 *     insumos y los montos, y quien los persiste NUNCA los recalcula.
 *
 * `peso_fino_gramos` y `valor_usd_gramo` salen con precision completa, porque
 * quien los persiste los redondea la columna (numeric 14,4 y 12,6) y esos dos
 * valores -1808.8018 y 85.153439- son solo referencia de auditoria, no insumo
 * de ningun calculo posterior: multiplicarlos de nuevo da 1072018.83 y
 * descuadra contra el Excel (test ORO-C).
 *
 * No escribe la base de datos ni habla HTTP: recibe insumos y devuelve
 * numeros. Lo unico que toca BD es el lector de parametros, y llega por
 * inyeccion para que los tests unitarios no necesiten aplicacion levantada.
 */
final class CalculoLiquidacionService
{
    public const CLAVE_GRAMOS_POR_ONZA_TROY = 'GRAMOS_POR_ONZA_TROY';

    public const CLAVE_ALICUOTA_APORTE = 'ALICUOTA_APORTE';

    public function __construct(private readonly LectorParametros $parametros) {}

    /**
     * Calcula los seis campos derivados de una liquidacion.
     *
     * @param array<string, mixed> $datos
     *        peso_gramos, ley_pagable, tipo_cambio, cotizacion_usd_oz_t y
     *        aplica_caja_salud.
     * @return array<string, string>
     *        gramos_por_onza_troy (el divisor usado, para persistirlo como
     *        snapshot), peso_fino_gramos, valor_usd_gramo, valor_bruto_bs,
     *        aporte_caja_salud_bs, total_deducciones_bs y valor_neto_bs. Los
     *        montos llegan con 2 decimales, los intermedios con precision
     *        completa.
     *
     * @throws CalculoInvalidoException   insumo fuera de rango
     * @throws ParametroInvalidoException parametro faltante o basura
     */
    public function calcular(array $datos): array
    {
        $peso = $this->insumo($datos, 'peso_gramos');
        $ley = $this->insumo($datos, 'ley_pagable');
        $tipoCambio = $this->insumo($datos, 'tipo_cambio');
        $cotizacion = $this->insumo($datos, 'cotizacion_usd_oz_t');

        $aplicaCajaSalud = $datos['aplica_caja_salud'] ?? null;
        if (! is_bool($aplicaCajaSalud)) {
            throw CalculoInvalidoException::interruptorCajaSaludInvalido();
        }

        $gramosPorOnzaTroy = $this->parametro(self::CLAVE_GRAMOS_POR_ONZA_TROY);
        $alicuotaAporte = $this->parametro(self::CLAVE_ALICUOTA_APORTE);

        $this->validar($peso, $ley, $cotizacion, $tipoCambio, $gramosPorOnzaTroy, $alicuotaAporte);

        $escala = Decimal::ESCALA;

        $pesoFino = bcmul($peso, $ley, $escala);
        $valorUsdGramo = bcdiv($cotizacion, $gramosPorOnzaTroy, $escala);
        // (cotizacion / gramos) * tipo_cambio * peso_fino, exactamente la
        // formula de la celda F10 del Excel, sin redondeo intermedio.
        $brutoExacto = bcmul(bcmul($valorUsdGramo, $tipoCambio, $escala), $pesoFino, $escala);

        // D4: base del aporte = bruto sin redondear.
        $aporteExacto = $aplicaCajaSalud
            ? bcmul($brutoExacto, $alicuotaAporte, $escala)
            : '0';

        // El total de deducciones es solo el aporte: no hay descuentos
        // variables (decision del negocio).
        $totalDeduccionesExacto = $aporteExacto;
        $netoExacto = bcsub($brutoExacto, $totalDeduccionesExacto, $escala);

        return [
            'gramos_por_onza_troy' => $gramosPorOnzaTroy,
            'peso_fino_gramos' => Decimal::normalizar($pesoFino),
            'valor_usd_gramo' => Decimal::normalizar($valorUsdGramo),
            'valor_bruto_bs' => Decimal::halfUp($brutoExacto),
            'aporte_caja_salud_bs' => Decimal::halfUp($aporteExacto),
            'total_deducciones_bs' => Decimal::halfUp($totalDeduccionesExacto),
            'valor_neto_bs' => Decimal::halfUp($netoExacto),
        ];
    }

    /**
     * El insumo de `$campo`, ya convertido a decimal usable.
     *
     * @param array<string, mixed> $datos
     */
    private function insumo(array $datos, string $campo): string
    {
        $valor = $this->texto($datos[$campo] ?? null);

        if ($valor === null) {
            throw CalculoInvalidoException::campoNoNumerico($campo);
        }

        return $valor;
    }

    /**
     * Convierte un valor entrante en decimal usable con bcmath, o null si no
     * lo es. Int y float llegan asi desde JSON; el float se convierte con el
     * cast de PHP (14 digitos de precision, sobra para cualquier insumo
     * valido) y no con un formato fijo de decimales, que inventaria ceros.
     * La notacion cientifica se rechaza: bcmath no la entiende, y un '1e3'
     * dentro de un peso es un error de captura, no un numero.
     */
    private function texto(mixed $valor): ?string
    {
        if (is_float($valor)) {
            if (is_nan($valor) || is_infinite($valor)) {
                return null;
            }

            $valor = (string) $valor;
        }

        if (is_int($valor)) {
            return (string) $valor;
        }

        if (! is_string($valor)) {
            return null;
        }

        $valor = trim($valor);

        if ($valor === '' || ! is_numeric($valor) || preg_match('/e/i', $valor) === 1) {
            return null;
        }

        return $valor;
    }

    /**
     * Los dos parametros de la formula, ya comprobados como numeros usables.
     */
    private function parametro(string $clave): string
    {
        $valor = $this->parametros->valor($clave);

        if ($valor === null) {
            throw ParametroInvalidoException::faltante($clave);
        }

        $valor = trim($valor);

        if ($valor === '' || ! is_numeric($valor) || preg_match('/e/i', $valor) === 1) {
            throw ParametroInvalidoException::noNumerico($clave, $valor);
        }

        return $valor;
    }

    /**
     * Los cortes fisicos del calculo. El Form Request de la FASE 6.1 valida
     * lo mismo antes; aqui se repite porque este servicio es el que pone el
     * dinero en juego y no debe depender de que lo llamen por la via correcta.
     *
     * @throws CalculoInvalidoException
     * @throws ParametroInvalidoException
     */
    private function validar(
        string $peso,
        string $ley,
        string $cotizacion,
        string $tipoCambio,
        string $gramosPorOnzaTroy,
        string $alicuotaAporte,
    ): void {
        $escala = Decimal::ESCALA;

        if (bccomp($peso, '0', $escala) <= 0) {
            throw CalculoInvalidoException::pesoNoPositivo();
        }

        // fraccion, no porcentaje (trampa T1)
        if (bccomp($ley, '0', $escala) <= 0 || bccomp($ley, '1', $escala) > 0) {
            throw CalculoInvalidoException::leyFueraDeRango();
        }

        if (bccomp($cotizacion, '0', $escala) <= 0) {
            throw CalculoInvalidoException::cotizacionNoPositiva();
        }

        if (bccomp($tipoCambio, '0', $escala) <= 0) {
            throw CalculoInvalidoException::tipoCambioNoPositivo();
        }

        if (bccomp($gramosPorOnzaTroy, '0', $escala) <= 0) {
            throw ParametroInvalidoException::fueraDeRango(self::CLAVE_GRAMOS_POR_ONZA_TROY, $gramosPorOnzaTroy);
        }

        if (bccomp($alicuotaAporte, '0', $escala) <= 0 || bccomp($alicuotaAporte, '1', $escala) >= 0) {
            throw ParametroInvalidoException::fueraDeRango(self::CLAVE_ALICUOTA_APORTE, $alicuotaAporte);
        }
    }
}

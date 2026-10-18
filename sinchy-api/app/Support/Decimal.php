<?php

namespace App\Support;

/**
 * Aritmetica decimal sobre cadenas: el unico soporte numerico del sistema.
 *
 * Los montos viajan como string de principio a fin (numeric de PostgreSQL),
 * nunca como float. 1072018.84 no es representable en binario, y castearlo a
 * float es exactamente como se pierde el centavo que separa 1072018.84 de
 * 1072018.83 (correccion C1, regla T3).
 */
final class Decimal
{
    /** Decimales del calculo intermedio: 20, sin redondear en ningun paso. */
    public const ESCALA = 20;

    /** Los montos en bolivianos se redondean a 2 decimales. */
    public const MONTO = 2;

    /**
     * Redondeo half-up a N decimales (correccion C9).
     *
     * `bcadd($valor, '0', 2)` TRUNCA y devuelve 1072018.83 para
     * 1072018.8360554035, que es justamente el numero que no cuadra con el
     * Excel; `round((float) $valor, 2)` reintroduce la perdida de precision
     * binaria que los strings existen para evitar; `bcdiv` trunca con digito
     * de guarda (bcdiv('100.505','100',2) -> 1.00 en vez de 1.01).
     *
     * La forma correcta: desplazar, sumar la media unidad EN EL DOMINIO YA
     * DESPLAZADO (0.5 y no 0.005), truncar a escala entera con bcadd -que si
     * trunca de forma predecible- y recien ahi dividir, cuando la division ya
     * es exacta. Verificado contra los 17 casos limite de tests/Unit/DecimalTest.
     */
    public static function halfUp(string $valor, int $decimales = self::MONTO, int $escala = 30): string
    {
        $factor = bcpow('10', (string) $decimales, 0);

        return bcdiv(
            bcadd(bcmul($valor, $factor, $escala), '0.5', 0),
            $factor,
            $decimales,
        );
    }

    /**
     * Quita los ceros sobrantes a la derecha.
     *
     * El producto 1944.53 * 0.9302 calculado a escala 20 es
     * '1808.80180600000000000000'; lo que tiene que viajar es '1808.801806'.
     */
    public static function normalizar(string $valor): string
    {
        if (! str_contains($valor, '.')) {
            return $valor;
        }

        $limpio = rtrim(rtrim($valor, '0'), '.');

        return ($limpio === '' || $limpio === '-') ? '0' : $limpio;
    }
}

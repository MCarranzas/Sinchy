<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * parametros_generales no tiene un valor usable (seccion 5.2).
 *
 * Hereda de RuntimeException y NO de ReglaNegocioException a proposito: si
 * falta GRAMOS_POR_ONZA_TROY o ALICUOTA_APORTE no es el cliente el que se
 * equivoco, es la tabla de parametros. 500, y hay que arreglar los datos.
 */
class ParametroInvalidoException extends RuntimeException
{
    public static function faltante(string $clave): self
    {
        return new self("Falta el parametro {$clave} en parametros_generales.");
    }

    public static function noNumerico(string $clave, string $valor): self
    {
        return new self("El parametro {$clave} debe ser numerico, tiene: {$valor}.");
    }

    /** El divisor tiene que ser > 0 y la alicuota una fraccion entre 0 y 1. */
    public static function fueraDeRango(string $clave, string $valor): self
    {
        return new self("El parametro {$clave} tiene un valor fuera de rango: {$valor}.");
    }
}

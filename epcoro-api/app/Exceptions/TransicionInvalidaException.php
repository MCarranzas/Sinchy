<?php

namespace App\Exceptions;

use App\Enums\EstadoLiquidacion;

/**
 * Intento de salto que la maquina de estados no permite (seccion 5.5).
 *
 * La capa 1 (el enum) es la que decide; esta excepcion es solo el aviso. La
 * decision D2 cae aqui sola: `aprobado` no tiene salida, asi que cualquier
 * transicion desde aprobado -anulacion incluida- acaba en este exception y no
 * se escribe ni una fila de historial.
 */
class TransicionInvalidaException extends ReglaNegocioException
{
    public static function noPermitida(EstadoLiquidacion $origen, EstadoLiquidacion $destino): self
    {
        return new self(
            "Transicion no permitida: de \"{$origen->value}\" a \"{$destino->value}\"."
        );
    }
}

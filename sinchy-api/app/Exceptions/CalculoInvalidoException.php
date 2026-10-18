<?php

namespace App\Exceptions;

/**
 * Entrada invalida para el calculo de una liquidacion (seccion 5.4.5).
 *
 * Todas las condiciones se comprueban ANTES de tocar la base de datos, por lo
 * que el servicio puede rechazar la peticion aunque la capa de validacion del
 * Form Request (FASE 6.1) no cubra el campo: doble cinturon, y aqui no hay
 * forma de que un 93.02 entre como si fuera 0.9302.
 */
class CalculoInvalidoException extends ReglaNegocioException
{
    public static function campoNoNumerico(string $campo): self
    {
        return new self("El campo {$campo} debe ser un numero decimal (sin notacion cientifica).");
    }

    /** El Excel admite 0 gramos por restriccion de la BD: aqui se corta antes. */
    public static function pesoNoPositivo(): self
    {
        return new self('El peso en gramos debe ser mayor que cero.');
    }

    /**
     * La ley es FRACCION (trampa T1): 0.9302 vale, 93.02 no. El corte lte:1
     * es deliberado: 93.02 es un error de captura, no un valor posible.
     */
    public static function leyFueraDeRango(): self
    {
        return new self('La ley pagable debe ser mayor que cero y hasta 1: se envia la fraccion (0.9302), no el porcentaje (93.02).');
    }

    public static function cotizacionNoPositiva(): self
    {
        return new self('La cotizacion quincenal en USD por onza troy debe ser mayor que cero.');
    }

    public static function tipoCambioNoPositivo(): self
    {
        return new self('El tipo de cambio Bs/$ debe ser mayor que cero.');
    }

    public static function interruptorCajaSaludInvalido(): self
    {
        return new self('aplica_caja_salud debe ser booleano (true/false).');
    }
}

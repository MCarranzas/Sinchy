<?php

namespace App\Exceptions;

use App\Enums\EstadoLiquidacion;

/**
 * Intento de EDITAR un registro que el flujo ya cerro (4.1 + D2).
 *
 * No es lo mismo que una transicion invalida, pero cae en la misma
 * respuesta: 422. La razon del corte es el ESTADO, no el permiso, y eso es
 * lo que el cliente necesita saber para pintar su pantalla (mostrar el
 * expediente como solo lectura en vez de decirle "403 prohibido").
 *
 * Se devuelve 422 y no 403 deliberadamente, como dice el docblock de
 * LiquidacionPolicy: 403 seria para "este usuario no puede editar", que es
 * la Policy; aqui el usuario podria editar, pero este registro ya no.
 */
class RegistroNoEditableException extends ReglaNegocioException
{
    public static function porEstado(EstadoLiquidacion $estado): self
    {
        return new self(
            "La liquidacion esta en estado \"{$estado->value}\" y solo se puede editar en \"creado\" o \"en_correccion\"."
        );
    }

    /** D2: una aprobada es inmutable para todos, el administrador incluido. */
    public static function aprobada(): self
    {
        return new self('La liquidacion aprobada es inmutable: no admite edicion, borrado ni anulacion.');
    }
}

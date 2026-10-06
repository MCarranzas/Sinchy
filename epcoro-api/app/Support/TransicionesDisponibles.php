<?php

namespace App\Support;

use App\Enums\EstadoLiquidacion;
use App\Models\Liquidacion;
use App\Models\User;

/**
 * Puente entre la maquina de estados (QUE se puede hacer) y la Policy
 * (QUIEN puede hacerlo) para una liquidacion concreta (6.4).
 *
 * Dos usos con la misma fuente de verdad:
 *
 *  - el Resource la expone en `transiciones_disponibles` para que el
 *    frontend solo dibuje los botones validos;
 *  - el controlador de transiciones traduce el (origen -> destino) que
 *    llega en el POST a la habilidad de la Policy antes de escribir.
 *
 * De ahi la distincion que el enum no puede ver por si solo: hacia
 * `rechazado` hay DOS caminos con permisos distintos, `solicitar_correccion`
 * desde la revision y `rechazar` desde la aprobacion (4.2). Depende del
 * origen, y por eso el mapa esta aqui y no en el enum.
 */
final class TransicionesDisponibles
{
    /**
     * Habilidad de la Policy para mover una liquidacion de $origen a $destino.
     *
     * null cuando no hay habilidad asociada (los tres estados terminales no
     * se alcanzan por este camino, o el destino no existe).
     */
    public static function habilidad(EstadoLiquidacion $origen, EstadoLiquidacion $destino): ?string
    {
        return match ($destino) {
            EstadoLiquidacion::EnRevision => 'enviarRevision',
            EstadoLiquidacion::Revisado => 'revisar',
            EstadoLiquidacion::Rechazado => $origen === EstadoLiquidacion::EnRevision
                ? 'solicitarCorreccion'
                : 'rechazar',
            EstadoLiquidacion::Aprobado => 'aprobar',
            EstadoLiquidacion::Anulado => 'anular',
            EstadoLiquidacion::EnCorreccion => 'corregir',
            EstadoLiquidacion::Eliminado => 'delete',
            default => null,
        };
    }

    /**
     * Destinos a los que ESTE usuario puede pasar esta liquidacion AHORA.
     *
     * Primero el enum (¿es posible el salto?), despues la Policy (¿le
     * corresponde a este usuario?). Una sin la otra no alcanza: el gerente
     * no puede aprobar algo en `creado` y el tecnico no puede aprobar nada,
     * aunque su liquidacion este `revisado`.
     *
     * @return array<string>
     */
    public static function paraUsuario(User $usuario, Liquidacion $liquidacion): array
    {
        $origen = $liquidacion->estado;
        $destinos = [];

        foreach ($origen->transiciones() as $destino) {
            $habilidad = self::habilidad($origen, $destino);

            if ($habilidad === null) {
                continue;
            }

            if ($usuario->can($habilidad, $liquidacion)) {
                $destinos[] = $destino->value;
            }
        }

        return $destinos;
    }
}

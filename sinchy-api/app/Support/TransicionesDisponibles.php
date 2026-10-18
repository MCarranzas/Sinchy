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
 * De ahi la distincion que el enum no puede ver por si solo: dos destinos
 * (decision del negocio 2026-10-12) tienen un camino de DEVOLUCION distinto
 * del camino normal, y el destino a secas no basta para elegir la habilidad:
 *
 *  - `en_correccion` solo se alcanza desde `en_revision` con la habilidad
 *    `devolverTecnico`: el revisor devuelve al tecnico para que corrija.
 *  - `en_revision` se alcanza desde `creado`/`en_correccion` con
 *    `enviarRevision` (el autor reenvia) y desde `revisado` con
 *    `devolverRevisor`: el gerente devuelve al revisor para que re-revise.
 *
 * Hacia `rechazado` hay UN solo camino con permiso `rechazar`, valido desde
 * `en_revision` y desde `revisado`, y es terminal: ni correccion ni
 * reactivacion (revisor y gerente comparten `rechazar`).
 */
final class TransicionesDisponibles
{
    /**
     * Habilidad de la Policy para mover una liquidacion de $origen a $destino.
     *
     * null cuando no hay habilidad asociada (los estados terminales no se
     * alcanzan por este camino, o el destino no existe).
     */
    public static function habilidad(EstadoLiquidacion $origen, EstadoLiquidacion $destino): ?string
    {
        return match (true) {
            // Devolver del gerente al revisor: `revisado` -> `en_revision`.
            $destino === EstadoLiquidacion::EnRevision && $origen === EstadoLiquidacion::Revisado => 'devolverRevisor',
            // Devolver del revisor al tecnico: `en_revision` -> `en_correccion`.
            $destino === EstadoLiquidacion::EnCorreccion => 'devolverTecnico',
            // Reenvio del autor: `creado`/`en_correccion` -> `en_revision`.
            $destino === EstadoLiquidacion::EnRevision => 'enviarRevision',
            $destino === EstadoLiquidacion::Revisado => 'revisar',
            $destino === EstadoLiquidacion::Rechazado => 'rechazar',
            $destino === EstadoLiquidacion::Aprobado => 'aprobar',
            $destino === EstadoLiquidacion::Anulado => 'anular',
            $destino === EstadoLiquidacion::Eliminado => 'delete',
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

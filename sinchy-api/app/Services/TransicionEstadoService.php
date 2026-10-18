<?php

namespace App\Services;

use App\Enums\EstadoLiquidacion;
use App\Exceptions\TransicionInvalidaException;
use App\Models\Liquidacion;
use App\Models\LiquidacionEstadoHistorial;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Maquina de estados + bitacora (secciones 5.5 y 3.5).
 *
 * Toda transicion pasa por aqui: `$liquidacion->update(['estado' => ...])` esta
 * prohibido. Aqui se hacen tres cosas, en este orden:
 *
 *  1. Validar el salto contra el enum (capa 1). D2 cae gratis: `aprobado` no
 *     tiene salida, asi que cualquier intento -anulacion incluida- acaba en
 *     TransicionInvalidaException y no se escribe ni una fila.
 *  2. Releer el registro con FOR UPDATE dentro de la transaccion, para que dos
 *     peticiones simultaneas no jueguen al mus con el mismo estado.
 *  3. Escribir la fila de historial en la MISMA transaccion: si falla la
 *     bitacora, la transicion tampoco se aplica.
 *
 * NO valida permisos: eso es la Policy (capa 4). Aqui solo se pregunta si la
 * transicion es posible, no si este usuario puede hacerla; el controlador
 * consulta las dos, porque una sin la otra no alcanza.
 *
 * El motivo del rechazo/anulacion lo exige el Form Request de la FASE 6, no
 * este servicio: la razon por la que se rechaza vive en la capa que habla con
 * el cliente, y aqui solo se persiste lo que llega.
 */
final class TransicionEstadoService
{
    /**
     * Aplica una transicion y deja el rastro en el historial.
     *
     * @throws TransicionInvalidaException si la maquina de estados no permite el salto
     */
    public function ejecutar(
        Liquidacion $liquidacion,
        EstadoLiquidacion $destino,
        User $usuario,
        ?string $comentario = null,
        ?string $motivo = null,
    ): Liquidacion {
        return DB::transaction(function () use (
            $liquidacion,
            $destino,
            $usuario,
            $comentario,
            $motivo,
        ): Liquidacion {
            /** @var Liquidacion $actual */
            $actual = Liquidacion::query()
                ->lockForUpdate()
                ->findOrFail($liquidacion->getKey());

            $origen = $actual->estado;

            if (! $origen->puedeTransicionA($destino)) {
                throw TransicionInvalidaException::noPermitida($origen, $destino);
            }

            $actual->forceFill(array_merge(
                ['estado' => $destino],
                $this->columnasDe($destino, $usuario, $motivo, $origen),
            ))->save();

            $this->historial($actual, $origen, $destino, $usuario, $comentario);

            return $actual;
        });
    }

    /**
     * Fila inicial del historial: NULL -> creado (seccion 3.5).
     *
     * La escribe el controlador dentro de la transaccion que crea la
     * liquidacion. Sin ella la bitacora arranca a medias y no se puede
     * reconstruir quien abrio el expediente.
     */
    public function registrarInicio(
        Liquidacion $liquidacion,
        User $usuario,
        ?string $comentario = null,
    ): void {
        DB::transaction(function () use ($liquidacion, $usuario, $comentario): void {
            $this->historial($liquidacion, null, $liquidacion->estado, $usuario, $comentario);
        });
    }

    /**
     * Columnas de auditoria que acompanan a cada destino.
     *
     * Solo cuatro estados tienen par de fecha/usuario en el esquema; `revisado`
     * y `en_correccion` dejan rastro unicamente en el historial.
     *
     * @return array<string, mixed>
     */
    private function columnasDe(EstadoLiquidacion $destino, User $usuario, ?string $motivo, ?EstadoLiquidacion $origen = null): array
    {
        return match ($destino) {
            EstadoLiquidacion::EnRevision => match ($origen) {
                // Devolver del gerente al revisor: NO pisa fecha_revision
                EstadoLiquidacion::Revisado => [],
                default => [
                    'fecha_revision' => now(),
                    'usuario_revision_id' => $usuario->id,
                ],
            },
            EstadoLiquidacion::Aprobado => [
                'fecha_aprobacion' => now(),
                'usuario_aprobacion_id' => $usuario->id,
            ],
            EstadoLiquidacion::Rechazado => [
                'fecha_rechazo' => now(),
                'usuario_rechazo_id' => $usuario->id,
                'motivo_rechazo' => $motivo,
            ],
            EstadoLiquidacion::Anulado => [
                'fecha_anulacion' => now(),
                'usuario_anulacion_id' => $usuario->id,
                'motivo_anulacion' => $motivo,
            ],
            default => [],
        };
    }

    private function historial(
        Liquidacion $liquidacion,
        ?EstadoLiquidacion $anterior,
        EstadoLiquidacion $nuevo,
        User $usuario,
        ?string $comentario,
    ): void {
        LiquidacionEstadoHistorial::query()->create([
            'liquidacion_id' => $liquidacion->id,
            'estado_anterior' => $anterior,
            'estado_nuevo' => $nuevo,
            'usuario_id' => $usuario->id,
            'usuario_nombre' => $usuario->nombre_completo,
            'comentario' => $comentario,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\EstadoLiquidacion;
use App\Exceptions\TransicionInvalidaException;
use App\Http\Requests\TransicionRequest;
use App\Http\Resources\LiquidacionResource;
use App\Models\Liquidacion;
use App\Services\TransicionEstadoService;
use App\Support\Auditoria;
use App\Support\TransicionesDisponibles;
use Illuminate\Http\JsonResponse;

/**
 * POST /liquidaciones/{id}/transiciones (seccion 5.5, FASE 6.7).
 *
 * El orden de las tres comprobaciones es la mitad del contrato:
 *
 *  1. EL ESTADO -> 422. Si el salto no existe, la respuesta dice que la
 *     maquina de estados no lo permite, que es la verdad. D2 cae aqui solo:
 *     `aprobado` no tiene salida, asi que anular una aprobada responde 422
 *     sin mirar siquiera quien lo pide.
 *  2. EL PERMISO -> 403, con la habilidad que toque para ese (origen,
 *     destino) concreto. Recien ahi sabemos que es posible y que le
 *     corresponde al usuario.
 *  3. LA ESCRITURA, que va dentro de la transaccion del servicio, con el
 *     lockForUpdate y el historial en la misma transaccion.
 *
 * Si se invirtiera 1 y 2, un gerente que intenta anular algo aprobado
 * recibira 403 ("no tienes permiso") en vez del 422 que corresponde, y el
 * frontend pintaria un error de seguridad para lo que es una regla de
 * negocio.
 */
class TransicionController extends Controller
{
    public function __construct(private readonly TransicionEstadoService $transiciones) {}

    public function store(TransicionRequest $request, Liquidacion $liquidacion): JsonResponse
    {
        $datos = $request->validated();
        $origen = $liquidacion->estado;
        $destino = EstadoLiquidacion::from($datos['estado_destino']);

        if (! $origen->puedeTransicionA($destino)) {
            throw TransicionInvalidaException::noPermitida($origen, $destino);
        }

        $habilidad = TransicionesDisponibles::habilidad($origen, $destino);

        if ($habilidad === null) {
            throw TransicionInvalidaException::noPermitida($origen, $destino);
        }

        $this->authorize($habilidad, $liquidacion);

        $liquidacion = $this->transiciones->ejecutar(
            $liquidacion,
            $destino,
            $request->user(),
            $datos['comentario'] ?? null,
            $datos['motivo'] ?? null,
        );

        Auditoria::registrar(Auditoria::TRANSICION, [
            'liquidacion_id' => $liquidacion->id,
            'numero_documento' => $liquidacion->numero_documento,
            'estado_anterior' => $origen->value,
            'estado_nuevo' => $destino->value,
        ]);

        return response()->json([
            'message' => 'Transicion aplicada: '.$origen->value.' -> '.$destino->value.'.',
            'data' => new LiquidacionResource(
                $liquidacion->load(['registro:id,nombre_completo,username'])
            ),
        ]);
    }
}

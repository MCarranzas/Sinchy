<?php

namespace App\Http\Controllers;

use App\Enums\EstadoLiquidacion;
use App\Models\Liquidacion;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mi bandeja (FASE 9.5): los tres conteos que abren la pantalla de trabajo.
 *
 * No es un reporte: es "que me toca ahora". Por eso los tres cortes son
 * distintos y ninguno se calcula con la misma regla:
 *
 *  - `mis_liquidaciones` lo decide la PROPIEDAD: lo que registre yo, sea
 *    cual sea su estado. El corte D3 (ver_todas / ver_propias) no aplica
 *    porque aqui la pregunta ya es "mias".
 *  - `por_revisar` lo decide el ESTADO (en_revision, la cola de revision)
 *    y solo se cuenta para quien puede revisar o pedir correccion: a un
 *    tecnico esa cola no le dice nada y no debe aparecerle.
 *  - `por_aprobar` es la cola siguiente (revisado) y solo se cuenta para
 *    quien tiene `aprobar`, que es el unico rol que puede cerrarla.
 *
 * El conteo respeta lo mismo que la Policy en detalle: quien solo ve lo suyo
 * no contara aqui ajenas, porque el unico estado al que aplica `ver_todas`
 * es el de las dos colas, y esas colas solo se abren para quien ademas
 * tiene el permiso de actuar sobre ellas.
 */
class BandejaController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Liquidacion::class);

        /** @var User $usuario */
        $usuario = $request->user();

        return response()->json([
            'data' => [
                'mis_liquidaciones' => Liquidacion::query()
                    ->where('usuario_registro_id', $usuario->id)
                    ->count(),
                'por_revisar' => $this->cola(
                    $usuario,
                    EstadoLiquidacion::EnRevision,
                    ['liquidaciones.revisar', 'liquidaciones.solicitar_correccion'],
                ),
                'por_aprobar' => $this->cola(
                    $usuario,
                    EstadoLiquidacion::Revisado,
                    ['liquidaciones.aprobar'],
                ),
            ],
        ]);
    }

    /**
     * Tamaño de una cola de revision, solo para quien puede actuar en ella.
     *
     * @param  list<string>  $permisos
     */
    private function cola(User $usuario, EstadoLiquidacion $estado, array $permisos): int
    {
        foreach ($permisos as $permiso) {
            if ($usuario->can($permiso)) {
                return Liquidacion::query()->where('estado', $estado)->count();
            }
        }

        return 0;
    }
}

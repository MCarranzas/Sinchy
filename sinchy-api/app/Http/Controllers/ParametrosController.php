<?php

namespace App\Http\Controllers;

use App\Http\Requests\ParametrosRequest;
use App\Models\ParametroGeneral;
use App\Support\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * GET/PUT /parametros (secciones 3.3 y 6.6).
 *
 * Lectura para todos los usuarios autenticados: el formulario muestra el
 * tipo de cambio por defecto y la cotizacion sugerida. Escritura solo para
 * `parametros.gestionar` (admin), decidido en ParametroGeneralPolicy, no en
 * el controller ni en el Form Request: la autorizacion vive en la policy.
 *
 * El cambio se hace de uno en uno y con updateOrCreate sobre la clave, que
 * es la primary key: el cliente manda un MAPA {clave: valor} y las claves
 * desconocidas se rechazan alla (ParametrosRequest), no aqui.
 *
 * El cambio queda en el canal `auditoria` (FASE 11.5): se anota quien toco
 * que claves, cuando y desde que IP. No es todavia una tabla consultable, que
 * era el alcance grande de R3, sino la traza de auditoria que pide la seccion
 * 11.5; si el negocio necesita consultar el historico desde el sistema, se
 * promueve a tabla en una fase posterior.
 */
class ParametrosController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', ParametroGeneral::class);

        return response()->json([
            'data' => ParametroGeneral::query()
                ->orderBy('clave')
                ->get()
                ->map(fn (ParametroGeneral $parametro): array => [
                    'clave' => $parametro->clave,
                    'valor' => $parametro->valor,
                    'tipo' => $parametro->tipo,
                    'descripcion' => $parametro->descripcion,
                    'actualizado_en' => $parametro->updated_at?->toIso8601String(),
                ]),
        ]);
    }

    public function update(ParametrosRequest $request): JsonResponse
    {
        $this->authorize('update', ParametroGeneral::class);
        /** @var array<string, string> $parametros */
        $parametros = $request->validated('parametros');

        $actualizados = DB::transaction(function () use ($parametros): array {
            foreach ($parametros as $clave => $valor) {
                ParametroGeneral::query()->updateOrCreate(
                    ['clave' => $clave],
                    ['valor' => $valor],
                );
            }

            return $parametros;
        });

        Auditoria::registrar(Auditoria::PARAMETROS, [
            'claves' => array_keys($actualizados),
        ]);

        return response()->json([
            'message' => 'Parametros actualizados correctamente.',
            'data' => collect($actualizados)
                ->map(fn (string $valor, string $clave): array => ['clave' => $clave, 'valor' => $valor])
                ->values(),
        ]);
    }
}

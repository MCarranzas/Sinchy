<?php

namespace App\Http\Controllers;

use App\Http\Requests\ParametrosRequest;
use App\Models\ParametroGeneral;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * GET/PUT /parametros (secciones 3.3 y 6.6).
 *
 * Lectura para todos los usuarios autenticados: el formulario muestra el
 * tipo de cambio por defecto y la cotizacion sugerida. Escritura solo para
 * `parametros.gestionar` (admin), decidido en el Form Request.
 *
 * El cambio se hace de uno en uno y con updateOrCreate sobre la clave, que
 * es la primary key: el cliente manda un MAPA {clave: valor} y las claves
 * desconocidas se rechazan alla (ParametrosRequest), no aqui.
 *
 * Falta la bitacora de cambios que pide R3 (quien cambio que y cuando).
 * No existe la tabla en el esquema de la FASE 4 y meterla ahora obligaria a
 * decidir el formato del historial sin el usuario de negocio al lado; queda
 * anotado como pendiente para la FASE 11, donde esta la auditoria.
 */
class ParametrosController extends Controller
{
    public function index(): JsonResponse
    {
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

        return response()->json([
            'message' => 'Parametros actualizados correctamente.',
            'data' => collect($actualizados)
                ->map(fn (string $valor, string $clave): array => ['clave' => $clave, 'valor' => $valor])
                ->values(),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\EstadoLiquidacion;
use App\Exports\LiquidacionesExport;
use App\Http\Resources\LiquidacionResource;
use App\Models\Liquidacion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reportes (seccion 4.6 + FASE 6.8). Todo bajo `reportes.ver`.
 *
 * Los totales se hacen en SQL, con COALESCE para que un periodo vacio
 * responda ceros y no null, y salen como CADENA: sumar numeric en
 * PostgreSQL devuelve numeric, y pasarlo por float aqui seria el unico
 * lugar del sistema donde se pierde un centavo en el camino de la consulta
 * al navegador (T3 y C1 dicen que los montos viajen como estan).
 *
 * Se excluye solo `eliminado`, que es el borrado logico y no es un
 * expediente real. `anulado` y `rechazado` SE CUENTAN: el agrupado por
 * estado los muestra aparte, y si el negocio quiere sacarlos del total
 * economico eso es una decision que se toma en la consulta, no escondida
 * en el codigo.
 *
 * El rango se recibe como `desde`/`hasta` (la forma que trae el plan) o
 * como `fecha_desde`/`fecha_hasta` (la misma que el listado): los dos
 * nombres se aceptan y se normalizan en rango().
 */
class ReporteController extends Controller
{
    public function resumen(Request $request): JsonResponse
    {
        $this->puedeVerReportes($request);

        $this->rango($request);

        $totales = $this->baseTabla($request)
            ->selectRaw('
                COUNT(*) AS n,
                COALESCE(SUM(peso_gramos), 0) AS peso_total,
                COALESCE(SUM(peso_fino_gramos), 0) AS peso_fino_total,
                COALESCE(SUM(valor_bruto_bs), 0) AS bruto_total,
                COALESCE(SUM(aporte_caja_salud_bs), 0) AS aportes_total,
                COALESCE(SUM(total_deducciones_bs), 0) AS deducciones_total,
                COALESCE(SUM(valor_neto_bs), 0) AS neto_total
            ')
            ->first();

        return response()->json([
            'data' => [
                'rango' => [
                    'desde' => $request->input('fecha_desde'),
                    'hasta' => $request->input('fecha_hasta'),
                ],
                'totales' => [
                    'liquidaciones' => (int) $totales->n,
                    'peso_total' => (string) $totales->peso_total,
                    'peso_fino_total' => (string) $totales->peso_fino_total,
                    'bruto_total' => (string) $totales->bruto_total,
                    'aportes_total' => (string) $totales->aportes_total,
                    'deducciones_total' => (string) $totales->deducciones_total,
                    'neto_total' => (string) $totales->neto_total,
                ],
                'por_estado' => $this->agrupado($request, 'estado'),
                'por_municipio' => $this->agrupado($request, 'municipio_nombre'),
                'por_mineral' => $this->agrupado($request, 'presentacion_nombre'),
            ],
        ]);
    }

    /**
     * Detalle de las liquidaciones del periodo, paginado: es lo que permite
     * comprobar de que se compone el total del resumen.
     */
    public function liquidaciones(Request $request): JsonResponse
    {
        $this->puedeVerReportes($request);

        $this->rango($request);

        $consulta = Liquidacion::query()
            ->with(['registro:id,nombre_completo,username', 'deducciones'])
            ->where('estado', '!=', EstadoLiquidacion::Eliminado->value);

        if ($request->filled('estado')) {
            $consulta->where('estado', $request->input('estado'));
        }

        $consulta->when($request->filled('fecha_desde'), fn ($q) => $q->where('fecha_transaccion', '>=', $request->input('fecha_desde')))
            ->when($request->filled('fecha_hasta'), fn ($q) => $q->where('fecha_transaccion', '<=', $request->input('fecha_hasta')))
            ->orderBy('fecha_transaccion')
            ->orderBy('id');

        $porPagina = min(200, max(1, (int) $request->integer('por_pagina', 50)));
        $paginator = $consulta->paginate($porPagina, ['*'], 'page', max(1, (int) $request->integer('pagina', 1)));

        return response()->json([
            'data' => LiquidacionResource::collection($paginator->items()),
            'links' => $paginator->linkCollection(),
            'meta' => collect($paginator->toArray())->except(['data', 'links'])->all(),
        ]);
    }

    /**
     * Exportacion del reporte (6.8): el mismo periodo del resumen, en Excel
     * o en PDF.
     */
    public function exportar(Request $request): Response
    {
        $this->puedeVerReportes($request);

        $this->rango($request);

        $formato = (string) $request->input('formato', 'xlsx');

        if (! in_array($formato, ['xlsx', 'pdf'], true)) {
            throw ValidationException::withMessages([
                'formato' => 'Formato invalido: use xlsx o pdf.',
            ]);
        }

        $desde = $request->input('fecha_desde');
        $hasta = $request->input('fecha_hasta');

        $liquidaciones = $this->base($request)->orderBy('fecha_transaccion')->orderBy('id')->get();

        $archivo = 'reporte-liquidaciones-'.($desde ?? 'inicio').'-'.($hasta ?? 'hoy');

        if ($formato === 'xlsx') {
            return Excel::download(new LiquidacionesExport($liquidaciones), $archivo.'.xlsx');
        }

        return Pdf::setPaper('a4', 'landscape')
            ->loadView('liquidaciones.reporte-pdf', [
                'liquidaciones' => $liquidaciones,
                'desde' => $desde,
                'hasta' => $hasta,
            ])
            ->download($archivo.'.pdf');
    }

    // ------------------------------------------------------------- utiles

    /**
     * El grupo, su etiqueta y las filas. Una sola estructura por llamada.
     *
     * @return array{grupo: string, filas: array<int, array<string, mixed>>}
     */
    private function agrupado(Request $request, string $columna): array
    {
        $filas = $this->baseTabla($request)
            ->groupBy($columna)
            ->selectRaw("
                {$columna} AS grupo,
                COUNT(*) AS n,
                COALESCE(SUM(valor_bruto_bs), 0) AS bruto_total,
                COALESCE(SUM(valor_neto_bs), 0) AS neto_total
            ")
            ->orderBy('grupo')
            ->get()
            ->map(fn ($fila): array => [
                'valor' => $fila->grupo,
                'liquidaciones' => (int) $fila->n,
                'bruto_total' => (string) $fila->bruto_total,
                'neto_total' => (string) $fila->neto_total,
            ])
            ->all();

        $etiquetas = [
            'estado' => 'estado',
            'municipio_nombre' => 'municipio',
            'presentacion_nombre' => 'presentacion',
        ];

        return [
            'grupo' => $etiquetas[$columna] ?? $columna,
            'filas' => $filas,
        ];
    }

    /**
     * La misma base para totales, agrupados, detalle y exportacion: si cada
     * vista filtrara por su cuenta, el total y el PDF dejan de coincidir.
     *
     * @return Builder<Liquidacion>
     */
    private function base(Request $request): Builder
    {
        return Liquidacion::query()
            ->where('estado', '!=', EstadoLiquidacion::Eliminado->value)
            ->when($request->filled('fecha_desde'), fn ($q) => $q->where('fecha_transaccion', '>=', $request->input('fecha_desde')))
            ->when($request->filled('fecha_hasta'), fn ($q) => $q->where('fecha_transaccion', '<=', $request->input('fecha_hasta')));
    }

    /**
     * La misma base pero sin Eloquent, para las SUMARIZACIONES: los totales
     * y los agrupados solo tocan columnas y no necesitan hidratar modelos.
     * El groupBy sobre la columna de una vista de agregacion es SQL puro.
     */
    private function baseTabla(Request $request): \Illuminate\Database\Query\Builder
    {
        return DB::table('liquidaciones')
            ->where('estado', '!=', EstadoLiquidacion::Eliminado->value)
            ->when($request->filled('fecha_desde'), fn ($q) => $q->where('fecha_transaccion', '>=', $request->input('fecha_desde')))
            ->when($request->filled('fecha_hasta'), fn ($q) => $q->where('fecha_transaccion', '<=', $request->input('fecha_hasta')));
    }

    private function puedeVerReportes(Request $request): void
    {
        Gate::forUser($request->user())->authorize('reportes.ver');
    }

    /**
     * Normaliza y valida el rango de fechas (desde/hasta o fecha_*).
     */
    private function rango(Request $request): void
    {
        $equivalencias = ['desde' => 'fecha_desde', 'hasta' => 'fecha_hasta'];

        foreach ($equivalencias as $entrada => $normalizada) {
            if ($request->filled($entrada) && ! $request->filled($normalizada)) {
                $request->merge([$normalizada => $request->input($entrada)]);
            }
        }

        $errores = [];

        foreach (['fecha_desde', 'fecha_hasta'] as $campo) {
            $valor = $request->input($campo);

            if ($valor !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $valor) !== 1) {
                $errores[$campo] = 'La fecha debe ir en formato YYYY-MM-DD.';
            }
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }

        if ($request->filled('fecha_desde')
            && $request->filled('fecha_hasta')
            && $request->input('fecha_desde') > $request->input('fecha_hasta')) {
            throw ValidationException::withMessages([
                'fecha_hasta' => 'La fecha hasta no puede ser anterior a la fecha desde.',
            ]);
        }
    }
}

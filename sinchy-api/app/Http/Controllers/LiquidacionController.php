<?php

namespace App\Http\Controllers;

use App\Enums\EstadoLiquidacion;
use App\Exceptions\RegistroNoEditableException;
use App\Exceptions\TransicionInvalidaException;
use App\Exports\LiquidacionExport;
use App\Http\Requests\ObservacionRequest;
use App\Http\Requests\StoreLiquidacionRequest;
use App\Http\Requests\UpdateLiquidacionRequest;
use App\Http\Resources\LiquidacionObservacionResource;
use App\Http\Resources\LiquidacionResource;
use App\Models\AreaMinera;
use App\Models\Comprador;
use App\Models\Liquidacion;
use App\Models\LiquidacionEstadoHistorial;
use App\Models\Persona;
use App\Models\PresentacionMineral;
use App\Models\TipoCambio;
use App\Models\User;
use App\Services\CalculoLiquidacionService;
use App\Services\NumeradorDocumento;
use App\Services\TransicionEstadoService;
use App\Support\Auditoria;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD de liquidaciones (FASE 6.2 y 6.3).
 *
 * El orden de cada operacion no es casual:
 *
 *   1. EL ESTADO PRIMERO. Si el registro no admite lo que se pide (D2: una
 *      aprobada es inmutable, una en_revision no se edita), se responde 422
 *      ANTES de mirar permisos. El motivo del corte es el estado del
 *      expediente, y ese es el dato que el frontend necesita para pintarse
 *      como solo lectura; un 403 le diria "no tienes permiso", que seria
 *      mentir.
 *   2. DESPUES EL PERMISO (403), con la Policy.
 *   3. AL FINAL, la escritura, dentro de una transaccion unica que junta
 *      numeracion + calculo + fila + bitacora (6.3). Si algo falla en el
 *      medio no queda ni el correlativo consumido ni la mitad de los datos.
 *
 * Los montos salen del servicio y se persisten tal cual (T3): este
 * controlador no vuelve a multiplicar nada, ni hoy ni nunca.
 */
class LiquidacionController extends Controller
{
    public function __construct(
        private readonly CalculoLiquidacionService $calculadora,
        private readonly NumeradorDocumento $numerador,
        private readonly TransicionEstadoService $transiciones,
    ) {}

    // ------------------------------------------------------------ lectura

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Liquidacion::class);

        /** @var User $usuario */
        $usuario = $request->user();

        $consulta = Liquidacion::query()
            ->with(['registro:id,nombre_completo,username']);

        // D3: quien no tiene ver_todas solo ve lo suyo. Va aqui y no en la
        // vista porque es un corte de datos, no de presentacion.
        if (! $usuario->can('liquidaciones.ver_todas')) {
            $consulta->where('usuario_registro_id', $usuario->id);
        }

        $this->filtrar($consulta, $request);

        // `page` es el nombre con el que el paginador GENERA los links
        // (?page=2), asi que es con el que hay que LEERLO: con `pagina` el
        // navegador pedia la pagina 2 y el backend devolvia siempre la 1.
        // Se conserva `pagina` como alias por compatibilidad con api.md.
        $pagina = max(1, (int) $request->integer('page', $request->integer('pagina', 1)));
        $porPagina = min(100, max(1, (int) $request->integer('por_pagina', 15)));

        $paginator = $consulta->paginate($porPagina, ['*'], 'page', $pagina);

        return response()->json([
            'data' => LiquidacionResource::collection($paginator->items()),
            'links' => $paginator->linkCollection(),
            'meta' => collect($paginator->toArray())
                ->except(['data', 'links'])
                ->all(),
        ]);
    }

    public function show(Liquidacion $liquidacion): JsonResponse
    {
        $this->authorize('view', $liquidacion);

        $liquidacion->load(['registro:id,nombre_completo,username']);

        return response()->json([
            'data' => new LiquidacionResource($liquidacion),
        ]);
    }

    /**
     * Descarga del documento oficial y de la planilla en Excel (6.5):
     * los dos se generan de lo que ya esta persistido, el PDF no recalcula
     * nada y el Excel tampoco (T3). El permiso es el mismo que verla.
     *
     * La hoja del Excel es una copia fiel del documento, no una tabla
     * resumida: es lo que el comprador archiva con su entrada.
     */
    public function pdf(Liquidacion $liquidacion): Response
    {
        $this->authorize('view', $liquidacion);

        Auditoria::registrar(Auditoria::EXPORTACION, [
            'formato' => 'pdf',
            'liquidacion_id' => $liquidacion->id,
            'numero_documento' => $liquidacion->numero_documento,
        ]);

        return Pdf::setPaper('a4', 'portrait')
            ->loadView('liquidaciones.pdf', [
                'liquidacion' => $this->cargada($liquidacion),
            ])
            ->download($liquidacion->numero_documento.'.pdf');
    }

    public function excel(Liquidacion $liquidacion): Response
    {
        $this->authorize('view', $liquidacion);

        Auditoria::registrar(Auditoria::EXPORTACION, [
            'formato' => 'xlsx',
            'liquidacion_id' => $liquidacion->id,
            'numero_documento' => $liquidacion->numero_documento,
        ]);

        return Excel::download(
            new LiquidacionExport($liquidacion),
            $liquidacion->numero_documento.'.xlsx',
        );
    }

    // ----------------------------------------------------------- escritura

    public function store(StoreLiquidacionRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $usuario = $request->user();

        // El tipo de cambio ya no lo manda el formulario: se toma del valor
        // diario cargado para la fecha de la transaccion.
        $datos['tipo_cambio'] = $this->tipoCambioDelDia($datos['fecha_transaccion']);

        $liquidacion = DB::transaction(function () use ($datos, $usuario): Liquidacion {
            $ejercicio = (int) substr($datos['fecha_transaccion'], 0, 4);

            // El numero puede traerlo el usuario (viene del Excel externo); si
            // no, lo asigna el contador del ejercicio. En ambos casos el
            // codigo_formulario se deriva, nunca llega del cliente (5.3).
            $numeracion = isset($datos['numero_documento'])
                ? [
                    'numero_documento' => $datos['numero_documento'],
                    'codigo_formulario' => NumeradorDocumento::codigoFormulario($datos['numero_documento'], $ejercicio),
                    'correlativo' => null,
                ]
                : $this->numerador->asignar('liquidacion', $ejercicio);

            $insumos = $this->insumos($datos);
            $calculo = $this->calculadora->calcular($insumos);

            $liquidacion = Liquidacion::query()->create(array_merge(
                $this->entradaPersistible($datos, $insumos['aplica_caja_salud']),
                $this->snapshots($datos),
                $numeracion,
                $calculo,
                [
                    'ejercicio' => $ejercicio,
                    'fecha_transaccion' => $datos['fecha_transaccion'],
                    'usuario_registro_id' => $usuario->id,
                    'estado' => EstadoLiquidacion::Creado,
                ],
            ));

            // Bitacora inicial (NULL -> creado) dentro de la MISMA transaccion.
            $this->transiciones->registrarInicio($liquidacion, $usuario);

            return $liquidacion;
        });

        return response()->json([
            'message' => 'Liquidacion creada correctamente.',
            'data' => new LiquidacionResource(
                $liquidacion->load(['registro:id,nombre_completo,username'])
            ),
        ], Response::HTTP_CREATED);
    }

    public function update(UpdateLiquidacionRequest $request, Liquidacion $liquidacion): JsonResponse
    {
        $this->sinEscritura($liquidacion);

        Gate::forUser($request->user())->authorize('update', $liquidacion);

        // Edicion parcial: lo que no viene se queda como estaba, y los
        // insumos del calculo se recomponen con lo viejo mas lo nuevo.
        $validados = $request->validated();
        $datos = array_merge($this->base($liquidacion), $validados);

        // El tipo de cambio se vuelve a resolver SOLO si cambia la fecha de la
        // transaccion. Mientras la fecha no cambie, la liquidacion conserva la
        // copia que ya tenia: editar el peso no debe alterar el tipo de cambio.
        if (array_key_exists('fecha_transaccion', $validados)) {
            $datos['tipo_cambio'] = $this->tipoCambioDelDia($datos['fecha_transaccion']);
        }

        $ejercicio = (int) substr($datos['fecha_transaccion'], 0, 4);

        $liquidacion = DB::transaction(function () use ($liquidacion, $datos, $ejercicio): Liquidacion {
            $insumos = $this->insumos($datos);
            $calculo = $this->calculadora->calcular($insumos);

            $liquidacion->fill(array_merge(
                $this->entradaPersistible($datos, $insumos['aplica_caja_salud']),
                $this->snapshots($datos),
                $calculo,
                [
                    'numero_documento' => $datos['numero_documento'],
                    'ejercicio' => $ejercicio,
                    // Se recalcula siempre: si cambio la fecha cambio el
                    // ejercicio, y el codigo del formulario lo lleva dentro.
                    'codigo_formulario' => NumeradorDocumento::codigoFormulario($datos['numero_documento'], $ejercicio),
                    'fecha_transaccion' => $datos['fecha_transaccion'],
                ],
            ))->save();

            return $liquidacion;
        });

        return response()->json([
            'message' => 'Liquidacion actualizada correctamente.',
            'data' => new LiquidacionResource(
                $liquidacion->load(['registro:id,nombre_completo,username'])
            ),
        ]);
    }

    /**
     * Borrado logico: `creado -> eliminado` por la maquina de estados.
     *
     * No se borra la fila jamas (T3 + D2): el expediente queda con su
     * historial y fuera del listado activo.
     */
    public function destroy(Request $request, Liquidacion $liquidacion): Response
    {
        // Estado primero: una aprobada responde 422 y no 403 (ver docblock).
        if (! $liquidacion->estado->puedeTransicionA(EstadoLiquidacion::Eliminado)) {
            throw TransicionInvalidaException::noPermitida(
                $liquidacion->estado,
                EstadoLiquidacion::Eliminado,
            );
        }

        Gate::forUser($request->user())->authorize('delete', $liquidacion);

        $this->transiciones->ejecutar(
            $liquidacion,
            EstadoLiquidacion::Eliminado,
            $request->user(),
        );

        return response()->noContent();
    }

    // ------------------------------------------------------------ flujo

    /**
     * Comentario en el flujo (3.6). La escritura de la observacion no cambia
     * el estado: el estado solo se mueve por /transiciones.
     */
    public function observar(ObservacionRequest $request, Liquidacion $liquidacion): JsonResponse
    {
        $this->authorize('comentar', $liquidacion);

        $observacion = $liquidacion->observacionesFlujo()->create([
            'liquidacion_id' => $liquidacion->id,
            'usuario_id' => $request->user()->id,
            'tipo' => 'comentario',
            'texto' => $request->validated('comentario'),
        ]);

        return response()->json([
            'message' => 'Comentario registrado.',
            'data' => new LiquidacionObservacionResource($observacion),
        ], Response::HTTP_CREATED);
    }

    /** Bitacora completa del expediente, en orden cronologico. */
    public function historial(Liquidacion $liquidacion): JsonResponse
    {
        $this->authorize('view', $liquidacion);

        $filas = LiquidacionEstadoHistorial::query()
            ->where('liquidacion_id', $liquidacion->id)
            ->with('usuario:id,username')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $filas->map(fn (LiquidacionEstadoHistorial $fila): array => [
                'id' => $fila->id,
                'estado_anterior' => $fila->estado_anterior?->value,
                'estado_nuevo' => $fila->estado_nuevo->value,
                'usuario' => [
                    'id' => $fila->usuario_id,
                    'nombre_completo' => $fila->usuario_nombre,
                    'username' => $fila->usuario->username ?? null,
                ],
                'comentario' => $fila->comentario,
                'fecha' => $fila->created_at?->toIso8601String(),
            ]),
        ]);
    }

    // ------------------------------------------------------------- filtros

    /**
     * Filtros del index (4.5). Todos opcionales y todos combinables.
     *
     * @param  Builder<Liquidacion>  $consulta
     */
    private function filtrar(Builder $consulta, Request $request): void
    {
        if ($request->filled('estado')) {
            $estados = preg_split('/\s*,\s*/', trim((string) $request->input('estado')), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            $invalidos = array_filter($estados, fn (string $e): bool => EstadoLiquidacion::tryFrom($e) === null);

            if ($invalidos !== []) {
                throw ValidationException::withMessages([
                    'estado' => 'Estado desconocido: '.implode(', ', $invalidos).'. Valores: '
                        .implode(', ', array_map(fn (EstadoLiquidacion $e): string => $e->value, EstadoLiquidacion::cases())),
                ]);
            }

            $consulta->whereIn('estado', $estados);
        }

        if ($request->filled('fecha_desde')) {
            $consulta->where('fecha_transaccion', '>=', $this->fecha($request, 'fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $consulta->where('fecha_transaccion', '<=', $this->fecha($request, 'fecha_hasta'));
        }

        foreach (['municipio_id' => 'municipio_id', 'area_minera_id' => 'area_minera_id', 'vendedor_id' => 'vendedor_persona_id'] as $entrada => $columna) {
            if ($request->filled($entrada)) {
                $consulta->where($columna, $request->integer($entrada));
            }
        }

        if ($request->filled('registrado_por')) {
            $valor = trim((string) $request->input('registrado_por'));

            $consulta->where('usuario_registro_id', ctype_digit($valor)
                ? (int) $valor
                : (int) User::query()->where('username', $valor)->value('id'));
        }

        if ($request->filled('q')) {
            $busqueda = '%'.str_replace(['%', '_'], ['\%', '\_'], trim((string) $request->input('q'))).'%';

            $consulta->where(function ($q) use ($busqueda): void {
                $q->where('numero_documento', 'ilike', $busqueda)
                    ->orWhere('comprador_razon_social', 'ilike', $busqueda)
                    ->orWhere('vendedor_nombre', 'ilike', $busqueda);
            });
        }

        $orden = (string) $request->input('orden', 'fecha');
        $columnas = [
            'fecha' => 'fecha_transaccion',
            'numero' => 'numero_documento',
            'neto' => 'valor_neto_bs',
            'bruto' => 'valor_bruto_bs',
        ];

        if (! isset($columnas[$orden])) {
            throw ValidationException::withMessages([
                'orden' => 'Orden invalido: '.implode(', ', array_keys($columnas)),
            ]);
        }

        $direccion = strtolower((string) $request->input('direccion', $orden === 'fecha' ? 'desc' : 'asc'));

        if (! in_array($direccion, ['asc', 'desc'], true)) {
            throw ValidationException::withMessages(['direccion' => 'La direccion debe ser asc o desc.']);
        }

        $consulta->orderBy($columnas[$orden], $direccion)
            // Desempate estable: dos liquidaciones del mismo dia se listan
            // siempre en el mismo orden, sino la paginacion salta filas.
            ->orderBy('id');
    }

    private function fecha(Request $request, string $campo): string
    {
        $valor = (string) $request->input($campo);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) !== 1) {
            throw ValidationException::withMessages([
                $campo => 'La fecha debe ir en formato YYYY-MM-DD.',
            ]);
        }

        return $valor;
    }

    // ------------------------------------------------------- persistencia

    /**
     * La liquidacion con lo que necesitan la vista y el export: el registro
     * (que la firma) y el historial en orden (la hoja del documento).
     */
    private function cargada(Liquidacion $liquidacion): Liquidacion
    {
        return $liquidacion->load([
            'registro:id,nombre_completo,username',
            'historial' => fn (HasMany $q) => $q->orderBy('created_at')->orderBy('id'),
        ]);
    }

    /**
     * El D3 congelado que se guarda al lado de cada FK: si mañana corrigen
     * un nombre en el catalogo, el PDF de hoy no cambia.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    /**
     * Campos de la pantalla de captura que se copian tal cual a la fila.
     *
     * Es el mismo bloque en alta y en edicion: la lista de campos no puede
     * divergir entre los dos, o una edicion parcial devolveria los montos
     * con insumos de otra captura.
     *
     * @param  array<string, mixed>  $datos
     */
    private function entradaPersistible(array $datos, bool $aplicaCajaSalud): array
    {
        return [
            'tipo_cambio' => $datos['tipo_cambio'],
            'cotizacion_id' => $datos['cotizacion_id'] ?? null,
            'cotizacion_usd_oz_t' => $datos['cotizacion_usd_oz_t'],
            'aplica_caja_salud' => $aplicaCajaSalud,
            'comprador_id' => Comprador::idDeRazonSocial($datos['comprador_razon_social']),
            'comprador_razon_social' => $datos['comprador_razon_social'],
            'comprador_nim' => $datos['comprador_nim'] ?? null,
            'comprador_nit' => $datos['comprador_nit'] ?? null,
            'vendedor_persona_id' => $datos['vendedor_persona_id'],
            'area_minera_id' => $datos['area_minera_id'],
            'peso_gramos' => $datos['peso_gramos'],
            'ley_pagable' => $datos['ley_pagable'],
            'observaciones' => $datos['observaciones'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function snapshots(array $datos): array
    {
        /** @var AreaMinera $area */
        $area = AreaMinera::query()
            ->with(['departamento:id,nombre', 'provincia:id,nombre', 'municipio:id,nombre,codigo', 'localidad:id,nombre'])
            ->findOrFail($datos['area_minera_id']);

        /** @var Persona $vendedor */
        $vendedor = Persona::query()->findOrFail($datos['vendedor_persona_id']);

        /** @var PresentacionMineral $presentacion */
        $presentacion = PresentacionMineral::query()->findOrFail($datos['presentacion_mineral_id']);

        return [
            'vendedor_nombre' => $vendedor->nombre_completo,
            'vendedor_ci' => $datos['vendedor_ci'],
            'nombre_area_minera' => $area->nombre_area_minera,
            'titular_derecho_minero' => $area->titular_derecho_minero,
            'codigo_ajam_patente' => $area->codigo_ajam_patente ?? $datos['codigo_ajam_patente'] ?? null,
            'departamento_id' => $area->departamento_id,
            'departamento_nombre' => $area->departamento->nombre,
            'provincia_id' => $area->provincia_id,
            'provincia_nombre' => $area->provincia->nombre,
            'municipio_id' => $area->municipio_id,
            'municipio_nombre' => $area->municipio->nombre,
            'codigo_municipio' => $area->municipio->codigo ?? $datos['codigo_municipio'] ?? null,
            'localidad_id' => $area->localidad_id,
            'localidad_nombre' => $area->localidad->nombre,
            'presentacion_mineral_id' => $presentacion->id,
            'presentacion_nombre' => $presentacion->nombre,
        ];
    }

    /**
     * Los cinco insumos + el interruptor, ya validados, en el formato que
     * el servicio espera.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function insumos(array $datos): array
    {
        return [
            'peso_gramos' => $datos['peso_gramos'],
            'ley_pagable' => $datos['ley_pagable'],
            'tipo_cambio' => $datos['tipo_cambio'],
            'cotizacion_usd_oz_t' => $datos['cotizacion_usd_oz_t'],
            'aplica_caja_salud' => is_bool($datos['aplica_caja_salud'])
                ? $datos['aplica_caja_salud']
                : filter_var($datos['aplica_caja_salud'], FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * El tipo de cambio oficial Bs/$ de la fecha de la transaccion.
     *
     * Ya no se captura por liquidacion: se toma de la tabla diaria
     * `tipos_cambio`. Si no hay valor cargado para ese dia se corta con 422,
     * porque una liquidacion sin tipo de cambio oficial no se puede calcular
     * y es preferible bloquear el alta a inventar un valor.
     */
    private function tipoCambioDelDia(string $fechaTransaccion): string
    {
        $tipoCambio = TipoCambio::delDia($fechaTransaccion);

        if ($tipoCambio === null) {
            throw ValidationException::withMessages([
                'fecha_transaccion' => 'No hay tipo de cambio registrado para el '.$fechaTransaccion.'. Cargue el tipo de cambio del dia antes de registrar la liquidacion.',
            ]);
        }

        return $tipoCambio->valor;
    }

    /**
     * Los valores ya guardados, para recombinarlos con los que llegan en
     * una edicion parcial.
     *
     * @return array<string, mixed>
     */
    private function base(Liquidacion $liquidacion): array
    {
        return [
            'numero_documento' => $liquidacion->numero_documento,
            'fecha_transaccion' => $liquidacion->fecha_transaccion->toDateString(),
            'tipo_cambio' => $liquidacion->tipo_cambio,
            'cotizacion_usd_oz_t' => $liquidacion->cotizacion_usd_oz_t,
            'cotizacion_id' => $liquidacion->cotizacion_id,
            'aplica_caja_salud' => $liquidacion->aplica_caja_salud,
            'peso_gramos' => $liquidacion->peso_gramos,
            'ley_pagable' => $liquidacion->ley_pagable,
            'comprador_id' => $liquidacion->comprador_id,
            'comprador_razon_social' => $liquidacion->comprador_razon_social,
            'comprador_nim' => $liquidacion->comprador_nim,
            'comprador_nit' => $liquidacion->comprador_nit,
            'vendedor_persona_id' => $liquidacion->vendedor_persona_id,
            'vendedor_ci' => $liquidacion->vendedor_ci,
            'area_minera_id' => $liquidacion->area_minera_id,
            'departamento_id' => $liquidacion->departamento_id,
            'provincia_id' => $liquidacion->provincia_id,
            'municipio_id' => $liquidacion->municipio_id,
            'localidad_id' => $liquidacion->localidad_id,
            'codigo_municipio' => $liquidacion->codigo_municipio,
            'codigo_ajam_patente' => $liquidacion->codigo_ajam_patente,
            'presentacion_mineral_id' => $liquidacion->presentacion_mineral_id,
            'observaciones' => $liquidacion->observaciones,
        ];
    }

    /**
     * Corte por estado, 422 y no 403 (ver docblock de la clase).
     */
    private function sinEscritura(Liquidacion $liquidacion): void
    {
        if ($liquidacion->estado === EstadoLiquidacion::Aprobado) {
            throw RegistroNoEditableException::aprobada();
        }

        if (! $liquidacion->estado->esEditable()) {
            throw RegistroNoEditableException::porEstado($liquidacion->estado);
        }
    }
}

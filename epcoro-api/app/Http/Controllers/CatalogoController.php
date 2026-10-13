<?php

namespace App\Http\Controllers;

use App\Http\Requests\PersonaRequest;
use App\Models\AreaMinera;
use App\Models\Comprador;
use App\Models\CotizacionQuincenal;
use App\Models\Departamento;
use App\Models\Localidad;
use App\Models\Municipio;
use App\Models\Persona;
use App\Models\PresentacionMineral;
use App\Models\Provincia;
use App\Models\TipoCambio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Los 8 catalogos que el formulario de captura necesita para no escribir
 * texto libre (seccion 3.2 + FASE 6.5).
 *
 * Son de LECTURA para cualquier usuario autenticado: el tecnico no puede
 * editar el catalogo, pero sin el no captura nada. El unico alta que se
 * agrega aqui es la de personas, porque 8.4 la exige ("buscador de personas
 * con alta rapida") y sin ella el formulario se queda sin vendedor la
 * primera vez que aparece uno nuevo.
 *
 * Las cascadas llegan con el id del padre: la CARGA la resuelve el cliente
 * (al cambiar el departamento se piden las provincias), que es como el
 * SelectorUbicacion.vue funciona. Orden alfabetico siempre, para que la
 * lista no cambie de orden entre una carga y otra.
 */
class CatalogoController extends Controller
{
    public function departamentos(): JsonResponse
    {
        return $this->lista(
            Departamento::query()->orderBy('nombre')->get(['id', 'codigo', 'nombre'])
        );
    }

    public function provincias(Request $request): JsonResponse
    {
        return $this->lista(
            Provincia::query()
                ->when($request->filled('departamento_id'), fn ($q) => $q->where('departamento_id', $request->integer('departamento_id')))
                ->orderBy('nombre')
                ->get(['id', 'departamento_id', 'codigo', 'nombre'])
        );
    }

    /**
     * Municipios. Con `provincia_id` filtra como en la cascada del formulario;
     * con `q` busca por nombre como el autocompletado del Index (8.10), y en
     * ese modo trae tambien el nombre de la provincia y el departamento para
     * el detalle de cada opcion. `limit(50)` solo vale cuando hay busqueda,
     * para que la cascada nunca recorte una provincia.
     */
    public function municipios(Request $request): JsonResponse
    {
        $municipios = Municipio::query()
            ->with([
                'provincia:id,nombre,departamento_id',
                'provincia.departamento:id,nombre',
            ])
            ->when($request->filled('provincia_id'), fn ($q) => $q->where('provincia_id', $request->integer('provincia_id')))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $busqueda = '%'.trim((string) $request->input('q')).'%';

                $q->where(
                    fn ($buscar) => $buscar
                        ->where('nombre', 'ilike', $busqueda)
                        ->orWhereHas('provincia', fn ($provincia) => $provincia->where('nombre', 'ilike', $busqueda))
                )->limit(50);
            })
            ->orderBy('nombre')
            ->get()
            ->map(fn (Municipio $municipio): array => [
                'id' => $municipio->id,
                'provincia_id' => $municipio->provincia_id,
                'codigo' => $municipio->codigo,
                'nombre' => $municipio->nombre,
                'provincia_nombre' => $municipio->provincia?->nombre,
                'departamento_nombre' => $municipio->provincia?->departamento?->nombre,
            ]);

        return $this->lista($municipios);
    }

    public function localidades(Request $request): JsonResponse
    {
        return $this->lista(
            Localidad::query()
                ->when($request->filled('municipio_id'), fn ($q) => $q->where('municipio_id', $request->integer('municipio_id')))
                ->orderBy('nombre')
                ->get(['id', 'municipio_id', 'codigo', 'nombre'])
        );
    }

    /**
     * El buscador de areas trae la UBICACION COMPLETA: el formulario
     * autocompleta los cuatro niveles desde aqui, y el backend despues
     * exige que coincidan (store/update).
     */
    public function areasMineras(Request $request): JsonResponse
    {
        $areas = AreaMinera::query()
            ->with([
                'departamento:id,nombre',
                'provincia:id,nombre',
                'municipio:id,nombre,codigo',
                'localidad:id,nombre',
            ])
            ->when($request->filled('q'), fn ($q) => $q->where(
                fn ($busqueda) => $busqueda
                    ->where('nombre_area_minera', 'ilike', '%'.trim((string) $request->input('q')).'%')
                    ->orWhere('titular_derecho_minero', 'ilike', '%'.trim((string) $request->input('q')).'%')
            ))
            ->orderBy('nombre_area_minera')
            ->limit(50)
            ->get()
            ->map(fn (AreaMinera $area): array => [
                'id' => $area->id,
                'nombre_area_minera' => $area->nombre_area_minera,
                'titular_derecho_minero' => $area->titular_derecho_minero,
                'codigo_ajam_patente' => $area->codigo_ajam_patente,
                'activo' => $area->activo,
                'ubicacion' => [
                    'departamento_id' => $area->departamento_id,
                    'departamento_nombre' => $area->departamento?->nombre,
                    'provincia_id' => $area->provincia_id,
                    'provincia_nombre' => $area->provincia?->nombre,
                    'municipio_id' => $area->municipio_id,
                    'municipio_nombre' => $area->municipio?->nombre,
                    'codigo_municipio' => $area->municipio?->codigo,
                    'localidad_id' => $area->localidad_id,
                    'localidad_nombre' => $area->localidad?->nombre,
                ],
            ]);

        return $this->lista($areas);
    }

    public function personas(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Persona::class);

        $personas = Persona::query()
            ->where('activo', true)
            ->when($request->filled('q'), function ($q) use ($request): void {
                $busqueda = '%'.trim((string) $request->input('q')).'%';

                $q->where(fn ($b) => $b
                    ->where('nombre_completo', 'ilike', $busqueda)
                    ->orWhere('ci', 'ilike', $busqueda)
                    ->orWhere('nit', 'ilike', $busqueda));
            })
            ->orderBy('nombre_completo')
            ->limit(50)
            ->get(['id', 'nombre_completo', 'nombres', 'apellidos', 'ci', 'nit', 'tipo_documento', 'email', 'telefono']);

        return $this->lista($personas);
    }

    public function presentacionesMineral(): JsonResponse
    {
        return $this->lista(
            PresentacionMineral::query()->orderBy('nombre')->get(['id', 'nombre'])
        );
    }

    /**
     * Catalogo de compradores (C11-C13): hoy es una sola fila (SINCHI WAYRA), la
     * que el formulario prellenada al abrir. Orden alfabetico para que la
     * lista no cambie de orden entre una carga y otra.
     */
    public function compradores(): JsonResponse
    {
        return $this->lista(
            Comprador::query()
                ->where('activo', true)
                ->orderBy('razon_social')
                ->get(['id', 'razon_social', 'nim', 'nit'])
        );
    }

    /**
     * Alta rapida de persona (8.4): el vendedor nuevo no puede obligar a
     * salirse del formulario.
     *
     * `ci` va con los mismos cortes C5 que vendedor_ci en la liquidacion:
     * es un identificador, y si aqui se guardara con puntos o en notacion
     * cientifica, despues no pasaria la validacion del expediente.
     *
     * La validacion vive en PersonaRequest, como toda escritura de la API.
     */
    public function guardarPersona(PersonaRequest $request): JsonResponse
    {
        $this->authorize('create', Persona::class);

        $datos = $request->validated();

        // El vendedor puede venir del Excel externo (N5): si el CI ya esta,
        // se devuelve la persona existente y no se crea un duplicado. La
        // escritura por encima de los nombres de alguien que ya existe no
        // se hace por aqui, para no pisar datos por una casilla mal llena.
        $persona = Persona::query()->where('ci', $datos['ci'])->first();

        if ($persona !== null) {
            return response()->json([
                'message' => 'La persona con este CI ya estaba registrada.',
                'data' => $persona,
            ], 200);
        }

        $persona = Persona::query()->create(array_merge($datos, [
            'tipo_documento' => $datos['tipo_documento'] ?? 'CI',
            'nombre_completo' => trim($datos['nombres'].' '.$datos['apellidos']),
            'activo' => true,
        ]));

        return response()->json([
            'message' => 'Persona registrada correctamente.',
            'data' => $persona,
        ], 201);
    }

    /**
     * Ultimas cotizaciones quincenales, la mas reciente primero (8.2).
     */
    public function cotizaciones(Request $request): JsonResponse
    {
        return $this->lista(
            CotizacionQuincenal::query()
                ->when($request->filled('ejercicio'), fn ($q) => $q->where('ejercicio', $request->integer('ejercicio')))
                ->orderByDesc('ejercicio')
                ->orderByDesc('quincena')
                ->limit(24)
                ->get()
        );
    }

    /**
     * Tipo de cambio oficial de una fecha (tabla `tipos_cambio`).
     *
     * El formulario de captura lo consume para MOSTRARLO de solo lectura: el
     * tipo de cambio ya no se captura por liquidacion, lo resuelve el backend
     * al guardar. `data` es null cuando ese dia no tiene valor cargado, y el
     * formulario lo muestra como aviso en vez de dejar enviar.
     */
    public function tipoCambio(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date_format:Y-m-d'],
        ]);

        $tipoCambio = TipoCambio::delDia($datos['fecha']);

        return response()->json([
            'data' => $tipoCambio === null ? null : [
                'fecha' => $tipoCambio->fecha->toDateString(),
                'valor' => $tipoCambio->valor,
                'fuente' => $tipoCambio->fuente,
            ],
        ]);
    }

    /**
     * @param  Collection<int, mixed>  $elementos
     */
    private function lista(Collection $elementos): JsonResponse
    {
        return response()->json(['data' => $elementos]);
    }
}

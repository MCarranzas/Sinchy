<?php

namespace App\Models;

use App\Enums\EstadoLiquidacion;
use Database\Factories\LiquidacionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Liquidacion de mineral: la tabla principal, diseno denso con una columna
 * por campo del Excel (seccion 3.4).
 *
 * Tres grupos de columnas que no se tocan como el resto:
 *
 *  - SNAPSHOT: los *_nombre y vendedor_* se copian ademas de la FK. Si
 *    mañana corrigen el nombre de un municipio, el PDF de hoy no cambia.
 *  - CALCULADOS: peso_fino_gramos, valor_usd_gramo, valor_bruto_bs,
 *    aporte_caja_salud_bs, total_deducciones_bs y valor_neto_bs se PERSISTEN
 *    y los reportes no los recalculan (regla T3).
 *  - ESTADO: no se cambia con update(). Ver Enums/EstadoLiquidacion.
 *
 * Los montos se leen como string (numeric de PostgreSQL) y asi deben
 * viajar: convertirlos a float es como se pierde el centavo que separa
 * 1072018.84 de 1072018.83.
 *
 * @property int $id
 * @property string $numero_documento
 * @property string|null $codigo_formulario
 * @property int|null $correlativo
 * @property int $ejercicio
 * @property string $fecha_transaccion
 * @property Carbon $fecha_registro
 * @property int $usuario_registro_id
 * @property EstadoLiquidacion $estado
 * @property Carbon|null $fecha_revision
 * @property int|null $usuario_revision_id
 * @property Carbon|null $fecha_aprobacion
 * @property int|null $usuario_aprobacion_id
 * @property Carbon|null $fecha_rechazo
 * @property int|null $usuario_rechazo_id
 * @property string|null $motivo_rechazo
 * @property Carbon|null $fecha_anulacion
 * @property int|null $usuario_anulacion_id
 * @property string|null $motivo_anulacion
 * @property string|null $observaciones
 * @property string $tipo_cambio
 * @property int|null $cotizacion_id
 * @property string $cotizacion_usd_oz_t
 * @property int|null $comprador_persona_id
 * @property string $comprador_razon_social
 * @property string|null $comprador_nim
 * @property string|null $comprador_nit
 * @property bool $aplica_caja_salud
 * @property int $vendedor_persona_id
 * @property string $vendedor_nombre
 * @property string $vendedor_ci
 * @property int $area_minera_id
 * @property string $nombre_area_minera
 * @property string $titular_derecho_minero
 * @property string|null $codigo_ajam_patente
 * @property int $departamento_id
 * @property string $departamento_nombre
 * @property int $provincia_id
 * @property string $provincia_nombre
 * @property int $municipio_id
 * @property string $municipio_nombre
 * @property string|null $codigo_municipio
 * @property int $localidad_id
 * @property string $localidad_nombre
 * @property int $presentacion_mineral_id
 * @property string $presentacion_nombre
 * @property string $peso_gramos
 * @property string $ley_pagable
 * @property string $gramos_por_onza_troy
 * @property string $peso_fino_gramos
 * @property string $valor_usd_gramo
 * @property string $valor_bruto_bs
 * @property string $aporte_caja_salud_bs
 * @property string $total_deducciones_bs
 * @property string $valor_neto_bs
 */
#[Fillable([
    'numero_documento',
    'codigo_formulario',
    'correlativo',
    'ejercicio',
    'fecha_transaccion',
    'fecha_registro',
    'usuario_registro_id',
    'estado',
    'tipo_cambio',
    'cotizacion_id',
    'cotizacion_usd_oz_t',
    'comprador_persona_id',
    'comprador_razon_social',
    'comprador_nim',
    'comprador_nit',
    'aplica_caja_salud',
    'vendedor_persona_id',
    'vendedor_nombre',
    'vendedor_ci',
    'area_minera_id',
    'nombre_area_minera',
    'titular_derecho_minero',
    'codigo_ajam_patente',
    'departamento_id',
    'departamento_nombre',
    'provincia_id',
    'provincia_nombre',
    'municipio_id',
    'municipio_nombre',
    'codigo_municipio',
    'localidad_id',
    'localidad_nombre',
    'presentacion_mineral_id',
    'presentacion_nombre',
    'peso_gramos',
    'ley_pagable',
    'gramos_por_onza_troy',
    'peso_fino_gramos',
    'valor_usd_gramo',
    'valor_bruto_bs',
    'aporte_caja_salud_bs',
    'total_deducciones_bs',
    'valor_neto_bs',
])]
class Liquidacion extends Model
{
    /** "liquidacions" no existe: el plural es liquidaciones. */
    protected $table = 'liquidaciones';

    /** @use HasFactory<LiquidacionFactory> */
    use HasFactory;

    /**
     * Los montos NO se castean a float: quedan como string, que es como
     * llegan desde numeric. Ver la nota de la clase.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoLiquidacion::class,
            'fecha_transaccion' => 'date',
            'fecha_registro' => 'datetime',
            'fecha_revision' => 'datetime',
            'fecha_aprobacion' => 'datetime',
            'fecha_rechazo' => 'datetime',
            'fecha_anulacion' => 'datetime',
            'aplica_caja_salud' => 'boolean',
            'ejercicio' => 'integer',
            'correlativo' => 'integer',
        ];
    }

    // ------------------------------------------------------------- identidad

    /** @return BelongsTo<CotizacionQuincenal, $this> */
    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(CotizacionQuincenal::class, 'cotizacion_id');
    }

    /** @return BelongsTo<User, $this> */
    public function registro(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_registro_id');
    }

    /** @return BelongsTo<Persona, $this> */
    public function comprador(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'comprador_persona_id');
    }

    /** @return BelongsTo<Persona, $this> */
    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'vendedor_persona_id');
    }

    // --------------------------------------------------------- procedencia

    /** @return BelongsTo<AreaMinera, $this> */
    public function areaMinera(): BelongsTo
    {
        return $this->belongsTo(AreaMinera::class);
    }

    /** @return BelongsTo<Departamento, $this> */
    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    /** @return BelongsTo<Provincia, $this> */
    public function provincia(): BelongsTo
    {
        return $this->belongsTo(Provincia::class);
    }

    /** @return BelongsTo<Municipio, $this> */
    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    /** @return BelongsTo<Localidad, $this> */
    public function localidad(): BelongsTo
    {
        return $this->belongsTo(Localidad::class);
    }

    /** @return BelongsTo<PresentacionMineral, $this> */
    public function presentacion(): BelongsTo
    {
        return $this->belongsTo(PresentacionMineral::class, 'presentacion_mineral_id');
    }

    // -------------------------------------------------------------- flujo

    /** @return HasMany<LiquidacionDeduccion, $this> */
    public function deducciones(): HasMany
    {
        return $this->hasMany(LiquidacionDeduccion::class);
    }

    /** @return HasMany<LiquidacionObservacion, $this> */
    public function observacionesFlujo(): HasMany
    {
        return $this->hasMany(LiquidacionObservacion::class);
    }

    /** @return HasMany<LiquidacionEstadoHistorial, $this> */
    public function historial(): HasMany
    {
        return $this->hasMany(LiquidacionEstadoHistorial::class);
    }

    /**
     * El registro es de solo lectura: D2 dice que una aprobada no se toca
     * jamas, ni siquiera el administrador. El bloqueo se hace con
     * `sinEscritura()` en el controlador, que devuelve 422 y no 403.
     */
    public function esInmutable(): bool
    {
        return $this->estado === EstadoLiquidacion::Aprobado;
    }
}

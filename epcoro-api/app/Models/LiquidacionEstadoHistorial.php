<?php

namespace App\Models;

use App\Enums\EstadoLiquidacion;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitacora de cambios de estado (seccion 3.5).
 *
 * La fila se escribe DENTRO de la misma transaccion que el cambio: si el
 * historial falla, la transicion tampoco se aplica.
 *
 * `estado_anterior` es NULL unicamente en la transicion inicial.
 * `usuario_nombre` es snapshot por si el usuario se renombra o se da de baja.
 *
 * @property int $id
 * @property int $liquidacion_id
 * @property EstadoLiquidacion|null $estado_anterior
 * @property EstadoLiquidacion $estado_nuevo
 * @property int $usuario_id
 * @property string $usuario_nombre
 * @property string|null $comentario
 * @property \Illuminate\Support\Carbon $created_at
 */
#[Fillable([
    'liquidacion_id',
    'estado_anterior',
    'estado_nuevo',
    'usuario_id',
    'usuario_nombre',
    'comentario',
])]
class LiquidacionEstadoHistorial extends Model
{
    /** @use HasFactory<\Database\Factories\LiquidacionEstadoHistorialFactory> */
    use HasFactory;

    /**
     * Solo created_at: el historial es de solo append.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Los enums de Laravel devuelven null solos cuando la columna es
            // NULL, asi que no hace falta un sufijo ':nullable'.
            'estado_anterior' => EstadoLiquidacion::class,
            'estado_nuevo' => EstadoLiquidacion::class,
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Liquidacion, $this> */
    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(Liquidacion::class);
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}

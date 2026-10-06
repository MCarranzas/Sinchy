<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Observacion del flujo: comentario, solicitud de correccion u observacion
 * del revisor (seccion 3.6). El motivo formal de rechazo va en
 * liquidaciones.motivo_rechazo, no aqui.
 *
 * @property int $id
 * @property int $liquidacion_id
 * @property int $usuario_id
 * @property string $tipo
 * @property string $texto
 * @property \Illuminate\Support\Carbon $created_at
 */
#[Fillable(['liquidacion_id', 'usuario_id', 'tipo', 'texto'])]
class LiquidacionObservacion extends Model
{
    /** @use HasFactory<\Database\Factories\LiquidacionObservacionFactory> */
    use HasFactory;

    /**
     * Solo tiene created_at: es una linea de bitacora y no se "edita".
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

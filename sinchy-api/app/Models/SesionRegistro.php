<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Bitacora permanente de accesos: una fila por cada login.
 *
 * No confundir con la tabla `sessions` de Laravel, que es operativa y se
 * purga. Esta NO se borra nunca, y por eso guarda la fecha y hora de ingreso
 * y la IP del equipo cliente como constancia.
 *
 * @property int $id
 * @property int $user_id
 * @property string $usuario_nombre
 * @property string|null $session_id
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon $fecha_ingreso
 * @property Carbon|null $fecha_salida
 */
#[Fillable(['user_id', 'usuario_nombre', 'session_id', 'ip', 'user_agent', 'fecha_ingreso', 'fecha_salida'])]
class SesionRegistro extends Model
{
    /** El pluralizador ingles diria "sesion_registros". */
    protected $table = 'sesiones_registro';

    /** La tabla solo tiene fecha_ingreso/fecha_salida, no created_at/updated_at. */
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_ingreso' => 'datetime',
            'fecha_salida' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

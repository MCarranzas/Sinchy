<?php

namespace App\Models;

use Database\Factories\TipoCambioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Tipo de cambio oficial Bs/$ de un dia.
 *
 * Se captura una vez por fecha (no por liquidacion). Cada liquidacion copia
 * el valor en su columna `tipo_cambio`, asi que cambiar una fila aqui no
 * reescribe liquidaciones ya registradas.
 *
 * @property int $id
 * @property Carbon $fecha
 * @property string $valor
 * @property string|null $fuente
 */
#[Fillable(['fecha', 'valor', 'fuente'])]
class TipoCambio extends Model
{
    /** El pluralizador ingles diria "tipo_cambios". */
    protected $table = 'tipos_cambio';

    /** @use HasFactory<TipoCambioFactory> */
    use HasFactory;

    /**
     * `valor` se mantiene como string para conservar los 4 decimales exactos:
     * un float convierte 6.96 en 6.959999999999999.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    /**
     * El tipo de cambio de una fecha, o null si ese dia no tiene valor.
     */
    public static function delDia(Carbon|string $fecha): ?self
    {
        $dia = $fecha instanceof Carbon ? $fecha->toDateString() : $fecha;

        return self::query()->whereDate('fecha', $dia)->first();
    }
}

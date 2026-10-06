<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una fila de deduccion de una liquidacion (seccion 3.7).
 *
 * Reemplaza las columnas H (ALIQUOTA/OTROS) e I (DESCUENTO) del Excel. Hoy
 * solo se usa el aporte del 1.8 %, pero la tabla admite regalias, IUF o
 * impuesto a la transaccion sin tocar el esquema.
 *
 * @property int $id
 * @property int $liquidacion_id
 * @property string $concepto
 * @property string $tipo
 * @property string|null $alicuota
 * @property string $base_monto
 * @property string $monto
 * @property bool $obligatorio
 * @property int $orden
 */
#[Fillable([
    'liquidacion_id',
    'concepto',
    'tipo',
    'alicuota',
    'base_monto',
    'monto',
    'obligatorio',
    'orden',
])]
class LiquidacionDeduccion extends Model
{
    /** @use HasFactory<\Database\Factories\LiquidacionDeduccionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'obligatorio' => 'boolean',
            'orden' => 'integer',
        ];
    }

    /** @return BelongsTo<Liquidacion, $this> */
    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(Liquidacion::class);
    }
}

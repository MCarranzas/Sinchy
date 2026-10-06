<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Historial de cotizaciones del oro, por quincena.
 *
 * Solo alimenta el formulario con una sugerencia: cada liquidacion guarda su
 * propia copia en liquidaciones.cotizacion_usd_oz_t, asi que cambiar una
 * cotizacion aqui no reescribe liquidaciones ya registradas.
 *
 * @property int $id
 * @property int $ejercicio
 * @property int $quincena
 * @property string $fecha_inicio
 * @property string $fecha_fin
 * @property string $valor_usd_oz_t
 * @property string|null $fuente
 */
#[Fillable([
    'ejercicio',
    'quincena',
    'fecha_inicio',
    'fecha_fin',
    'valor_usd_oz_t',
    'fuente',
])]
class CotizacionQuincenal extends Model
{
    /** @use HasFactory<\Database\Factories\CotizacionQuincenalFactory> */
    use HasFactory;

    /**
     * Se mantienen como string para conservar los 4 decimales exactos:
     * un float convierte 2648.57 en 2648.5699999999997.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ejercicio' => 'integer',
            'quincena' => 'integer',
        ];
    }
}

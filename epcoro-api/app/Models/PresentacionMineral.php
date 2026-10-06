<?php

namespace App\Models;

use Database\Factories\PresentacionMineralFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Presentacion del mineral ('ORO FUNDIDO', 'ORO EN GRANO', ...).
 *
 * @property int $id
 * @property string $nombre
 */
#[Fillable(['nombre'])]
class PresentacionMineral extends Model
{
    /** El pluralizador ingles diria "presentacion_minerals". */
    protected $table = 'presentaciones_mineral';

    /** @use HasFactory<PresentacionMineralFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'nombre' => 'string',
        ];
    }
}

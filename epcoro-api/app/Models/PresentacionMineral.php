<?php

namespace App\Models;

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
    /** @use HasFactory<\Database\Factories\PresentacionMineralFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'nombre' => 'string',
        ];
    }
}

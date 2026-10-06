<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cuarto nivel: caserio o comunidad dentro de un municipio.
 *
 * @property int $id
 * @property int $municipio_id
 * @property string $codigo
 * @property string $nombre
 * @property Municipio|null $municipio
 */
#[Fillable(['municipio_id', 'codigo', 'nombre'])]
class Localidad extends Model
{
    /** @use HasFactory<\Database\Factories\LocalidadFactory> */
    use HasFactory;

    /** @return BelongsTo<Municipio, $this> */
    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }
}

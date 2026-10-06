<?php

namespace App\Models;

use Database\Factories\LocalidadFactory;
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
    /**
     * Declarada a mano: el pluralizador de Laravel es ingles y sacaria
     * "localidads" en vez de "localidades". Los demas modelos de negocio la
     * declaran por la misma razon, aunque a veces les acertaria de casualidad.
     */
    protected $table = 'localidades';

    /** @use HasFactory<LocalidadFactory> */
    use HasFactory;

    /** @return BelongsTo<Municipio, $this> */
    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }
}

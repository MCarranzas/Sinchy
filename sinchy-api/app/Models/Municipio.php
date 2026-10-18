<?php

namespace App\Models;

use Database\Factories\MunicipioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tercer nivel. Su `codigo` es el que se copia a
 * liquidaciones.codigo_municipio.
 *
 * @property int $id
 * @property int $provincia_id
 * @property string $codigo
 * @property string $nombre
 * @property Provincia|null $provincia
 */
#[Fillable(['provincia_id', 'codigo', 'nombre'])]
class Municipio extends Model
{
    /** Declarada como en el resto de modelos: no se confia en el plural. */
    protected $table = 'municipios';

    /** @use HasFactory<MunicipioFactory> */
    use HasFactory;

    /** @return BelongsTo<Provincia, $this> */
    public function provincia(): BelongsTo
    {
        return $this->belongsTo(Provincia::class);
    }

    /** @return HasMany<Localidad, $this> */
    public function localidades(): HasMany
    {
        return $this->hasMany(Localidad::class);
    }
}

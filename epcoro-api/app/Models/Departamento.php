<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Primer nivel de la jerarquia Dep > Pro > Mun > Loc.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 */
#[Fillable(['codigo', 'nombre'])]
class Departamento extends Model
{
    /** @use HasFactory<\Database\Factories\DepartamentoFactory> */
    use HasFactory;

    /** @return HasMany<Provincia, $this> */
    public function provincias(): HasMany
    {
        return $this->hasMany(Provincia::class);
    }
}

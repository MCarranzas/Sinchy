<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Area minera. Al elegirla el formulario autocompleta los cuatro datos de
 * procedencia que despues se copian a la liquidacion como snapshot.
 *
 * @property int $id
 * @property string $nombre_area_minera
 * @property string $titular_derecho_minero
 * @property string|null $codigo_ajam_patente
 * @property int $departamento_id
 * @property int $provincia_id
 * @property int $municipio_id
 * @property int $localidad_id
 * @property bool $activo
 * @property Departamento|null $departamento
 * @property Provincia|null $provincia
 * @property Municipio|null $municipio
 * @property Localidad|null $localidad
 */
#[Fillable([
    'nombre_area_minera',
    'titular_derecho_minero',
    'codigo_ajam_patente',
    'departamento_id',
    'provincia_id',
    'municipio_id',
    'localidad_id',
    'activo',
])]
class AreaMinera extends Model
{
    /** @use HasFactory<\Database\Factories\AreaMineraFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /** @return BelongsTo<Departamento, $this> */
    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    /** @return BelongsTo<Provincia, $this> */
    public function provincia(): BelongsTo
    {
        return $this->belongsTo(Provincia::class);
    }

    /** @return BelongsTo<Municipio, $this> */
    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    /** @return BelongsTo<Localidad, $this> */
    public function localidad(): BelongsTo
    {
        return $this->belongsTo(Localidad::class);
    }
}

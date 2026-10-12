<?php

namespace App\Models;

use Database\Factories\CompradorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Comprador del mineral (hoy un solo fijo: EPCORO, celdas C11-C13).
 *
 * Catalogo propio para usos posteriores (varios compradores, gestion,
 * reportes). `liquidaciones` conserva el snapshot del dia
 * (comprador_razon_social/nim/nit) mas la FK `comprador_id` a esta tabla.
 *
 * `nim` y `nit` se manejan SIEMPRE como string: son identificadores, no
 * cantidades (correccion C5).
 *
 * @property int $id
 * @property string $razon_social
 * @property string|null $nim
 * @property string|null $nit
 * @property bool $activo
 */
#[Fillable([
    'razon_social',
    'nim',
    'nit',
    'activo',
])]
class Comprador extends Model
{
    /** El pluralizador ingles diria "compradors". */
    protected $table = 'compradores';

    /** @use HasFactory<CompradorFactory> */
    use HasFactory;

    /**
     * El id del comprador del catalogo cuya razon social es la pasada, o null
     * cuando la liquidacion lleva un comprador que no esta en el catalogo (el
     * Excel admite otro comprador en casos puntuales).
     */
    public static function idDeRazonSocial(string $razonSocial): ?int
    {
        return self::query()
            ->where('activo', true)
            ->whereRaw('upper(razon_social) = upper(?)', [$razonSocial])
            ->value('id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}

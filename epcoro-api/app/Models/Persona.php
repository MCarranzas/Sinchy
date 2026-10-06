<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Persona fisica o juridica. Se usa para VENDEDOR y para COMPRADOR.
 *
 * `ci` y `nit` se manejan SIEMPRE como string: son identificadores, no
 * cantidades (correccion C5). Nunca castearlos a int o float.
 *
 * @property int $id
 * @property string $nombres
 * @property string $apellidos
 * @property string $nombre_completo
 * @property string|null $ci
 * @property string|null $nit
 * @property string $tipo_documento
 * @property string|null $email
 * @property string|null $telefono
 * @property string|null $direccion
 * @property bool $activo
 */
#[Fillable([
    'nombres',
    'apellidos',
    'nombre_completo',
    'ci',
    'nit',
    'tipo_documento',
    'email',
    'telefono',
    'direccion',
    'activo',
])]
class Persona extends Model
{
    /** @use HasFactory<\Database\Factories\PersonaFactory> */
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
}

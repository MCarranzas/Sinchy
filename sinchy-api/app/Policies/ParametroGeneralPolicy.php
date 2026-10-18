<?php

namespace App\Policies;

use App\Models\User;

/**
 * Quien toca parametros_generales (secciones 3.3 y 6.6).
 *
 * Leer es para cualquier usuario autenticado: el formulario de captura
 * necesita la cotizacion sugerida y el tipo de cambio por defecto para no
 * arrancar en cero. Escribir, solo `parametros.gestionar` (administrador):
 * de aqui depende la formula que pone el dinero en juego (D4) y una clave
 * mal editada no es un bug que se note al segundo.
 *
 * El permiso vive aqui y no en el Form Request (la FASE 6.3 lo tenia alla):
 * la capa de autorizacion es la policy, y el request solo valida los datos.
 */
class ParametroGeneralPolicy
{
    public function viewAny(User $usuario): bool
    {
        return true;
    }

    public function update(User $usuario): bool
    {
        return $usuario->can('parametros.gestionar');
    }
}

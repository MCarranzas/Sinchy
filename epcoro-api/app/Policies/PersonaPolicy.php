<?php

namespace App\Policies;

use App\Models\User;

/**
 * Quien administra el catalogo de personas (seccion 8.4).
 *
 * Casi no tiene reglas: leer el buscador y dar de alta a un vendedor nuevo
 * son parte de la captura, y la captura la hace cualquier usuario
 * autenticado. No hay permiso `personas.*` en el esquema de la FASE 5 ni
 * decision de negocio que lo exija, asi que la policy es la puerta abierta
 * pero DOCUMENTADA (todo endpoint nuevo tiene que pasar por aqui), igual que
 * el middleware de ruta exige sesion: dos capas que dicen lo mismo.
 *
 * La escritura sobre una persona existente no se autoriza aqui: reutiliza el
 * N registrado (N5) y el alta nunca pisa datos.
 */
class PersonaPolicy
{
    public function viewAny(User $usuario): bool
    {
        return true;
    }

    public function create(User $usuario): bool
    {
        return true;
    }
}

<?php

namespace App\Policies;

use App\Enums\EstadoLiquidacion;
use App\Models\Liquidacion;
use App\Models\User;

/**
 * Quien puede ver y quien puede mover cada liquidacion (D3 + 4.2).
 *
 * Esta Policy es la CUARTA capa de defensa de la maquina de estados, no la
 * segunda: el permiso dice SI el rol puede ejecutar la transicion y el enum
 * dice SI el estado actual permite pasar al destino. Las dos se preguntan
 * siempre, porque una sin la otra no alcanza:
 *
 *   - permiso sin estado  -> el gerente no puede aprobar algo en `creado`.
 *   - estado sin permiso  -> el tecnico no puede aprobar, aunque su
 *     liquidacion este `revisado` (4.2, separacion de responsabilidades).
 *
 * Dos decisiones que aqui estan codificadas y que en los documentos son
 * explicitas:
 *
 *   1. D3: `ver_todas` (admin, gerente, revisor) vs `ver_propias`
 *      (tecnico). Implementado en Policy y no en el controlador para que
 *      ningun endpoint nuevo pueda olvidarse de filtrar (3.5b).
 *   2. D2: una liquidacion `aprobado` es terminal e inmutable para TODOS,
 *      el administrador incluido. Se consigue gratis: `puedeTransicionA()`
 *      devuelve false y `esEditable()` tambien, asi que ninguna transicion
 *      ni la edicion pasan, sin importar el permiso que se tenga.
 */
class LiquidacionPolicy
{
    /** Listado: quien tiene algun permiso de lectura entra al index. */
    public function viewAny(User $usuario): bool
    {
        return $usuario->can('liquidaciones.ver')
            || $usuario->can('liquidaciones.ver_todas')
            || $usuario->can('liquidaciones.ver_propias');
    }

    /**
     * Detalle. El revisor ve todas (las ajenas mas las propias) porque sin
     * ver el trabajo de otros el paso de revision es imposible: ver D3,
     * pendiente de confirmar en el documento.
     *
     * `liquidaciones.ver` da acceso solo a lo propio (4.2), asi que no
     * alcanza por si solo: quien no tiene `ver_todas` tiene que ser el autor.
     */
    public function view(User $usuario, Liquidacion $liquidacion): bool
    {
        if ($this->veTodas($usuario)) {
            return true;
        }

        return ($usuario->can('liquidaciones.ver') || $usuario->can('liquidaciones.ver_propias'))
            && $this->esPropia($usuario, $liquidacion);
    }

    public function create(User $usuario): bool
    {
        return $usuario->can('liquidaciones.crear');
    }

    /**
     * Editar datos. Solo `creado` y `en_correccion` son editables (4.1) y
     * solo las propias: el tecnico no toca lo que ya salio de sus manos.
     */
    public function update(User $usuario, Liquidacion $liquidacion): bool
    {
        return $usuario->can('liquidaciones.editar')
            && $liquidacion->estado->esEditable()
            && $this->esPropia($usuario, $liquidacion);
    }

    /**
     * Borrado logico. Dos cortes, ambos de la decision D2: el estado tiene
     * que poder ir a `eliminado` (solo `creado`) y el que borra tiene que
     * ser el autor. Borrar en revision o en aprobado por la puerta de atras
     * anularia D2.
     */
    public function delete(User $usuario, Liquidacion $liquidacion): bool
    {
        return $usuario->can('liquidaciones.eliminar')
            && $liquidacion->estado->puedeTransicionA(EstadoLiquidacion::Eliminado)
            && $this->esPropia($usuario, $liquidacion);
    }

    /** creado/en_correccion -> en_revision. Solo el autor reenvia lo suyo. */
    public function enviarRevision(User $usuario, Liquidacion $liquidacion): bool
    {
        return $usuario->can('liquidaciones.enviar_revision')
            && $liquidacion->estado->puedeTransicionA(EstadoLiquidacion::EnRevision)
            && $this->esPropia($usuario, $liquidacion);
    }

    /** en_revision -> revisado. */
    public function revisar(User $usuario, Liquidacion $liquidacion): bool
    {
        return $usuario->can('liquidaciones.revisar')
            && $liquidacion->estado->puedeTransicionA(EstadoLiquidacion::Revisado);
    }

    /** Devuelve para correccion: -> rechazado, con observaciones. */
    public function solicitarCorreccion(User $usuario, Liquidacion $liquidacion): bool
    {
        return $usuario->can('liquidaciones.solicitar_correccion')
            && $liquidacion->estado->puedeTransicionA(EstadoLiquidacion::Rechazado);
    }

    public function rechazar(User $usuario, Liquidacion $liquidacion): bool
    {
        return $usuario->can('liquidaciones.rechazar')
            && $liquidacion->estado->puedeTransicionA(EstadoLiquidacion::Rechazado);
    }

    /**
     * revisado -> aprobado, y NUNCA la propia: separacion de
     * responsabilidades (4.2). El tecnico que captura no aprueba lo suyo
     * aunque le den el permiso. El corte es el de la linea 751 de
     * instrucciones.txt: usuario_registro_id === auth()->id() -> 403.
     */
    public function aprobar(User $usuario, Liquidacion $liquidacion): bool
    {
        return $usuario->can('liquidaciones.aprobar')
            && $liquidacion->estado->puedeTransicionA(EstadoLiquidacion::Aprobado)
            && ! $this->esPropia($usuario, $liquidacion);
    }

    /**
     * -> anulado. NUNCA desde `aprobado`: el usuario de negocio confirmo el
     * 2026-10-05 que una aprobada no se anula, solo se rechaza antes de
     * aprobarla. El enum ya lo garantiza; aqui se expresa la razon.
     */
    public function anular(User $usuario, Liquidacion $liquidacion): bool
    {
        return $usuario->can('liquidaciones.anular')
            && $liquidacion->estado->puedeTransicionA(EstadoLiquidacion::Anulado);
    }

    /** Comentar en el flujo (3.6). Mismos criterios que ver. */
    public function comentar(User $usuario, Liquidacion $liquidacion): bool
    {
        return $usuario->can('liquidaciones.comentar')
            && ($this->veTodas($usuario) || $this->esPropia($usuario, $liquidacion));
    }

    /** D3: acceso global al listado y al detalle. */
    private function veTodas(User $usuario): bool
    {
        return $usuario->can('liquidaciones.ver_todas');
    }

    /** `usuario_registro_id = auth()->id()`. */
    private function esPropia(User $usuario, Liquidacion $liquidacion): bool
    {
        return $liquidacion->usuario_registro_id === $usuario->id;
    }
}

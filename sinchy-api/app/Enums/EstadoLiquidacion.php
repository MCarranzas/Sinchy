<?php

namespace App\Enums;

/**
 * Estados posibles de una liquidacion. Es el PHP del ENUM
 * `liquidacion_estado` de PostgreSQL: el mismo orden, los mismos valores.
 *
 * La maquina de estados es la de docs/reglas-negocio.md (decision D2), que es
 * la que manda sobre instrucciones.txt. Dos decisiones del usuario de negocio
 * conviven aqui:
 *
 *  - `aprobado` y `rechazado` son TERMINALES e inmutables: una aprobada no se
 *    toca jamas, y una rechazada tampoco. El rechazo es final, no abre
 *    correccion.
 *  - "Devolver" (decision 2026-10-12) es una accion APARTE del rechazo: el
 *    revisor devuelve al tecnico con una observacion (`en_revision` ->
 *    `en_correccion`, donde el autor corrige y reenvia) y el gerente devuelve
 *    al revisor (`revisado` -> `en_revision`, donde el revisor re-revisa).
 *    `en_correccion` vuelve a ser alcanzable, pero solo por esa via.
 *
 * Capas de defensa (seccion 4.1):
 *   1. este enum  -> puedeTransicionA()
 *   2. Services/TransicionEstadoService.php  -> validacion + historial
 *   3. el ENUM de PostgreSQL                 -> ultima linea
 *   4. LiquidacionPolicy                    -> quien puede
 *
 * Prohibido cambiar el estado con `$liquidacion->update(['estado' => ...])`.
 */
enum EstadoLiquidacion: string
{
    case Creado = 'creado';
    case EnRevision = 'en_revision';
    case Revisado = 'revisado';
    case Aprobado = 'aprobado';
    case Rechazado = 'rechazado';
    case EnCorreccion = 'en_correccion';
    case Anulado = 'anulado';
    case Eliminado = 'eliminado';

    /**
     * Transiciones validas desde cada estado.
     *
     * @var array<string, array<string>>
     */
    private const TRANSICIONES = [
        'creado' => ['en_revision', 'anulado', 'eliminado'],
        // `en_correccion`: el revisor devuelve al tecnico para que corrija.
        'en_revision' => ['revisado', 'rechazado', 'anulado', 'en_correccion'],
        // `en_revision`: el gerente devuelve al revisor para que corrija su revision.
        'revisado' => ['aprobado', 'rechazado', 'anulado', 'en_revision'],
        'rechazado' => [], // TERMINAL, inmutable. Confirmado por el negocio 2026-10-12: sin correccion.
        'en_correccion' => ['en_revision', 'anulado'], // vive: solo se alcanza desde en_revision (devolver).
        'aprobado' => [],  // TERMINAL, inmutable. Confirmado por el negocio 2026-10-05.
        'anulado' => [],   // terminal: es cancelar un tramite en curso.
        'eliminado' => [], // terminal: borrado logico, jamas se borra de la BD.
    ];

    /**
     * Estados a los que se puede pasar desde este.
     *
     * @return array<string, self>
     */
    public function transiciones(): array
    {
        return array_map(
            fn (string $estado): self => self::from($estado),
            self::TRANSICIONES[$this->value],
        );
    }

    public function puedeTransicionA(self $destino): bool
    {
        return in_array($destino->value, self::TRANSICIONES[$this->value], true);
    }

    /**
     * Un estado terminal no tiene salida: aprobado, anulado y eliminado.
     */
    public function esTerminal(): bool
    {
        return self::TRANSICIONES[$this->value] === [];
    }

    /**
     * Solo `creado` y `en_correccion` aceptan edicion de los datos (4.1).
     */
    public function esEditable(): bool
    {
        return in_array($this, [self::Creado, self::EnCorreccion], true);
    }

    /**
     * Etiqueta para la UI. No se codifica en la API: el frontend puede usar
     * el valor crudo y traducir, pero el backend la expone en resources.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Creado => 'Creado',
            self::EnRevision => 'En revision',
            self::Revisado => 'Revisado',
            self::Aprobado => 'Aprobado',
            self::Rechazado => 'Rechazado',
            self::EnCorreccion => 'En correccion',
            self::Anulado => 'Anulado',
            self::Eliminado => 'Eliminado',
        };
    }
}

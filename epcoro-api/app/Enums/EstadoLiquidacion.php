<?php

namespace App\Enums;

/**
 * Estados posibles de una liquidacion. Es el PHP del ENUM
 * `liquidacion_estado` de PostgreSQL: el mismo orden, los mismos valores.
 *
 * La maquina de estados es la de docs/reglas-negocio.md (decision D2), que es
 * la que manda sobre instrucciones.txt. La fila `aprobado` esta CONFIRMADA
 * por el usuario de negocio como terminal e inmutable; las demas transiciones
 * siguen siendo la propuesta de esa tabla y estan pendientes de confirmacion
 * una por una (punto abierto 3).
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
        'en_revision' => ['revisado', 'rechazado', 'anulado'],
        'revisado' => ['aprobado', 'rechazado', 'anulado'],
        'rechazado' => ['en_correccion', 'anulado'],
        'en_correccion' => ['en_revision', 'anulado'],
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

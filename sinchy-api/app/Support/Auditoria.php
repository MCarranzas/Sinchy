<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Registro de acciones sensibles (FASE 11.5).
 *
 * Escribe en el canal `auditoria` (ver config/logging.php), que es un archivo
 * aparte del log de aplicacion: lo que aqui se guarda no es depuracion, es la
 * constancia de quien hizo que y cuando. La tabla
 * `liquidacion_estado_historial` ya cumple ese papel para las transiciones de
 * una liquidacion; esto lo complementa con lo que NO tiene tabla propia
 * (login, exportaciones, cambios de parametros) y con los datos de la
 * peticion (IP, agente) que el historial no guarda.
 *
 * Cada linea lleva `accion` y un `contexto` en JSON, nunca la contrasena ni
 * cualquier secreto. Los eventos de autenticacion son los que exige la
 * seccion 11.5: login, logout, intento fallido y bloqueo de cuenta.
 */
final class Auditoria
{
    public const LOGIN = 'login';

    public const LOGOUT = 'logout';

    public const LOGIN_FALLIDO = 'login_fallido';

    public const CUENTA_BLOQUEADA = 'cuenta_bloqueada';

    public const PASSWORD_CAMBIADA = 'password_cambiada';

    public const TRANSICION = 'transicion';

    public const PARAMETROS = 'parametros_actualizados';

    public const EXPORTACION = 'exportacion';

    public const RESPALDO = 'respaldo_base_datos';

    /**
     * @param  array<string, mixed>  $contexto
     */
    public static function registrar(string $accion, array $contexto = []): void
    {
        Log::channel('auditoria')->info($accion, array_merge(
            self::desdeLaPeticion(),
            $contexto,
        ));
    }

    /**
     * Los datos del actor y de la peticion. Fuera de una peticion HTTP (por
     * ejemplo el respaldo por consola) solo queda el usuario autenticado si
     * lo hay; el resto de claves se omite en vez de escribirse como null.
     *
     * @return array<string, mixed>
     */
    private static function desdeLaPeticion(): array
    {
        $contexto = [];

        $usuario = auth()->user();

        if ($usuario instanceof User) {
            $contexto['usuario_id'] = $usuario->id;
            $contexto['usuario'] = $usuario->username;
        }

        if (! app()->runningInConsole() && app('request') !== null) {
            $contexto['ip'] = app('request')->ip();
            $contexto['agente'] = Str::limit((string) app('request')->userAgent(), 200, '');
        }

        return $contexto;
    }
}

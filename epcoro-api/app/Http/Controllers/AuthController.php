<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Models\SesionRegistro;
use App\Models\User;
use App\Support\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    /**
     * Inicia sesion con usuario y contrasena.
     *
     * Orden de las comprobaciones, y por que en ese orden:
     *  1. bloqueado por intentos fallidos -> 429, porque el usuario no tiene
     *     nada que hacer hasta que expire y hay que decirle cuanto;
     *  2. credenciales -> 422 generico, que no revela si el usuario existe;
     *  3. cuenta inactiva -> 403, que si revela. Es una decision consciente:
     *     en un sistema interno de la entidad los usuarios ya saben quien
     *     existe, y "tu cuenta esta desactivada" es accionable mientras que
     *     "credenciales incorrectas" solo confunde. Si algun dia esto se
     *     expone fuera de la organizacion, este 403 es lo primero que hay que
     *     cambiar.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->credentials();
        $intentosMaximos = (int) config('autenticacion.intentos_maximos');

        // 1. Bloqueo por intentos fallidos.
        $usuarioBloqueado = User::query()
            ->where('username', $credentials['username'])
            ->first();

        if ($usuarioBloqueado !== null && $usuarioBloqueado->estaBloqueado()) {
            Auditoria::registrar(Auditoria::CUENTA_BLOQUEADA, [
                'usuario' => $credentials['username'],
                'minutos_restantes' => $usuarioBloqueado->minutosDeBloqueoRestantes(),
            ]);

            return response()->json([
                'message' => 'La cuenta esta bloqueada por intentos fallidos. Intenten de nuevo mas tarde.',
                'minutos_restantes' => $usuarioBloqueado->minutosDeBloqueoRestantes(),
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        // 2. Credenciales.
        if (! Auth::attempt($credentials, (bool) $request->boolean('remember'))) {
            if ($usuarioBloqueado !== null) {
                $this->registrarIntentoFallido($usuarioBloqueado, $intentosMaximos);
            }

            Auditoria::registrar(Auditoria::LOGIN_FALLIDO, [
                'usuario' => $credentials['username'],
                'intentos_fallidos' => $usuarioBloqueado?->fresh()?->intentos_fallidos,
            ]);

            throw ValidationException::withMessages([
                'username' => 'Las credenciales no son correctas.',
            ]);
        }

        // 3. Cuenta inactiva. Se comprueba DESPUES de validar la contrasena a
        //    proposito: si se hiciera antes, bastaria con conocer un usuario
        //    valido para enumerar quienes estan activos.
        /** @var User $usuario */
        $usuario = Auth::user();

        if (! $usuario->activo) {
            Auth::logout();

            throw ValidationException::withMessages([
                'username' => 'Las credenciales no son correctas.',
            ]);
        }

        $usuario->forceFill([
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
            'ultimo_acceso' => now(),
        ])->save();

        // regenerate(true) y no regenerate(): el parametro $destroy viene en
        // false por defecto, asi que la sesion previa NO se borra de la tabla
        // sessions y queda una fila huerfana con el user_id ya escrito. Con
        // true se destruye antes de emitir el identificador nuevo, que es lo
        // que evita el fixation y ademas no acumula filas.
        $request->session()->regenerate(true);

        $this->registrarIngresoSesion($request, $usuario);

        Auditoria::registrar(Auditoria::LOGIN, [
            'usuario_id' => $usuario->id,
            'usuario' => $usuario->username,
        ]);

        return response()->json([
            'message' => 'Sesion iniciada correctamente.',
            'usuario' => $usuario->fresh()->perfilParaApi(),
        ]);
    }

    /**
     * Cierra la sesion del usuario actual.
     *
     * Se invalida la sesion en servidor y ademas se borra la cookie de sesion.
     * Sin el segundo paso el navegador sigue enviando un identificador que ya
     * no vale, que es una de las formas clasicas de fixation de sesion.
     */
    public function logout(Request $request): JsonResponse
    {
        $usuario = $request->user();

        // Se captura ANTES de invalidar: invalidate() regenera el id y ya no
        // se podria correlacionar con la fila abierta en la bitacora.
        $sessionId = $request->session()->getId();

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $this->cerrarSesionRegistrada($sessionId);

        if ($usuario instanceof User) {
            Auditoria::registrar(Auditoria::LOGOUT, [
                'usuario_id' => $usuario->id,
                'usuario' => $usuario->username,
            ]);
        }

        return response()->json([
            'message' => 'Sesion cerrada correctamente.',
        ]);
    }

    /**
     * Datos del usuario autenticado.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        return response()->json([
            'usuario' => $usuario->perfilParaApi(),
        ]);
    }

    /**
     * Cambia la contrasena del usuario autenticado.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        if (! Hash::check($request->string('password_actual')->toString(), $usuario->password)) {
            throw ValidationException::withMessages([
                'password_actual' => 'La contrasena actual no es correcta.',
            ]);
        }

        if (config('autenticacion.exigir_contrasena_distinta')
            && Hash::check($request->string('password')->toString(), $usuario->password)) {
            throw ValidationException::withMessages([
                'password' => 'La nueva contrasena debe ser distinta de la actual.',
            ]);
        }

        $request->cambiar();

        $sessionIdAnterior = $request->session()->getId();

        $this->cerrarLasOTRASSesiones($request, $usuario);
        $request->session()->regenerate(true);

        // regenerate(true) cambia el id de la sesion actual: hay que reapuntar
        // su fila abierta o el logout posterior ya no la encontraria.
        $this->reapuntarSesionRegistrada($sessionIdAnterior, $request->session()->getId());

        Auditoria::registrar(Auditoria::PASSWORD_CAMBIADA, [
            'usuario_id' => $usuario->id,
            'usuario' => $usuario->username,
        ]);

        return response()->json([
            'message' => 'Contrasena actualizada correctamente.',
        ]);
    }

    /**
     * Cierra las demas sesiones del usuario y deja viva la actual.
     *
     * Tras cambiar la contrasena, si el usuario esta logueado en dos equipos, el
     * otro equipo sigue con una credencial que el usuario acaba de invalidar.
     *
     * NO se usa Auth::logoutOtherDevices() a proposito: ese metodo solo marca el
     * hash en la sesion y espera que el middleware AuthenticateSession invalide
     * al resto. AuthenticateSession no esta en el grupo de middleware de la API,
     * asi que llamarlo no haria nada: pareceria seguro sin serlo. Con el driver
     * de sesion en base de datos, borrar las filas directamente si funciona.
     */
    private function cerrarLasOTRASSesiones(Request $request, User $usuario): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $idActual = $request->session()->getId();

        $otrasSesiones = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $usuario->id)
            ->where('id', '!=', $idActual)
            ->pluck('id');

        if ($otrasSesiones->isNotEmpty()) {
            SesionRegistro::query()
                ->whereIn('session_id', $otrasSesiones)
                ->whereNull('fecha_salida')
                ->update(['fecha_salida' => now()]);
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $usuario->id)
            ->where('id', '!=', $idActual)
            ->delete();
    }

    /**
     * Deja constancia permanente del ingreso: fecha, hora e IP del equipo.
     *
     * Se llama DESPUES de regenerate() para guardar el id definitivo de la
     * sesion, que es el que permite cerrarla luego en el logout.
     */
    private function registrarIngresoSesion(Request $request, User $usuario): void
    {
        SesionRegistro::query()->create([
            'user_id' => $usuario->id,
            'usuario_nombre' => $usuario->username,
            'session_id' => $request->session()->getId(),
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'fecha_ingreso' => now(),
        ]);
    }

    /**
     * Marca como cerrada la fila de la bitacora que corresponde a esa sesion.
     */
    private function cerrarSesionRegistrada(?string $sessionId): void
    {
        if ($sessionId === null) {
            return;
        }

        SesionRegistro::query()
            ->where('session_id', $sessionId)
            ->whereNull('fecha_salida')
            ->update(['fecha_salida' => now()]);
    }

    /**
     * Actualiza el id de sesion de la fila abierta tras regenerar el id.
     */
    private function reapuntarSesionRegistrada(?string $idAnterior, string $idNuevo): void
    {
        if ($idAnterior === null || $idAnterior === $idNuevo) {
            return;
        }

        SesionRegistro::query()
            ->where('session_id', $idAnterior)
            ->whereNull('fecha_salida')
            ->update(['session_id' => $idNuevo]);
    }

    /**
     * Suma un intento fallido y bloquea la cuenta al superar el maximo.
     */
    private function registrarIntentoFallido(User $usuario, int $intentosMaximos): void
    {
        $intentos = $usuario->intentos_fallidos + 1;

        $atributos = ['intentos_fallidos' => $intentos];

        if ($intentos >= $intentosMaximos) {
            $atributos['bloqueado_hasta'] = now()->addMinutes(
                (int) config('autenticacion.minutos_bloqueo')
            );
        }

        $usuario->forceFill($atributos)->save();
    }
}

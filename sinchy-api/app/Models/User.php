<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Las anotaciones @property no son decorativas: el metodo casts() declara los
 * tipos como string, asi que sin ellas PHPStan (y el IDE) ven `bloqueado_hasta`
 * como string y no dejan llamar isFuture(). Los casts siguen siendo la fuente
 * de verdad en tiempo de ejecucion; esto solo le dice al analizador que el
 * atributo ya viene convertido a Carbon.
 *
 * @property int $id
 * @property string $username
 * @property string|null $email
 * @property string $nombre_completo
 * @property bool $activo
 * @property int $intentos_fallidos
 * @property Carbon|null $bloqueado_hasta
 * @property Carbon|null $ultimo_acceso
 * @property bool $debe_cambiar_password
 */
#[Fillable([
    'username',
    'email',
    'nombre_completo',
    'password',
    'activo',
    'debe_cambiar_password',
])]
#[Hidden(['password', 'remember_token', 'intentos_fallidos', 'bloqueado_hasta'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'activo' => 'boolean',
            'debe_cambiar_password' => 'boolean',
            'intentos_fallidos' => 'integer',
            'bloqueado_hasta' => 'datetime',
            'ultimo_acceso' => 'datetime',
        ];
    }

    /**
     * Slug del unico rol del usuario.
     *
     * En SINCHI WAYRA cada usuario tiene exactamente un rol, asi que se expone como
     * un string y no como una relacion. Centralizarlo aqui evita que cada Policy
     * tenga que consultar el pivote de Spatie por separado.
     */
    public function rol(): ?string
    {
        return $this->getRoleNames()->first();
    }

    /**
     * El usuario esta bloqueado por intentos fallidos y el bloqueo no expiro.
     */
    public function estaBloqueado(): bool
    {
        return $this->bloqueado_hasta !== null && $this->bloqueado_hasta->isFuture();
    }

    /**
     * Tiempo restante de bloqueo, en minutos, o null si no esta bloqueado.
     *
     * El orden de los argumentos en diffInMinutes es now() -> hasta, NO al reves:
     * en Carbon 3 el resultado viene con signo y $hasta->diffInMinutes(now())
     * devuelve un numero NEGATIVO, que es justo lo que se veia antes.
     */
    public function minutosDeBloqueoRestantes(): ?int
    {
        if (! $this->estaBloqueado()) {
            return null;
        }

        /** @var Carbon $hasta */
        $hasta = $this->bloqueado_hasta;

        return max(1, (int) ceil(now()->diffInMinutes($hasta)));
    }

    /**
     * Los roles y permisos, listos para la respuesta de /api/auth/me.
     *
     * @return array<string, mixed>
     */
    public function perfilParaApi(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'nombre_completo' => $this->nombre_completo,
            'rol' => $this->rol(),
            'permisos' => $this->getAllPermissions()->pluck('name')->values()->all(),
            'activo' => $this->activo,
            'debe_cambiar_password' => $this->debe_cambiar_password,
            'ultimo_acceso' => $this->ultimo_acceso?->toIso8601String(),
        ];
    }
}

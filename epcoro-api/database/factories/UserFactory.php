<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * La contraseña de todos los usuarios creados por la factory.
     *
     * Es fija a proposito: los tests necesitan poder hacer login con una
     * contraseña conocida. En producción eso no debe pasar nunca, y por eso el
     * AdminSeeder genera una aleatoria en vez de usar esta factory.
     */
    public const PASSWORD = 'Prueba1234';

    protected $model = User::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = fake()->name();

        return [
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'nombre_completo' => $nombre,
            'password' => Hash::make(self::PASSWORD),
            'activo' => true,
            'intentos_fallidos' => 0,
            'debe_cambiar_password' => false,
            'remember_token' => Str::random(10),
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $atributos): array => [
            'activo' => false,
        ]);
    }

    /**
     * Usuario bloqueado hasta el momento indicado.
     */
    public function bloqueadoHasta(\DateTimeInterface $hasta): static
    {
        return $this->state(fn (array $atributos): array => [
            'bloqueado_hasta' => $hasta,
            'intentos_fallidos' => 5,
        ]);
    }

    /**
     * Usuario cuyo bloqueo ya expiro. Debe poder entrar con normalidad.
     */
    public function bloqueoExpirado(): static
    {
        return $this->state(fn (array $atributos): array => [
            'bloqueado_hasta' => now()->subMinute(),
            'intentos_fallidos' => 5,
        ]);
    }

    /**
     * Usuario con un rol de los cuatro de EPCORO.
     */
    public function conRol(string $rol): static
    {
        return $this->afterCreating(function (User $usuario) use ($rol): void {
            $usuario->assignRole($rol);
        });
    }
}

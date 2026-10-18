<?php

namespace Database\Factories;

use App\Models\Persona;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Persona>
 */
class PersonaFactory extends Factory
{
    protected $model = Persona::class;

    /**
     * `ci` y `nit` son IDENTIFICADORES y se generan como string de digitos
     * (correccion C5): si se generaran como entero, un NIT de 10 cifras
     * podria serializarse en notacion cientifica y perder ceros.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombres = fake()->firstName();
        $apellidos = fake()->lastName().' '.fake()->lastName();

        return [
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'nombre_completo' => $nombres.' '.$apellidos,
            'ci' => (string) fake()->unique()->numberBetween(1000000, 89999999),
            'nit' => (string) fake()->unique()->numberBetween(1000000000, 8999999999),
            'tipo_documento' => 'CI',
            'email' => fake()->unique()->safeEmail(),
            'telefono' => fake()->numerify('#######'),
            'direccion' => fake()->address(),
            'activo' => true,
        ];
    }

    public function inactiva(): static
    {
        return $this->state(fn (array $atributos): array => [
            'activo' => false,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\TipoCambio;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<TipoCambio>
 */
class TipoCambioFactory extends Factory
{
    protected $model = TipoCambio::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // `fecha` es UNIQUE: se parte de un dia aleatorio de 2026 sin
            // repetir dentro de la misma corrida.
            'fecha' => Carbon::create(2026, 1, 1)
                ->addDays(fake()->unique()->numberBetween(0, 364))
                ->format('Y-m-d'),
            // String, no float: conserva los 4 decimales exactos (regla T3).
            'valor' => (string) fake()->randomFloat(4, 6, 8),
            'fuente' => fake()->randomElement(['BCB', 'Manual']),
        ];
    }

    /**
     * El valor del formulario de prueba (6.9100 Bs/$).
     */
    public function delCasoOro(): static
    {
        return $this->state(fn (array $atributos): array => [
            'valor' => '6.9100',
            'fuente' => 'BCB',
        ]);
    }
}

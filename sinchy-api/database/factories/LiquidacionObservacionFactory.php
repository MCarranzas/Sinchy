<?php

namespace Database\Factories;

use App\Models\Liquidacion;
use App\Models\LiquidacionObservacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LiquidacionObservacion>
 */
class LiquidacionObservacionFactory extends Factory
{
    protected $model = LiquidacionObservacion::class;

    /**
     * `usuario_id` es NOT NULL: no existe observacion sin autor.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'liquidacion_id' => Liquidacion::factory(),
            'usuario_id' => User::factory(),
            'tipo' => fake()->randomElement(['comentario', 'solicitud', 'observacion']),
            'texto' => fake()->sentence(8),
        ];
    }

    public function comentario(string $texto): static
    {
        return $this->state(fn (array $atributos): array => [
            'tipo' => 'comentario',
            'texto' => $texto,
        ]);
    }
}

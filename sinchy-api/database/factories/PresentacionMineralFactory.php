<?php

namespace Database\Factories;

use App\Models\PresentacionMineral;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PresentacionMineral>
 */
class PresentacionMineralFactory extends Factory
{
    protected $model = PresentacionMineral::class;

    /**
     * El nombre es UNIQUE en la tabla, de ahi el `unique()` del generador.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => strtoupper(fake()->unique()->words(2, true)),
        ];
    }

    /**
     * La presentacion real de hoja.xlsx (celda C37).
     */
    public function oroFundido(): static
    {
        return $this->state(fn (array $atributos): array => [
            'nombre' => 'ORO FUNDIDO',
        ]);
    }
}

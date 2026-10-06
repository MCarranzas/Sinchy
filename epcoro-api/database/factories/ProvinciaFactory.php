<?php

namespace Database\Factories;

use App\Models\Departamento;
use App\Models\Provincia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Provincia>
 */
class ProvinciaFactory extends Factory
{
    protected $model = Provincia::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'departamento_id' => Departamento::factory(),
            'codigo' => str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'nombre' => strtoupper(fake()->city()),
        ];
    }
}

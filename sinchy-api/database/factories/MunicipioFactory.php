<?php

namespace Database\Factories;

use App\Models\Municipio;
use App\Models\Provincia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Municipio>
 */
class MunicipioFactory extends Factory
{
    protected $model = Municipio::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provincia_id' => Provincia::factory(),
            'codigo' => str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'nombre' => strtoupper(fake()->city()),
        ];
    }
}

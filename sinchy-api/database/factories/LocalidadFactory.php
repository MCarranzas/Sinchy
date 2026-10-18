<?php

namespace Database\Factories;

use App\Models\Localidad;
use App\Models\Municipio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Localidad>
 */
class LocalidadFactory extends Factory
{
    protected $model = Localidad::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'municipio_id' => Municipio::factory(),
            'codigo' => str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'nombre' => strtoupper(fake()->streetName()),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Comprador;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comprador>
 */
class CompradorFactory extends Factory
{
    protected $model = Comprador::class;

    /**
     * `nim` y `nit` son IDENTIFICADORES y van como string de digitos/guiones
     * (correccion C5).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'razon_social' => 'SINCHI WAYRA S.A.',
            'nim' => '02-2388-05',
            'nit' => '1006965023',
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $atributos): array => [
            'activo' => false,
        ]);
    }
}

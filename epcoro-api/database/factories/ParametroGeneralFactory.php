<?php

namespace Database\Factories;

use App\Models\ParametroGeneral;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParametroGeneral>
 */
class ParametroGeneralFactory extends Factory
{
    protected $model = ParametroGeneral::class;

    /**
     * La PK es `clave`, no un id: por eso no hay `id` en la definicion.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clave' => strtoupper(fake()->unique()->bothify('PARAMETRO_####')),
            'valor' => (string) fake()->randomFloat(4, 1, 100),
            'tipo' => 'decimal',
            'descripcion' => fake()->sentence(),
        ];
    }

    /**
     * Parametro de texto, para los que no son numericos (NIT, NIM, razones
     * sociales). La clave se recibe porque el nombre del parametro es el dato,
     * no algo que se pueda inventar.
     */
    public function texto(string $clave, string $valor): static
    {
        return $this->state(fn (array $atributos): array => [
            'clave' => $clave,
            'valor' => $valor,
            'tipo' => 'texto',
        ]);
    }
}

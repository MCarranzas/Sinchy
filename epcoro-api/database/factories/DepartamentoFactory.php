<?php

namespace Database\Factories;

use App\Models\Departamento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Departamento>
 */
class DepartamentoFactory extends Factory
{
    protected $model = Departamento::class;

    /**
     * El codigo es UNIQUE y el nombre tambien, asi que se resuelven juntos:
     * el nombre lleva el codigo pegado, de modo que dos departamentos
     * generados nunca chocan aunque Faker repita topónimo.
     *
     * El rango del codigo es de 4 digitos (9999 combinaciones) y no el de 2
     * del INE: en un solo test se crean decenas de departamentos porque cada
     * area minera y cada municipio arrastra su propia jerarquia, y con el
     * rango de 9 del INE el pool `unique()` de Faker se agota y lanza
     * OverflowException.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $codigo = str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT);

        return [
            'codigo' => $codigo,
            'nombre' => strtoupper(fake()->city()).' '.$codigo,
        ];
    }
}

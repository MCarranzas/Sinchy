<?php

namespace Database\Factories;

use App\Models\CotizacionQuincenal;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<CotizacionQuincenal>
 */
class CotizacionQuincenalFactory extends Factory
{
    protected $model = CotizacionQuincenal::class;

    /**
     * Las fechas se generan como par de 15 dias sin pretender que el numero
     * de quincena derive de ellas: el calendario exacto de quincenas (1..6)
     * es una regla de negocio que todavia no esta confirmada, y codificarla
     * aqui seria inventarla.
     *
     * El par (ejercicio, quincena) es UNIQUE en la tabla, asi que se genera
     * desde un unico contador repartido sobre 10 ejercicios x 6 quincenas:
     * 60 combinaciones posibles antes de agotar el pool de `unique()`, que en
     * Faker lanza OverflowException en lugar de repetir un valor.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $indice = fake()->unique()->numberBetween(0, 59);
        $ejercicio = 2024 + intdiv($indice, 6);
        $inicio = Carbon::create($ejercicio, fake()->numberBetween(1, 12), 1)
            ->addDays(fake()->randomElement([0, 15]));

        return [
            'ejercicio' => $ejercicio,
            'quincena' => ($indice % 6) + 1,
            'fecha_inicio' => $inicio->format('Y-m-d'),
            'fecha_fin' => $inicio->copy()->addDays(14)->format('Y-m-d'),
            // Se genera como string: un float convertiria 2648.57 en
            // 2648.5699999999997, que es exactamente el error que la regla
            // T3 busca evitar.
            'valor_usd_oz_t' => (string) fake()->randomFloat(4, 1500, 3000),
            'fuente' => fake()->randomElement(['MINEM', 'LMMA', 'LBMA']),
        ];
    }

    /**
     * Cotizacion del caso de prueba ORO (2648.57 $/OzT).
     */
    public function delCasoOro(): static
    {
        return $this->state(fn (array $atributos): array => [
            'ejercicio' => 2024,
            'quincena' => 6,
            'fecha_inicio' => '2024-12-01',
            'fecha_fin' => '2024-12-15',
            'valor_usd_oz_t' => '2648.5700',
            'fuente' => 'MINEM',
        ]);
    }
}

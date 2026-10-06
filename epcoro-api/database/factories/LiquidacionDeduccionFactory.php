<?php

namespace Database\Factories;

use App\Models\Liquidacion;
use App\Models\LiquidacionDeduccion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LiquidacionDeduccion>
 */
class LiquidacionDeduccionFactory extends Factory
{
    protected $model = LiquidacionDeduccion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $alicuota = '0.0180';
        $base = (string) fake()->randomFloat(2, 100, 100000);

        return [
            'liquidacion_id' => Liquidacion::factory(),
            'concepto' => 'APORTE CAJA DE SALUD - LEY 649',
            'tipo' => 'alicuota',
            'alicuota' => $alicuota,
            'base_monto' => $base,
            // Calculado con bcmath a escala 20 y redondeo half-up, igual que
            // el servicio de la FASE 5, para que la fila sea coherente.
            'monto' => $this->redondear($base, $alicuota),
            'obligatorio' => true,
            'orden' => 1,
        ];
    }

    /**
     * El unico aporte de la D1 con la alicuota oficial (1.8 % sobre el
     * bruto, sin redondear la base: D4).
     */
    public function aporteCajaSalud(string $base): static
    {
        return $this->state(fn (array $atributos): array => [
            'concepto' => 'APORTE CAJA DE SALUD - LEY 649',
            'alicuota' => '0.0180',
            'base_monto' => $base,
            'monto' => $this->redondear($base, '0.0180'),
        ]);
    }

    private function redondear(string $base, string $alicuota): string
    {
        $exacto = bcdiv(bcmul($base, $alicuota, 20), '1', 20);
        $factor = bcpow('10', '2', 0);

        return bcdiv(bcadd(bcmul($exacto, $factor, 30), '0.5', 0), $factor, 2);
    }
}

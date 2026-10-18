<?php

namespace Database\Factories;

use App\Enums\EstadoLiquidacion;
use App\Models\Liquidacion;
use App\Models\LiquidacionEstadoHistorial;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LiquidacionEstadoHistorial>
 */
class LiquidacionEstadoHistorialFactory extends Factory
{
    protected $model = LiquidacionEstadoHistorial::class;

    /**
     * `usuario_id` es una factory porque el historial necesita un responsable;
     * el nombre se copia del usuario para conservar el snapshot (mismo
     * criterio que en la liquidacion: si el usuario se renombra, el
     * historial no cambia).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $usuario = User::factory()->create();

        return [
            'liquidacion_id' => Liquidacion::factory(),
            'estado_anterior' => null,
            'estado_nuevo' => EstadoLiquidacion::Creado,
            'usuario_id' => $usuario,
            'usuario_nombre' => $usuario->nombre_completo,
            'comentario' => fake()->sentence(6),
        ];
    }

    /**
     * Transicion creado > en_revision, que es el primer movimiento del flujo
     * (quien captura envia a revisar).
     */
    public function envioARevision(): static
    {
        return $this->state(fn (array $atributos): array => [
            'estado_anterior' => EstadoLiquidacion::Creado,
            'estado_nuevo' => EstadoLiquidacion::EnRevision,
        ]);
    }
}

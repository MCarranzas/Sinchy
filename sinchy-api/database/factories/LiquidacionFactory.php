<?php

namespace Database\Factories;

use App\Enums\EstadoLiquidacion;
use App\Models\AreaMinera;
use App\Models\Liquidacion;
use App\Models\Persona;
use App\Models\PresentacionMineral;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Liquidacion>
 */
class LiquidacionFactory extends Factory
{
    protected $model = Liquidacion::class;

    /**
     * Vendedor, area minera y presentacion se crean ANTES de la liquidacion
     * y se le pasan como modelos, no como factories anidadas. El motivo es el
     * SNAPSHOT: vendedor_nombre/vendedor_ci, los cuatro *_nombre de la
     * procedencia y presentacion_nombre tienen que ser la COPIA de esos
     * registros, y una factory anidada solo entregaria el id, dejando el
     * snapshot con un valor inventado que no coincide con la fila referida.
     *
     * La ubicacion de la liquidacion sale del area minera, igual que en el
     * formulario: al elegir el area se autocompletan los cuatro niveles.
     *
     * Efecto secundario conocido: un `make()` tambien crea esas filas.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $vendedor = Persona::factory()->create();
        $area = AreaMinera::factory()->create();
        $presentacion = PresentacionMineral::factory()->create();

        $peso = (string) fake()->randomFloat(2, 1, 5000);
        $ley = (string) fake()->randomFloat(4, 0.5, 1);
        $pesoFino = bcmul($peso, $ley, 4);
        $cotizacion = (string) fake()->randomFloat(4, 1500, 3000);
        $tipoCambio = (string) fake()->randomFloat(4, 6, 8);
        $bruto = $this->bruto($pesoFino, $cotizacion, $tipoCambio);

        $numero = 'EP-BCB-'.fake()->unique()->numberBetween(1, 9999999);
        $ejercicio = 2024;

        return [
            'numero_documento' => $numero,
            'ejercicio' => $ejercicio,
            'fecha_transaccion' => fake()->dateTimeBetween('-2 years')->format('Y-m-d'),
            'usuario_registro_id' => User::factory(),
            'estado' => EstadoLiquidacion::Creado,

            'tipo_cambio' => $tipoCambio,
            'cotizacion_usd_oz_t' => $cotizacion,

            'comprador_razon_social' => 'SINCHI WAYRA S.A.',
            'comprador_nim' => '02-2388-05',
            'comprador_nit' => '1006965023',
            'aplica_caja_salud' => false,

            'vendedor_persona_id' => $vendedor,
            'vendedor_nombre' => $vendedor->nombre_completo,
            'vendedor_ci' => (string) $vendedor->ci,

            'area_minera_id' => $area,
            'nombre_area_minera' => $area->nombre_area_minera,
            'titular_derecho_minero' => $area->titular_derecho_minero,
            'codigo_ajam_patente' => $area->codigo_ajam_patente,
            'departamento_id' => $area->departamento_id,
            'departamento_nombre' => $area->departamento->nombre,
            'provincia_id' => $area->provincia_id,
            'provincia_nombre' => $area->provincia->nombre,
            'municipio_id' => $area->municipio_id,
            'municipio_nombre' => $area->municipio->nombre,
            'codigo_municipio' => $area->municipio->codigo,
            'localidad_id' => $area->localidad_id,
            'localidad_nombre' => $area->localidad->nombre,

            'presentacion_mineral_id' => $presentacion,
            'presentacion_nombre' => $presentacion->nombre,
            'peso_gramos' => $peso,
            'ley_pagable' => $ley,

            // Calculados con los mismos insumos que el servicio usara en la
            // FASE 5, para que la fila sea coherente por si un test los lee.
            'gramos_por_onza_troy' => '31.1035',
            'peso_fino_gramos' => $pesoFino,
            'valor_usd_gramo' => bcdiv($cotizacion, '31.1035', 6),
            'valor_bruto_bs' => $bruto,
            'aporte_caja_salud_bs' => '0.00',
            'total_deducciones_bs' => '0.00',
            'valor_neto_bs' => $bruto,
        ];
    }

    /** Liquidacion recien capturada, editable por su autor. */
    public function creada(): static
    {
        return $this->state(fn (array $atributos): array => [
            'estado' => EstadoLiquidacion::Creado,
        ]);
    }

    /** Enviada a revision: solo lectura para el tecnico. */
    public function enRevision(): static
    {
        return $this->state(fn (array $atributos): array => [
            'estado' => EstadoLiquidacion::EnRevision,
        ]);
    }

    /**
     * Aprobada. D2: a partir de ahi el registro es inmutable para TODOS,
     * incluido el administrador.
     */
    public function aprobada(): static
    {
        return $this->state(fn (array $atributos): array => [
            'estado' => EstadoLiquidacion::Aprobado,
            'fecha_aprobacion' => now(),
        ]);
    }

    /** Con el interruptor de caja de salud encendido (caso ORO-B). */
    public function conCajaDeSalud(): static
    {
        return $this->state(fn (array $atributos): array => [
            'aplica_caja_salud' => true,
        ]);
    }

    /**
     * Bruto con bcmath a escala 20 y redondeo half-up al final, que es como
     * lo calcula la FASE 5. La factory no reemplaza al servicio: existe para
     * que un test pueda leer una fila coherente sin montar todo el flujo.
     */
    private function bruto(string $pesoFino, string $cotizacion, string $tipoCambio): string
    {
        $exacto = bcdiv(bcmul(bcmul($cotizacion, $tipoCambio, 20), $pesoFino, 20), '31.1035', 20);

        $factor = bcpow('10', '2', 0);

        return bcdiv(bcadd(bcmul($exacto, $factor, 30), '0.5', 0), $factor, 2);
    }
}

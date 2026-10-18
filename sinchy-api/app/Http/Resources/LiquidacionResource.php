<?php

namespace App\Http\Resources;

use App\Models\Liquidacion;
use App\Support\TransicionesDisponibles;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * La forma en que viaja una liquidacion por la API (seccion 5: "nunca
 * devolver el modelo crudo").
 *
 * Tres razones para agrupar en vez de aplanar:
 *
 *  1. `calculo` es un bloque aparte porque es exactamente lo que T3 dice
 *     que NUNCA se recalcula: los seis montos salen juntos o no salen.
 *  2. `transiciones_disponibles` la calcula el backend (6.4): el frontend
 *     solo dibuja botones y no adivina permisos. La seguridad real sigue
 *     en la Policy; esto es solo para que la UI sea coherente con ella.
 *  3. Los montos van como CADENA. En la base son numeric y asi se leen;
 *     convertirlos a float aqui seria perder el centavo que separa
 *     1072018.84 de 1072018.83 en el camino de vuelta al navegador.
 *
 * @mixin Liquidacion
 */
class LiquidacionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $usuario = $request->user();

        return [
            'id' => $this->resource->id,
            'numero_documento' => $this->resource->numero_documento,
            'codigo_formulario' => $this->resource->codigo_formulario,
            'correlativo' => $this->resource->correlativo,
            'ejercicio' => $this->resource->ejercicio,
            'fecha_transaccion' => $this->resource->fecha_transaccion?->toDateString(),

            // ------------------------------------------------------- flujo
            'estado' => $this->resource->estado->value,
            'estado_label' => $this->resource->estado->etiqueta(),
            'transiciones_disponibles' => $usuario === null
                ? []
                : TransicionesDisponibles::paraUsuario($usuario, $this->resource),

            // ------------------------------------------------------ insumos
            'insumos' => [
                'peso_gramos' => $this->resource->peso_gramos,
                'ley_pagable' => $this->resource->ley_pagable,
                'tipo_cambio' => $this->resource->tipo_cambio,
                'cotizacion_usd_oz_t' => $this->resource->cotizacion_usd_oz_t,
                'cotizacion_id' => $this->resource->cotizacion_id,
                'aplica_caja_salud' => $this->resource->aplica_caja_salud,
                'gramos_por_onza_troy' => $this->resource->gramos_por_onza_troy,
            ],

            // ----------------------------------------------------- calculo
            'calculo' => [
                'peso_fino_gramos' => $this->resource->peso_fino_gramos,
                'valor_usd_gramo' => $this->resource->valor_usd_gramo,
                'valor_bruto_bs' => $this->resource->valor_bruto_bs,
                'aporte_caja_salud_bs' => $this->resource->aporte_caja_salud_bs,
                'total_deducciones_bs' => $this->resource->total_deducciones_bs,
                'valor_neto_bs' => $this->resource->valor_neto_bs,
            ],

            // ---------------------------------------------------- personas
            'comprador' => [
                'id' => $this->resource->comprador_id,
                'razon_social' => $this->resource->comprador_razon_social,
                'nim' => $this->resource->comprador_nim,
                'nit' => $this->resource->comprador_nit,
            ],
            'vendedor' => [
                'persona_id' => $this->resource->vendedor_persona_id,
                'nombre' => $this->resource->vendedor_nombre,
                'ci' => $this->resource->vendedor_ci,
            ],

            // -------------------------------------------------- procedencia
            'procedencia' => [
                'area_minera_id' => $this->resource->area_minera_id,
                'nombre_area_minera' => $this->resource->nombre_area_minera,
                'titular_derecho_minero' => $this->resource->titular_derecho_minero,
                'codigo_ajam_patente' => $this->resource->codigo_ajam_patente,
                'departamento' => ['id' => $this->resource->departamento_id, 'nombre' => $this->resource->departamento_nombre],
                'provincia' => ['id' => $this->resource->provincia_id, 'nombre' => $this->resource->provincia_nombre],
                'municipio' => ['id' => $this->resource->municipio_id, 'nombre' => $this->resource->municipio_nombre, 'codigo' => $this->resource->codigo_municipio],
                'localidad' => ['id' => $this->resource->localidad_id, 'nombre' => $this->resource->localidad_nombre],
            ],
            'presentacion' => [
                'id' => $this->resource->presentacion_mineral_id,
                'nombre' => $this->resource->presentacion_nombre,
            ],

            'observaciones' => $this->resource->observaciones,

            // ---------------------------------------------------- auditoria
            'auditoria' => [
                'registrado_por' => $this->resource->registro === null
                    ? null
                    : [
                        'id' => $this->resource->registro->id,
                        'nombre_completo' => $this->resource->registro->nombre_completo,
                        'username' => $this->resource->registro->username,
                    ],
                'fecha_registro' => $this->resource->fecha_registro?->toIso8601String(),
                'fecha_revision' => $this->resource->fecha_revision?->toIso8601String(),
                'fecha_aprobacion' => $this->resource->fecha_aprobacion?->toIso8601String(),
                'fecha_rechazo' => $this->resource->fecha_rechazo?->toIso8601String(),
                'motivo_rechazo' => $this->resource->motivo_rechazo,
                'fecha_anulacion' => $this->resource->fecha_anulacion?->toIso8601String(),
                'motivo_anulacion' => $this->resource->motivo_anulacion,
            ],
        ];
    }
}

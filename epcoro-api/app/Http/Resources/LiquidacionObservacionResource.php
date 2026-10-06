<?php

namespace App\Http\Resources;

use App\Models\LiquidacionObservacion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Comentario del flujo (3.6). El motivo formal de rechazo no vive aqui:
 * va en liquidaciones.motivo_rechazo y se expone en LiquidacionResource.
 *
 * @mixin LiquidacionObservacion
 */
class LiquidacionObservacionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'tipo' => $this->resource->tipo,
            'texto' => $this->resource->texto,
            'usuario' => [
                'id' => $this->resource->usuario_id,
                'nombre_completo' => $this->resource->usuario?->nombre_completo,
            ],
            'fecha' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}

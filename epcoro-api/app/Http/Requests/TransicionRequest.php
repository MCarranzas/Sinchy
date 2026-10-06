<?php

namespace App\Http\Requests;

use App\Enums\EstadoLiquidacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Transicion de estado (secciones 3.5 y 5.5).
 *
 * El permiso NO se valida aqui: depende de la transicion CONCRETA que se
 * pide (origen -> destino) y de quien la pide, asi que la Policy y el mapa
 * de habilidades viven en el controlador. Lo que si se exige aqui es la
 * razon del corte, porque es el unico dato que no se puede deducir despues:
 * un rechazo sin motivo deja al tecnico sin saber que arreglar.
 *
 * La distincion entre `solicitar_correccion` y `rechazar` es de la seccion
 * 4.2: dos caminos distintos hacia `rechazado`, uno desde la revision y otro
 * desde la aprobacion. El servicio solo ve el destino; el controlador
 * traduce el origen a la habilidad que toca.
 */
class TransicionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estado_destino' => ['required', Rule::enum(EstadoLiquidacion::class)],
            'comentario' => ['nullable', 'string', 'max:2000'],
            'motivo' => [
                'nullable',
                'string',
                'max:2000',
                // Obligatorio al rechazar (y por lo tanto al solicitar
                // correccion) y al anular: 4.2 y 9.2. Al aprobar y al enviar
                // a revision es opcional.
                'required_if:estado_destino,rechazado',
                'required_if:estado_destino,anulado',
            ],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\EstadoLiquidacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Transicion de estado (secciones 3.5 y 5.5).
 *
 * El permiso NO se valida aqui: depende de la transicion CONCRETA que se
 * pide (origen -> destino) y de quien la pide, asi que la Policy y el mapa
 * de habilidades viven en el controlador. Lo que si se exige aqui es la
 * razon del corte, porque es el unico dato que no se puede deducir despues:
 *
 *  - `motivo` obligatorio al rechazar (terminal) y al anular: opcional al
 *    aprobar y al reenviar. Una rechazada queda cerrada y el motivo es lo que
 *    el expediente guarda para siempre.
 *  - `comentario` (la observacion) obligatorio al DEVOLVER, ya sea el revisor
 *    al tecnico (`en_revision` -> `en_correccion`) o el gerente al revisor
 *    (`revisado` -> `en_revision`): es lo que el tecnico o el revisor leen
 *    para saber que corregir. No es el `motivo` de un rechazo: la liquidacion
 *    sigue viva.
 *
 * `rechazado` es terminal: desde `en_revision` y desde `revisado` se llega
 * con la habilidad `rechazar`, y desde ahi ya no hay salida. El servicio
 * solo ve el destino; el controlador traduce el origen a la habilidad que
 * toca.
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
                // Obligatorio al rechazar y al anular: 4.2 y 9.2. Al aprobar y
                // al enviar a revision es opcional.
                'required_if:estado_destino,rechazado',
                'required_if:estado_destino,anulado',
            ],
        ];
    }

    /**
     * La observacion (el `comentario`) es obligatoria al DEVOLVER, ya sea el
     * revisor al tecnico o el gerente al revisor. Se anade despues, con
     * `required` (regla implicita que corre aunque el campo venga vacio), del
     * mismo modo que `required_if` lo hace con el `motivo`.
     */
    public function withValidator(Validator $validator): void
    {
        if ($this->esDevolucion()) {
            $validator->addRules(['comentario' => ['required']]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'comentario.required' => 'La observacion es obligatoria al devolver la liquidacion.',
        ];
    }

    /**
     * El POST pide una devolucion (observacion obligatoria) cuando el destino
     * es `en_correccion` (devolver al tecnico) o cuando es `en_revision`
     * saliendo de `revisado` (devolver al revisor). El reenvio del autor a
     * `en_revision` desde `creado`/`en_correccion` NO es una devolucion.
     */
    private function esDevolucion(): bool
    {
        $destino = (string) $this->input('estado_destino');

        if ($destino === EstadoLiquidacion::EnCorreccion->value) {
            return true;
        }

        return $destino === EstadoLiquidacion::EnRevision->value
            && ($this->route('liquidacion')->estado ?? null) === EstadoLiquidacion::Revisado;
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Comentario en el flujo de una liquidacion (seccion 3.6).
 *
 * Solo sirve si el usuario puede ver el expediente: lo comprueba el policy
 * `comentar`, no este request, porque depende de si es propio o ajeno.
 */
class ObservacionRequest extends FormRequest
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
            'comentario' => ['required', 'string', 'max:2000'],
        ];
    }
}

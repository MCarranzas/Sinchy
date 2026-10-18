<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta rapida de persona (seccion 8.4): el vendedor nuevo no puede obligar a
 * salirse del formulario.
 *
 * `ci` va con los mismos cortes C5 que vendedor_ci en la liquidacion: es un
 * identificador, y si aqui se guardara con puntos o en notacion cientifica,
 * despues no pasaria la validacion del expediente.
 *
 * Si el CI ya existe no es error del request: el controlador devuelve la
 * persona existente (N5), porque el vendedor puede venir del Excel externo.
 */
class PersonaRequest extends FormRequest
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
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'ci' => ['required', 'string', 'regex:/^\d{1,10}$/'],
            'nit' => ['nullable', 'string', 'regex:/^\d{1,20}$/'],
            'tipo_documento' => ['nullable', 'string', 'in:CI,NIT,PAS'],
            'email' => ['nullable', 'email', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'direccion' => ['nullable', 'string', 'max:200'],
        ];
    }
}

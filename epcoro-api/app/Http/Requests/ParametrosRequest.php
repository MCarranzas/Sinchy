<?php

namespace App\Http\Requests;

use App\Models\ParametroGeneral;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Edicion de parametros_generales (seccion 3.3), solo administracion.
 *
 * Tres cortes que aqui importan:
 *
 *  1. Solo se ACTUALIZAN claves que ya existen. Los parametros nacen en el
 *     seeder (ParametrosSeeder) porque tienen tipo y descripcion, y una
 *     clave inventada en la peticion no tendria ninguno de los dos; ademas
 *     ALICUOTA_APORTE y GRAMOS_POR_ONZA_TROY son los dos valores que ponen
 *     el dinero en juego.
 *  2. C5 de nuevo: los valores son CADENAS. Se convierte el numero que
 *     llegue como number del JSON a texto ANTES de validar, y se rechaza la
 *     notacion cientifica, que es como un 0.00001 se convierte en 1.0E-5 y
 *     arruina la formula sin que nadie lo note.
 *  3. El rango de seguridad no se fija aqui: lo valida el servicio en cada
 *     calculo (ParametroInvalidoException -> 500). Cambiar la alicuota a 9
 *     tiene que estallar, no guardar.
 */
class ParametrosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('parametros.gestionar') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'parametros' => ['required', 'array', 'min:1', 'max:20'],
            'parametros.*' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * El valor puede llegar como number del JSON: a texto, antes de validar.
     */
    protected function prepareForValidation(): void
    {
        $parametros = $this->input('parametros');

        if (! is_array($parametros)) {
            return;
        }

        $sanitizados = [];

        foreach ($parametros as $clave => $valor) {
            $sanitizados[$clave] = is_int($valor) || is_float($valor) ? (string) $valor : $valor;
        }

        $this->merge(['parametros' => $sanitizados]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ((array) $this->input('parametros', []) as $clave => $valor) {
                $this->validarClave($validator, (string) $clave, (string) $valor);
            }
        });
    }

    private function validarClave(Validator $validator, string $clave, string $valor): void
    {
        $parametro = ParametroGeneral::query()->find($clave);

        if ($parametro === null) {
            $validator->errors()->add(
                'parametros',
                'El parametro "'.$clave.'" no existe. Los parametros se crean en el seeder y aqui solo se actualizan.'
            );

            return;
        }

        if (stripos($valor, 'e') !== false) {
            $validator->errors()->add(
                'parametros.'.$clave,
                'El valor de '.$clave.' no puede ir en notacion cientifica.'
            );

            return;
        }

        if ($parametro->tipo === 'decimal' && preg_match('/^\d+(\.\d{1,10})?$/', $valor) !== 1) {
            $validator->errors()->add(
                'parametros.'.$clave,
                'El valor de '.$clave.' debe ser un decimal con hasta 10 cifras despues del punto.'
            );
        }

        if ($parametro->tipo === 'entero' && preg_match('/^\d+$/', $valor) !== 1) {
            $validator->errors()->add(
                'parametros.'.$clave,
                'El valor de '.$clave.' debe ser un entero.'
            );
        }
    }
}

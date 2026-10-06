<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidaUbicacionDelArea;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta de una liquidacion (seccion 4.3 + correccion C5).
 *
 * Dos ideas que aqui estan codificadas y que no son detalles:
 *
 *  1. C5: vendedor_ci, comprador_nim, comprador_nit, codigo_municipio y
 *     codigo_ajam_patente son IDENTIFICADORES, no cantidades. Se validan
 *     como digitos con regex, nunca como `numeric`, para que ni el backend
 *     ni el JSON produzcan notacion cientifica ni pierdan ceros a la
 *     izquierda (un NIT en notacion cientifica es un NIT basura).
 *  2. T1: ley_pagable es FRACCION y se corta con `lte:1`. Un 93.02 es un
 *     error de captura, no un valor posible, y el 422 es deliberado.
 *
 * El numero de documento es opcional: si no llega, lo asigna
 * NumeradorDocumento con el correlativo del ejercicio (5.3). Si llega,
 * tiene que ser unico, que es lo que el indice unico de la tabla traduce a
 * un 422 legible en vez de una excepcion.
 *
 * Los montos NO se validan con `numeric` suelto: llevan `decimal:0,2` para
 * que un peso de 3 decimales se rechace aqui y no lo rechace la columna de
 * PostgreSQL despues de haber escrito media transaccion.
 */
class StoreLiquidacionRequest extends FormRequest
{
    use ValidaUbicacionDelArea;

    public function authorize(): bool
    {
        return $this->user()?->can('liquidaciones.crear') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // -------------------------------------------------- documento
            'numero_documento' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^EP-BCB-\d{1,9}$/',
                Rule::unique('liquidaciones', 'numero_documento'),
            ],
            'fecha_transaccion' => ['required', 'date_format:Y-m-d'],
            'observaciones' => ['nullable', 'string', 'max:5000'],

            // --------------------------------------------------- insumos
            'tipo_cambio' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'cotizacion_usd_oz_t' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'cotizacion_id' => ['nullable', 'integer', 'exists:cotizaciones_quincenales,id'],
            'aplica_caja_salud' => ['required', 'boolean'],
            'peso_gramos' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:99999999.99'],
            // fraccion, no porcentaje (trampa T1)
            'ley_pagable' => ['required', 'numeric', 'gt:0', 'lte:1'],

            // ------------------------------------------ deducciones fijas
            'deducciones' => ['nullable', 'array', 'max:20'],
            'deducciones.*.concepto' => ['required', 'string', 'max:100'],
            'deducciones.*.monto' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:99999999.99'],

            // ------------------------------------------------- comprador
            'comprador_persona_id' => ['nullable', 'integer', 'exists:personas,id'],
            'comprador_razon_social' => ['required', 'string', 'max:200'],
            'comprador_nim' => ['nullable', 'string', 'regex:/^[0-9\-]{5,30}$/'],
            'comprador_nit' => ['nullable', 'string', 'regex:/^\d{1,20}$/'],

            // -------------------------------------------------- vendedor
            'vendedor_persona_id' => ['required', 'integer', 'exists:personas,id'],
            'vendedor_ci' => ['required', 'string', 'regex:/^\d{1,10}$/'],

            // ------------------------------------------------ procedencia
            'area_minera_id' => ['required', 'integer', 'exists:areas_mineras,id'],
            'departamento_id' => ['required', 'integer', 'exists:departamentos,id'],
            'provincia_id' => ['required', 'integer', 'exists:provincias,id'],
            'municipio_id' => ['required', 'integer', 'exists:municipios,id'],
            'localidad_id' => ['required', 'integer', 'exists:localidades,id'],
            'codigo_municipio' => ['nullable', 'string', 'regex:/^\d{1,10}$/'],
            'codigo_ajam_patente' => ['nullable', 'string', 'regex:/^\d{1,20}$/'],

            // ------------------------------------------------ presentacion
            'presentacion_mineral_id' => ['required', 'integer', 'exists:presentaciones_mineral,id'],
        ];
    }

    /**
     * Los cuatro niveles de procedencia tienen que ser los del area (trait).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validarUbicacionDelArea($validator);
        });
    }
}

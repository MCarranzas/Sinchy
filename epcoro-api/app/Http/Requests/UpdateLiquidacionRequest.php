<?php

namespace App\Http\Requests;

use App\Enums\EstadoLiquidacion;
use App\Exceptions\RegistroNoEditableException;
use App\Http\Requests\Concerns\ValidaUbicacionDelArea;
use App\Models\Liquidacion;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edicion de una liquidacion.
 *
 * Es una actualizacion PARCIAL: solo los campos enviados se modifican, y
 * quien persiste mezcla lo nuevo con lo que ya estaba antes de recalcular.
 * Un tecnico que solo quiere arreglar una observacion no deberia tener que
 * reenviar los 25 campos del formulario para no romper los montos.
 *
 * Los cinco insumos del calculo viajan juntos en la practica (el formulario
 * los muestra en la misma pantalla), pero aqui no se exige el paquete
 * completo: si llega uno, se recalcula con ese uno mas los que ya estaban.
 * El corte de seguridad es el del servicio, que revalida todo igual.
 *
 * `numero_documento` puede corregirse solo si NO lo asigno el sistema: un
 * numero con correlativo es el que se imprime y el que cuelga del contador
 * del ejercicio, y cambiarlo a mano dejaria el contador y el documento sin
 * relacion. Si hace falta otro numero, se borra (solo en `creado`) y se
 * captura de nuevo.
 */
class UpdateLiquidacionRequest extends FormRequest
{
    use ValidaUbicacionDelArea;

    /**
     * Orden documentado en LiquidacionController: ESTADO primero (422),
     * despues PERMISO (403), y recien ahi la validacion.
     *
     * Si aqui se cortara con el permiso, un gerente (que no tiene
     * `liquidaciones.editar`) que intente tocar una aprobada recibira 403 y
     * la pantalla no podria distinguir "usted no puede editar" de "este
     * expediente ya esta cerrado". Ademas se resuelve ANTES de validar: una
     * fila no editable no admite ningun payload, ni siquiera uno invalido.
     *
     * @throws RegistroNoEditableException si la fila es de solo lectura
     */
    public function authorize(): bool
    {
        $this->cortarPorEstado();

        return $this->user()?->can('liquidaciones.editar') ?? false;
    }

    /**
     * El mismo corte que hace `sinEscritura()` en el controlador, pero aqui
     * llega primero: el FormRequest se resuelve antes del cuerpo del
     * metodo, y sin esto el orden 422 -> 403 quedaria invertido.
     *
     * @throws RegistroNoEditableException
     */
    private function cortarPorEstado(): void
    {
        $liquidacion = $this->route('liquidacion');

        if (! $liquidacion instanceof Liquidacion) {
            return;
        }

        if ($liquidacion->estado === EstadoLiquidacion::Aprobado) {
            throw RegistroNoEditableException::aprobada();
        }

        if (! $liquidacion->estado->esEditable()) {
            throw RegistroNoEditableException::porEstado($liquidacion->estado);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // -------------------------------------------------- documento
            'numero_documento' => [
                'sometimes',
                'string',
                'max:50',
                'regex:/^EP-BCB-\d{1,9}$/',
                Rule::unique('liquidaciones', 'numero_documento')->ignore($this->identificador()),
            ],
            'fecha_transaccion' => ['sometimes', 'date_format:Y-m-d'],
            'observaciones' => ['nullable', 'string', 'max:5000'],

            // --------------------------------------------------- insumos
            'tipo_cambio' => ['sometimes', 'numeric', 'gt:0', 'decimal:0,4'],
            'cotizacion_usd_oz_t' => ['sometimes', 'numeric', 'gt:0', 'decimal:0,4'],
            'cotizacion_id' => ['nullable', 'integer', 'exists:cotizaciones_quincenales,id'],
            'aplica_caja_salud' => ['sometimes', 'boolean'],
            'peso_gramos' => ['sometimes', 'numeric', 'gt:0', 'decimal:0,2', 'max:99999999.99'],
            'ley_pagable' => ['sometimes', 'numeric', 'gt:0', 'lte:1'],

            // ------------------------------------------ deducciones fijas
            'deducciones' => ['sometimes', 'array', 'max:20'],
            'deducciones.*.concepto' => ['required', 'string', 'max:100'],
            'deducciones.*.monto' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:99999999.99'],

            // ------------------------------------------------- comprador
            'comprador_persona_id' => ['sometimes', 'nullable', 'integer', 'exists:personas,id'],
            'comprador_razon_social' => ['sometimes', 'string', 'max:200'],
            'comprador_nim' => ['nullable', 'string', 'regex:/^[0-9\-]{5,30}$/'],
            'comprador_nit' => ['nullable', 'string', 'regex:/^\d{1,20}$/'],

            // -------------------------------------------------- vendedor
            'vendedor_persona_id' => ['sometimes', 'integer', 'exists:personas,id'],
            'vendedor_ci' => ['sometimes', 'string', 'regex:/^\d{1,10}$/'],

            // ------------------------------------------------ procedencia
            'area_minera_id' => ['sometimes', 'integer', 'exists:areas_mineras,id'],
            'departamento_id' => ['sometimes', 'integer', 'exists:departamentos,id'],
            'provincia_id' => ['sometimes', 'integer', 'exists:provincias,id'],
            'municipio_id' => ['sometimes', 'integer', 'exists:municipios,id'],
            'localidad_id' => ['sometimes', 'integer', 'exists:localidades,id'],
            'codigo_municipio' => ['nullable', 'string', 'regex:/^\d{1,10}$/'],
            'codigo_ajam_patente' => ['nullable', 'string', 'regex:/^\d{1,20}$/'],

            // ------------------------------------------------ presentacion
            'presentacion_mineral_id' => ['sometimes', 'integer', 'exists:presentaciones_mineral,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validarNumeroAsignadoPorElSistema($validator);
            $this->validarUbicacionDelArea($validator);
        });
    }

    /**
     * El numero que asigno el numerador no se toca (ver docblock).
     *
     * Corrige el caso en que el cliente manda el MISMO numero que ya tiene:
     * eso no es un cambio y pasa sin mas.
     */
    private function validarNumeroAsignadoPorElSistema(Validator $validator): void
    {
        if (! $this->has('numero_documento')) {
            return;
        }

        /** @var Liquidacion|null $liquidacion */
        $liquidacion = $this->route('liquidacion');

        if ($liquidacion === null || $liquidacion->correlativo === null) {
            return;
        }

        if ($this->input('numero_documento') !== $liquidacion->numero_documento) {
            $validator->errors()->add(
                'numero_documento',
                'El numero '.$liquidacion->numero_documento.' fue asignado por el sistema con su correlativo y no se puede cambiar. Para usar otro numero, capture una liquidacion nueva.'
            );
        }
    }

    /** El id de la ruta, cuando la hay (para ignorar el registro en el unique). */
    private function identificador(): int|string|null
    {
        $identificador = $this->route('liquidacion');

        return is_object($identificador) ? $identificador->getKey() : $identificador;
    }
}

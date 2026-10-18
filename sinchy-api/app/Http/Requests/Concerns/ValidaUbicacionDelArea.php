<?php

namespace App\Http\Requests\Concerns;

use App\Models\AreaMinera;
use Illuminate\Contracts\Validation\Validator;

/**
 * Los cuatro niveles de procedencia tienen que ser los del area minera.
 *
 * En el formulario los selects se autocompletan desde el area elegida (es
 * como se llena el Excel, y como la factory monta la fila). Si llega un
 * municipio que no pertenece a esa area, la copia de procedencia que se
 * persiste para el PDF mintiria sobre el origen del mineral: un snapshot
 * que miente es peor que una validacion que se niega.
 *
 * Compartido por el alta y la edicion para que las dos respuestas digan lo
 * mismo en los mismos casos.
 */
trait ValidaUbicacionDelArea
{
    private function validarUbicacionDelArea(Validator $validator): void
    {
        $campos = ['area_minera_id', 'departamento_id', 'provincia_id', 'municipio_id', 'localidad_id'];

        if ($validator->errors()->hasAny($campos)) {
            return;
        }

        $area = AreaMinera::query()->find($this->areaEnEvaluacion());

        if ($area === null) {
            return;
        }

        foreach (['departamento_id', 'provincia_id', 'municipio_id', 'localidad_id'] as $campo) {
            // En una edicion parcial solo se compara lo que llego: el resto
            // ya esta guardado y fue validado la vez que se capturo.
            if (! $this->has($campo)) {
                continue;
            }

            if ($this->integer($campo) !== (int) $area->{$campo}) {
                $validator->errors()->add(
                    $campo,
                    'La ubicacion no coincide con el area minera elegida: los cuatro niveles se completan desde el area.'
                );
            }
        }
    }

    /**
     * El area que hay que mirar: la que viene en la peticion o, en una
     * edicion parcial que no la trae, la que el registro ya tenia. Revisar
     * solo lo que llega dejaria la puerta abierta a mandar un municipio
     * ajeno al area sin cambiar de area.
     */
    private function areaEnEvaluacion(): ?int
    {
        if ($this->has('area_minera_id')) {
            return $this->integer('area_minera_id');
        }

        $ruta = $this->route('liquidacion');

        return is_object($ruta) ? (int) $ruta->area_minera_id : null;
    }
}

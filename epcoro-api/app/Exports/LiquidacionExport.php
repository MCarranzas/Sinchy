<?php

namespace App\Exports;

use App\Models\Liquidacion;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

/**
 * Excel de UNA liquidacion (GET /liquidaciones/{id}/excel).
 *
 * Va por vista y no por fila: el expediente no es una tabla sino un
 * formulario, y la forma legible es la de un bloque de etiqueta/valor por
 * seccion, que es ademas como se imprime el oficial.
 *
 * Los montos salen con number_format a 2 decimales ya REDONDEADOS por el
 * servicio: aqui solo se pintan, nadie vuelve a calcular (T3).
 */
class LiquidacionExport implements FromView
{
    public function __construct(private readonly Liquidacion $liquidacion) {}

    public function view(): View
    {
        $this->liquidacion->loadMissing(['deducciones', 'registro', 'historial']);

        return view('liquidaciones.excel', [
            'liquidacion' => $this->liquidacion,
        ]);
    }
}

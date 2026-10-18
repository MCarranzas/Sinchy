<?php

namespace App\Exports;

use App\Models\Liquidacion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromView;

/**
 * Excel del REPORTE de liquidaciones (GET /reportes/exportar?formato=xlsx).
 *
 * Una fila por expediente, con los mismos montos que muestra el resumen, y
 * una ultima fila de totales: si el total del pie no da el mismo numero
 * que la suma de las filas, el reporte esta mal y hay que verlo de una.
 *
 * @param Collection<int, Liquidacion> $liquidaciones
 */
class LiquidacionesExport implements FromView
{
    public function __construct(private readonly Collection $liquidaciones) {}

    public function view(): View
    {
        return view('liquidaciones.excel-reporte', [
            'liquidaciones' => $this->liquidaciones,
        ]);
    }
}

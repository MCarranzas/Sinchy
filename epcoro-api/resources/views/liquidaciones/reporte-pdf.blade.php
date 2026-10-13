<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Reporte de liquidaciones</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #111; }
        h1 { font-size: 12px; text-align: center; margin: 0 0 4px; }
        .sub { text-align: center; color: #555; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #bbb; padding: 2px 4px; text-align: left; }
        th { background: #f3f4f6; }
        td.monto, th.monto { text-align: right; }
        tfoot td { background: #fef2f2; font-weight: bold; }
    </style>
</head>

<body>
    <h1>REPORTE DE LIQUIDACIONES - EPCORO</h1>
    <div class="sub">Desde {{ $desde ?: 'inicio' }} hasta {{ $hasta ?: 'hoy' }} - {{ $liquidaciones->count() }} liquidaciones</div>

    <table>
        <thead>
            <tr>
                <th>Numero</th>
                <th>Fecha</th>
                <th>Estado</th>
                <th>Vendedor</th>
                <th>Municipio</th>
                <th>Mineral</th>
                <th class="monto">Peso g</th>
                <th class="monto">Peso fino g</th>
                <th class="monto">Bruto Bs</th>
                <th class="monto">Aportes Bs</th>
                <th class="monto">Deducciones Bs</th>
                <th class="monto">Neto Bs</th>
                <th>Registrado por</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($liquidaciones as $liquidacion)
                <tr>
                    <td>{{ $liquidacion->numero_documento }}</td>
                    <td>{{ $liquidacion->fecha_transaccion?->format('d/m/Y') }}</td>
                    <td>{{ $liquidacion->estado->etiqueta() }}</td>
                    <td>{{ $liquidacion->vendedor_nombre }}</td>
                    <td>{{ $liquidacion->municipio_nombre }}</td>
                    <td>{{ $liquidacion->presentacion_nombre }}</td>
                    <td class="monto">{{ number_format((float) $liquidacion->peso_gramos, 2, ',', '.') }}</td>
                    <td class="monto">{{ number_format((float) $liquidacion->peso_fino_gramos, 2, ',', '.') }}</td>
                    <td class="monto">{{ number_format((float) $liquidacion->valor_bruto_bs, 2, ',', '.') }}</td>
                    <td class="monto">{{ number_format((float) $liquidacion->aporte_caja_salud_bs, 2, ',', '.') }}</td>
                    <td class="monto">{{ number_format((float) $liquidacion->total_deducciones_bs, 2, ',', '.') }}</td>
                    <td class="monto">{{ number_format((float) $liquidacion->valor_neto_bs, 2, ',', '.') }}</td>
                    <td>{{ $liquidacion->registro?->username }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="7">TOTALES</td>
                <td class="monto">{{ number_format((float) $liquidaciones->sum('peso_fino_gramos'), 2, ',', '.') }}</td>
                <td class="monto">{{ number_format((float) $liquidaciones->sum('valor_bruto_bs'), 2, ',', '.') }}</td>
                <td class="monto">{{ number_format((float) $liquidaciones->sum('aporte_caja_salud_bs'), 2, ',', '.') }}</td>
                <td class="monto">{{ number_format((float) $liquidaciones->sum('total_deducciones_bs'), 2, ',', '.') }}</td>
                <td class="monto">{{ number_format((float) $liquidaciones->sum('valor_neto_bs'), 2, ',', '.') }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</body>

</html>
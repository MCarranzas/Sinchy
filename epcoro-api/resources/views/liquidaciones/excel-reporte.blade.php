<table>
    <thead>
        <tr>
            <th>Numero</th>
            <th>Fecha</th>
            <th>Estado</th>
            <th>Vendedor</th>
            <th>Municipio</th>
            <th>Mineral</th>
            <th>Peso g</th>
            <th>Peso fino g</th>
            <th>Bruto Bs</th>
            <th>Aportes Bs</th>
            <th>Deducciones Bs</th>
            <th>Neto Bs</th>
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
                <td>{{ number_format((float) $liquidacion->peso_gramos, 2, ',', '.') }}</td>
                <td>{{ number_format((float) $liquidacion->peso_fino_gramos, 2, ',', '.') }}</td>
                <td>{{ number_format((float) $liquidacion->valor_bruto_bs, 2, ',', '.') }}</td>
                <td>{{ number_format((float) $liquidacion->aporte_caja_salud_bs, 2, ',', '.') }}</td>
                <td>{{ number_format((float) $liquidacion->total_deducciones_bs, 2, ',', '.') }}</td>
                <td>{{ number_format((float) $liquidacion->valor_neto_bs, 2, ',', '.') }}</td>
                <td>{{ $liquidacion->registro?->username }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <th colspan="7">TOTALES ({{ $liquidaciones->count() }} liquidaciones)</th>
            <th>{{ number_format((float) $liquidaciones->sum('peso_fino_gramos'), 2, ',', '.') }}</th>
            <th>{{ number_format((float) $liquidaciones->sum('valor_bruto_bs'), 2, ',', '.') }}</th>
            <th>{{ number_format((float) $liquidaciones->sum('aporte_caja_salud_bs'), 2, ',', '.') }}</th>
            <th>{{ number_format((float) $liquidaciones->sum('total_deducciones_bs'), 2, ',', '.') }}</th>
            <th>{{ number_format((float) $liquidaciones->sum('valor_neto_bs'), 2, ',', '.') }}</th>
            <th></th>
        </tr>
    </tfoot>
</table>

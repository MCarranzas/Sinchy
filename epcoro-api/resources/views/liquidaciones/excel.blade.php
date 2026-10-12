<table>
    <thead>
        <tr>
            <th colspan="2">LIQUIDACION {{ $liquidacion->numero_documento }}</th>
        </tr>
    </thead>
    <tbody>
        <tr><td>Numero de documento</td><td>{{ $liquidacion->numero_documento }}</td></tr>
        <tr><td>Codigo de formulario</td><td>{{ $liquidacion->codigo_formulario }}</td></tr>
        <tr><td>Ejercicio</td><td>{{ $liquidacion->ejercicio }}</td></tr>
        <tr><td>Fecha de transaccion</td><td>{{ $liquidacion->fecha_transaccion?->format('d/m/Y') }}</td></tr>
        <tr><td>Estado</td><td>{{ $liquidacion->estado->etiqueta() }}</td></tr>
        <tr><td>Fecha de registro</td><td>{{ $liquidacion->fecha_registro?->format('d/m/Y H:i') }}</td></tr>
        <tr><td>Registrado por</td><td>{{ $liquidacion->registro?->nombre_completo }}</td></tr>
    </tbody>
</table>

<table>
    <thead>
        <tr>
            <th colspan="2">COMPRADOR</th>
        </tr>
    </thead>
    <tbody>
        <tr><td>Razon social</td><td>{{ $liquidacion->comprador_razon_social }}</td></tr>
        <tr><td>NIM</td><td>{{ $liquidacion->comprador_nim }}</td></tr>
        <tr><td>NIT</td><td>{{ $liquidacion->comprador_nit }}</td></tr>
    </tbody>
</table>

<table>
    <thead>
        <tr>
            <th colspan="2">VENDEDOR</th>
        </tr>
    </thead>
    <tbody>
        <tr><td>Nombre</td><td>{{ $liquidacion->vendedor_nombre }}</td></tr>
        <tr><td>CI</td><td>{{ $liquidacion->vendedor_ci }}</td></tr>
    </tbody>
</table>

<table>
    <thead>
        <tr>
            <th colspan="2">PROCEDENCIA</th>
        </tr>
    </thead>
    <tbody>
        <tr><td>Area minera</td><td>{{ $liquidacion->nombre_area_minera }}</td></tr>
        <tr><td>Titular del derecho minero</td><td>{{ $liquidacion->titular_derecho_minero }}</td></tr>
        <tr><td>Pajata AJAM</td><td>{{ $liquidacion->codigo_ajam_patente }}</td></tr>
        <tr><td>Departamento</td><td>{{ $liquidacion->departamento_nombre }}</td></tr>
        <tr><td>Provincia</td><td>{{ $liquidacion->provincia_nombre }}</td></tr>
        <tr><td>Municipio</td><td>{{ $liquidacion->municipio_nombre }} ({{ $liquidacion->codigo_municipio }})</td></tr>
        <tr><td>Localidad</td><td>{{ $liquidacion->localidad_nombre }}</td></tr>
        <tr><td>Presentacion del mineral</td><td>{{ $liquidacion->presentacion_nombre }}</td></tr>
    </tbody>
</table>

<table>
    <thead>
        <tr>
            <th colspan="2">CALCULO</th>
        </tr>
    </thead>
    <tbody>
        <tr><td>Peso en gramos</td><td>{{ number_format((float) $liquidacion->peso_gramos, 2, ',', '.') }}</td></tr>
        <tr><td>Ley pagable</td><td>{{ $liquidacion->ley_pagable }}</td></tr>
        <tr><td>Tipo de cambio Bs/$</td><td>{{ $liquidacion->tipo_cambio }}</td></tr>
        <tr><td>Cotizacion USD/oz t</td><td>{{ $liquidacion->cotizacion_usd_oz_t }}</td></tr>
        <tr><td>Aporte caja de salud</td><td>{{ $liquidacion->aplica_caja_salud ? 'SI' : 'NO' }}</td></tr>
        <tr><td>Peso fino (g)</td><td>{{ number_format((float) $liquidacion->peso_fino_gramos, 4, ',', '.') }}</td></tr>
        <tr><td>Valor USD/g</td><td>{{ number_format((float) $liquidacion->valor_usd_gramo, 6, ',', '.') }}</td></tr>
        <tr><td>Valor bruto Bs</td><td>{{ number_format((float) $liquidacion->valor_bruto_bs, 2, ',', '.') }}</td></tr>
        <tr><td>Aporte caja salud Bs</td><td>{{ number_format((float) $liquidacion->aporte_caja_salud_bs, 2, ',', '.') }}</td></tr>
        <tr><td>Total deducciones Bs</td><td>{{ number_format((float) $liquidacion->total_deducciones_bs, 2, ',', '.') }}</td></tr>
        <tr><td>VALOR NETO Bs</td><td>{{ number_format((float) $liquidacion->valor_neto_bs, 2, ',', '.') }}</td></tr>
    </tbody>
</table>

<table>
    <thead>
        <tr>
            <th colspan="2">HISTORIAL DE ESTADOS</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($liquidacion->historial as $movimiento)
            <tr>
                <td>{{ $movimiento->created_at?->format('d/m/Y H:i') }}</td>
                <td>
                    {{ $movimiento->estado_anterior?->etiqueta() ?? '(inicio)' }}
                    &rarr;
                    {{ $movimiento->estado_nuevo->etiqueta() }}
                    -
                    {{ $movimiento->usuario_nombre }}
                    @if ($movimiento->comentario)
                        ({{ $movimiento->comentario }})
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="2">Sin movimientos</td></tr>
        @endforelse
    </tbody>
</table>

<table>
    <tbody>
        <tr>
            <td>Observaciones: {{ $liquidacion->observaciones ?: '-' }}</td>
        </tr>
    </tbody>
</table>

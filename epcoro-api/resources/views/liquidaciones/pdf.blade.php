<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>{{ $liquidacion->numero_documento }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; margin: 0; }
        h1 { font-size: 13px; margin: 0 0 2px; }
        .encabezado { text-align: center; border-bottom: 2px solid #b91c1c; padding-bottom: 8px; margin-bottom: 12px; }
        .encabezado small { display: block; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { border: 1px solid #bbb; padding: 4px 6px; text-align: left; }
        th { background: #f3f4f6; width: 34%; }
        td.monto { text-align: right; }
        .fila-netos td { background: #fef2f2; font-weight: bold; }
        .firmas { margin-top: 26px; width: 100%; }
        .firmas td { border: none; width: 50%; text-align: center; vertical-align: top; }
        .firma-linea { border-top: 1px solid #000; margin: 46px 30px 4px; }
    </style>
</head>

<body>
    <div class="encabezado">
        <h1>LIQUIDACION DE MINERAL</h1>
        <small>EPCORO - {{ $liquidacion->numero_documento }}</small>
        <small>{{ $liquidacion->codigo_formulario }}</small>
    </div>

    <table>
        <tr><th>Estado</th><td>{{ $liquidacion->estado->etiqueta() }}</td></tr>
        <tr><th>Fecha de transaccion</th><td>{{ $liquidacion->fecha_transaccion?->format('d/m/Y') }}</td></tr>
        <tr><th>Registrado por</th><td>{{ $liquidacion->registro?->username }} ({{ $liquidacion->fecha_registro?->format('d/m/Y H:i') }})</td></tr>
        <tr><th>Observaciones</th><td>{{ $liquidacion->observaciones ?: '-' }}</td></tr>
    </table>

    <table>
        <tr><th>Comprador</th><td>{{ $liquidacion->comprador_razon_social }} - NIM {{ $liquidacion->comprador_nim }} - NIT {{ $liquidacion->comprador_nit }}</td></tr>
        <tr><th>Vendedor</th><td>{{ $liquidacion->vendedor_nombre }} - CI {{ $liquidacion->vendedor_ci }}</td></tr>
    </table>

    <table>
        <tr><th>Area minera</th><td>{{ $liquidacion->nombre_area_minera }}</td></tr>
        <tr><th>Titular del derecho minero</th><td>{{ $liquidacion->titular_derecho_minero }} (Pajata {{ $liquidacion->codigo_ajam_patente ?: '-' }})</td></tr>
        <tr><th>Procedencia</th><td>{{ $liquidacion->departamento_nombre }} / {{ $liquidacion->provincia_nombre }} / {{ $liquidacion->municipio_nombre }} ({{ $liquidacion->codigo_municipio }}) / {{ $liquidacion->localidad_nombre }}</td></tr>
        <tr><th>Presentacion del mineral</th><td>{{ $liquidacion->presentacion_nombre }}</td></tr>
    </table>

    <table>
        <tr><th>Peso (g)</th><td>{{ number_format((float) $liquidacion->peso_gramos, 2, ',', '.') }}</td></tr>
        <tr><th>Ley pagable</th><td>{{ $liquidacion->ley_pagable }}</td></tr>
        <tr><th>Tipo de cambio Bs/$</th><td>{{ $liquidacion->tipo_cambio }}</td></tr>
        <tr><th>Cotizacion USD/oz t</th><td>{{ $liquidacion->cotizacion_usd_oz_t }}</td></tr>
        <tr><th>Aporte de caja de salud</th><td>{{ $liquidacion->aplica_caja_salud ? 'Aplica (1,8%)' : 'No aplica' }}</td></tr>
        <tr><th>Peso fino (g)</th><td class="monto">{{ number_format((float) $liquidacion->peso_fino_gramos, 4, ',', '.') }}</td></tr>
        <tr><th>Valor USD/g</th><td class="monto">{{ number_format((float) $liquidacion->valor_usd_gramo, 6, ',', '.') }}</td></tr>
        <tr><th>Valor bruto (Bs)</th><td class="monto">{{ number_format((float) $liquidacion->valor_bruto_bs, 2, ',', '.') }}</td></tr>
        <tr><th>Aporte caja de salud (Bs)</th><td class="monto">{{ number_format((float) $liquidacion->aporte_caja_salud_bs, 2, ',', '.') }}</td></tr>
        <tr><th>Total deducciones (Bs)</th><td class="monto">{{ number_format((float) $liquidacion->total_deducciones_bs, 2, ',', '.') }}</td></tr>
        <tr class="fila-netos"><td>VALOR NETO (Bs)</td><td class="monto">{{ number_format((float) $liquidacion->valor_neto_bs, 2, ',', '.') }}</td></tr>
    </table>

    <table>
        <thead>
            <tr><th>Fecha</th><th>Movimiento</th></tr>
        </thead>
        <tbody>
            @forelse ($liquidacion->historial as $movimiento)
                <tr>
                    <td>{{ $movimiento->created_at?->format('d/m/Y H:i') }}</td>
                    <td>{{ $movimiento->estado_anterior?->etiqueta() ?? '(inicio)' }} &rarr; {{ $movimiento->estado_nuevo->etiqueta() }} - {{ $movimiento->usuario_nombre }}{{ $movimiento->comentario ? ' ('.$movimiento->comentario.')' : '' }}</td>
                </tr>
            @empty
                <tr><td colspan="2">Sin movimientos</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($liquidacion->estado->etiqueta() === 'Aprobado')
        <table class="firmas">
            <tr>
                <td><div class="firma-linea"></div>TECNICO (SR. / SRA.)</td>
                <td><div class="firma-linea"></div>GERENTE - Vo. Bo.</td>
            </tr>
        </table>
    @endif
</body>

</html>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #2e3440; }
        h1 { font-size: 16px; margin: 0 0 8px; }
        .meta { margin-bottom: 12px; }
        .meta p { margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d8dee9; padding: 4px 6px; text-align: left; }
        th { background: #eceff4; }
        thead { display: table-header-group; }
    </style>
</head>
<body>
    <h1>Reporte parametrizado — PANDORA</h1>
    <div class="meta">
        <p><strong>Generado en:</strong> {{ $generadoEn }}</p>
        <p><strong>Generado por:</strong> {{ $generadoPor }}</p>
        <p><strong>Período:</strong> {{ $report->parametrosAplicados['desde'] }} — {{ $report->parametrosAplicados['hasta'] }}</p>
        <p><strong>Área:</strong> {{ $areaEtiqueta }}</p>
        <p><strong>Indicadores:</strong> {{ implode(', ', $report->parametrosAplicados['indicadores']) }}</p>
        <p><strong>Agrupación:</strong> {{ implode(', ', $report->parametrosAplicados['agrupar_por']) }}</p>
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($report->parametrosAplicados['agrupar_por'] as $dimension)
                    <th>{{ $dimension }}</th>
                @endforeach
                @foreach ($report->columnas as $columna)
                    <th>{{ $columna['etiqueta'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($report->grupos as $grupo)
                <tr>
                    @foreach ($report->parametrosAplicados['agrupar_por'] as $dimension)
                        @php($key = $dimension === 'indicador' ? 'indicador_etiqueta' : $dimension)
                        <td>{{ $grupo['dimensiones'][$key] ?? '—' }}</td>
                    @endforeach
                    @foreach ($report->columnas as $columna)
                        @php($valor = $grupo['valores'][$columna['id']] ?? null)
                        <td>
                            @if (!$valor)
                                0
                            @elseif (!empty($valor['suprimido']))
                                {{ ($valor['supresion'] ?? '') === 'primaria' ? '<5' : 'Protegido' }}
                            @else
                                {{ $valor['valor'] ?? 0 }}{{ ($valor['unidad'] ?? null) === 'porcentaje' ? '%' : '' }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($report->parametrosAplicados['agrupar_por']) + count($report->columnas) }}">
                        Sin datos para los filtros aplicados.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>

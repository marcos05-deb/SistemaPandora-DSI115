<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 62pt 34pt 40pt; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; line-height: 1.15; color: #202c38; }
        header { position: fixed; top: -38pt; left: 0; right: 0; height: 33pt; border-bottom: 1pt solid #d9e1e9; }
        .wordmark { font-size: 15pt; font-weight: bold; letter-spacing: 2pt; color: #355c7c; }
        .tagline { font-size: 7pt; color: #536477; }
        .header-note { position: absolute; right: 0; top: 7pt; font-size: 8pt; color: #536477; }
        footer { position: fixed; bottom: -15pt; left: 0; right: 0; border-top: 1pt solid #d9e1e9; padding-top: 5pt; font-size: 7pt; color: #536477; }
        .page-number { float: right; }
        .page-number:after { content: counter(page); }
        .eyebrow { color: #355c7c; font-size: 8pt; letter-spacing: 1.5pt; margin: 0 0 5pt; }
        h1 { font-size: 23pt; line-height: 1.1; margin: 0 0 9pt; font-weight: normal; }
        h2 { font-size: 12pt; margin: 10pt 0 5pt; color: #24384b; page-break-after: avoid; }
        .meta { background: #e8f0f6; border-left: 3pt solid #88b8b3; padding: 9pt 12pt; }
        .meta p { margin: 2pt 0; }
        .muted, .filters { color: #536477; font-size: 7.5pt; }
        .filters { margin: 8pt 0 5pt; }
        .privacy { color: #536477; font-size: 7.5pt; margin: 5pt 0 10pt; }
        table { width: 100%; table-layout: fixed; border-collapse: collapse; }
        th, td { padding: 6pt 9pt; text-align: left; vertical-align: top; overflow-wrap: break-word; word-wrap: break-word; }
        th { background: #355c7c; color: white; font-size: 8pt; font-weight: normal; }
        thead { display: table-header-group; page-break-inside: avoid; page-break-after: avoid; }
        tr { page-break-inside: avoid; }
        .ledger td { padding: 2pt 9pt; border-bottom: 0.5pt solid #d9e1e9; }
        .ledger tr:nth-child(even) td { background: #f7f9fb; }
        .metric { text-align: right; color: #355c7c; font-weight: bold; }
        .summary td { border-bottom: 0.5pt solid #d9e1e9; font-size: 8pt; }
        .group-card { page-break-inside: avoid; margin-top: 10pt; border: 0.5pt solid #d9e1e9; }
        .group-heading { background: #e8f0f6; border-left: 3pt solid #88b8b3; padding: 7pt 9pt; font-size: 8pt; }
        .group-heading p { margin: 2pt 0; }
        .group-heading .section { font-size: 7pt; color: #536477; text-transform: uppercase; letter-spacing: 1pt; }
        .metrics td { width: 33.33%; border-bottom: 0.5pt solid #ecf1f5; padding: 6pt 9pt; }
        .metric-label { font-size: 7.5pt; color: #536477; margin-bottom: 2pt; }
        .metric-value { font-size: 10pt; color: #355c7c; font-weight: bold; }
    </style>
</head>
<body>
    @php
        $formatValue = static fn (?array $value): string => !empty($value['suprimido'])
            ? 'Oculto por privacidad'
            : (string) ($value['valor'] ?? 0).(($value['unidad'] ?? null) === 'porcentaje' ? '%' : '');
        $dimensions = $report->parametrosAplicados['agrupar_por'];
        $columnsById = array_column($report->columnas, null, 'id');
        $indicatorGroups = [];
        $canonicalIndicators = $dimensions === ['indicador'] && count($report->grupos) === count($report->columnas);
        foreach ($report->grupos as $group) {
            $id = $group['dimensiones']['indicador'] ?? null;
            if (!$id || !isset($columnsById[$id]) || isset($indicatorGroups[$id]) || array_keys($group['valores']) !== [$id] || !isset($report->totales[$id])) {
                $canonicalIndicators = false;
            }
            if ($id) { $indicatorGroups[$id] = $group; }
        }
        $sameTotals = $canonicalIndicators;
        if ($canonicalIndicators) {
            foreach ($report->columnas as $column) {
                if ($formatValue($report->totales[$column['id']]) !== $formatValue($indicatorGroups[$column['id']]['valores'][$column['id']])) {
                    $sameTotals = false;
                }
            }
        }
        $shield = 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="none" stroke="#355c7c" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>');
    @endphp
    <header>
        <img src="{{ $shield }}" style="width: 26pt; height: 26pt; float: left; margin-right: 8pt;" alt="">
        <div class="wordmark">PANDORA</div>
        <div class="tagline">Expediente clínico universitario</div>
        <div class="header-note">INFORME INSTITUCIONAL</div>
    </header>
    <footer><span class="page-number">Página </span>PANDORA · Uso institucional · Información agregada</footer>
    <p class="eyebrow">ANALÍTICA INSTITUCIONAL</p>
    <h1>Reporte personalizado</h1>
    <div class="meta">
        <p><strong>Período:</strong> {{ $report->parametrosAplicados['desde'] }} - {{ $report->parametrosAplicados['hasta'] }} &nbsp; | &nbsp; <strong>Área:</strong> {{ $areaEtiqueta }}</p>
        <p><strong>Agrupado por:</strong> {{ implode(', ', $agrupacionesEtiquetas) }}</p>
        <p class="muted"><strong>Generado por:</strong> {{ $generadoPor }} &nbsp; | &nbsp; <strong>Generado en:</strong> {{ \Carbon\Carbon::parse($generadoEn)->setTimezone('America/El_Salvador')->format('d/m/Y H:i') }} (hora de El Salvador)</p>
    </div>
    <p class="filters"><strong>Indicadores:</strong> {{ implode(', ', $indicadoresEtiquetas) }}</p>
    <p class="privacy">Por privacidad, se ocultan los grupos con menos de {{ $report->minimoCelda }} casos y los totales que permitirían deducirlos. Un cero visible representa un valor real; «No aplica» identifica una combinación sin valor en el reporte.</p>

    @if ($canonicalIndicators)
        <h2>Totales por indicador</h2>
        @if ($sameTotals)<p class="muted">El total y el detalle coinciden para esta agrupación; se presentan una sola vez.</p>@endif
        <table class="ledger">
            <thead><tr><th style="width: 65%">Indicador</th><th>Total</th>@if (!$sameTotals)<th>Detalle</th>@endif</tr></thead>
            <tbody>
                @foreach ($report->columnas as $column)
                    <tr><td>{{ $column['etiqueta'] }}</td><td class="metric">{{ $formatValue($report->totales[$column['id']]) }}</td>@if (!$sameTotals)<td class="metric">{{ $formatValue($indicatorGroups[$column['id']]['valores'][$column['id']]) }}</td>@endif</tr>
                @endforeach
            </tbody>
        </table>
    @else
        <h2>Totales generales</h2>
        <table class="summary">
            <tbody>
                @foreach (array_chunk($report->totales, 2, true) as $pair)
                    <tr>@foreach ($pair as $id => $value)<td>{{ $value['etiqueta'] ?? $columnsById[$id]['etiqueta'] ?? $id }}</td><td class="metric">{{ $formatValue($value) }}</td>@endforeach @if (count($pair) === 1)<td></td><td></td>@endif</tr>
                @endforeach
            </tbody>
        </table>
        @forelse ($report->grupos as $group)
            <table class="group-card"><tr><td style="padding: 0">
                <table><tr><td class="group-heading">
                    <div class="section">Detalle por grupo</div>
                    @foreach ($dimensions as $index => $dimension)
                        <p><strong>{{ $agrupacionesEtiquetas[$index] ?? $dimension }}</strong> &nbsp; {{ $group['dimensiones'][$dimension === 'indicador' ? 'indicador_etiqueta' : $dimension] ?? '—' }}</p>
                    @endforeach
                </td></tr></table>
                <table class="metrics"><tbody>
                    @foreach (array_chunk($report->columnas, 3) as $row)
                        <tr>@foreach ($row as $column)<td><div class="metric-label">{{ $column['etiqueta'] }}</div><div class="metric-value">{{ array_key_exists($column['id'], $group['valores']) ? $formatValue($group['valores'][$column['id']]) : 'No aplica' }}</div></td>@endforeach</tr>
                    @endforeach
                </tbody></table>
            </td></tr></table>
        @empty
            <p>Sin datos para los filtros aplicados.</p>
        @endforelse
    @endif
</body>
</html>

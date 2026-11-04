<?php

declare(strict_types=1);

use App\DTO\ReporteGenerado;
use App\Enums\TipoIndicador;
use App\Models\Especialista;
use App\Services\ReporteExportService;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

test('HU20-E01 should_extract_real_pdf_and_match_excel_totals_and_monthly_rows', function (): void {
    $binary = (new ExecutableFinder)->find('pdftotext');
    if ($binary === null) {
        throw new RuntimeException('HU20 acceptance requires Poppler pdftotext on PATH; see docs/evidencia/SPRINT_3_REPAIR_VERIFICATION.md.');
    }

    $value = static fn (int $count): array => ['valor' => $count, 'suprimido' => false, 'unidad' => 'conteo'];
    $total = $value(13) + ['etiqueta' => 'Consultas registradas'];
    $report = new ReporteGenerado(
        parametrosAplicados: ['desde' => '2026-09-01', 'hasta' => '2026-10-22', 'area' => 1,
            'indicadores' => ['consultas_registradas'], 'agrupar_por' => ['mes']],
        generadoEn: '2026-10-22T12:00:00-06:00', generadoPor: 'QA Synthetic',
        columnas: [['id' => 'consultas_registradas', 'etiqueta' => 'Consultas registradas']],
        grupos: [
            ['dimensiones' => ['mes' => '2026-09'], 'valores' => ['consultas_registradas' => $value(6)]],
            ['dimensiones' => ['mes' => '2026-10'], 'valores' => ['consultas_registradas' => $value(7)]],
        ],
        totales: ['consultas_registradas' => $total], sinDatos: false, minimoCelda: 5,
    );
    $user = Especialista::factory()->withRole('sysadmin')->create(['name' => 'QA Synthetic']);
    $service = app(ReporteExportService::class);
    $pdf = (new ReflectionMethod($service, 'buildPdf'))->invoke($service, $report, $user, 'QA Area');
    $xlsx = (new ReflectionMethod($service, 'buildExcel'))->invoke($service, $report, $user, 'QA Area');
    $pdfPath = tempnam(sys_get_temp_dir(), 'hu20_pdf_');
    $xlsxPath = tempnam(sys_get_temp_dir(), 'hu20_xlsx_');
    $reader = new Reader;
    try {
        file_put_contents($pdfPath, $pdf);
        $process = new Process([$binary, '-layout', $pdfPath, '-']);
        $process->mustRun();
        $text = $process->getOutput();
        expect($text)->toMatch('/Consultas registradas\s+13/')
            ->and($text)->toMatch('/2026-09\s+Consultas registradas\s+6/')
            ->and($text)->toMatch('/2026-10\s+Consultas registradas\s+7/');

        file_put_contents($xlsxPath, $xlsx);
        $reader->open($xlsxPath);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = array_map(static fn ($cell): string => (string) $cell->getValue(), $row->getCells());
            }
        }
        expect($rows)->toContain(['Consultas registradas', '13'])
            ->and($rows)->toContain(['2026-09', '6'])
            ->and($rows)->toContain(['2026-10', '7']);

        if (getenv('PANDORA_WRITE_QA_EVIDENCE') === '1') {
            $directory = base_path('docs/evidencia/HU-20');
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            file_put_contents($directory.'/synthetic-monthly.pdf', $pdf);
            file_put_contents($directory.'/synthetic-monthly.xlsx', $xlsx);
        }
    } finally {
        $reader->close();
        @unlink($pdfPath);
        @unlink($xlsxPath);
    }
});

test('HU20 should_keep_all_group_metrics_readable_and_preserve_every_value', function (): void {
    $binary = (new ExecutableFinder)->find('pdftotext');
    if ($binary === null) {
        throw new RuntimeException('PDF layout acceptance requires Poppler pdftotext on PATH.');
    }

    $columns = array_map(static fn (TipoIndicador $indicator): array => [
        'id' => $indicator->value, 'etiqueta' => $indicator->etiqueta(),
    ], TipoIndicador::cases());
    $groups = [];
    $expectedValues = [];
    foreach (range(1, 18) as $row) {
        $values = [];
        foreach ($columns as $index => $column) {
            $suppressed = ($row === 1 && $index === 0) || ($row === 18 && $index === 11);
            $percentage = $column['id'] === TipoIndicador::TasaAsistencia->value;
            $value = $suppressed ? 987654321 : ($percentage ? number_format(50 + $row / 100, 2, '.', '') : ($index + 1) * 10000 + $row);
            $values[$column['id']] = [
                'valor' => $value, 'unidad' => $percentage ? 'porcentaje' : 'conteo',
                'suprimido' => $suppressed, 'supresion' => $row === 1 ? 'primaria' : 'complementaria',
            ];
            if (! $suppressed) {
                $expectedValues[] = $value.($percentage ? '%' : '');
            }
        }
        $label = $row === 18 ? str_repeat('Orientacion', 18) : str_repeat('Unidad de orientación y acompañamiento académico ', 3);
        $groups[] = [
            'dimensiones' => ['area' => sprintf('R%02d - %s', $row, $label), 'mes' => '2026-10'],
            'valores' => $values,
        ];
    }
    $totals = [];
    foreach ($columns as $index => $column) {
        $totals[$column['id']] = ['valor' => 5000 + $index, 'etiqueta' => $column['etiqueta'], 'suprimido' => false];
    }
    $report = new ReporteGenerado(
        parametrosAplicados: ['desde' => '2026-10-01', 'hasta' => '2026-10-31', 'area' => 'todas',
            'indicadores' => array_column($columns, 'id'), 'agrupar_por' => ['area', 'mes']],
        generadoEn: '2026-11-03T12:00:00-06:00', generadoPor: 'Layout QA', columnas: $columns,
        grupos: $groups, totales: $totals, sinDatos: false, minimoCelda: 5,
    );
    $user = Especialista::factory()->withRole('sysadmin')->create(['name' => 'Layout QA']);
    $service = app(ReporteExportService::class);
    $pdf = (new ReflectionMethod($service, 'buildPdf'))->invoke($service, $report, $user, 'Todas las áreas');
    $path = tempnam(sys_get_temp_dir(), 'hu20_wide_');
    try {
        file_put_contents($path, $pdf);
        $process = new Process([$binary, '-bbox-layout', $path, '-']);
        $process->mustRun();
        if (getenv('PANDORA_WRITE_PDF_LAYOUT_QA') === '1') {
            file_put_contents(storage_path('app/qa/pdf-branding-20261103/all-indicators.pdf'), $pdf);
        }
        $xml = new DOMDocument;
        expect($xml->loadXML($process->getOutput(), LIBXML_NONET))->toBeTrue();
        $xpath = new DOMXPath($xml);
        $allWords = [];
        $detailPages = 0;
        foreach ($xpath->query('//*[local-name()="page"]') as $page) {
            $words = [];
            foreach ($xpath->query('.//*[local-name()="word"]', $page) as $word) {
                $words[] = $word->textContent;
                expect((float) $word->getAttribute('xMin'))->toBeGreaterThanOrEqual(23)
                    ->and((float) $word->getAttribute('xMax'))->toBeLessThanOrEqual((float) $page->getAttribute('width') - 23)
                    ->and((float) $word->getAttribute('yMin'))->toBeGreaterThanOrEqual(23)
                    ->and((float) $word->getAttribute('yMax'))->toBeLessThanOrEqual((float) $page->getAttribute('height') - 23);
            }
            $allWords = array_merge($allWords, $words);
            if (in_array('DETALLE', $words, true)) {
                $detailPages++;
                expect($words)->toContain('Área')->toContain('Mes')
                    ->and(preg_grep('/^R\d{2}$/', $words))->not->toBeEmpty();
            }
        }
        expect($detailPages)->toBeGreaterThan(1);
        $text = implode(' ', $allWords);
        expect($text)->toContain('Oculto por privacidad')->not->toContain('987654321');
        foreach ($expectedValues as $value) {
            expect(preg_match_all('/(?<![0-9.])'.preg_quote((string) $value, '/').'(?![0-9.%])/', $text))->toBe(1);
        }
        foreach (range(1, 18) as $row) {
            expect(count(array_filter($allWords, static fn (string $word): bool => $word === sprintf('R%02d', $row))))->toBe(1);
        }
        if (getenv('PANDORA_WRITE_PDF_LAYOUT_QA') === '1') {
            $directory = storage_path('app/qa/pdf-branding-20261103');
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            file_put_contents($directory.'/all-indicators.pdf', $pdf);
            file_put_contents($directory.'/all-indicators-bbox.xml', $process->getOutput());
        }
    } finally {
        @unlink($path);
    }
});

test('HU20 should_present_indicator_grouping_without_artificial_diagonal_zeros', function (): void {
    $columns = array_map(static fn (TipoIndicador $indicator): array => ['id' => $indicator->value, 'etiqueta' => $indicator->etiqueta()], TipoIndicador::cases());
    $groups = $totals = [];
    foreach ($columns as $index => $column) {
        $value = ['valor' => $index === 0 ? 0 : ($index === 9 ? 87.5 : 120 + $index), 'unidad' => $index === 9 ? 'porcentaje' : 'conteo', 'suprimido' => $index === 11];
        $totals[$column['id']] = $value + ['etiqueta' => $column['etiqueta']];
        $groups[] = ['dimensiones' => ['indicador' => $column['id'], 'indicador_etiqueta' => $column['etiqueta']], 'valores' => [$column['id'] => $value]];
    }
    $report = new ReporteGenerado(
        parametrosAplicados: ['desde' => '2026-10-01', 'hasta' => '2026-10-31', 'area' => 'todas', 'indicadores' => array_column($columns, 'id'), 'agrupar_por' => ['indicador']],
        generadoEn: '2026-11-03T12:00:00-06:00', generadoPor: 'QA Synthetic', columnas: $columns,
        grupos: $groups, totales: $totals, sinDatos: false, minimoCelda: 5,
    );
    $user = Especialista::factory()->withRole('sysadmin')->create(['name' => 'Equipo de análisis institucional']);
    $service = app(ReporteExportService::class);
    $pdf = (new ReflectionMethod($service, 'buildPdf'))->invoke($service, $report, $user, 'Todas las áreas');
    $path = tempnam(sys_get_temp_dir(), 'hu20_brand_');
    try {
        file_put_contents($path, $pdf);
        $process = new Process(['pdftotext', '-layout', $path, '-']);
        $process->mustRun();
        $text = $process->getOutput();
        if (getenv('PANDORA_WRITE_PDF_LAYOUT_QA') === '1') {
            file_put_contents(storage_path('app/qa/pdf-branding-20261103/indicator-summary.pdf'), $pdf);
        }
        expect($text)->toContain('PANDORA', 'Totales por indicador', 'Oculto por privacidad', '87.5%', 'Página 1')
            ->and(preg_match_all('/\b0\b/', $text))->toBe(1)
            ->and(substr_count($text, "\f"))->toBe(1);
        foreach ($columns as $column) {
            expect($text)->toContain($column['etiqueta']);
        }
        if (getenv('PANDORA_WRITE_PDF_LAYOUT_QA') === '1') {
            file_put_contents(storage_path('app/qa/pdf-branding-20261103/indicator-summary.pdf'), $pdf);
        }
    } finally {
        @unlink($path);
    }
});

test('HU20 should_not_merge_divergent_totals_or_discard_explicit_off_diagonal_values', function (): void {
    $value = static fn (int $number): array => ['valor' => $number, 'suprimido' => false];
    $columns = [['id' => 'a', 'etiqueta' => 'Indicador A'], ['id' => 'b', 'etiqueta' => 'Indicador B']];
    $groups = [
        ['dimensiones' => ['indicador' => 'a', 'indicador_etiqueta' => 'Indicador A'], 'valores' => ['a' => $value(12)]],
        ['dimensiones' => ['indicador' => 'b', 'indicador_etiqueta' => 'Indicador B'], 'valores' => ['b' => $value(23)]],
    ];
    $render = static function (array $groups) use ($value, $columns): string {
        $report = new ReporteGenerado(
            parametrosAplicados: ['desde' => '2026-10-01', 'hasta' => '2026-10-31', 'agrupar_por' => ['indicador']],
            generadoEn: '2026-11-03T12:00:00-06:00', generadoPor: 'QA', columnas: $columns,
            grupos: $groups, totales: ['a' => $value(120), 'b' => $value(230)], sinDatos: false, minimoCelda: 5,
        );

        return view('exports.reporte-pdf', ['report' => $report, 'areaEtiqueta' => 'QA', 'generadoPor' => 'QA', 'generadoEn' => $report->generadoEn, 'indicadoresEtiquetas' => ['Indicador A', 'Indicador B'], 'agrupacionesEtiquetas' => ['Indicador']])->render();
    };
    expect($render($groups))->toContain('<th>Detalle</th>', '>120<', '>12<', '>230<', '>23<');
    $groups[0]['valores']['b'] = $value(888);
    expect($render($groups))->toContain('Detalle por grupo', '>888<', 'No aplica')->not->toContain('El total y el detalle coinciden');
});

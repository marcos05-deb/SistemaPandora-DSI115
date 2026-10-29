<?php

declare(strict_types=1);

use App\DTO\ReporteGenerado;
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
    $report = new ReporteGenerado(
        parametrosAplicados: ['desde' => '2026-09-01', 'hasta' => '2026-10-22', 'area' => 1,
            'indicadores' => ['consultas_registradas'], 'agrupar_por' => ['mes']],
        generadoEn: '2026-10-22T12:00:00-06:00', generadoPor: 'QA Synthetic',
        columnas: [['id' => 'consultas_registradas', 'etiqueta' => 'Consultas registradas']],
        grupos: [
            ['dimensiones' => ['mes' => '2026-09'], 'valores' => ['consultas_registradas' => $value(6)]],
            ['dimensiones' => ['mes' => '2026-10'], 'valores' => ['consultas_registradas' => $value(7)]],
        ],
        totales: ['consultas_registradas' => $value(13)], sinDatos: false, minimoCelda: 5,
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
        expect($text)->toMatch('/consultas_registradas\s+13/')
            ->and($text)->toMatch('/2026-09\s+6/')
            ->and($text)->toMatch('/2026-10\s+7/');

        file_put_contents($xlsxPath, $xlsx);
        $reader->open($xlsxPath);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = array_map(static fn ($cell): string => (string) $cell->getValue(), $row->getCells());
            }
        }
        expect($rows)->toContain(['consultas_registradas', '13'])
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

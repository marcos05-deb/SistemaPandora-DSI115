<?php

declare(strict_types=1);

use App\DTO\ReporteGenerado;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Console\Kernel;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Synthetic data only: no authentication, database reads, seeds or migrations.
$value = ['valor' => 13, 'suprimido' => false, 'unidad' => 'conteo'];
$report = new ReporteGenerado(
    parametrosAplicados: ['desde' => '2026-09-01', 'hasta' => '2026-10-22', 'area' => 1,
        'indicadores' => ['consultas_registradas'], 'agrupar_por' => ['indicador']],
    generadoEn: '2026-10-22T12:00:00-06:00', generadoPor: 'QA Synthetic',
    columnas: [['id' => 'consultas_registradas', 'etiqueta' => 'Consultas registradas']],
    grupos: [['dimensiones' => ['indicador_etiqueta' => 'Consultas registradas'],
        'valores' => ['consultas_registradas' => $value]]],
    totales: ['consultas_registradas' => $value], sinDatos: false, minimoCelda: 5,
);
$path = tempnam(sys_get_temp_dir(), 'hu20_readonly_');
if ($path === false) {
    throw new RuntimeException('Temporary filesystem is not writable.');
}
$writer = new Writer;
try {
    $pdf = Pdf::loadView('exports.reporte-pdf', [
        'report' => $report, 'areaEtiqueta' => 'QA Area',
        'generadoPor' => 'QA Synthetic', 'generadoEn' => $report->generadoEn,
    ])->setPaper('a4', 'landscape')->output();
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['consultas_registradas', 13]));
    $writer->close();
    $xlsx = (string) file_get_contents($path);
    if (! str_starts_with($pdf, '%PDF') || ! str_starts_with($xlsx, 'PK')) {
        throw new RuntimeException('Invalid PDF or XLSX output.');
    }
    echo "HU20-E13 synthetic renderer smoke: PASS\n";
    echo 'PDF SHA256: '.hash('sha256', $pdf)."\n";
    echo 'XLSX SHA256: '.hash('sha256', $xlsx)."\n";
} finally {
    $writer->close();
    @unlink($path);
}

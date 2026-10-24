<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\ArchivoExportado;
use App\DTO\ParametrosReporte;
use App\DTO\ReporteGenerado;
use App\Enums\FormatoExportacion;
use App\Models\Especialista;
use App\Models\ExportacionReporte;
use App\Policies\ReportePolicy;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

final class ReporteExportService
{
    public const MAX_FILAS = 5000;

    public function __construct(
        private readonly ReporteParametrizadoService $reportes,
        private readonly ReportePolicy $policy,
    ) {}

    public function exportar(ParametrosReporte $parameters, FormatoExportacion $formato, Especialista $user): ArchivoExportado
    {
        if (! $this->policy->generar($user, $parameters->areaId, $parameters->todasAreas)) {
            throw new AuthorizationException;
        }

        $report = $this->reportes->generar($parameters, $user);
        if (count($report->grupos) > self::MAX_FILAS) {
            throw ValidationException::withMessages([
                'indicadores' => 'El reporte supera '.self::MAX_FILAS.' filas. Acota el período o los indicadores.',
            ]);
        }

        $areaEtiqueta = $this->etiquetaArea($parameters);
        $contenido = match ($formato) {
            FormatoExportacion::Pdf => $this->buildPdf($report, $user, $areaEtiqueta),
            FormatoExportacion::Excel => $this->buildExcel($report, $user, $areaEtiqueta),
        };

        $sha = hash('sha256', $contenido);
        $nombre = $this->nombreArchivo($parameters, $formato);

        DB::transaction(function () use ($user, $formato, $parameters, $sha): void {
            ExportacionReporte::query()->create([
                'usuario_id' => $user->id,
                'formato' => $formato->value,
                'parametros' => $parameters->toArray(),
                'sha256' => $sha,
                'generado_en' => now('UTC'),
            ]);
        });

        return new ArchivoExportado($contenido, $nombre, $formato, $sha);
    }

    private function buildPdf(ReporteGenerado $report, Especialista $user, string $areaEtiqueta): string
    {
        $html = view('exports.reporte-pdf', [
            'report' => $report,
            'areaEtiqueta' => $areaEtiqueta,
            'generadoPor' => $this->etiquetaUsuario($user),
            'generadoEn' => Carbon::now(config('app.timezone'))->toIso8601String(),
        ])->render();

        return Pdf::loadHTML($html)
            ->setPaper('a4', 'landscape')
            ->output();
    }

    private function buildExcel(ReporteGenerado $report, Especialista $user, string $areaEtiqueta): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'rpt_');
        if ($tmp === false) {
            throw new \RuntimeException('No se pudo crear temporal para Excel.');
        }
        $path = $tmp.'.xlsx';
        @unlink($tmp);

        $writer = new XlsxWriter;
        $writer->openToFile($path);

        $writer->getCurrentSheet()->setName('Resumen');
        $writer->addRow(Row::fromValues([$this->sanitizeCell('Reporte parametrizado PANDORA')]));
        $writer->addRow(Row::fromValues([
            $this->sanitizeCell('Generado en'),
            $this->sanitizeCell(Carbon::now(config('app.timezone'))->toIso8601String()),
        ]));
        $writer->addRow(Row::fromValues([
            $this->sanitizeCell('Generado por'),
            $this->sanitizeCell($this->etiquetaUsuario($user)),
        ]));
        $writer->addRow(Row::fromValues([
            $this->sanitizeCell('Período'),
            $this->sanitizeCell($report->parametrosAplicados['desde'].' — '.$report->parametrosAplicados['hasta']),
        ]));
        $writer->addRow(Row::fromValues([
            $this->sanitizeCell('Área'),
            $this->sanitizeCell($areaEtiqueta),
        ]));
        $writer->addRow(Row::fromValues([
            $this->sanitizeCell('Indicadores'),
            $this->sanitizeCell(implode(', ', $report->parametrosAplicados['indicadores'])),
        ]));
        $writer->addRow(Row::fromValues([
            $this->sanitizeCell('Agrupación'),
            $this->sanitizeCell(implode(', ', $report->parametrosAplicados['agrupar_por'])),
        ]));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues([$this->sanitizeCell('Totales')]));
        foreach ($report->totales as $indicatorId => $valor) {
            $writer->addRow(Row::fromValues([
                $this->sanitizeCell((string) $indicatorId),
                $this->sanitizeCell($this->formatValor($valor)),
            ]));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName('Detalle');
        $headers = array_merge(
            array_map(static fn (string $d): string => $d, $report->parametrosAplicados['agrupar_por']),
            array_map(static fn (array $c): string => $c['etiqueta'], $report->columnas)
        );
        $writer->addRow(Row::fromValues(array_map(fn (string $h): string => $this->sanitizeCell($h), $headers)));

        foreach ($report->grupos as $grupo) {
            $row = [];
            foreach ($report->parametrosAplicados['agrupar_por'] as $dimension) {
                $key = $dimension === 'indicador' ? 'indicador_etiqueta' : $dimension;
                $row[] = $this->sanitizeCell((string) ($grupo['dimensiones'][$key] ?? '—'));
            }
            foreach ($report->columnas as $columna) {
                $row[] = $this->sanitizeCell($this->formatValor($grupo['valores'][$columna['id']] ?? null));
            }
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();
        $contenido = (string) file_get_contents($path);
        @unlink($path);

        return $contenido;
    }

    /** @param array<string, mixed>|null $valor */
    private function formatValor(?array $valor): string
    {
        if ($valor === null) {
            return '0';
        }
        if (! empty($valor['suprimido'])) {
            return ($valor['supresion'] ?? '') === 'primaria' ? '<5' : 'Protegido';
        }

        $number = (string) ($valor['valor'] ?? 0);

        return ($valor['unidad'] ?? null) === 'porcentaje' ? $number.'%' : $number;
    }

    private function sanitizeCell(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t"], true)) {
            return "'".$value;
        }

        return $value;
    }

    private function etiquetaUsuario(Especialista $user): string
    {
        $rol = $user->roles()->orderByDesc('roles.nivel')->value('roles.nombre') ?? 'Usuario';

        return trim($user->name).' ('.$rol.')';
    }

    private function etiquetaArea(ParametrosReporte $parameters): string
    {
        if ($parameters->todasAreas) {
            return 'Todas las áreas';
        }

        $nombre = DB::table('areas')->where('id', $parameters->areaId)->value('nombre');

        return is_string($nombre) && $nombre !== '' ? $nombre : (string) $parameters->areaId;
    }

    private function nombreArchivo(ParametrosReporte $parameters, FormatoExportacion $formato): string
    {
        $slug = implode('-', array_slice($parameters->indicadores, 0, 3));
        $stamp = Carbon::now(config('app.timezone'))->format('Ymd-Hi');

        return sprintf(
            'reporte_%s_%s_%s_%s.%s',
            preg_replace('/[^a-z0-9\-]+/i', '-', $slug) ?: 'indicadores',
            $parameters->desde,
            $parameters->hasta,
            $stamp,
            $formato->extension()
        );
    }
}

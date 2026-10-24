<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\ArchivoExportado;
use App\DTO\FiltrosBitacora;
use App\Enums\FormatoExportacion;
use App\Models\Especialista;
use App\Models\ExportacionAuditoria;
use App\Policies\BitacoraPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BitacoraExportService
{
    public const MAX_EVENTOS = 50000;

    public const FORMATO_VERSION = 'bitacora-csv-v1';

    public function __construct(
        private readonly BitacoraQueryService $bitacora,
        private readonly BitacoraPolicy $policy,
    ) {}

    public function exportar(FiltrosBitacora $filtros, Especialista $user): ArchivoExportado
    {
        if (! $this->policy->exportar($user)) {
            throw new AuthorizationException;
        }

        $corteEn = CarbonImmutable::now('UTC');
        $max = (int) config('auditoria.export_max_eventos', self::MAX_EVENTOS);
        $total = $this->bitacora->contar($filtros, $corteEn);
        if ($total > $max) {
            throw ValidationException::withMessages([
                'hasta' => 'La exportación supera '.$max.' eventos. Acota el período o los filtros.',
            ]);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'bit_');
        if ($tmp === false) {
            throw new \RuntimeException('No se pudo crear temporal para la exportación.');
        }
        $path = $tmp.'.csv';
        @unlink($tmp);

        try {
            $handle = fopen($path, 'wb');
            if ($handle === false) {
                throw new \RuntimeException('No se pudo abrir el temporal CSV.');
            }

            fwrite($handle, "\xEF\xBB\xBF");
            $generadoEn = CarbonImmutable::now(config('app.timezone'));
            $rol = $user->roles()->orderByDesc('roles.nivel')->value('roles.nombre') ?? 'Usuario';
            $this->writeMeta($handle, [
                '# PANDORA evidencia de bitácora',
                '# formato='.self::FORMATO_VERSION,
                '# generado_por='.$this->csvCell($user->name.' ('.$rol.') #'.$user->id),
                '# generado_en='.$generadoEn->toIso8601String(),
                '# corte_en='.$corteEn->toIso8601String(),
                '# desde='.$filtros->desde,
                '# hasta='.$filtros->hasta,
                '# usuario_filtro='.($filtros->usuarioId ?? ''),
                '# accion_filtro='.($filtros->accion ?? ''),
                '# registro_filtro='.($filtros->registroId ?? ''),
                '# tipo_filtro='.($filtros->tipo ?? ''),
                '# total_eventos='.$total,
            ]);

            fputcsv($handle, [
                'id', 'fecha', 'usuario_id', 'usuario_nombre', 'usuario_rol',
                'accion', 'objeto_tipo', 'objeto_id', 'ip', 'ruta', 'campos',
            ]);

            foreach ($this->bitacora->mapear($this->bitacora->consulta($filtros, $corteEn)->get()) as $row) {
                fputcsv($handle, [
                    $this->csvCell((string) $row['id']),
                    $this->csvCell((string) $row['fecha']),
                    $this->csvCell((string) ($row['usuario']['id'] ?? '')),
                    $this->csvCell((string) ($row['usuario']['nombre'] ?? '')),
                    $this->csvCell((string) ($row['usuario']['rol'] ?? '')),
                    $this->csvCell((string) $row['accion']),
                    $this->csvCell((string) $row['objeto_tipo']),
                    $this->csvCell((string) $row['objeto_id']),
                    $this->csvCell((string) ($row['ip'] ?? '')),
                    $this->csvCell((string) ($row['ruta'] ?? '')),
                    $this->csvCell(implode('|', $row['campos'])),
                ]);
            }

            fclose($handle);
            $handle = null;

            $contenido = (string) file_get_contents($path);
            $sha = hash('sha256', $contenido);
            $nombre = sprintf(
                'bitacora_%s_%s_%s.csv',
                $filtros->desde,
                $filtros->hasta,
                $generadoEn->format('Ymd-Hi')
            );

            if (config('auditoria.simulate_export_failure')) {
                throw new \RuntimeException('Fallo simulado de exportación de bitácora.');
            }

            DB::transaction(function () use ($user, $filtros, $total, $sha, $corteEn, $generadoEn): void {
                ExportacionAuditoria::query()->create([
                    'usuario_id' => $user->id,
                    'filtros' => $filtros->toArray(),
                    'total_eventos' => $total,
                    'sha256' => $sha,
                    'formato' => 'csv',
                    'corte_en' => $corteEn,
                    'generada_en' => $generadoEn->utc(),
                ]);
            });

            return new ArchivoExportado($contenido, $nombre, FormatoExportacion::Csv, $sha);
        } catch (\Throwable $e) {
            if (is_resource($handle ?? null)) {
                fclose($handle);
            }
            throw $e;
        } finally {
            @unlink($path);
        }
    }

    /** @param resource $handle */
    private function writeMeta($handle, array $lines): void
    {
        foreach ($lines as $line) {
            fwrite($handle, $line."\n");
        }
    }

    private function csvCell(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t"], true)) {
            return "'".$value;
        }

        return $value;
    }
}

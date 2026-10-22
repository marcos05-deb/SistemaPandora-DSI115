<?php

declare(strict_types=1);

namespace App\Enums;

enum TipoIndicador: string
{
    case ConsultasRegistradas = 'consultas_registradas';
    case PacientesAtendidos = 'pacientes_atendidos';
    case ExpedientesAbiertos = 'expedientes_abiertos';
    case ExpedientesCerrados = 'expedientes_cerrados';
    case CitasProgramadas = 'citas_programadas';
    case CitasAsistidas = 'citas_asistidas';
    case CitasNoAsistidas = 'citas_no_asistidas';
    case CitasCanceladas = 'citas_canceladas';
    case CitasReprogramadas = 'citas_reprogramadas';
    case TasaAsistencia = 'tasa_asistencia';
    case AlertasGeneradas = 'alertas_generadas';
    case SeguimientosRegistrados = 'seguimientos_registrados';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ConsultasRegistradas => 'Consultas registradas',
            self::PacientesAtendidos => 'Pacientes atendidos por área',
            self::ExpedientesAbiertos => 'Expedientes abiertos',
            self::ExpedientesCerrados => 'Expedientes cerrados',
            self::CitasProgramadas => 'Citas programadas',
            self::CitasAsistidas => 'Citas asistidas',
            self::CitasNoAsistidas => 'Citas no asistidas',
            self::CitasCanceladas => 'Citas canceladas',
            self::CitasReprogramadas => 'Citas reprogramadas',
            self::TasaAsistencia => 'Tasa de asistencia',
            self::AlertasGeneradas => 'Alertas generadas por señal',
            self::SeguimientosRegistrados => 'Seguimientos preventivos',
        };
    }

    public function definicion(): string
    {
        return match ($this) {
            self::ConsultasRegistradas => 'Atenciones clínicas registradas dentro del período.',
            self::PacientesAtendidos => 'Pacientes distintos atendidos en el período; el total institucional cuenta cada paciente una sola vez.',
            self::ExpedientesAbiertos => 'Expedientes abiertos o en atención creados dentro del período.',
            self::ExpedientesCerrados => 'Expedientes cerrados dentro del período según fecha de cierre.',
            self::CitasProgramadas => 'Citas con estado programada y fecha dentro del período.',
            self::CitasAsistidas => 'Citas con estado asistida y fecha dentro del período.',
            self::CitasNoAsistidas => 'Citas con estado ausente y fecha dentro del período.',
            self::CitasCanceladas => 'Citas con estado cancelada y fecha dentro del período.',
            self::CitasReprogramadas => 'Citas con estado reprogramada y fecha dentro del período.',
            self::TasaAsistencia => 'Asistidas divididas entre asistidas más no asistidas.',
            self::AlertasGeneradas => 'Alertas preventivas generadas dentro del período, desglosadas por señal.',
            self::SeguimientosRegistrados => 'Seguimientos preventivos registrados dentro del período.',
        };
    }

    public function formula(): string
    {
        return match ($this) {
            self::ConsultasRegistradas => 'COUNT(consultas.id)',
            self::PacientesAtendidos => 'COUNT(DISTINCT expedientes.paciente_id) por área y en el total institucional',
            self::ExpedientesAbiertos => "COUNT(estado IN ('abierto', 'en_atencion'))",
            self::ExpedientesCerrados => "COUNT(estado = 'cerrado')",
            self::CitasProgramadas => "COUNT(estado = 'programada')",
            self::CitasAsistidas => "COUNT(estado = 'asistida')",
            self::CitasNoAsistidas => "COUNT(estado = 'ausente')",
            self::CitasCanceladas => "COUNT(estado = 'cancelada')",
            self::CitasReprogramadas => "COUNT(estado = 'reprogramada')",
            self::TasaAsistencia => 'asistidas / (asistidas + no_asistidas) * 100',
            self::AlertasGeneradas => 'COUNT(alertas_preventivas.id) GROUP BY senal',
            self::SeguimientosRegistrados => 'COUNT(seguimientos_preventivos.id)',
        };
    }

    public function fuente(): string
    {
        return match ($this) {
            self::ConsultasRegistradas, self::PacientesAtendidos => 'consultas + expedientes',
            self::ExpedientesAbiertos, self::ExpedientesCerrados => 'expedientes',
            self::CitasProgramadas, self::CitasAsistidas, self::CitasNoAsistidas,
            self::CitasCanceladas, self::CitasReprogramadas, self::TasaAsistencia => 'citas',
            self::AlertasGeneradas => 'alertas_preventivas',
            self::SeguimientosRegistrados => 'seguimientos_preventivos',
        };
    }

    public function categoria(): string
    {
        return match ($this) {
            self::ConsultasRegistradas, self::PacientesAtendidos => 'atencion',
            self::ExpedientesAbiertos, self::ExpedientesCerrados => 'expedientes',
            self::CitasProgramadas, self::CitasAsistidas, self::CitasNoAsistidas,
            self::CitasCanceladas, self::CitasReprogramadas, self::TasaAsistencia => 'asistencia',
            self::AlertasGeneradas, self::SeguimientosRegistrados => 'riesgo_preventivo',
        };
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(static fn (self $indicador): string => $indicador->value, self::cases());
    }
}

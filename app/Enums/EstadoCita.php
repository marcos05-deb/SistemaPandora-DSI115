<?php

declare(strict_types=1);

namespace App\Enums;

enum EstadoCita: string
{
    case Programada = 'programada';
    case Asistida = 'asistida';
    case Ausente = 'ausente';
    case Reprogramada = 'reprogramada';
    case Cancelada = 'cancelada';

    /**
     * Estados resultantes del registro de asistencia/ausencia.
     *
     * @return list<string>
     */
    public static function asistenciaValues(): array
    {
        return [
            self::Asistida->value,
            self::Ausente->value,
        ];
    }

    public function esResultadoAsistencia(): bool
    {
        return in_array($this, [self::Asistida, self::Ausente], true);
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Programada => 'Programada',
            self::Asistida => 'Asistió',
            self::Ausente => 'Ausente',
            self::Reprogramada => 'Reprogramada',
            self::Cancelada => 'Cancelada',
        };
    }
}

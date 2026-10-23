<?php

declare(strict_types=1);

namespace App\Enums;

enum AgrupacionReporte: string
{
    case Indicador = 'indicador';
    case Area = 'area';
    case Mes = 'mes';
    case Semana = 'semana';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Indicador => 'Indicador',
            self::Area => 'Área',
            self::Mes => 'Mes',
            self::Semana => 'Semana',
        };
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(static fn (self $group): string => $group->value, self::cases());
    }
}

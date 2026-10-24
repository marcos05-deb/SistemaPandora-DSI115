<?php

declare(strict_types=1);

namespace App\Enums;

enum FormatoExportacion: string
{
    case Pdf = 'pdf';
    case Excel = 'xlsx';
    case Csv = 'csv';

    public function mime(): string
    {
        return match ($this) {
            self::Pdf => 'application/pdf',
            self::Excel => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::Csv => 'text/csv; charset=UTF-8',
        };
    }

    public function extension(): string
    {
        return $this->value;
    }
}

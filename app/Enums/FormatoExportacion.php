<?php

declare(strict_types=1);

namespace App\Enums;

enum FormatoExportacion: string
{
    case Pdf = 'pdf';
    case Excel = 'xlsx';

    public function mime(): string
    {
        return match ($this) {
            self::Pdf => 'application/pdf',
            self::Excel => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };
    }

    public function extension(): string
    {
        return $this->value;
    }
}

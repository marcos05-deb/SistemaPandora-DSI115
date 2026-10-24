<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enums\FormatoExportacion;

final readonly class ArchivoExportado
{
    public function __construct(
        public string $contenido,
        public string $nombreArchivo,
        public FormatoExportacion $formato,
        public string $sha256,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class FiltrosBitacora
{
    public function __construct(
        public string $desde,
        public string $hasta,
        public ?int $usuarioId,
        public ?string $accion,
        public ?string $registroId,
        public ?string $tipo,
        public int $page = 1,
    ) {}

    /** @return array{desde: string, hasta: string, usuario: int|null, accion: string|null, registro: string|null, tipo: string|null} */
    public function toArray(): array
    {
        return [
            'desde' => $this->desde,
            'hasta' => $this->hasta,
            'usuario' => $this->usuarioId,
            'accion' => $this->accion,
            'registro' => $this->registroId,
            'tipo' => $this->tipo,
        ];
    }
}

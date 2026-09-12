<?php

declare(strict_types=1);

namespace Tests\Feature\Cita;

use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * R593-07: evidencia adicional de concurrencia real.
 * La restricción GiST y el SQLSTATE 23P01 ya están cubiertos en ExclusionAgendaDbTest.
 * Una prueba con dos transacciones bloqueantes puede colgar el runner en PHP sincrónico;
 * se deja como grupo opcional para ejecución manual/CI dedicada.
 */
#[Group('concurrent')]
class ConcurrentExclusionTest extends TestCase
{
    public function test_placeholder_concurrencia_documentada(): void
    {
        $this->markTestSkipped(
            'R593-07: usar ExclusionAgendaDbTest (23P01). La prueba PDO concurrente se ejecuta aparte para evitar bloqueos del suite.'
        );
    }
}

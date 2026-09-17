<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Worker solo para pruebas RF-02: inserta una cita tras una barrera de archivos.
 */
class TestingConcurrentCitaInsertCommand extends Command
{
    protected $signature = 'testing:concurrent-cita-insert
                            {--barrier= : Directorio de sincronización entre procesos}
                            {--worker= : Identificador del worker (a|b)}
                            {--expediente= : UUID expediente}
                            {--consulta= : UUID consulta (opcional)}
                            {--profesional= : UUID perfil profesional}
                            {--profesional-user= : ID usuario dueño de la agenda}
                            {--area= : ID área}
                            {--fecha= : Fecha/hora ISO}
                            {--motivo= : Motivo plano (sin cifrado de área)}';

    protected $description = 'Inserta una cita concurrente (solo testing)';

    public function handle(): int
    {
        if (! app()->environment('testing')) {
            $this->error('Este comando solo puede ejecutarse con APP_ENV=testing.');

            return self::FAILURE;
        }

        $barrier = (string) $this->option('barrier');
        $worker = (string) $this->option('worker');

        if ($barrier === '' || $worker === '') {
            $this->error('Faltan --barrier y --worker.');

            return self::FAILURE;
        }

        if (! is_dir($barrier)) {
            $this->error('Barrera inválida.');

            return self::FAILURE;
        }

        file_put_contents($barrier.'/ready-'.$worker, '1');

        $deadline = microtime(true) + 15;
        while (! is_file($barrier.'/go')) {
            if (microtime(true) > $deadline) {
                $this->error('Timeout esperando barrera go.');

                return self::FAILURE;
            }
            usleep(5_000);
        }

        $id = (string) Str::uuid();
        $consultaId = $this->option('consulta');

        try {
            DB::connection('pgsql')->beginTransaction();

            DB::connection('pgsql')->insert(
                <<<'SQL'
                    INSERT INTO citas (
                        id, expediente_id, consulta_id, profesional_id, profesional_user_id, area_id,
                        fecha_hora, estado, motivo, created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, 'programada', ?, NOW(), NOW()
                    )
                SQL,
                [
                    $id,
                    $this->option('expediente'),
                    $consultaId !== null && $consultaId !== '' ? $consultaId : null,
                    $this->option('profesional'),
                    (int) $this->option('profesional-user'),
                    (int) $this->option('area'),
                    $this->option('fecha'),
                    $this->option('motivo') ?: 'Concurrente',
                ]
            );

            DB::connection('pgsql')->commit();
            $this->line('ok:'.$id);

            return self::SUCCESS;
        } catch (Throwable $e) {
            try {
                DB::connection('pgsql')->rollBack();
            } catch (Throwable) {
                // ignore
            }

            $sqlState = $e instanceof \Illuminate\Database\QueryException
                ? ($e->errorInfo[0] ?? null)
                : null;

            if ($sqlState === '23P01'
                || str_contains($e->getMessage(), 'citas_no_solapamiento_usuario_programada')
                || str_contains($e->getMessage(), 'exclusion constraint')) {
                $this->line('conflict:23P01');

                return 2;
            }

            $this->error('error:'.$e->getMessage());

            return self::FAILURE;
        }
    }
}

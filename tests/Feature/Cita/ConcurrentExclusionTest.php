<?php

declare(strict_types=1);

namespace Tests\Feature\Cita;

use App\Enums\EstadoCita;
use App\Models\Area;
use App\Models\Cita;
use App\Models\Consulta;
use App\Models\Especialista;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * RF-02: dos procesos independientes intentan insertar citas solapadas
 * para la misma persona (perfiles distintos) y solo una debe confirmar.
 */
#[Group('concurrent')]
class ConcurrentExclusionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sin transacción envolvente: los workers hijos deben ver datos confirmados.
     *
     * @var array<int, string>
     */
    protected $connectionsToTransact = [];

    public function test_dos_procesos_misma_persona_areas_distintas_solo_una_confirma(): void
    {
        session(['_sym_key' => str_repeat('a', 32)]);

        $areaA = Area::factory()->create(['nombre' => 'Psicología Concurrente']);
        $areaB = Area::factory()->create(['nombre' => 'Medicina Concurrente']);
        $role = Role::firstOrCreate(
            ['slug' => 'specialist'],
            ['nombre' => 'Especialista', 'is_active' => true]
        );

        $user = Especialista::factory()->create(['is_active' => true]);
        $user->roles()->attach($role);

        $profA = Profesional::factory()->create([
            'user_id' => $user->id,
            'area_id' => $areaA->id,
        ]);
        $profB = Profesional::factory()->create([
            'user_id' => $user->id,
            'area_id' => $areaB->id,
        ]);

        $paciente = Paciente::factory()->create();
        $expedienteA = Expediente::factory()->create([
            'paciente_id' => $paciente->codigo,
            'area_id' => $areaA->id,
            'estado' => 'abierto',
        ]);
        $expedienteB = Expediente::factory()->create([
            'paciente_id' => $paciente->codigo,
            'area_id' => $areaB->id,
            'estado' => 'abierto',
        ]);

        $consultaA = Consulta::create([
            'expediente_id' => $expedienteA->id,
            'profesional_id' => $profA->id,
            'motivo_consulta' => 'Motivo A concurrente',
            'notas_clinicas' => 'Notas A',
            'diagnostico' => 'Dx A',
            'plan_atencion' => 'Plan A',
            'tecnica_utilizada' => 'Entrevista',
            'fecha_consulta' => now(),
        ]);
        $consultaB = Consulta::create([
            'expediente_id' => $expedienteB->id,
            'profesional_id' => $profB->id,
            'motivo_consulta' => 'Motivo B concurrente',
            'notas_clinicas' => 'Notas B',
            'diagnostico' => 'Dx B',
            'plan_atencion' => 'Plan B',
            'tecnica_utilizada' => 'Entrevista',
            'fecha_consulta' => now(),
        ]);

        $inicio = now()->addDays(5)->setTime(10, 0)->seconds(0);
        $fechaIso = $inicio->toIso8601String();
        $barrier = sys_get_temp_dir().'/cita-barrier-'.uniqid('', true);
        mkdir($barrier, 0700, true);

        try {
            $php = PHP_BINARY;
            $artisan = base_path('artisan');
            $cwd = base_path();
            $env = $this->workerEnvironment();

            $mk = function (string $worker, Profesional $prof, Expediente $exp, Consulta $consulta) use (
                $php,
                $artisan,
                $cwd,
                $env,
                $barrier,
                $fechaIso,
                $user
            ): Process {
                $process = new Process(
                    [
                        $php,
                        $artisan,
                        'testing:concurrent-cita-insert',
                        '--barrier='.$barrier,
                        '--worker='.$worker,
                        '--expediente='.$exp->id,
                        '--consulta='.$consulta->id,
                        '--profesional='.$prof->id,
                        '--profesional-user='.$user->id,
                        '--area='.$exp->area_id,
                        '--fecha='.$fechaIso,
                        '--motivo=Concurrente '.$worker,
                    ],
                    $cwd,
                    $env,
                    null,
                    30
                );
                $process->start();

                return $process;
            };

            $procA = $mk('a', $profA, $expedienteA, $consultaA);
            $procB = $mk('b', $profB, $expedienteB, $consultaB);

            $readyDeadline = microtime(true) + 10;
            while (! (is_file($barrier.'/ready-a') && is_file($barrier.'/ready-b'))) {
                if (microtime(true) > $readyDeadline) {
                    $procA->stop(1);
                    $procB->stop(1);
                    $this->fail(
                        'Los workers no señalaron ready a tiempo. A='.$procA->getOutput().$procA->getErrorOutput().
                        ' B='.$procB->getOutput().$procB->getErrorOutput()
                    );
                }
                usleep(5_000);
            }

            file_put_contents($barrier.'/go', '1');

            $procA->wait();
            $procB->wait();

            $outputs = [
                trim($procA->getOutput().$procA->getErrorOutput()),
                trim($procB->getOutput().$procB->getErrorOutput()),
            ];
            $codes = [$procA->getExitCode(), $procB->getExitCode()];

            $exitosos = collect($codes)->filter(fn ($c) => $c === 0)->count();
            $rechazados = collect($codes)->filter(fn ($c) => $c === 2)->count();

            $this->assertSame(
                1,
                $exitosos,
                'Debía haber exactamente un proceso exitoso. Códigos='.json_encode($codes).' Salidas: '.implode(' | ', $outputs)
            );
            $this->assertSame(
                1,
                $rechazados,
                'Debía haber exactamente un conflicto 23P01. Códigos='.json_encode($codes).' Salidas: '.implode(' | ', $outputs)
            );

            $programadas = Cita::withoutGlobalScopes()
                ->where('profesional_user_id', $user->id)
                ->where('estado', EstadoCita::Programada->value)
                ->where('fecha_hora', '>=', $inicio->copy()->subSecond())
                ->where('fecha_hora', '<=', $inicio->copy()->addSecond())
                ->count();

            $this->assertSame(1, $programadas);
        } finally {
            Cita::withoutGlobalScopes()
                ->where('profesional_user_id', $user->id ?? 0)
                ->forceDelete();

            foreach (glob($barrier.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($barrier);

            // Evitar que datos confirmados (sin transacción) contaminen el resto de la suite.
            \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
        }
    }

    /**
     * @return array<string, string>
     */
    private function workerEnvironment(): array
    {
        $env = [];
        foreach (array_merge($_SERVER, $_ENV) as $key => $value) {
            if (is_string($key) && (is_string($value) || is_numeric($value) || is_bool($value))) {
                $env[$key] = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
            }
        }

        $env['APP_ENV'] = 'testing';
        $env['DB_CONNECTION'] = (string) config('database.default', 'pgsql');
        $env['DB_HOST'] = (string) config('database.connections.pgsql.host');
        $env['DB_PORT'] = (string) config('database.connections.pgsql.port');
        $env['DB_DATABASE'] = (string) config('database.connections.pgsql.database');
        $env['DB_USERNAME'] = (string) config('database.connections.pgsql.username');
        $env['DB_PASSWORD'] = (string) config('database.connections.pgsql.password');
        $env['APP_KEY'] = (string) config('app.key');

        return $env;
    }
}

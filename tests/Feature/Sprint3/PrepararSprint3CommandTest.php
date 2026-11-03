<?php

declare(strict_types=1);

use App\Models\AlertaPreventiva;
use App\Models\Area;
use App\Models\Carrera;
use App\Models\Cita;
use App\Models\Consulta;
use App\Models\Especialista;
use App\Models\EstadisticaPreventivaAusencia;
use App\Models\EvaluacionRiesgo;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\Role;
use App\Models\RubricaVariable;
use App\Models\RubricaVersion;
use App\Models\SeguimientoPreventivo;
use App\Services\RubricaResolver;
use App\Support\PrivacyThreshold;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;

const SPRINT3_TEST_PASSWORD = 'Demo-Sprint3-Passw0rd';

beforeEach(function (): void {
    $this->withoutMiddleware([PreventRequestForgery::class, ThrottleRequests::class]);
    config([
        'app.area_key_secret' => str_repeat('area-secret-', 4),
        'app.sprint3_demo_password' => SPRINT3_TEST_PASSWORD,
    ]);
    $this->withSession(['_sym_key' => base64_encode(str_repeat('s', SODIUM_CRYPTO_SECRETBOX_KEYBYTES))]);
});

/** @return array<string, int> */
function sprint3Conteos(): array
{
    $conteos = [];
    foreach ([
        Role::class, Area::class, Carrera::class, Especialista::class, Profesional::class, Paciente::class,
        Expediente::class, Consulta::class, Cita::class, EstadisticaPreventivaAusencia::class,
        RubricaVersion::class, RubricaVariable::class, EvaluacionRiesgo::class, AlertaPreventiva::class,
        SeguimientoPreventivo::class,
    ] as $modelo) {
        $conteos[$modelo] = $modelo::query()->withoutGlobalScopes()->count();
    }

    return $conteos;
}

it('refuses to run without a strong SPRINT3_DEMO_PASSWORD', function (): void {
    config(['app.sprint3_demo_password' => null]);
    $this->artisan('pandora:preparar-sprint3')->assertExitCode(1);

    config(['app.sprint3_demo_password' => 'corta']);
    $this->artisan('pandora:preparar-sprint3')->assertExitCode(1);

    expect(Especialista::query()->count())->toBe(0);
});

it('requires --force in production', function (): void {
    $this->app->detectEnvironment(fn (): string => 'production');

    $this->artisan('pandora:preparar-sprint3')->assertExitCode(1);

    expect(Especialista::query()->count())->toBe(0);
});

it('is idempotent and prepares the Sprint 3 demo data', function (): void {
    $this->artisan('pandora:preparar-sprint3')->assertExitCode(0);
    $primera = sprint3Conteos();
    $passwordHash = Especialista::query()->where('email', 'admin@pandora.com')->value('password');

    $this->artisan('pandora:preparar-sprint3')->assertExitCode(0);

    expect(sprint3Conteos())->toBe($primera);
    // Existing users keep their password unless --reset-passwords is passed.
    expect(Especialista::query()->where('email', 'admin@pandora.com')->value('password'))->toBe($passwordHash);

    // Rubric: non-demo, validated and currently in force.
    $vigente = app(RubricaResolver::class)->vigente();
    expect($vigente)->not->toBeNull()
        ->and($vigente->es_demo)->toBeFalsy()
        ->and($vigente->referencia_validacion)->toBe('Validación Sprint 3 — DSI115')
        ->and($vigente->variables->pluck('tipo_senal')->sort()->values()->all())
        ->toBe(['ausencia_cita', 'crisis_psicologica', 'tendencia_suicida']);

    // Analytics volume in Psicología is above the k-anonymity minimum cell.
    $psicologia = Area::query()->where('nombre', 'Psicología')->firstOrFail();
    $pacientesPsi = Expediente::query()->withoutGlobalScopes()->where('area_id', $psicologia->id)->distinct()->count('paciente_id');
    $ausentesPsi = Cita::query()->withoutGlobalScopes()->where('area_id', $psicologia->id)->where('estado', 'ausente')->count();
    $asistidasPsi = Cita::query()->withoutGlobalScopes()->where('area_id', $psicologia->id)->where('estado', 'asistida')->count();
    expect($pacientesPsi)->toBeGreaterThanOrEqual(PrivacyThreshold::minCell())
        ->and($ausentesPsi)->toBeGreaterThanOrEqual(PrivacyThreshold::minCell())
        ->and($asistidasPsi)->toBeGreaterThanOrEqual(PrivacyThreshold::minCell());

    // HU-16: patient ready for the live demo has no alerts and two past pending appointments.
    $paciente = Paciente::query()->where('carnet', 'DM26010')->firstOrFail();
    $expediente = Expediente::query()->withoutGlobalScopes()->where('paciente_id', $paciente->codigo)->firstOrFail();
    expect($expediente->estado)->toBe(Expediente::ESTADO_ABIERTO)
        ->and(AlertaPreventiva::query()->withoutGlobalScopes()->where('paciente_id', $paciente->codigo)->count())->toBe(0);
    $pendientes = Cita::query()->withoutGlobalScopes()
        ->where('expediente_id', $expediente->id)
        ->where('estado', 'programada')
        ->where('fecha_hora', '<', now())
        ->get();
    expect($pendientes)->toHaveCount(2);

    // HU-17 backups: one active alert and one closed alert with follow-up.
    $activa = Paciente::query()->where('carnet', 'DM26020')->firstOrFail();
    $cerrada = Paciente::query()->where('carnet', 'DM26021')->firstOrFail();
    expect(AlertaPreventiva::query()->withoutGlobalScopes()->where('paciente_id', $activa->codigo)->value('estado'))->toBe('activa');
    $alertaCerrada = AlertaPreventiva::query()->withoutGlobalScopes()->where('paciente_id', $cerrada->codigo)->firstOrFail();
    expect($alertaCerrada->estado)->toBe('cerrada')
        ->and(SeguimientoPreventivo::query()->withoutGlobalScopes()->where('alerta_preventiva_id', $alertaCerrada->id)->count())->toBe(1);

    // Marking the two pending appointments as absent triggers the real alert.
    $profesional = Profesional::query()->findOrFail($pendientes->first()->profesional_id);
    foreach ($pendientes->sortBy('fecha_hora') as $cita) {
        $cita->registrarAsistencia('ausente', $profesional->id);
    }
    expect(AlertaPreventiva::query()->withoutGlobalScopes()
        ->where('paciente_id', $paciente->codigo)
        ->where('senal', 'ausencia_cita')
        ->where('estado', 'activa')
        ->count())->toBe(1);
});

it('lets every seeded user log in', function (): void {
    $this->artisan('pandora:preparar-sprint3')->assertExitCode(0);

    foreach ([
        'admin@pandora.com', 'coordinador@pandora.com', 'especialista-psi@pandora.com',
        'especialista@pandora.com', 'especialista-nutri@pandora.com', 'psicosocial@pandora.com',
    ] as $email) {
        $usuario = Especialista::query()->where('email', $email)->firstOrFail();
        expect($usuario->is_active)->toBeTrue()
            ->and($usuario->must_change_password)->toBeFalsy();

        $this->post('/login', ['email' => $email, 'password' => SPRINT3_TEST_PASSWORD])
            ->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($usuario->fresh());
        expect(session('_sym_key'))->not->toBeEmpty();

        $this->post('/logout');
    }
});

it('resets passwords of existing users only with --reset-passwords', function (): void {
    $this->artisan('pandora:preparar-sprint3')->assertExitCode(0);

    config(['app.sprint3_demo_password' => 'Otra-Passw0rd-Distinta']);
    $this->artisan('pandora:preparar-sprint3')->assertExitCode(0);
    $this->post('/login', ['email' => 'admin@pandora.com', 'password' => 'Otra-Passw0rd-Distinta'])
        ->assertSessionHasErrors('email');

    $this->artisan('pandora:preparar-sprint3', ['--reset-passwords' => true])->assertExitCode(0);
    $this->post('/login', ['email' => 'admin@pandora.com', 'password' => 'Otra-Passw0rd-Distinta'])
        ->assertSessionHasNoErrors();
});


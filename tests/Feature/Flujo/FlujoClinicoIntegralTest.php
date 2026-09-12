<?php

declare(strict_types=1);

/**
 * Prueba integral del flujo clínico de Eduardo (HU-07 → HU-08 → HU-11 → HU-12).
 *
 * Derivar paciente → Registrar consulta → Crear próxima cita → Registrar asistencia.
 */

use App\Enums\EstadoCita;
use App\Events\CitaAusenciaRegistrada;
use App\Models\Area;
use App\Models\Cita;
use App\Models\Consulta;
use App\Models\Especialista;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\Role;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use OwenIt\Auditing\Models\Audit;

function payloadConsultaIntegral(array $overrides = []): array
{
    return array_merge([
        'motivo_consulta' => 'Seguimiento por ansiedad académica',
        'notas_clinicas' => 'El paciente reporta dificultades de concentración en época de exámenes.',
        'diagnostico' => 'Ansiedad situacional leve',
        'plan_atencion' => 'Psicoeducación y seguimiento quincenal con técnicas de respiración.',
        'tecnica_utilizada' => 'Entrevista clínica',
        'fecha_consulta' => now()->toDateString(),
        'evaluacion_inicial' => [
            'apariencia_externa' => 'Adecuada',
            'voz' => 'Normal',
            'patrones_habla' => 'Fluido',
            'expresiones_faciales' => 'Concordantes',
            'ademanes' => 'Normales',
            'actitudes_tratamiento' => 'Colaboradora',
            'impresion' => 'Favorable',
            'plan_tratamiento' => 'Continuar seguimiento',
            'pronostico' => 'Bueno',
        ],
    ], $overrides);
}

beforeEach(function () {
    session(['_sym_key' => str_repeat('a', 32)]);

    $this->area = Area::factory()->create(['nombre' => 'Psicología']);

    $this->roleReferente = Role::create([
        'nombre' => 'Psychosocial Referent',
        'slug' => 'psychosocial_referent',
        'description' => 'Referente psicosocial',
        'is_active' => true,
    ]);

    $this->roleSpecialist = Role::create([
        'nombre' => 'Specialist',
        'slug' => 'specialist',
        'description' => 'Especialista',
        'is_active' => true,
    ]);

    $this->userReferent = Especialista::factory()->create([
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);
    $this->userReferent->roles()->attach($this->roleReferente->id);
    $this->profesionalReferent = Profesional::factory()->create([
        'user_id' => $this->userReferent->id,
    ]);

    $this->userSpecialist = Especialista::factory()->create([
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);
    $this->userSpecialist->roles()->attach($this->roleSpecialist->id);
    $this->profesionalSpecialist = Profesional::factory()->create([
        'user_id' => $this->userSpecialist->id,
        'area_id' => $this->area->id,
    ]);

    $this->paciente = Paciente::factory()->create([
        'creado_por_profesional_id' => $this->profesionalReferent->id,
    ]);

    $this->motivoDerivacion = 'Derivación por indicadores de ansiedad académica persistente.';
});

afterEach(function () {
    Carbon::setTestNow();
});

it('completa el flujo clínico: derivar → consulta → cita → asistencia', function () {
    Event::fake([CitaAusenciaRegistrada::class]);

    // 1) HU-07 — Referente deriva al paciente
    $this->actingAs($this->userReferent)
        ->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('pacientes.derivar.store', $this->paciente->codigo), [
            'area_id' => $this->area->id,
            'motivo_derivacion' => $this->motivoDerivacion,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $expediente = Expediente::withoutGlobalScopes()->sole();
    expect($expediente->paciente_id)->toBe($this->paciente->codigo)
        ->and($expediente->area_id)->toBe($this->area->id)
        ->and($expediente->estado)->toBe(Expediente::ESTADO_ABIERTO)
        ->and($expediente->motivo_derivacion)->toBe($this->motivoDerivacion)
        ->and($expediente->derivado_por_profesional_id)->toBe($this->profesionalReferent->id);

    $motivoRaw = DB::table('expedientes')->where('id', $expediente->id)->value('motivo_derivacion');
    expect($motivoRaw)->not->toContain('ansiedad académica');

    // 2) HU-08 — Especialista registra consulta
    $this->actingAs($this->userSpecialist)
        ->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('consultas.store', $expediente->id), payloadConsultaIntegral())
        ->assertRedirect(route('pacientes.show', $this->paciente->carnet))
        ->assertSessionHas('message', 'Consulta registrada exitosamente.');

    $consulta = Consulta::sole();
    expect($consulta->expediente_id)->toBe($expediente->id)
        ->and($consulta->profesional_id)->toBe($this->profesionalSpecialist->id)
        ->and($consulta->plan_atencion)->toBe(payloadConsultaIntegral()['plan_atencion'])
        ->and($consulta->fecha_consulta->toDateString())->toBe(now()->toDateString());

    $planRaw = DB::table('consultas')->where('id', $consulta->id)->value('plan_atencion');
    expect($planRaw)->not->toContain('Psicoeducación');

    $expediente->refresh();
    expect($expediente->estado)->toBe(Expediente::ESTADO_EN_ATENCION);

    // 3) HU-11 — Desde la consulta, asigna próxima cita (aún futura)
    Carbon::setTestNow(Carbon::parse('2026-09-06 09:00:00', config('app.timezone')));
    $fechaCita = Carbon::parse('2026-09-06 15:00:00', config('app.timezone'));

    $this->actingAs($this->userSpecialist)
        ->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('citas.store', $expediente->id), [
            'consulta_id' => $consulta->id,
            'fecha_hora' => $fechaCita->format('Y-m-d H:i:s'),
            'motivo' => 'Seguimiento post consulta integral',
            'acordada_con_paciente' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $cita = Cita::sole();
    expect($cita->consulta_id)->toBe($consulta->id)
        ->and($cita->expediente_id)->toBe($expediente->id)
        ->and($cita->profesional_id)->toBe($this->profesionalSpecialist->id)
        ->and($cita->estado)->toBe(EstadoCita::Programada->value)
        ->and($cita->fecha_hora->equalTo($fechaCita))->toBeTrue();

    // 3b) Antes de la hora programada no se puede registrar asistencia
    $this->actingAs($this->userSpecialist)
        ->withSession(['_sym_key' => str_repeat('a', 32)])
        ->patch(route('citas.asistencia', [$expediente->id, $cita->id]), [
            'estado' => EstadoCita::Asistida->value,
        ])
        ->assertSessionHasErrors('fecha_hora');

    expect($cita->fresh()->estado)->toBe(EstadoCita::Programada->value);

    // 4) HU-12 — Llegada la hora, registra Ausente (alimenta estadísticas)
    Carbon::setTestNow(Carbon::parse('2026-09-06 15:05:00', config('app.timezone')));

    $this->actingAs($this->userSpecialist)
        ->withSession(['_sym_key' => str_repeat('a', 32)])
        ->patch(route('citas.asistencia', [$expediente->id, $cita->id]), [
            'estado' => EstadoCita::Ausente->value,
        ])
        ->assertRedirect()
        ->assertSessionHas('variant', 'success');

    $cita->refresh();
    expect($cita->estado)->toBe(EstadoCita::Ausente->value)
        ->and($cita->registrado_por_profesional_id)->toBe($this->profesionalSpecialist->id)
        ->and($cita->fecha_registro_asistencia)->not->toBeNull()
        ->and($cita->consulta_id)->toBe($consulta->id);

    Event::assertDispatched(CitaAusenciaRegistrada::class, fn (CitaAusenciaRegistrada $e) => $e->cita->id === $cita->id);

    // No se puede volver a registrar
    $this->actingAs($this->userSpecialist)
        ->withSession(['_sym_key' => str_repeat('a', 32)])
        ->patch(route('citas.asistencia', [$expediente->id, $cita->id]), [
            'estado' => EstadoCita::Asistida->value,
        ])
        ->assertSessionHasErrors('estado');

    expect($cita->fresh()->estado)->toBe(EstadoCita::Ausente->value);

    // Auditoría mínima del flujo
    expect(
        Audit::where('auditable_type', Expediente::class)->where('auditable_id', $expediente->id)->exists()
    )->toBeTrue();
    expect(
        Audit::where('auditable_type', Consulta::class)->where('auditable_id', $consulta->id)->exists()
    )->toBeTrue();
    expect(
        Audit::where('auditable_type', Cita::class)->where('auditable_id', $cita->id)->exists()
    )->toBeTrue();
});

it('rechaza el flujo si se intenta crear cita sin consulta origen', function () {
    $this->actingAs($this->userReferent)
        ->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('pacientes.derivar.store', $this->paciente->codigo), [
            'area_id' => $this->area->id,
            'motivo_derivacion' => $this->motivoDerivacion,
        ])
        ->assertRedirect();

    $expediente = Expediente::withoutGlobalScopes()->sole();

    $this->actingAs($this->userSpecialist)
        ->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('citas.store', $expediente->id), [
            'fecha_hora' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'motivo' => 'Cita sin consulta origen',
            'acordada_con_paciente' => true,
        ])
        ->assertSessionHasErrors('consulta_id');

    expect(Cita::count())->toBe(0);
});

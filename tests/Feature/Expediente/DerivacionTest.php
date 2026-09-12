<?php

declare(strict_types=1);

use App\Models\Area;
use App\Models\Especialista;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    session(['_sym_key' => str_repeat('a', 32)]);
    $this->area = Area::factory()->create();

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

    $this->paciente = Paciente::factory()->create([
        'creado_por_profesional_id' => $this->profesionalReferent->id,
    ]);

    $this->motivoValido = 'Derivación por indicadores de ansiedad académica persistente.';
});

it('can derivar a patient to an area', function () {
    $this->actingAs($this->userReferent);

    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->from(route('pacientes.index'))
        ->post(route('pacientes.derivar.store', $this->paciente->codigo), [
            'area_id' => $this->area->id,
            'motivo_derivacion' => $this->motivoValido,
        ]);

    $response->assertRedirect(route('pacientes.index'));
    $response->assertSessionHas('success', 'Paciente derivado exitosamente.');

    expect(Expediente::withoutGlobalScopes()->count())->toBe(1);

    $expediente = Expediente::withoutGlobalScopes()->first();
    expect($expediente->paciente_id)->toBe($this->paciente->codigo)
        ->and($expediente->area_id)->toBe($this->area->id)
        ->and($expediente->estado)->toBe('abierto')
        ->and($expediente->derivado_por_profesional_id)->toBe($this->profesionalReferent->id)
        ->and($expediente->fecha_derivacion)->not->toBeNull()
        ->and($expediente->motivo_derivacion)->toBe($this->motivoValido);
});

it('stores motivo_derivacion encrypted at rest', function () {
    $this->actingAs($this->userReferent);

    $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('pacientes.derivar.store', $this->paciente->codigo), [
            'area_id' => $this->area->id,
            'motivo_derivacion' => $this->motivoValido,
        ])
        ->assertRedirect();

    $expediente = Expediente::withoutGlobalScopes()->first();
    $raw = DB::table('expedientes')->where('id', $expediente->id)->value('motivo_derivacion');

    expect($raw)->not->toBeNull()
        ->and($raw)->not->toContain('ansiedad académica')
        ->and(base64_decode($raw, true))->not->toBeFalse()
        ->and($expediente->motivo_derivacion)->toBe($this->motivoValido);
});

it('rejects empty motivo_derivacion', function () {
    $this->actingAs($this->userReferent);

    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->from(route('pacientes.index'))
        ->post(route('pacientes.derivar.store', $this->paciente->codigo), [
            'area_id' => $this->area->id,
            'motivo_derivacion' => '',
        ]);

    $response->assertSessionHasErrors('motivo_derivacion');
    expect(Expediente::withoutGlobalScopes()->count())->toBe(0);
});

it('rejects motivo_derivacion shorter than minimum', function () {
    $this->actingAs($this->userReferent);

    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->from(route('pacientes.index'))
        ->post(route('pacientes.derivar.store', $this->paciente->codigo), [
            'area_id' => $this->area->id,
            'motivo_derivacion' => 'corto',
        ]);

    $response->assertSessionHasErrors('motivo_derivacion');
    expect(Expediente::withoutGlobalScopes()->count())->toBe(0);
});

it('cannot derivar if already open in the same area', function () {
    $this->actingAs($this->userReferent);

    Expediente::create([
        'paciente_id' => $this->paciente->codigo,
        'area_id' => $this->area->id,
        'estado' => Expediente::ESTADO_ABIERTO,
        'derivado_por_profesional_id' => $this->profesionalReferent->id,
        'fecha_derivacion' => now(),
        'motivo_derivacion' => $this->motivoValido,
    ]);

    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->from(route('pacientes.index'))
        ->post(route('pacientes.derivar.store', $this->paciente->codigo), [
            'area_id' => $this->area->id,
            'motivo_derivacion' => $this->motivoValido,
        ]);

    $response->assertSessionHasErrors(['area_id' => Expediente::MENSAJE_EXPEDIENTE_ACTIVO_DUPLICADO]);

    expect(Expediente::withoutGlobalScopes()->count())->toBe(1);
});

it('cannot derivar if expediente is already en_atencion in the same area', function () {
    $this->actingAs($this->userReferent);

    Expediente::create([
        'paciente_id' => $this->paciente->codigo,
        'area_id' => $this->area->id,
        'estado' => Expediente::ESTADO_EN_ATENCION,
        'derivado_por_profesional_id' => $this->profesionalReferent->id,
        'fecha_derivacion' => now(),
        'motivo_derivacion' => $this->motivoValido,
    ]);

    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->from(route('pacientes.index'))
        ->post(route('pacientes.derivar.store', $this->paciente->codigo), [
            'area_id' => $this->area->id,
            'motivo_derivacion' => $this->motivoValido,
        ]);

    $response->assertSessionHasErrors(['area_id' => Expediente::MENSAJE_EXPEDIENTE_ACTIVO_DUPLICADO]);
    expect(Expediente::withoutGlobalScopes()->count())->toBe(1);
});

it('allows derivar again after expediente was cerrado', function () {
    $this->actingAs($this->userReferent);

    Expediente::create([
        'paciente_id' => $this->paciente->codigo,
        'area_id' => $this->area->id,
        'estado' => Expediente::ESTADO_CERRADO,
        'derivado_por_profesional_id' => $this->profesionalReferent->id,
        'fecha_derivacion' => now()->subMonth(),
        'motivo_derivacion' => $this->motivoValido,
        'fecha_cierre' => now()->subWeek(),
    ]);

    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->from(route('pacientes.index'))
        ->post(route('pacientes.derivar.store', $this->paciente->codigo), [
            'area_id' => $this->area->id,
            'motivo_derivacion' => $this->motivoValido,
        ]);

    $response->assertRedirect(route('pacientes.index'));
    expect(Expediente::withoutGlobalScopes()->count())->toBe(2);
});

it('unique index prevents a second active expediente at database level', function () {
    Expediente::create([
        'paciente_id' => $this->paciente->codigo,
        'area_id' => $this->area->id,
        'estado' => Expediente::ESTADO_ABIERTO,
        'derivado_por_profesional_id' => $this->profesionalReferent->id,
        'fecha_derivacion' => now(),
        'motivo_derivacion' => $this->motivoValido,
    ]);

    expect(fn () => Expediente::create([
        'paciente_id' => $this->paciente->codigo,
        'area_id' => $this->area->id,
        'estado' => Expediente::ESTADO_EN_ATENCION,
        'derivado_por_profesional_id' => $this->profesionalReferent->id,
        'fecha_derivacion' => now(),
        'motivo_derivacion' => 'Segundo intento de expediente activo concurrente.',
    ]))->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});

it('forbids non psychosocial_referent to derivar', function () {
    $this->actingAs($this->userSpecialist);

    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('pacientes.derivar.store', $this->paciente->codigo), [
            'area_id' => $this->area->id,
            'motivo_derivacion' => $this->motivoValido,
        ]);

    $response->assertForbidden();
});

it('forbids another referent from derivar a patient they do not own', function () {
    $otroReferente = Especialista::factory()->create([
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);
    $otroReferente->roles()->attach($this->roleReferente->id);
    Profesional::factory()->create([
        'user_id' => $otroReferente->id,
    ]);

    $this->actingAs($otroReferente);

    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('pacientes.derivar.store', $this->paciente->codigo), [
            'area_id' => $this->area->id,
            'motivo_derivacion' => $this->motivoValido,
        ]);

    $response->assertForbidden();
    expect(Expediente::withoutGlobalScopes()->count())->toBe(0);
});

it('permite derivar con autorización vigente aunque no sea el creador', function () {
    $otroReferente = Especialista::factory()->create([
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);
    $otroReferente->roles()->attach($this->roleReferente->id);
    $otroProfesional = Profesional::factory()->create([
        'user_id' => $otroReferente->id,
    ]);

    \App\Models\AutorizacionPaciente::create([
        'paciente_id' => $this->paciente->codigo,
        'profesional_id' => $otroProfesional->id,
        'otorgada_por_profesional_id' => $this->profesionalReferent->id,
        'vigente_desde' => now()->subDay(),
        'vigente_hasta' => now()->addDays(7),
    ]);

    $this->actingAs($otroReferente);

    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->from(route('pacientes.index'))
        ->post(route('pacientes.derivar.store', $this->paciente->codigo), [
            'area_id' => $this->area->id,
            'motivo_derivacion' => $this->motivoValido,
        ]);

    $response->assertRedirect(route('pacientes.index'));
    expect(Expediente::withoutGlobalScopes()->count())->toBe(1);
});

it('rechaza derivar si la autorización ya venció', function () {
    $otroReferente = Especialista::factory()->create([
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);
    $otroReferente->roles()->attach($this->roleReferente->id);
    $otroProfesional = Profesional::factory()->create([
        'user_id' => $otroReferente->id,
    ]);

    \App\Models\AutorizacionPaciente::create([
        'paciente_id' => $this->paciente->codigo,
        'profesional_id' => $otroProfesional->id,
        'otorgada_por_profesional_id' => $this->profesionalReferent->id,
        'vigente_desde' => now()->subDays(10),
        'vigente_hasta' => now()->subDay(),
    ]);

    $this->actingAs($otroReferente);

    $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('pacientes.derivar.store', $this->paciente->codigo), [
            'area_id' => $this->area->id,
            'motivo_derivacion' => $this->motivoValido,
        ])
        ->assertForbidden();
});

it('audits the derivacion with author patient area date and masked motivo', function () {
    $this->actingAs($this->userReferent);

    $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('pacientes.derivar.store', $this->paciente->codigo), [
            'area_id' => $this->area->id,
            'motivo_derivacion' => $this->motivoValido,
        ])
        ->assertRedirect();

    $expediente = Expediente::withoutGlobalScopes()->first();
    expect($expediente)->not->toBeNull();

    $audit = \OwenIt\Auditing\Models\Audit::query()
        ->where('auditable_type', Expediente::class)
        ->where('auditable_id', $expediente->id)
        ->where('event', 'created')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($this->userReferent->id)
        ->and($audit->new_values['paciente_id'] ?? null)->toBe($this->paciente->codigo)
        ->and((int) ($audit->new_values['area_id'] ?? 0))->toBe($this->area->id)
        ->and($audit->new_values['derivado_por_profesional_id'] ?? null)->toBe($this->profesionalReferent->id)
        ->and($audit->new_values['fecha_derivacion'] ?? null)->not->toBeNull()
        ->and($audit->new_values['motivo_derivacion'] ?? null)->toBe('[CIFRADO]')
        ->and(json_encode($audit->new_values))->not->toContain('ansiedad académica');
});

<?php

declare(strict_types=1);

use App\Models\Area;
use App\Models\Audit;
use App\Models\Especialista;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Models\Role;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

beforeEach(function (): void {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->withSession(['_sym_key' => str_repeat('a', 32)]);

    Role::firstOrCreate(['slug' => 'specialist'], ['nombre' => 'Especialista', 'nivel' => 30]);
    Role::firstOrCreate(['slug' => 'psychosocial_referent'], ['nombre' => 'Referente', 'nivel' => 20]);

    $this->area = Area::factory()->create();
    $this->specialist = Especialista::factory()
        ->withRole('specialist')
        ->withProfesional($this->area)
        ->create();

    $this->paciente = Paciente::factory()->create([
        'creado_por_profesional_id' => $this->specialist->profesional->id,
    ]);
    $this->expediente = Expediente::factory()->create([
        'paciente_id' => $this->paciente->codigo,
        'area_id' => $this->area->id,
        'estado' => 'abierto',
        'derivado_por_profesional_id' => $this->specialist->profesional->id,
        'fecha_derivacion' => now()->subDay(),
    ]);
});

/** @return \Illuminate\Support\Collection<int, Audit> */
function hu21LecturasAudits(string $action)
{
    return Audit::query()
        ->where('event', 'accessed')
        ->get()
        ->filter(fn (Audit $audit): bool => ($audit->new_values['action'] ?? null) === $action)
        ->values();
}

it('HU21-CA1 audits a successful secure search once without storing the code', function (): void {
    $this->actingAs($this->specialist)
        ->post(route('busqueda-segura.search'), ['code' => $this->paciente->codigo])
        ->assertOk();

    $rows = hu21LecturasAudits('view_patient_secure_search');

    expect($rows)->toHaveCount(1);
    $row = $rows->first();
    expect($row->user_id)->toBe($this->specialist->id)
        ->and($row->auditable_type)->toBe(Paciente::class)
        ->and((string) $row->auditable_id)->toBe((string) $this->paciente->codigo)
        ->and($row->ip_address)->not->toBeEmpty()
        ->and($row->url)->not->toContain($this->paciente->codigo);
});

it('HU21-CA1 does not audit a not-found or unauthorized secure search', function (): void {
    $this->actingAs($this->specialist)
        ->post(route('busqueda-segura.search'), ['code' => '00000000-0000-4000-8000-000000000000'])
        ->assertRedirect(route('busqueda-segura'));

    $other = Especialista::factory()->create();
    $this->actingAs($other)
        ->post(route('busqueda-segura.search'), ['code' => $this->paciente->codigo])
        ->assertRedirect(route('busqueda-segura'));

    expect(hu21LecturasAudits('view_patient_secure_search'))->toHaveCount(0);
});

it('HU21-CA1 audits the consulta form read once with user, expediente and IP', function (): void {
    $this->actingAs($this->specialist)
        ->get(route('consultas.create', $this->expediente->id))
        ->assertOk();

    $rows = hu21LecturasAudits('view_consulta_form');

    expect($rows)->toHaveCount(1);
    $row = $rows->first();
    expect($row->user_id)->toBe($this->specialist->id)
        ->and($row->auditable_type)->toBe(Expediente::class)
        ->and((string) $row->auditable_id)->toBe((string) $this->expediente->id)
        ->and($row->ip_address)->not->toBeEmpty();
});

it('HU21-CA1 does not audit the consulta form when authorization fails', function (): void {
    $referent = Especialista::factory()->withRole('psychosocial_referent')->create();

    $this->actingAs($referent)
        ->get(route('consultas.create', $this->expediente->id))
        ->assertForbidden();

    expect(hu21LecturasAudits('view_consulta_form'))->toHaveCount(0);
});

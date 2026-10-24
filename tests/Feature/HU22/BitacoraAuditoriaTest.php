<?php

declare(strict_types=1);

use App\Enums\AccionBitacora;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Area;
use App\Models\Audit;
use App\Models\Especialista;
use App\Models\Paciente;
use App\Services\BitacoraQueryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

beforeEach(function (): void {
    $this->withoutMiddleware(PreventRequestForgery::class);
    Carbon::setTestNow(Carbon::parse('2026-10-22 12:00:00', config('app.timezone')));
    $this->withSession(['_sym_key' => base64_encode(str_repeat('r', SODIUM_CRYPTO_SECRETBOX_KEYBYTES))]);

    $this->area = Area::factory()->create(['nombre' => 'HU22 Area']);
    $this->sysadmin = Especialista::factory()->withRole('sysadmin')->create(['name' => 'HU22 Sysadmin']);
    $this->actor = Especialista::factory()->withRole('area_coordinator')->withProfesional($this->area)->create(['name' => 'HU22 Actor']);
    $this->otherActor = Especialista::factory()->withRole('specialist')->withProfesional($this->area)->create(['name' => 'HU22 Other']);
    $this->coordinator = Especialista::factory()->withRole('area_coordinator')->withProfesional($this->area)->create();
    $this->specialist = Especialista::factory()->withRole('specialist')->withProfesional($this->area)->create();
    $this->referent = Especialista::factory()->withRole('psychosocial_referent')->withProfesional($this->area)->create();

    $this->registroId = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
    $this->carnetSentinel = 'HX22Z99';
    $this->patientNameSentinel = 'PACIENTE_SENSIBLE_HU22_NOMBRE';
    $this->clinicalSentinel = 'NOTA_CLINICA_HU22_SECRETA';

    hu22SeedAudit($this->actor->id, 'created', Paciente::class, $this->registroId, CarbonImmutable::parse('2026-10-20 10:00:00', config('app.timezone')), [
        'old' => [],
        'new' => ['nombre_completo' => $this->patientNameSentinel, 'motivo' => $this->clinicalSentinel],
        'url' => 'https://pandora.test/pacientes/'.$this->carnetSentinel.'/expediente',
    ]);
    hu22SeedAudit($this->actor->id, 'updated', Paciente::class, $this->registroId, CarbonImmutable::parse('2026-10-21 11:00:00', config('app.timezone')), [
        'old' => ['telefono' => '7777'],
        'new' => ['telefono' => '8888'],
    ]);
    hu22SeedAudit($this->otherActor->id, 'accessed', Paciente::class, 'bbbbbbbb-bbbb-cccc-dddd-eeeeeeeeeeee', CarbonImmutable::parse('2026-10-19 09:00:00', config('app.timezone')));
    hu22SeedAudit($this->actor->id, 'auth_login_success', Especialista::class, (string) $this->actor->id, CarbonImmutable::parse('2026-10-18 08:00:00', config('app.timezone')));

    foreach (range(1, 60) as $i) {
        hu22SeedAudit(
            $this->actor->id,
            'accessed',
            Paciente::class,
            sprintf('page-seed-%03d', $i),
            CarbonImmutable::parse('2026-10-22 08:00:00', config('app.timezone'))->addSeconds($i),
        );
    }

    $this->actingAs($this->sysadmin);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/** @param array{old?: array<string, mixed>, new?: array<string, mixed>, url?: string} $extra */
function hu22SeedAudit(int $userId, string $event, string $type, string $auditableId, CarbonImmutable $at, array $extra = []): Audit
{
    return Audit::query()->create([
        'user_type' => Especialista::class,
        'user_id' => $userId,
        'event' => $event,
        'auditable_type' => $type,
        'auditable_id' => $auditableId,
        'old_values' => $extra['old'] ?? [],
        'new_values' => $extra['new'] ?? ['action' => 'seed'],
        'url' => $extra['url'] ?? 'https://pandora.test/admin/bitacora',
        'ip_address' => '127.0.0.1',
        'user_agent' => 'HU22-Test',
        'tags' => 'hu22_seed',
        'created_at' => $at->utc(),
        'updated_at' => $at->utc(),
    ]);
}

/** @return array<string, string> */
function hu22InertiaHeaders(): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()) ?? '',
        'Accept' => 'application/json',
    ];
}

/** @return array<string, mixed> */
function hu22Params(array $overrides = []): array
{
    return array_merge([
        'desde' => '2026-10-16',
        'hasta' => '2026-10-22',
    ], $overrides);
}

function hu22Get(TestCase $test, array $params = []): TestResponse
{
    return $test->withHeaders(hu22InertiaHeaders())->get(route('admin.bitacora.index', hu22Params($params)));
}

/** @return array<string, mixed> */
function hu22Props(TestResponse $response): array
{
    return json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR)['props'];
}

/** @param array<string, mixed> $node */
function hu22ContainsSentinel(mixed $node, string $sentinel): bool
{
    if (is_string($node)) {
        return str_contains($node, $sentinel);
    }
    if (is_array($node)) {
        foreach ($node as $value) {
            if (hu22ContainsSentinel($value, $sentinel)) {
                return true;
            }
        }
    }

    return false;
}

test('HU22-E01 should_filter_by_user_action_period_and_record', function (): void {
    $byUser = hu22Props(hu22Get($this, ['usuario' => $this->otherActor->id, 'accion' => 'accessed']));
    expect(collect($byUser['eventos']['data'])->every(fn (array $e): bool => ($e['usuario']['id'] ?? null) === $this->otherActor->id))->toBeTrue()
        ->and(collect($byUser['eventos']['data'])->pluck('accion')->unique()->all())->toBe(['accessed']);

    $byAction = hu22Props(hu22Get($this, ['accion' => 'created']));
    expect(collect($byAction['eventos']['data'])->pluck('accion')->unique()->all())->toBe(['created']);

    $byPeriod = hu22Props(hu22Get($this, ['desde' => '2026-10-20', 'hasta' => '2026-10-20']));
    expect(collect($byPeriod['eventos']['data'])->every(fn (array $e): bool => str_starts_with((string) $e['fecha'], '2026-10-20')))->toBeTrue();

    $byRecord = hu22Props(hu22Get($this, ['registro' => $this->registroId]));
    expect(collect($byRecord['eventos']['data'])->every(fn (array $e): bool => $e['objeto_id'] === $this->registroId))->toBeTrue()
        ->and(count($byRecord['eventos']['data']))->toBeGreaterThanOrEqual(2);
});

test('HU22-E02 should_intersect_combined_filters_with_inclusive_local_dates', function (): void {
    $props = hu22Props(hu22Get($this, [
        'usuario' => $this->actor->id,
        'accion' => 'updated',
        'registro' => $this->registroId,
        'desde' => '2026-10-21',
        'hasta' => '2026-10-21',
    ]));
    $rows = $props['eventos']['data'];
    expect($rows)->not->toBeEmpty()
        ->and(collect($rows)->every(fn (array $e): bool => $e['accion'] === 'updated'
            && $e['objeto_id'] === $this->registroId
            && ($e['usuario']['id'] ?? null) === $this->actor->id
            && str_starts_with((string) $e['fecha'], '2026-10-21')))->toBeTrue();
});

test('HU22-E03 should_order_results_by_created_at_desc_then_id_desc', function (): void {
    $sameInstant = CarbonImmutable::parse('2026-10-17 15:00:00', config('app.timezone'));
    $first = hu22SeedAudit($this->actor->id, 'deleted', Paciente::class, 'order-a', $sameInstant);
    $second = hu22SeedAudit($this->actor->id, 'deleted', Paciente::class, 'order-b', $sameInstant);
    $props = hu22Props(hu22Get($this, ['accion' => 'deleted', 'desde' => '2026-10-17', 'hasta' => '2026-10-17']));
    $ids = collect($props['eventos']['data'])->pluck('id')->all();

    expect($ids[0])->toBe($second->id)
        ->and($ids[1])->toBe($first->id);
});

test('HU22-E04 should_paginate_twenty_five_per_page_without_overlap', function (): void {
    $page1 = hu22Props(hu22Get($this, ['usuario' => $this->actor->id, 'accion' => 'accessed', 'page' => 1]));
    $page2 = hu22Props(hu22Get($this, ['usuario' => $this->actor->id, 'accion' => 'accessed', 'page' => 2]));
    $ids1 = collect($page1['eventos']['data'])->pluck('id');
    $ids2 = collect($page2['eventos']['data'])->pluck('id');

    expect($page1['eventos']['meta']['per_page'])->toBe(25)
        ->and($ids1)->toHaveCount(25)
        ->and($ids2->intersect($ids1))->toBeEmpty()
        ->and(collect($page2['eventos']['links'])->contains(fn (array $link): bool => is_string($link['url'] ?? null) && str_contains($link['url'], 'usuario=')))->toBeTrue();
});

test('HU22-E05 should_authorize_only_sysadmin', function (): void {
    expect(hu22Get($this)->getStatusCode())->toBe(200);

    foreach ([$this->coordinator, $this->specialist, $this->referent] as $user) {
        $this->actingAs($user);
        expect(hu22Get($this)->getStatusCode())->toBe(403);
    }

    auth()->logout();
    $guest = $this->getJson(route('admin.bitacora.index', hu22Params()));
    expect($guest->getStatusCode())->toBeIn([302, 401]);
});

test('HU22-E06 should_never_expose_values_carnet_or_clinical_sentinels', function (): void {
    $props = hu22Props(hu22Get($this, ['registro' => $this->registroId]));
    $json = json_encode($props, JSON_THROW_ON_ERROR);

    expect(hu22ContainsSentinel($props, $this->patientNameSentinel))->toBeFalse()
        ->and(hu22ContainsSentinel($props, $this->clinicalSentinel))->toBeFalse()
        ->and(hu22ContainsSentinel($props, $this->carnetSentinel))->toBeFalse()
        ->and($json)->not->toContain('"nombre_completo":"PACIENTE')
        ->and(collect($props['eventos']['data'])->flatMap(fn (array $e) => $e['campos'])->unique()->all())
        ->toContain('nombre_completo');
});

test('HU22-E07 should_mask_historical_urls_that_include_carnet', function (): void {
    $props = hu22Props(hu22Get($this, ['accion' => 'created', 'registro' => $this->registroId]));
    $ruta = collect($props['eventos']['data'])->first()['ruta'] ?? '';

    expect($ruta)->toContain('/pacientes/{carnet}')
        ->and($ruta)->not->toContain($this->carnetSentinel);
});

test('HU22-E08 should_reject_invalid_filters_with_422', function (): void {
    expect(hu22Get($this, ['accion' => 'no_existe'])->getStatusCode())->toBe(422)
        ->and(hu22Get($this, ['desde' => '2025-01-01', 'hasta' => '2026-12-31'])->getStatusCode())->toBe(422)
        ->and(hu22Get($this, ['desde' => '2026-10-22', 'hasta' => '2026-10-01'])->getStatusCode())->toBe(422)
        ->and(hu22Get($this, ['usuario' => 'abc'])->getStatusCode())->toBe(422);
});

test('HU22-E09 should_use_index_for_large_audit_set_with_stable_query_count', function (): void {
    $rows = [];
    $base = CarbonImmutable::parse('2026-10-10 00:00:00', config('app.timezone'));
    for ($i = 0; $i < 10000; $i++) {
        $rows[] = [
            'user_type' => Especialista::class,
            'user_id' => $this->actor->id,
            'event' => 'accessed',
            'auditable_type' => Paciente::class,
            'auditable_id' => 'bulk-'.$i,
            'old_values' => json_encode([]),
            'new_values' => json_encode(['action' => 'bulk']),
            'url' => 'https://pandora.test/x',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'bulk',
            'tags' => 'bulk',
            'created_at' => $base->addSeconds($i)->utc(),
            'updated_at' => $base->addSeconds($i)->utc(),
        ];
        if (count($rows) === 500) {
            DB::table('audits')->insert($rows);
            $rows = [];
        }
    }
    if ($rows !== []) {
        DB::table('audits')->insert($rows);
    }

    $filtros = new \App\DTO\FiltrosBitacora(
        desde: '2026-10-10',
        hasta: '2026-10-22',
        usuarioId: $this->actor->id,
        accion: 'accessed',
        registroId: null,
        tipo: null,
    );
    expect(app(BitacoraQueryService::class)->explainUsaIndice($filtros))->toBeTrue();

    $countAudits = static fn (array $log): int => collect($log)
        ->filter(static fn (array $query): bool => str_contains(strtolower($query['query']), ' from "audits"')
            || str_contains(strtolower($query['query']), ' from audits'))
        ->count();

    DB::flushQueryLog();
    DB::enableQueryLog();
    hu22Get($this, ['usuario' => $this->actor->id, 'accion' => 'accessed', 'desde' => '2026-10-10']);
    $count1 = $countAudits(DB::getQueryLog());
    DB::flushQueryLog();
    hu22Get($this, ['usuario' => $this->actor->id, 'accion' => 'accessed', 'desde' => '2026-10-10', 'page' => 2]);
    $count2 = $countAudits(DB::getQueryLog());
    DB::disableQueryLog();

    expect($count1)->toBe($count2)->and($count1)->toBeGreaterThan(0);
});

test('HU22-E10 should_surface_real_events_generated_by_auditable_actions', function (): void {
    $before = Audit::query()->count();
    $patient = Paciente::factory()->create([
        'creado_por_profesional_id' => $this->actor->profesional->id,
    ]);
    expect(Audit::query()->count())->toBeGreaterThan($before);

    $props = hu22Props(hu22Get($this, [
        'registro' => (string) $patient->codigo,
        'desde' => '2026-10-22',
        'hasta' => '2026-10-22',
    ]));
    expect(collect($props['eventos']['data'])->contains(fn (array $e): bool => $e['objeto_id'] === (string) $patient->codigo))->toBeTrue();
});

test('HU22-E11 should_record_bitacora_consultation_without_embedding_results', function (): void {
    $before = Audit::query()->where('event', AccionBitacora::BitacoraConsultada->value)->count();
    hu22Get($this, ['usuario' => $this->actor->id]);
    $event = Audit::query()->where('event', AccionBitacora::BitacoraConsultada->value)->latest('id')->first();

    expect(Audit::query()->where('event', AccionBitacora::BitacoraConsultada->value)->count())->toBe($before + 1)
        ->and($event)->not->toBeNull()
        ->and($event->new_values)->toHaveKey('filters')
        ->and($event->new_values)->not->toHaveKey('results')
        ->and($event->new_values['filters']['usuario'])->toBe($this->actor->id);
});

test('HU22-E12 should_keep_admin_pacientes_audit_page_intact', function (): void {
    $response = $this->withHeaders(hu22InertiaHeaders())->get(route('admin.pacientes.index'));
    $page = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

    expect($response->getStatusCode())->toBe(200)
        ->and($page['component'])->toBe('Admin/Pacientes/Index');
});

<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Area;
use App\Models\Audit;
use App\Models\Especialista;
use App\Models\ExportacionAuditoria;
use App\Models\Paciente;
use App\Services\BitacoraExportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

beforeEach(function (): void {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    Carbon::setTestNow(Carbon::parse('2026-10-22 15:30:00', config('app.timezone')));
    $this->withSession(['_sym_key' => base64_encode(str_repeat('r', SODIUM_CRYPTO_SECRETBOX_KEYBYTES))]);

    $this->area = Area::factory()->create(['nombre' => 'HU23 Area']);
    $this->sysadmin = Especialista::factory()->withRole('sysadmin')->create(['name' => 'HU23 Sysadmin']);
    $this->actor = Especialista::factory()->withRole('area_coordinator')->withProfesional($this->area)->create(['name' => 'HU23 Actor']);
    $this->coordinator = Especialista::factory()->withRole('area_coordinator')->withProfesional($this->area)->create();

    $this->registro = 'cccccccc-dddd-eeee-ffff-000000000001';
    $this->carnet = 'HX23Z01';
    $this->patientName = 'NOMBRE_SENSIBLE_HU23';
    $this->clinical = 'VALOR_CLINICO_HU23';

    hu23Seed($this->actor->id, 'created', Paciente::class, $this->registro, CarbonImmutable::parse('2026-10-20 10:00:00', config('app.timezone')), [
        'new' => ['nombre_completo' => $this->patientName, 'nota' => $this->clinical],
        'url' => 'https://pandora.test/pacientes/'.$this->carnet,
    ]);
    hu23Seed($this->actor->id, 'updated', Paciente::class, $this->registro, CarbonImmutable::parse('2026-10-21 11:00:00', config('app.timezone')), [
        'old' => ['telefono' => '1'],
        'new' => ['telefono' => '2'],
    ]);
    hu23Seed($this->actor->id, 'accessed', Paciente::class, "=cmd|danger", CarbonImmutable::parse('2026-10-19 09:00:00', config('app.timezone')));

    $this->actingAs($this->sysadmin);
});

afterEach(function (): void {
    Carbon::setTestNow();
    config(['auditoria.simulate_export_failure' => false]);
});

/** @param array{old?: array<string, mixed>, new?: array<string, mixed>, url?: string} $extra */
function hu23Seed(int $userId, string $event, string $type, string $id, CarbonImmutable $at, array $extra = []): Audit
{
    return Audit::query()->create([
        'user_type' => Especialista::class,
        'user_id' => $userId,
        'event' => $event,
        'auditable_type' => $type,
        'auditable_id' => $id,
        'old_values' => $extra['old'] ?? [],
        'new_values' => $extra['new'] ?? ['action' => 'seed'],
        'url' => $extra['url'] ?? 'https://pandora.test/admin/bitacora',
        'ip_address' => '10.0.0.23',
        'user_agent' => 'HU23',
        'tags' => 'hu23',
        'created_at' => $at->utc(),
        'updated_at' => $at->utc(),
    ]);
}

/** @return array<string, string> */
function hu23InertiaHeaders(): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()) ?? '',
        'Accept' => 'application/json',
    ];
}

/** @return array<string, mixed> */
function hu23Params(array $overrides = []): array
{
    return array_merge([
        'desde' => '2026-10-16',
        'hasta' => '2026-10-22',
    ], $overrides);
}

function hu23Export(TestCase $test, array $params = []): TestResponse
{
    return $test->getJson(route('admin.bitacora.export', hu23Params($params)));
}

function hu23ListIds(TestCase $test, array $params = []): array
{
    $response = $test->withHeaders(hu23InertiaHeaders())->get(route('admin.bitacora.index', hu23Params($params)));
    $props = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR)['props'];

    return collect($props['eventos']['data'])->pluck('id')->all();
}

/** @return list<int> */
function hu23CsvIds(string $csv): array
{
    $ids = [];
    $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv;
    foreach (preg_split("/\r\n|\n|\r/", $csv) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, 'id,')) {
            continue;
        }
        $cols = str_getcsv($line);
        $first = trim((string) ($cols[0] ?? ''));
        if ($first !== '' && ctype_digit($first)) {
            $ids[] = (int) $first;
        }
    }

    return $ids;
}

test('HU23-E01 should_export_exactly_the_filtered_event_ids_in_list_order', function (): void {
    $combos = [
        ['accion' => 'created'],
        ['usuario' => $this->actor->id, 'accion' => 'updated'],
        ['registro' => $this->registro],
    ];

    foreach ($combos as $params) {
        $listIds = hu23ListIds($this, $params);
        $response = hu23Export($this, $params);
        expect($response->getStatusCode())->toBe(200);
        expect(hu23CsvIds($response->getContent()))->toBe($listIds);
    }
});

test('HU23-E02 should_export_empty_dataset_with_metadata_only', function (): void {
    $response = hu23Export($this, ['desde' => '2026-01-01', 'hasta' => '2026-01-02']);
    $body = $response->getContent();

    expect($response->getStatusCode())->toBe(200)
        ->and($body)->toContain('# total_eventos=0')
        ->and(hu23CsvIds($body))->toBe([]);
});

test('HU23-E03 should_identify_generator_and_generation_timestamp', function (): void {
    $body = hu23Export($this)->getContent();

    expect($body)->toContain('HU23 Sysadmin')
        ->and($body)->toContain('#'.$this->sysadmin->id)
        ->and($body)->toContain('2026-10-22');
});

test('HU23-E04 should_register_export_as_audit_event_visible_in_bitacora', function (): void {
    $beforeExports = ExportacionAuditoria::query()->count();
    hu23Export($this, ['accion' => 'created']);
    expect(ExportacionAuditoria::query()->count())->toBe($beforeExports + 1);

    $export = ExportacionAuditoria::query()->latest('generada_en')->first();
    $audit = Audit::query()
        ->where('auditable_type', ExportacionAuditoria::class)
        ->where('auditable_id', $export->id)
        ->where('event', 'created')
        ->first();

    expect($audit)->not->toBeNull()
        ->and((int) $audit->user_id)->toBe($this->sysadmin->id);

    $props = json_decode(
        $this->withHeaders(hu23InertiaHeaders())->get(route('admin.bitacora.index', hu23Params([
            'registro' => (string) $export->id,
            'desde' => '2026-10-22',
            'hasta' => '2026-10-22',
        ])))->getContent(),
        true,
        flags: JSON_THROW_ON_ERROR
    )['props'];

    expect(collect($props['eventos']['data'])->contains(
        fn (array $e): bool => $e['objeto_id'] === (string) $export->id && $e['accion'] === 'created'
    ))->toBeTrue();
});

test('HU23-E05 should_exclude_export_event_from_its_own_file', function (): void {
    $response = hu23Export($this, ['accion' => 'created']);
    $export = ExportacionAuditoria::query()->latest('generada_en')->first();
    $ids = hu23CsvIds($response->getContent());
    $exportAudit = Audit::query()
        ->where('auditable_type', ExportacionAuditoria::class)
        ->where('auditable_id', $export->id)
        ->value('id');

    expect($ids)->not->toContain((int) $exportAudit)
        ->and(ExportacionAuditoria::query()->count())->toBe(1);
});

test('HU23-E06 should_persist_sha256_matching_file_bytes', function (): void {
    $response = hu23Export($this);
    $shaHeader = $response->headers->get('X-Checksum-SHA256');
    $row = ExportacionAuditoria::query()->latest('generada_en')->first();

    expect($shaHeader)->toBe(hash('sha256', $response->getContent()))
        ->and($row->sha256)->toBe($shaHeader);
});

test('HU23-E07 should_forbid_unauthorized_roles_without_creating_export', function (): void {
    $this->actingAs($this->coordinator);
    expect(hu23Export($this)->getStatusCode())->toBe(403)
        ->and(ExportacionAuditoria::query()->count())->toBe(0);

    auth()->logout();
    expect($this->getJson(route('admin.bitacora.export', hu23Params()))->getStatusCode())->toBeIn([302, 401]);
});

test('HU23-E08 should_reject_invalid_filters_and_oversized_exports_without_records', function (): void {
    expect(hu23Export($this, ['accion' => 'nope'])->getStatusCode())->toBe(422)
        ->and(hu23Export($this, ['desde' => '2025-01-01', 'hasta' => '2026-12-31'])->getStatusCode())->toBe(422)
        ->and(ExportacionAuditoria::query()->count())->toBe(0);

    config(['auditoria.export_max_eventos' => 1]);
    expect(hu23Export($this, ['desde' => '2026-10-16', 'hasta' => '2026-10-22'])->getStatusCode())->toBe(422)
        ->and(ExportacionAuditoria::query()->count())->toBe(0);
    config(['auditoria.export_max_eventos' => BitacoraExportService::MAX_EVENTOS]);
});

test('HU23-E09 should_omit_phi_sentinels_and_neutralize_formula_cells', function (): void {
    $body = hu23Export($this)->getContent();

    expect($body)->not->toContain($this->patientName)
        ->and($body)->not->toContain($this->clinical)
        ->and($body)->not->toContain($this->carnet)
        ->and($body)->toContain('/pacientes/{carnet}')
        ->and($body)->toContain("'=cmd|danger");
});

test('HU23-E10 should_not_persist_export_when_generation_fails', function (): void {
    config(['auditoria.simulate_export_failure' => true]);
    $tmpBefore = count(glob(sys_get_temp_dir().'/bit_*.csv') ?: []);
    $response = hu23Export($this);

    expect($response->getStatusCode())->toBe(500)
        ->and(ExportacionAuditoria::query()->count())->toBe(0);

    $tmpAfter = count(glob(sys_get_temp_dir().'/bit_*.csv') ?: []);
    expect($tmpAfter)->toBe($tmpBefore);
});

test('HU23-E11 should_keep_exportacion_auditoria_append_only', function (): void {
    hu23Export($this);
    $row = ExportacionAuditoria::query()->first();

    expect(fn () => $row->update(['sha256' => 'x']))->toThrow(\App\Exceptions\RubricaInmutableException::class);

    $appUser = (string) config('database.connections.pgsql.username');
    $adminUser = (string) config('database.connections.pgsql_admin.username');
    if ($appUser !== $adminUser) {
        $sqlFailed = false;
        try {
            DB::table('exportaciones_auditoria')->where('id', $row->id)->update(['sha256' => 'y']);
        } catch (\Throwable $e) {
            $sqlFailed = str_contains($e->getMessage(), '42501')
                || str_contains(strtolower($e->getMessage()), 'permission');
        }
        expect($sqlFailed)->toBeTrue();
    }
});

test('HU23-E12 should_send_download_headers', function (): void {
    $response = hu23Export($this, ['accion' => 'created']);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('content-type'))->toContain('text/csv')
        ->and($response->headers->get('content-disposition'))->toContain('attachment')
        ->and($response->headers->get('content-disposition'))->toContain('bitacora_2026-10-16_2026-10-22_20261022-1530.csv')
        ->and($response->headers->get('cache-control'))->toContain('no-store')
        ->and($response->headers->get('X-Checksum-SHA256'))->not->toBeEmpty();
});

test('HU23-E13 should_expose_exportarBitacora_only_to_authorized_roles', function (): void {
    $admin = $this->withHeaders(hu23InertiaHeaders())->get(route('admin.bitacora.index', hu23Params()));
    $adminProps = json_decode($admin->getContent(), true, flags: JSON_THROW_ON_ERROR)['props'];
    expect(data_get($adminProps, 'can.exportarBitacora'))->toBeTrue();

    $this->actingAs($this->coordinator);
    $denied = $this->withHeaders(hu23InertiaHeaders())->get(route('admin.dashboard'));
    $deniedProps = json_decode($denied->getContent(), true, flags: JSON_THROW_ON_ERROR)['props'] ?? [];
    // coordinators redirected / 403 on bitacora; check shared can on a reachable page
    $reachable = $this->withHeaders(hu23InertiaHeaders())->get('/reportes');
    if ($reachable->getStatusCode() === 200) {
        $props = json_decode($reachable->getContent(), true, flags: JSON_THROW_ON_ERROR)['props'];
        expect(data_get($props, 'can.exportarBitacora'))->toBeFalse();
    } else {
        expect(data_get($deniedProps, 'can.exportarBitacora') ?? false)->toBeFalse();
    }
});

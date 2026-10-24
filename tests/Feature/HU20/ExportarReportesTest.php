<?php

declare(strict_types=1);

use App\DTO\ParametrosReporte;
use App\Enums\TipoIndicador;
use App\Exceptions\RubricaInmutableException;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Area;
use App\Models\Carrera;
use App\Models\Consulta;
use App\Models\Especialista;
use App\Models\Expediente;
use App\Models\ExportacionReporte;
use App\Models\Facultad;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Services\ReporteParametrizadoService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Tests\TestCase;

beforeEach(function (): void {
    $this->withoutMiddleware(PreventRequestForgery::class);
    config(['app.area_key_secret' => str_repeat('HU20-area-key-', 4)]);
    Carbon::setTestNow(Carbon::parse('2026-10-22 12:00:00', config('app.timezone')));
    $this->withSession(['_sym_key' => base64_encode(str_repeat('r', SODIUM_CRYPTO_SECRETBOX_KEYBYTES))]);

    $this->area = Area::factory()->create(['nombre' => 'HU20 Psychology']);
    $this->otherArea = Area::factory()->create(['nombre' => 'HU20 Nutrition']);
    $this->formulaArea = Area::factory()->create(['nombre' => "=cmd|' /C calc'!A0"]);
    $this->coordinator = Especialista::factory()
        ->withRole('area_coordinator')
        ->withProfesional($this->area)
        ->create();
    $this->professional = $this->coordinator->profesional;
    $this->otherSpecialist = Especialista::factory()
        ->withRole('specialist')
        ->withProfesional($this->otherArea)
        ->create();
    $this->specialist = Especialista::factory()
        ->withRole('specialist')
        ->withProfesional($this->area)
        ->create();
    $this->formulaSpecialist = Especialista::factory()
        ->withRole('area_coordinator')
        ->withProfesional($this->formulaArea)
        ->create();

    $faculty = Facultad::factory()->create();
    $career = Carrera::factory()->create(['facultad_id' => $faculty->id]);
    [$this->patient, $this->file] = hu20CreatePatientFile($this->area, $this->professional, $career->id);
    [, $this->otherFile] = hu20CreatePatientFile($this->otherArea, $this->otherSpecialist->profesional, $career->id);
    [, $formulaFile] = hu20CreatePatientFile($this->formulaArea, $this->formulaSpecialist->profesional, $career->id);

    foreach (range(1, 6) as $index) {
        hu20CreateConsultation($this->file, $this->professional, CarbonImmutable::parse('2026-09-10', config('app.timezone'))->addHours($index));
    }
    foreach (range(1, 7) as $index) {
        hu20CreateConsultation($this->file, $this->professional, CarbonImmutable::parse('2026-10-10', config('app.timezone'))->addHours($index));
    }
    foreach (range(1, 6) as $index) {
        hu20CreateConsultation($this->otherFile, $this->otherSpecialist->profesional, CarbonImmutable::parse('2026-10-12', config('app.timezone'))->addHours($index));
    }
    foreach (range(1, 6) as $index) {
        hu20CreateConsultation($formulaFile, $this->formulaSpecialist->profesional, CarbonImmutable::parse('2026-10-10', config('app.timezone'))->addHours($index));
    }

    $this->actingAs($this->coordinator);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/** @return array<string, string> */
function hu20InertiaHeaders(): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()) ?? '',
        'Accept' => 'application/json',
    ];
}

/** @return array{0: Paciente, 1: Expediente} */
function hu20CreatePatientFile(Area $area, Profesional $professional, int $careerId): array
{
    $patient = Paciente::factory()->create([
        'carrera_id' => $careerId,
        'creado_por_profesional_id' => $professional->id,
    ]);
    $file = Expediente::factory()->create([
        'paciente_id' => $patient->codigo,
        'area_id' => $area->id,
        'estado' => Expediente::ESTADO_ABIERTO,
        'derivado_por_profesional_id' => $professional->id,
        'fecha_derivacion' => now()->subDays(20),
    ]);

    return [$patient, $file];
}

function hu20CreateConsultation(Expediente $file, Profesional $professional, CarbonImmutable $date, array $overrides = []): Consulta
{
    $consulta = new Consulta;
    $consulta->expediente_id = $file->id;
    $consulta->setRelation('expediente', $file);
    $consulta->fill(array_merge([
        'profesional_id' => $professional->id,
        'motivo_consulta' => 'HU20 PHI motivo secreto único XYZ',
        'notas_clinicas' => 'HU20 PHI nota clínica secreta ABC',
        'diagnostico' => 'HU20 PHI diagnóstico',
        'plan_atencion' => 'HU20 PHI plan',
        'tecnica_utilizada' => 'HU20 técnica',
        'fecha_consulta' => $date,
        'evaluacion_inicial' => [],
        'crisis_psicologica' => 'No',
        'indicadores_tendencia_suicida' => [],
    ], $overrides));
    $consulta->save();

    return $consulta;
}

/** @return array<string, mixed> */
function hu20ValidParameters(int|string $areaId, array $overrides = []): array
{
    return array_merge([
        'desde' => '2026-09-01',
        'hasta' => '2026-10-22',
        'area' => (string) $areaId,
        'indicadores' => [TipoIndicador::ConsultasRegistradas->value],
        'agrupar_por' => ['indicador'],
    ], $overrides);
}

function hu20Export(TestCase $test, string $format, array $parameters): TestResponse
{
    $route = $format === 'pdf' ? 'reportes.export.pdf' : 'reportes.export.excel';

    return $test->getJson(route($route, $parameters));
}

/** @return list<list<string>> */
function hu20ReadXlsxRows(string $binary): array
{
    $path = tempnam(sys_get_temp_dir(), 'hu20_').'.xlsx';
    file_put_contents($path, $binary);
    $reader = new XlsxReader;
    $reader->open($path);
    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = array_map(static fn ($cell): string => (string) $cell->getValue(), $row->getCells());
        }
    }
    $reader->close();
    @unlink($path);

    return $rows;
}

function hu20PdfHtml(TestCase $test, array $parameters): string
{
    $dto = new ParametrosReporte(
        desde: $parameters['desde'],
        hasta: $parameters['hasta'],
        areaId: $parameters['area'] === 'todas' ? null : (int) $parameters['area'],
        todasAreas: $parameters['area'] === 'todas',
        indicadores: $parameters['indicadores'],
        agruparPor: $parameters['agrupar_por'],
    );
    $report = app(ReporteParametrizadoService::class)->generar($dto, $test->coordinator);
    $areaEtiqueta = $dto->todasAreas
        ? 'Todas las áreas'
        : (string) Area::query()->whereKey($dto->areaId)->value('nombre');

    return view('exports.reporte-pdf', [
        'report' => $report,
        'areaEtiqueta' => $areaEtiqueta,
        'generadoPor' => $test->coordinator->name,
        'generadoEn' => Carbon::now(config('app.timezone'))->toIso8601String(),
    ])->render();
}

test('HU20-E01 should_export_pdf_and_excel_with_same_totals_as_screen', function (): void {
    $parameters = hu20ValidParameters($this->area->id);
    $screen = $this->withHeaders(hu20InertiaHeaders())->get(route('reportes.generate', $parameters));
    $props = json_decode($screen->getContent(), true, flags: JSON_THROW_ON_ERROR)['props'];
    $total = (string) ($props['reporte']['totales'][TipoIndicador::ConsultasRegistradas->value]['valor'] ?? 0);

    $pdf = hu20Export($this, 'pdf', $parameters);
    $xlsx = hu20Export($this, 'excel', $parameters);
    $html = hu20PdfHtml($this, $parameters);

    expect($pdf->getStatusCode())->toBe(200)
        ->and($xlsx->getStatusCode())->toBe(200)
        ->and($pdf->headers->get('content-type'))->toContain('application/pdf')
        ->and(str_starts_with($pdf->getContent(), '%PDF'))->toBeTrue()
        ->and($xlsx->headers->get('content-type'))->toContain('spreadsheetml')
        ->and($html)->toContain($total);

    $rows = hu20ReadXlsxRows($xlsx->getContent());
    $flat = collect($rows)->flatten()->implode(' ');
    expect($flat)->toContain($total)
        ->and($flat)->toContain('Consultas registradas');
});

test('HU20-E02 should_include_active_filters_in_both_export_headers', function (): void {
    $parameters = hu20ValidParameters($this->area->id);
    $html = hu20PdfHtml($this, $parameters);
    $xlsxText = collect(hu20ReadXlsxRows(hu20Export($this, 'excel', $parameters)->getContent()))->flatten()->implode(' ');
    $pdf = hu20Export($this, 'pdf', $parameters);

    expect($pdf->getStatusCode())->toBe(200)
        ->and(str_starts_with($pdf->getContent(), '%PDF'))->toBeTrue();

    foreach (['2026-09-01', '2026-10-22', 'HU20 Psychology', 'consultas_registradas'] as $needle) {
        expect($html)->toContain($needle)
            ->and($xlsxText)->toContain($needle);
    }
});

test('HU20-E03 should_stamp_generation_datetime_within_test_now_tolerance', function (): void {
    $parameters = hu20ValidParameters($this->area->id);
    $html = hu20PdfHtml($this, $parameters);
    $xlsxText = collect(hu20ReadXlsxRows(hu20Export($this, 'excel', $parameters)->getContent()))->flatten()->implode(' ');

    expect($html)->toContain('2026-10-22')
        ->and($xlsxText)->toContain('2026-10-22');
});

test('HU20-E04 should_identify_period_and_indicators_in_headers', function (): void {
    $parameters = hu20ValidParameters($this->area->id, [
        'indicadores' => [
            TipoIndicador::ConsultasRegistradas->value,
            TipoIndicador::PacientesAtendidos->value,
        ],
        'agrupar_por' => ['indicador'],
    ]);
    $html = hu20PdfHtml($this, $parameters);

    expect($html)->toContain('2026-09-01')
        ->and($html)->toContain('consultas_registradas')
        ->and($html)->toContain('pacientes_atendidos');
});

test('HU20-E05 should_forbid_foreign_area_and_scope_limited_exports', function (): void {
    $forbidden = hu20Export($this, 'pdf', hu20ValidParameters($this->otherArea->id));
    expect($forbidden->getStatusCode())->toBe(403)
        ->and(ExportacionReporte::query()->count())->toBe(0);

    $ok = hu20Export($this, 'excel', hu20ValidParameters($this->area->id, [
        'agrupar_por' => ['area'],
    ]));
    expect($ok->getStatusCode())->toBe(200);
    $text = collect(hu20ReadXlsxRows($ok->getContent()))->flatten()->implode(' ');
    expect($text)->not->toContain('HU20 Nutrition')
        ->and($text)->toContain('HU20 Psychology');
});

test('HU20-E06 should_never_embed_seeded_phi_in_exports', function (): void {
    $parameters = hu20ValidParameters($this->area->id);
    $pdf = hu20Export($this, 'pdf', $parameters)->getContent();
    $xlsx = hu20Export($this, 'excel', $parameters)->getContent();
    $patientName = (string) $this->patient->nombre_completo;
    $carnet = (string) $this->patient->codigo;
    $uuid = (string) $this->file->id;

    foreach ([$pdf, $xlsx] as $binary) {
        expect($binary)->not->toContain('HU20 PHI motivo secreto único XYZ')
            ->and($binary)->not->toContain('HU20 PHI nota clínica secreta ABC')
            ->and($binary)->not->toContain($carnet)
            ->and($binary)->not->toContain($uuid);
        if ($patientName !== '') {
            expect($binary)->not->toContain($patientName);
        }
    }
});

test('HU20-E07 should_reject_incomplete_parameters_without_file', function (): void {
    $base = hu20ValidParameters($this->area->id);
    unset($base['desde']);
    $response = hu20Export($this, 'pdf', $base);

    expect($response->getStatusCode())->toBe(422)
        ->and(ExportacionReporte::query()->count())->toBe(0)
        ->and($response->headers->get('content-disposition'))->toBeNull();
});

test('HU20-E08 should_ignore_client_supplied_result_payload', function (): void {
    $parameters = hu20ValidParameters($this->area->id, [
        'totales' => [TipoIndicador::ConsultasRegistradas->value => ['valor' => 999999]],
        'grupos' => [['fake' => true]],
        'resultado_cliente' => 'ignorar',
    ]);
    $response = hu20Export($this, 'excel', $parameters);
    $text = collect(hu20ReadXlsxRows($response->getContent()))->flatten()->implode(' ');

    expect($response->getStatusCode())->toBe(200)
        ->and($text)->not->toContain('999999')
        ->and($text)->not->toContain('ignorar');
});

test('HU20-E09 should_neutralize_formula_injection_in_excel_cells', function (): void {
    $this->actingAs($this->formulaSpecialist);
    $parameters = hu20ValidParameters($this->formulaArea->id, [
        'agrupar_por' => ['area'],
    ]);
    $rows = hu20ReadXlsxRows(hu20Export($this, 'excel', $parameters)->getContent());
    $flat = collect($rows)->flatten()->all();
    $dangerous = collect($flat)->filter(fn (string $cell): bool => str_contains($cell, 'cmd'))->values();

    expect($dangerous)->not->toBeEmpty()
        ->and($dangerous->every(fn (string $cell): bool => str_starts_with($cell, "'")))->toBeTrue();
});

test('HU20-E10 should_record_append_only_export_metadata_with_sha256', function (): void {
    $parameters = hu20ValidParameters($this->area->id);
    $response = hu20Export($this, 'pdf', $parameters);
    $sha = hash('sha256', $response->getContent());
    $row = ExportacionReporte::query()->latest('generado_en')->first();

    expect($row)->not->toBeNull()
        ->and($row->formato)->toBe('pdf')
        ->and($row->sha256)->toBe($sha)
        ->and($row->usuario_id)->toBe($this->coordinator->id)
        ->and($row->parametros['area'])->toBe($this->area->id);

    expect(fn () => $row->update(['sha256' => 'tampered']))
        ->toThrow(RubricaInmutableException::class);
});

test('HU20-E11 should_expose_export_capability_on_reportes_page', function (): void {
    $response = $this->flushHeaders()
        ->withHeaders(hu20InertiaHeaders())
        ->get(route('reportes.index'));

    expect($response->getStatusCode())->toBe(200);
    $page = json_decode($response->getContent(), true);
    expect($page)->toBeArray()
        ->and($page['component'] ?? null)->toBe('Reportes/Index')
        ->and(data_get($page, 'props.can.exportar'))->toBeTrue()
        ->and(data_get($page, 'props.can.reportes'))->toBeTrue();
});

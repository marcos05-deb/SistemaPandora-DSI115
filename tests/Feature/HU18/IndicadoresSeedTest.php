<?php

declare(strict_types=1);

use App\Enums\TipoIndicador;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Especialista;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;

test('HU18-E01 should_match_independent_sql_for_every_aggregate_indicator', function (): void {
    // Arrange
    $this->withoutMiddleware(PreventRequestForgery::class);
    config(['app.area_key_secret' => str_repeat('HU18-seed-key-', 4)]);
    $this->withSession(['_sym_key' => base64_encode(str_repeat('h', SODIUM_CRYPTO_SECRETBOX_KEYBYTES))]);
    $this->seed(DatabaseSeeder::class);
    $coordinator = Especialista::query()->where('email', 'coordinador@pandora.com')->firstOrFail();
    $areaId = (int) $coordinator->profesional->area_id;
    $from = now()->subDays(29)->toDateString();
    $through = now()->toDateString();
    $startUtc = CarbonImmutable::parse($from, config('app.timezone'))->startOfDay()->utc();
    $endUtc = CarbonImmutable::parse($through, config('app.timezone'))->addDay()->startOfDay()->utc();

    // Act
    $response = $this->actingAs($coordinator)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()) ?? '',
            'Accept' => 'application/json',
        ])
        ->get(route('indicadores.index', [
            'desde' => $from,
            'hasta' => $through,
            'indicadores' => TipoIndicador::valores(),
        ]));
    $page = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    $result = $page['props']['resultados'];
    $expected = hu18SeedIndependentCounts($areaId, $startUtc, $endUtc);

    // Assert
    expect($response->getStatusCode())->toBe(200)
        ->and($result['sin_datos'])->toBeFalse();

    foreach ($expected as $indicator => $value) {
        $total = $result['totales'][$indicator];
        if ($value < 5) {
            expect($total['suprimido'])->toBeTrue();
        } else {
            expect($total['suprimido'])->toBeFalse()
                ->and((float) $total['valor'])->toBe((float) $value);
        }
    }

    $attendanceDenominator = $expected['citas_asistidas'] + $expected['citas_no_asistidas'];
    $attendance = $result['totales'][TipoIndicador::TasaAsistencia->value];
    if ($attendanceDenominator < 5 || min($expected['citas_asistidas'], $expected['citas_no_asistidas']) < 5) {
        expect($attendance['suprimido'])->toBeTrue();
    } else {
        $expectedRate = round($expected['citas_asistidas'] * 100 / $attendanceDenominator, 1);
        expect($attendance['suprimido'])->toBeFalse()
            ->and((float) $attendance['valor'])->toBe($expectedRate);
    }

    $expectedAlertsBySignal = hu18SeedIndependentAlertsBySignal($areaId, $startUtc, $endUtc);
    foreach ($expectedAlertsBySignal as $signal => $count) {
        $row = collect($result['filas'])->first(fn (array $row): bool => $row['indicador'] === TipoIndicador::AlertasGeneradas->value
            && $row['segmento'] === $signal
            && (int) $row['area_id'] === $areaId
        );

        expect($row)->not->toBeNull();
        if ($count < 5) {
            expect($row['supresion'])->toBe('primaria');
        } else {
            expect($row['supresion'])->toBeNull()
                ->and((int) $row['valor'])->toBe($count);
        }
    }
});

/** @return array<string, int> */
function hu18SeedIndependentCounts(int $areaId, CarbonImmutable $from, CarbonImmutable $to): array
{
    $from = hu18SqlTimestamp($from);
    $to = hu18SqlTimestamp($to);
    $consultas = DB::table('consultas as consulta')
        ->join('expedientes as expediente', 'expediente.id', '=', 'consulta.expediente_id')
        ->where('expediente.area_id', $areaId)
        ->whereNull('consulta.deleted_at')
        ->whereNull('expediente.deleted_at')
        ->where('consulta.fecha_consulta', '>=', $from)
        ->where('consulta.fecha_consulta', '<', $to)
        ->selectRaw('COUNT(*) AS consultas, COUNT(DISTINCT expediente.paciente_id) AS pacientes')
        ->first();
    $expedientes = DB::table('expedientes')
        ->where('area_id', $areaId)
        ->whereNull('deleted_at')
        ->where(function ($query) use ($from, $to): void {
            $query->where(function ($query) use ($from, $to): void {
                $query->whereIn('estado', ['abierto', 'en_atencion'])
                    ->where(function ($query) use ($from, $to): void {
                        $query->where(function ($query) use ($from, $to): void {
                            $query->whereNotNull('fecha_derivacion')
                                ->where('fecha_derivacion', '>=', $from)
                                ->where('fecha_derivacion', '<', $to);
                        })->orWhere(function ($query) use ($from, $to): void {
                            $query->whereNull('fecha_derivacion')
                                ->where('created_at', '>=', $from)
                                ->where('created_at', '<', $to);
                        });
                    });
            })->orWhere(function ($query) use ($from, $to): void {
                $query->where('estado', 'cerrado')
                    ->where('fecha_cierre', '>=', $from)
                    ->where('fecha_cierre', '<', $to);
            });
        })
        ->selectRaw("SUM(CASE WHEN estado IN ('abierto', 'en_atencion') THEN 1 ELSE 0 END) AS abiertos, SUM(CASE WHEN estado = 'cerrado' THEN 1 ELSE 0 END) AS cerrados")
        ->first();
    $citas = DB::table('citas')
        ->where('area_id', $areaId)
        ->whereNull('deleted_at')
        ->where('fecha_hora', '>=', $from)
        ->where('fecha_hora', '<', $to)
        ->selectRaw("COUNT(*) FILTER (WHERE estado = 'programada') AS programadas, COUNT(*) FILTER (WHERE estado = 'asistida') AS asistidas, COUNT(*) FILTER (WHERE estado = 'ausente') AS ausentes, COUNT(*) FILTER (WHERE estado = 'cancelada') AS canceladas, COUNT(*) FILTER (WHERE estado = 'reprogramada') AS reprogramadas")
        ->first();
    $seguimientos = DB::table('seguimientos_preventivos')
        ->where('area_id', $areaId)
        ->where('created_at', '>=', $from)
        ->where('created_at', '<', $to)
        ->count();

    return [
        TipoIndicador::ConsultasRegistradas->value => (int) $consultas->consultas,
        TipoIndicador::PacientesAtendidos->value => (int) $consultas->pacientes,
        TipoIndicador::ExpedientesAbiertos->value => (int) $expedientes->abiertos,
        TipoIndicador::ExpedientesCerrados->value => (int) $expedientes->cerrados,
        TipoIndicador::CitasProgramadas->value => (int) $citas->programadas,
        TipoIndicador::CitasAsistidas->value => (int) $citas->asistidas,
        TipoIndicador::CitasNoAsistidas->value => (int) $citas->ausentes,
        TipoIndicador::CitasCanceladas->value => (int) $citas->canceladas,
        TipoIndicador::CitasReprogramadas->value => (int) $citas->reprogramadas,
        TipoIndicador::SeguimientosRegistrados->value => $seguimientos,
    ];
}

/** @return array<string, int> */
function hu18SeedIndependentAlertsBySignal(int $areaId, CarbonImmutable $from, CarbonImmutable $to): array
{
    $from = hu18SqlTimestamp($from);
    $to = hu18SqlTimestamp($to);

    return DB::table('alertas_preventivas')
        ->where('area_id', $areaId)
        ->where('generada_en', '>=', $from)
        ->where('generada_en', '<', $to)
        ->select('senal')
        ->selectRaw('COUNT(*) AS total')
        ->groupBy('senal')
        ->pluck('total', 'senal')
        ->map(static fn (mixed $total): int => (int) $total)
        ->all();
}

function hu18SqlTimestamp(CarbonImmutable $date): string
{
    return $date->format('Y-m-d H:i:s.uP');
}

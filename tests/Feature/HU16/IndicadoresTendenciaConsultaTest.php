<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Area;
use App\Models\Especialista;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\Rubrica;
use App\Models\RubricaVersion;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

beforeEach(function (): void {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->withSession(['_sym_key' => str_repeat('a', 32)]);

    $this->area = Area::factory()->create();
    $this->specialist = Especialista::factory()
        ->withRole('specialist')
        ->withProfesional($this->area)
        ->create();
    $this->professional = $this->specialist->profesional;
    $this->rubrica = Rubrica::query()
        ->where('codigo', Rubrica::CODIGO_RIESGO_PREVENTIVO)
        ->firstOrFail();

    $patient = Paciente::factory()->create([
        'creado_por_profesional_id' => $this->professional->id,
    ]);
    $this->expediente = Expediente::factory()->create([
        'paciente_id' => $patient->codigo,
        'area_id' => $this->area->id,
        'estado' => 'abierto',
        'derivado_por_profesional_id' => $this->professional->id,
        'fecha_derivacion' => now()->subDay(),
    ]);

    $this->actingAs($this->specialist);
});

/** @param array<int, string> $trendIndicators */
function hu16IndicadoresCreateRubricVersion(
    Rubrica $rubrica,
    Profesional $professional,
    int $number,
    CarbonInterface $effectiveAt,
    array $trendIndicators,
): RubricaVersion {
    $version = $rubrica->versiones()->create([
        'numero_version' => $number,
        'vigente_desde' => $effectiveAt,
        'umbral_puntaje_global' => null,
        'justificacion_cambio' => $number === 1 ? null : 'Actualización de indicadores.',
        'validada_en' => now()->subDay()->toDateString(),
        'referencia_validacion' => 'Protocolo de validación HU16',
        'es_demo' => false,
        'creada_por_profesional_id' => $professional->id,
    ]);

    $variables = [
        [
            'tipo_senal' => 'crisis_psicologica',
            'clave_indicador' => null,
            'modo_calculo' => 'frecuencia',
            'ventana_dias' => 30,
            'ponderacion' => '1.00',
            'umbral' => '1.00',
        ],
        [
            'tipo_senal' => 'ausencia_cita',
            'clave_indicador' => null,
            'modo_calculo' => 'frecuencia',
            'ventana_dias' => 30,
            'ponderacion' => '1.00',
            'umbral' => '1.00',
        ],
        ...array_map(static fn (string $indicator): array => [
            'tipo_senal' => 'tendencia_suicida',
            'clave_indicador' => $indicator,
            'modo_calculo' => 'puntaje',
            'ventana_dias' => null,
            'ponderacion' => '1.00',
            'umbral' => '1.00',
        ], $trendIndicators),
    ];

    foreach ($variables as $variable) {
        $version->variables()->create($variable);
    }

    return $version;
}

/** @return array<string, string> */
function hu16IndicadoresInertiaHeaders(): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()) ?? '',
        'Accept' => 'application/json',
    ];
}

test('consulta creation exposes trend indicators from the effective rubric version', function (): void {
    // Arrange
    hu16IndicadoresCreateRubricVersion(
        $this->rubrica,
        $this->professional,
        1,
        now()->subDay(),
        ['legacy_indicator'],
    );
    hu16IndicadoresCreateRubricVersion(
        $this->rubrica,
        $this->professional,
        2,
        now()->addDays(2),
        ['self_harm_plan', 'persistent_hopelessness'],
    );

    // Act
    $response = $this->withHeaders(hu16IndicadoresInertiaHeaders())
        ->get(route('consultas.create', $this->expediente->id));
    $page = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

    // Assert
    expect($response->getStatusCode())->toBe(200)
        ->and($page['component'])->toBe('Consultas/Create')
        ->and($page['props']['indicadoresTendenciaSuicida'])
        ->toBe(['legacy_indicator'])
        ->and($page['props']['esPrimeraConsulta'])->toBeTrue();
});

test('consulta creation exposes an empty trend indicator list when no rubric version is configured', function (): void {
    // Arrange
    $expectedIndicators = [];

    // Act
    $response = $this->withHeaders(hu16IndicadoresInertiaHeaders())
        ->get(route('consultas.create', $this->expediente->id));
    $page = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

    // Assert
    expect($response->getStatusCode())->toBe(200)
        ->and($page['props']['indicadoresTendenciaSuicida'])->toBe($expectedIndicators);
});

test('consulta creation keeps authorization for users without consultation access', function (): void {
    // Arrange
    $referent = Especialista::factory()
        ->withRole('psychosocial_referent')
        ->withProfesional($this->area)
        ->create();

    // Act
    $response = $this->actingAs($referent)
        ->withHeaders(hu16IndicadoresInertiaHeaders())
        ->get(route('consultas.create', $this->expediente->id));

    // Assert
    expect($response->getStatusCode())->toBe(403);
});

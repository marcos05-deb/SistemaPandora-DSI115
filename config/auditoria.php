<?php

declare(strict_types=1);
use App\Models\AlertaPreventiva;
use App\Models\Area;
use App\Models\AutorizacionPaciente;
use App\Models\Carrera;
use App\Models\Cita;
use App\Models\Consulta;
use App\Models\ContactoPaciente;
use App\Models\Especialista;
use App\Models\EstadisticaPreventivaAusencia;
use App\Models\ExportacionAuditoria;
use App\Models\ExportacionReporte;
use App\Models\EvaluacionRiesgo;
use App\Models\Expediente;
use App\Models\Facultad;
use App\Models\Paciente;
use App\Models\PacienteCorreccionAuditoria;
use App\Models\Profesional;
use App\Models\Role;
use App\Models\Rubrica;
use App\Models\RubricaVariable;
use App\Models\RubricaVersion;
use App\Models\SeguimientoPreventivo;
use App\Models\SolicitudCorreccionExpediente;

/**
 * Clasificación de modelos para cobertura de auditoría (HU-21).
 * Todo modelo Eloquent en app/Models debe figurar en una de las dos listas.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Modelos sensibles (deben implementar Auditable o registro equivalente)
    |--------------------------------------------------------------------------
    */
    'modelos_sensibles' => [
        Paciente::class,
        ContactoPaciente::class,
        Expediente::class,
        Consulta::class,
        Cita::class,
        SolicitudCorreccionExpediente::class,
        Especialista::class,
        Profesional::class,
        Rubrica::class,
        RubricaVersion::class,
        RubricaVariable::class,
        EvaluacionRiesgo::class,
        AlertaPreventiva::class,
        SeguimientoPreventivo::class,
        ExportacionReporte::class,
        ExportacionAuditoria::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Modelos no sensibles (justificación textual)
    |--------------------------------------------------------------------------
    */
    'modelos_no_sensibles' => [
        Area::class => 'Catálogo institucional; sin PHI.',
        Carrera::class => 'Catálogo académico; sin PHI.',
        Facultad::class => 'Catálogo académico; sin PHI.',
        Role::class => 'Catálogo de roles; sin PHI.',
        AutorizacionPaciente::class => 'Relación de autorización de área; el acceso se audita sobre Paciente/Expediente.',
        EstadisticaPreventivaAusencia::class => 'Agregado estadístico sin identificadores directos de paciente.',
        PacienteCorreccionAuditoria::class => 'Bitácora de dominio append-only; valores ya redactados ([CIFRADO]); no usa OwenIt.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Patrón de carnet en URLs (AA00000) — se sustituye por {carnet}
    |--------------------------------------------------------------------------
    */
    // Segmento tras /pacientes/ (carnet u otro id en ruta) → placeholder.
    'carnet_url_pattern' => '#/pacientes/[^/?\s]+#i',
    'carnet_url_replacement' => '/pacientes/{carnet}',

    // Solo tests HU-23: fuerza fallo tras generar el temporal y antes de registrar.
    'simulate_export_failure' => false,

    // Tope de eventos por exportación CSV de bitácora (HU-23).
    'export_max_eventos' => 50000,
];

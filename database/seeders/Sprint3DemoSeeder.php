<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Http\Requests\StorePacienteRequest;
use App\Models\AlertaPreventiva;
use App\Models\Area;
use App\Models\Carrera;
use App\Models\Cita;
use App\Models\Consulta;
use App\Models\Especialista;
use App\Models\EstadisticaPreventivaAusencia;
use App\Models\Expediente;
use App\Models\Facultad;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\Role;
use App\Models\Rubrica;
use App\Services\AlertaSeguimientoService;
use App\Services\EvaluadorRiesgoPreventivo;
use App\Services\RubricaResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Production-safe, idempotent preparation of the Sprint 3 demo (HU-16..HU-23).
 *
 * Unlike RubricaDemoSeeder / DashboardCasosDemoSeeder (local/testing only) this class
 * runs in any environment, but it is only reachable through `pandora:preparar-sprint3`,
 * which enforces explicit opt-in. Every block runs in its own transaction and is skipped
 * when its natural key already exists. Demo patients are recognised by the "DM" carnet prefix.
 *
 * Encryption note: field keys derive from BLIND_INDEX_SECRET / AREA_KEY_SECRET (global),
 * not from per-user keys, so seeded users only need a password; `kdf_salt` is generated at first login.
 */
class Sprint3DemoSeeder extends Seeder
{
    public const PREFIJO_CARNET = 'DM';

    /** Patients ready for the live HU-16 demo: first is the main one, the rest are rehearsal spares. */
    public const CARNETS_HU16 = ['DM26010', 'DM26011', 'DM26012'];

    public const CARNET_ALERTA_ACTIVA = 'DM26020';

    public const CARNET_ALERTA_CERRADA = 'DM26021';

    private const USUARIOS = [
        ['admin@pandora.com', 'Administrador', 'sysadmin', null, null, null],
        ['coordinador@pandora.com', 'Dr. Coordinador', 'area_coordinator', 'Psicología', 'Psicología Clínica', 'PSI-COORD-001'],
        ['especialista-psi@pandora.com', 'Lic. Psicólogo Dos', 'specialist', 'Psicología', 'Psicología Infantil', 'PSI-002'],
        ['especialista@pandora.com', 'Dr. Especialista', 'specialist', 'Medicina General', 'Medicina General', 'MED-001'],
        ['especialista-nutri@pandora.com', 'Licda. Nutricionista', 'specialist', 'Nutrición', 'Nutrición Clínica', 'NUT-001'],
        ['psicosocial@pandora.com', 'Lic. Psicosocial', 'psychosocial_referent', 'Trabajo Social', 'Trabajo Social', 'TS-001'],
    ];

    /** @var list<array{0: string, 1: string}> [block, status] */
    private array $informe = [];

    private bool $hayErrores = false;

    /** @var list<int> */
    private array $carreraIds = [];

    /** Default entry point (e.g. `db:seed --class=Sprint3DemoSeeder`); the command is the supported path. */
    public function run(): void
    {
        $password = config('app.sprint3_demo_password');
        if (! is_string($password) || mb_strlen($password) < 12) {
            throw new RuntimeException('SPRINT3_DEMO_PASSWORD debe tener al menos 12 caracteres.');
        }

        $this->sembrar($password);
    }

    /**
     * @return array{informe: list<array{0: string, 1: string}>, errores: bool}
     */
    public function sembrar(string $password, bool $resetPasswords = false): array
    {
        $this->informe = [];
        $this->hayErrores = false;

        $this->bloque('Catálogos (roles, áreas, facultades y carreras)', fn (): string => $this->catalogos());

        foreach (self::USUARIOS as $usuario) {
            $this->bloque('Usuario '.$usuario[0], fn (): string => $this->usuario($usuario, $password, $resetPasswords));
        }

        $this->bloque('Rúbrica riesgo_preventivo (validada y vigente)', fn (): string => $this->rubrica());

        $this->bloque('Panel/analítica Psicología (6 pacientes)', fn (): string => $this->analiticaPsicologia());
        $this->bloque('Analítica Medicina General (3 pacientes)', fn (): string => $this->analiticaMedicina());
        $this->bloque('Analítica Nutrición (2 pacientes)', fn (): string => $this->analiticaNutricion());

        foreach (self::CARNETS_HU16 as $i => $carnet) {
            $this->bloque("HU-16 paciente listo para alerta {$carnet}", fn (): string => $this->pacienteListoParaAlerta($carnet, $i));
        }

        $this->bloque('HU-17 paciente con alerta ACTIVA '.self::CARNET_ALERTA_ACTIVA, fn (): string => $this->pacienteConAlerta(self::CARNET_ALERTA_ACTIVA, false));
        $this->bloque('HU-17 paciente con alerta CERRADA '.self::CARNET_ALERTA_CERRADA, fn (): string => $this->pacienteConAlerta(self::CARNET_ALERTA_CERRADA, true));

        return ['informe' => $this->informe, 'errores' => $this->hayErrores];
    }

    /** @param callable(): string $accion Returns "creado", "existente" or "pendiente: ...". */
    private function bloque(string $nombre, callable $accion): void
    {
        try {
            $estado = DB::transaction($accion);
        } catch (Throwable $exception) {
            $this->hayErrores = true;
            $estado = 'ERROR ('.$exception::class.'): '.mb_substr($exception->getMessage(), 0, 200);
            Log::error('Sprint 3 demo block failed.', ['bloque' => $nombre, 'exception' => $exception::class]);
        }

        $this->informe[] = [$nombre, $estado];
    }

    private function catalogos(): string
    {
        $antes = Role::count() + Area::count() + Facultad::count() + Carrera::count();

        (new RoleSeeder)->run();
        (new AreaSeeder)->run();
        (new FacultadCarreraSeeder)->run();

        $nuevos = Role::count() + Area::count() + Facultad::count() + Carrera::count() - $antes;

        return $nuevos > 0 ? "creado ({$nuevos} registros nuevos)" : 'existente';
    }

    /** @param array{0: string, 1: string, 2: string, 3: ?string, 4: ?string, 5: ?string} $def */
    private function usuario(array $def, string $password, bool $resetPasswords): string
    {
        [$email, $nombre, $rolSlug, $areaNombre, $especialidad, $registro] = $def;

        $rol = Role::query()->where('slug', $rolSlug)->firstOrFail();
        $user = Especialista::withTrashed()->where('email', $email)->first();
        $cambios = [];

        if ($user === null) {
            $user = new Especialista(['name' => $nombre, 'email' => $email, 'is_active' => true, 'password' => $password]);
            // must_change_password is intentionally not mass-assignable.
            $user->forceFill(['must_change_password' => false])->save();
            $cambios[] = 'usuario';
        } elseif ($resetPasswords && ! $user->trashed()) {
            $user->forceFill([
                'password' => $password,
                'must_change_password' => false,
                'is_active' => true,
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ])->save();
            $cambios[] = 'contraseña restablecida';
        }

        if (! $user->roles()->where('roles.id', $rol->id)->exists()) {
            $user->roles()->attach($rol->id);
            $cambios[] = 'rol';
        }

        if ($areaNombre !== null) {
            $area = Area::query()->where('nombre', $areaNombre)->firstOrFail();
            if ($user->profesionalParaArea($area->id) === null) {
                Profesional::query()->create([
                    'user_id' => $user->id,
                    'area_id' => $area->id,
                    'especialidad' => $especialidad,
                    'numero_registro' => $registro,
                ]);
                $cambios[] = 'perfil profesional';
            }
        }

        $sufijo = $user->trashed() ? ' [AVISO: usuario eliminado lógicamente, no puede iniciar sesión]' : '';

        return ($cambios === [] ? 'existente' : 'creado/actualizado ('.implode(', ', $cambios).')').$sufijo;
    }

    private function rubrica(): string
    {
        $rubrica = Rubrica::query()->where('codigo', Rubrica::CODIGO_RIESGO_PREVENTIVO)->first();
        if ($rubrica === null) {
            throw new RuntimeException('La rúbrica canónica riesgo_preventivo no existe; ejecute las migraciones.');
        }

        $validada = $rubrica->versiones()
            ->where('es_demo', false)
            ->whereNotNull('validada_en')
            ->whereNotNull('referencia_validacion');
        if ($validada->exists()) {
            return 'existente';
        }

        $coordinador = $this->profesional('coordinador@pandora.com', 'Psicología');

        // The append-only table only allows INSERT for the app role; reuse the same lock the editor uses.
        DB::selectOne('SELECT public.lock_rubrica_for_versioning(?)', [$rubrica->getKey()]);

        $ultima = $rubrica->versiones()->orderByDesc('vigente_desde')->first();
        // ponytail: the UI forces a future vigente_desde; the DB does not. A seed needs the version live
        // in this same run, so it starts one minute ago (still strictly after any previous version).
        $vigenteDesde = now()->subMinute();
        if ($ultima !== null && ! $vigenteDesde->isAfter($ultima->vigente_desde)) {
            $vigenteDesde = $ultima->vigente_desde->copy()->addSecond();
        }

        $version = $rubrica->versiones()->create([
            'numero_version' => ((int) $rubrica->versiones()->max('numero_version')) + 1,
            'vigente_desde' => $vigenteDesde,
            'umbral_puntaje_global' => null,
            'justificacion_cambio' => 'Versión inicial validada para la demostración del Sprint 3.',
            'validada_en' => now()->toDateString(),
            'referencia_validacion' => 'Validación Sprint 3 — DSI115',
            'es_demo' => false,
            'creada_por_profesional_id' => $coordinador->id,
        ]);

        foreach ([
            ['tipo_senal' => 'ausencia_cita', 'clave_indicador' => null, 'modo_calculo' => 'frecuencia', 'ventana_dias' => 30, 'ponderacion' => 1.00, 'umbral' => 1.00],
            ['tipo_senal' => 'crisis_psicologica', 'clave_indicador' => null, 'modo_calculo' => 'frecuencia', 'ventana_dias' => 90, 'ponderacion' => 1.00, 'umbral' => 1.00],
            ['tipo_senal' => 'tendencia_suicida', 'clave_indicador' => 'ideas_suicidas', 'modo_calculo' => 'puntaje', 'ventana_dias' => null, 'ponderacion' => 1.00, 'umbral' => 1.00],
        ] as $variable) {
            $version->variables()->create($variable);
        }

        return $vigenteDesde->isFuture()
            ? 'creado (AÚN NO vigente: vuelva a ejecutar el comando cuando lo sea)'
            : 'creado (versión '.$version->numero_version.')';
    }

    private function analiticaPsicologia(): string
    {
        $area = $this->area('Psicología');
        $creador = $this->profesional('coordinador@pandora.com', 'Psicología');
        $atiende = $this->profesional('especialista-psi@pandora.com', 'Psicología');
        $tags = [
            ['Problemas Académicos', 'Dificultades Socioeconómicas'], ['Duelo'], ['Violencia Familiar'],
            ['Conflictos Interpersonales', 'Violencia Pareja'], ['Problemas de Adaptación'], ['Problemas Académicos'],
        ];
        $creados = 0;

        // One attended appointment + one absence per patient, spread over ~3 months.
        // A single absence per patient stays below the alert threshold (2 in 30 days).
        for ($i = 0; $i < 6; $i++) {
            $dias = 8 + $i * 15;
            $creados += $this->caso(sprintf('DM26%03d', $i + 1), $i, $area, $creador, $atiende, [
                'consulta' => $dias,
                'asistida' => $dias,
                'ausente' => $dias + 6,
                'tags' => $tags[$i],
            ]) ? 1 : 0;
        }

        return $creados > 0 ? "creado ({$creados} pacientes nuevos)" : 'existente';
    }

    private function analiticaMedicina(): string
    {
        $area = $this->area('Medicina General');
        $prof = $this->profesional('especialista@pandora.com', 'Medicina General');
        $creados = 0;

        foreach ([[101, 12, null], [102, 40, 45], [103, 70, null]] as $i => [$n, $dias, $ausente]) {
            $creados += $this->caso('DM26'.$n, 10 + $i, $area, $prof, $prof, [
                'consulta' => $dias, 'asistida' => $dias, 'ausente' => $ausente, 'tags' => [],
            ]) ? 1 : 0;
        }

        return $creados > 0 ? "creado ({$creados} pacientes nuevos)" : 'existente';
    }

    private function analiticaNutricion(): string
    {
        $area = $this->area('Nutrición');
        $prof = $this->profesional('especialista-nutri@pandora.com', 'Nutrición');
        $creados = 0;

        foreach ([[201, 20], [202, 55]] as $i => [$n, $dias]) {
            $creados += $this->caso('DM26'.$n, 20 + $i, $area, $prof, $prof, [
                'consulta' => $dias, 'asistida' => $dias, 'ausente' => null, 'tags' => [],
            ]) ? 1 : 0;
        }

        return $creados > 0 ? "creado ({$creados} pacientes nuevos)" : 'existente';
    }

    /** Open file, no alerts, two past appointments still "programada": marking both absent triggers HU-16. */
    private function pacienteListoParaAlerta(string $carnet, int $indice): string
    {
        $area = $this->area('Psicología');
        $creador = $this->profesional('coordinador@pandora.com', 'Psicología');
        $atiende = $this->profesional('especialista-psi@pandora.com', 'Psicología');

        $creado = $this->caso($carnet, 30 + $indice, $area, $creador, $atiende, [
            'consulta' => 14,
            'asistida' => 14,
            'ausente' => null,
            'pendientes' => [2, 1],
            'tags' => ['Problemas Académicos'],
        ]);

        return $creado ? 'creado (2 citas pasadas pendientes de asistencia)' : 'existente';
    }

    /** Active alert (or closed with follow-up), generated by the real evaluator and follow-up service. */
    private function pacienteConAlerta(string $carnet, bool $cerrada): string
    {
        if (Paciente::withTrashed()->where('carnet', $carnet)->exists()) {
            return 'existente';
        }

        if (app(RubricaResolver::class)->vigente() === null) {
            return 'pendiente: aún no hay rúbrica vigente; vuelva a ejecutar el comando en un par de minutos';
        }

        $area = $this->area('Psicología');
        $creador = $this->profesional('coordinador@pandora.com', 'Psicología');
        $atiende = $this->profesional('especialista-psi@pandora.com', 'Psicología');

        $this->caso($carnet, 40 + (int) $cerrada, $area, $creador, $atiende, [
            'consulta' => 12,
            'asistida' => null,
            'ausente' => null,
            'tags' => ['Violencia Familiar'],
            'ausencias_con_alerta' => [9, 3],
        ]);

        $paciente = Paciente::query()->where('carnet', $carnet)->firstOrFail();
        $alerta = AlertaPreventiva::query()
            ->where('paciente_id', $paciente->codigo)
            ->where('senal', 'ausencia_cita')
            ->first();
        if ($alerta === null) {
            throw new RuntimeException('El evaluador no generó la alerta esperada para '.$carnet.'.');
        }

        if ($cerrada) {
            $usuario = $atiende->especialista;
            $servicio = app(AlertaSeguimientoService::class);
            $servicio->registrarSeguimiento($alerta, $usuario, [
                'valoracion' => 'Paciente contactado; refiere carga académica y dificultades de transporte.',
                'accion_preventiva' => 'Se reprogramó la atención y se acordó recordatorio previo a cada cita.',
                'responsable_profesional_id' => $atiende->id,
                'proxima_revision' => now()->addDays(7)->toDateString(),
            ]);
            $servicio->cerrar($alerta->refresh(), $usuario, [
                'resultado' => 'Paciente retomó asistencia regular; se cierra la alerta preventiva.',
            ]);
        }

        return $cerrada ? 'creado (alerta cerrada con seguimiento)' : 'creado (alerta activa)';
    }

    /**
     * Creates patient + open file + consultation + appointments through the real models.
     * Returns false when the patient already exists (whole case skipped).
     *
     * @param  array{consulta: int, asistida: ?int, ausente: ?int, tags: list<string>, pendientes?: list<int>, ausencias_con_alerta?: list<int>}  $plan  days-ago offsets
     */
    private function caso(string $carnet, int $indice, Area $area, Profesional $creador, Profesional $atiende, array $plan): bool
    {
        if (Paciente::withTrashed()->where('carnet', $carnet)->exists()) {
            return false;
        }

        $etiquetas = array_values(array_intersect($plan['tags'], StorePacienteRequest::ETIQUETAS_VALIDAS));

        $paciente = Paciente::query()->create([
            'carnet' => $carnet,
            'carrera_id' => $this->carreraId($indice),
            'creado_por_profesional_id' => $creador->id,
            'sexo' => $indice % 2 === 0 ? 'F' : 'M',
            'estado_civil' => 'Soltero',
            'nombre_completo' => 'Estudiante DEMO '.$carnet,
            'direccion' => 'Campus DEMO Sprint 3',
            'fecha_nacimiento' => '2003-05-12',
            'profesion_ocupacion' => 'Estudiante',
            'fecha_primera_consulta' => now()->subDays($plan['consulta'])->toDateString(),
            'motivo_consulta' => 'Motivo sintético para la demostración del Sprint 3.',
            'etiquetas_motivo' => $etiquetas,
        ]);

        $expediente = Expediente::query()->create([
            'paciente_id' => $paciente->codigo,
            'area_id' => $area->id,
            'motivo_consulta' => 'Expediente sintético Sprint 3.',
            'notas_clinicas' => 'Nota sintética para la demostración.',
            'diagnostico' => 'Diagnóstico sintético de demostración.',
            'estado' => Expediente::ESTADO_ABIERTO,
            'derivado_por_profesional_id' => $creador->id,
            'fecha_derivacion' => now()->subDays($plan['consulta'] + 1),
        ]);

        $consulta = new Consulta;
        $consulta->expediente_id = $expediente->id;
        $consulta->setRelation('expediente', $expediente);
        $consulta->fill([
            'profesional_id' => $atiende->id,
            'motivo_consulta' => 'Consulta sintética de demostración.',
            'notas_clinicas' => 'Registro sintético Sprint 3.',
            'diagnostico' => 'Sin hallazgos relevantes (datos de demostración).',
            'plan_atencion' => 'Seguimiento periódico.',
            'tecnica_utilizada' => 'Entrevista',
            'fecha_consulta' => $this->fecha($plan['consulta'], 10),
            'evaluacion_inicial' => [],
            'crisis_psicologica' => 'No',
            'indicadores_tendencia_suicida' => [],
        ]);
        $consulta->save();

        if ($plan['asistida'] !== null) {
            $this->cita($expediente, $atiende, $consulta, 'asistida', $this->fecha($plan['asistida'], 10));
        }
        if ($plan['ausente'] !== null) {
            $this->cita($expediente, $atiende, $consulta, 'ausente', $this->fecha($plan['ausente'], 15));
        }
        foreach ($plan['pendientes'] ?? [] as $dias) {
            // Past but still "programada": the attendance register accepts it, the alert fires on "ausente".
            $this->cita($expediente, $atiende, $consulta, 'programada', $this->fecha($dias, 9));
        }
        foreach ($plan['ausencias_con_alerta'] ?? [] as $dias) {
            $cita = $this->cita($expediente, $atiende, $consulta, 'ausente', $this->fecha($dias, 15));
            app(EvaluadorRiesgoPreventivo::class)->evaluarAusencia($cita);
        }

        return true;
    }

    private function cita(Expediente $expediente, Profesional $profesional, Consulta $consulta, string $estado, Carbon $fecha): Cita
    {
        $cita = new Cita;
        $cita->expediente_id = $expediente->id;
        $cita->consulta_id = $consulta->id;
        $cita->profesional_id = $profesional->id;
        $cita->area_id = $expediente->area_id; // before encrypted attributes (area key resolution)
        $cita->fecha_hora = $fecha;
        $cita->motivo = 'Seguimiento (demostración Sprint 3)';
        $cita->estado = $estado;

        if ($estado !== 'programada') {
            $cita->fecha_registro_asistencia = $fecha->copy()->addMinutes(30);
            $cita->registrado_por_profesional_id = $profesional->id;
        }
        $cita->save();

        if ($estado === 'ausente') {
            EstadisticaPreventivaAusencia::query()->firstOrCreate(
                ['cita_id' => $cita->id],
                [
                    'paciente_id' => $expediente->paciente_id,
                    'profesional_id' => $profesional->id,
                    'area_id' => $expediente->area_id,
                    'fecha_registro' => $cita->fecha_registro_asistencia,
                ]
            );
        }

        return $cita;
    }

    private function fecha(int $diasAtras, int $hora): Carbon
    {
        return now()->subDays($diasAtras)->setTime($hora, 0);
    }

    private function carreraId(int $indice): int
    {
        if ($this->carreraIds === []) {
            $this->carreraIds = Carrera::query()->orderBy('id')->limit(12)->pluck('id')->all();
        }
        if ($this->carreraIds === []) {
            throw new RuntimeException('No hay carreras; el catálogo de facultades/carreras está vacío.');
        }

        return (int) $this->carreraIds[$indice % count($this->carreraIds)];
    }

    private function area(string $nombre): Area
    {
        return Area::query()->where('nombre', $nombre)->firstOrFail();
    }

    private function profesional(string $email, string $areaNombre): Profesional
    {
        $user = Especialista::query()->where('email', $email)->firstOrFail();
        $perfil = $user->profesionalParaArea($this->area($areaNombre)->id);
        if ($perfil === null) {
            throw new RuntimeException("{$email} no tiene perfil profesional en {$areaNombre}.");
        }

        return $perfil;
    }
}

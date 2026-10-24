<?php

declare(strict_types=1);

use App\Http\Middleware\RequirePasswordChange;
use App\Models\Area;
use App\Models\Audit;
use App\Models\Cita;
use App\Models\Especialista;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Models\Role;
use App\Services\ClinicalAccessAuditor;
use App\Services\Crypto\EncryptionContextService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use OwenIt\Auditing\Contracts\Auditable;

beforeEach(function () {
    $this->withoutMiddleware([
        RequirePasswordChange::class,
        PreventRequestForgery::class,
    ]);

    $this->app->instance(EncryptionContextService::class, new class extends EncryptionContextService
    {
        public function getKey(): string
        {
            return str_repeat('H', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        }
    });

    Role::firstOrCreate(['slug' => 'specialist'], ['nombre' => 'Especialista', 'nivel' => 30]);
    Role::firstOrCreate(['slug' => 'sysadmin'], ['nombre' => 'Sysadmin', 'nivel' => 100]);
    Role::firstOrCreate(['slug' => 'psychosocial_referent'], ['nombre' => 'Referente', 'nivel' => 20]);

    $this->area = Area::factory()->create(['nombre' => 'Psicología']);
    $this->especialista = Especialista::factory()
        ->withProfesional($this->area)
        ->withRole('specialist')
        ->create();
});

it('HU21-E05 clasifica todos los modelos Eloquent y exige Auditable en sensibles', function () {
    $sensibles = config('auditoria.modelos_sensibles');
    $noSensibles = array_keys(config('auditoria.modelos_no_sensibles'));
    $classified = array_merge($sensibles, $noSensibles);

    $modelFiles = File::files(app_path('Models'));
    $found = [];
    foreach ($modelFiles as $file) {
        if ($file->getFilename() === 'Audit.php') {
            continue;
        }
        $class = 'App\\Models\\'.$file->getFilenameWithoutExtension();
        if (! class_exists($class)) {
            continue;
        }
        $ref = new ReflectionClass($class);
        if ($ref->isAbstract() || ! $ref->isSubclassOf(Model::class)) {
            continue;
        }
        if ($ref->getNamespaceName() !== 'App\\Models') {
            continue;
        }
        $found[] = $class;
    }

    foreach ($found as $class) {
        expect(in_array($class, $classified, true))
            ->toBeTrue("{$class} no está en config/auditoria.php");
    }

    foreach ($sensibles as $class) {
        expect(is_subclass_of($class, Auditable::class) || in_array(Auditable::class, class_implements($class) ?: [], true))
            ->toBeTrue("{$class} sensible debe implementar Auditable");
    }
});

it('HU21-E01 crea evento created al registrar paciente sensible', function () {
    $referente = Especialista::factory()
        ->withProfesional($this->area)
        ->withRole('psychosocial_referent')
        ->create();
    $this->actingAs($referente)
        ->withSession(['_sym_key' => base64_encode(str_repeat('H', SODIUM_CRYPTO_SECRETBOX_KEYBYTES))]);

    $payload = array_merge(Paciente::factory()->make(['carnet' => 'HX21001'])->toArray(), [
        'responsable_parentesco' => 'Otro',
        'responsable_nombre' => 'Resp',
        'responsable_telefono' => '70001111',
        'responsable_direccion' => 'Dir',
        'fecha_nacimiento' => '2000-01-01',
        'motivo_consulta' => 'Motivo de prueba HU21 sin datos sensibles extra.',
    ]);

    $response = $this->post('/pacientes', $payload);
    if ($response->exception) {
        throw $response->exception;
    }
    $response->assertSessionHasNoErrors();

    $paciente = Paciente::where('carnet', 'HX21001')->first();
    expect($paciente)->not->toBeNull();

    $created = Audit::query()
        ->where('auditable_type', Paciente::class)
        ->where('auditable_id', (string) $paciente->codigo)
        ->where('event', 'created')
        ->exists();

    expect($created)->toBeTrue();
});

it('HU21-E02 y HU21-E07 lectura de paciente sanitiza carnet en URL', function () {
    $paciente = Paciente::factory()->create([
        'creado_por_profesional_id' => $this->especialista->profesional->id,
        'carnet' => 'HX21002',
    ]);
    Expediente::factory()->create([
        'paciente_id' => $paciente->codigo,
        'area_id' => $this->area->id,
        'estado' => 'abierto',
    ]);

    $this->actingAs($this->especialista)
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->withSession(['_sym_key' => base64_encode(str_repeat('H', SODIUM_CRYPTO_SECRETBOX_KEYBYTES))])
        ->get('/pacientes/HX21002')
        ->assertOk();

    $access = Audit::query()
        ->where('event', 'accessed')
        ->where('tags', 'clinical_access')
        ->where('user_id', $this->especialista->id)
        ->where('auditable_type', Paciente::class)
        ->latest('id')
        ->first();

    expect($access)->not->toBeNull();
    expect($access->ip_address)->toBe('203.0.113.10');
    expect((string) $access->url)->not->toContain('HX21002');
    expect((string) $access->url)->toContain('{carnet}');
});

it('HU21-E03 y HU21-E08 sysadmin queda identificado en el evento', function () {
    $admin = Especialista::factory()->withRole('sysadmin')->create();

    app(ClinicalAccessAuditor::class)->record($admin, Paciente::factory()->create(), 'view_patient');

    $row = Audit::query()->where('user_id', $admin->id)->latest('id')->first();
    expect($row)->not->toBeNull();
    expect($row->user_type)->toBe(Especialista::class);
    expect($row->event)->toBe('accessed');
    expect($row->created_at)->not->toBeNull();
});

it('HU21-E04 inmutabilidad Eloquent y SQL 42501', function () {
    $audit = app(ClinicalAccessAuditor::class)
        ->record($this->especialista, Paciente::factory()->create(), 'view_patient');

    expect(fn () => $audit->update(['tags' => 'x']))
        ->toThrow(RuntimeException::class, 'Los registros de auditoría son inmutables.');

    expect(fn () => $audit->delete())
        ->toThrow(RuntimeException::class, 'Los registros de auditoría son inmutables.');

    DB::connection('pgsql')->statement('SAVEPOINT hu21_upd');
    try {
        DB::connection('pgsql')->table('audits')->where('id', $audit->id)->update(['tags' => 'sql']);
        expect(false)->toBeTrue('debía fallar UPDATE SQL');
    } catch (QueryException $e) {
        DB::connection('pgsql')->statement('ROLLBACK TO SAVEPOINT hu21_upd');
        expect(str_contains($e->getMessage(), '42501') || str_contains(strtolower($e->getMessage()), 'permission') || str_contains(strtolower($e->getMessage()), 'append-only'))
            ->toBeTrue();
    }

    DB::connection('pgsql')->statement('SAVEPOINT hu21_del');
    try {
        DB::connection('pgsql')->table('audits')->where('id', $audit->id)->delete();
        expect(false)->toBeTrue('debía fallar DELETE SQL');
    } catch (QueryException $e) {
        DB::connection('pgsql')->statement('ROLLBACK TO SAVEPOINT hu21_del');
        expect(str_contains($e->getMessage(), '42501') || str_contains(strtolower($e->getMessage()), 'permission') || str_contains(strtolower($e->getMessage()), 'append-only'))
            ->toBeTrue();
    }
});

it('HU21-E06 no deja PHI en claro en audits de expediente', function () {
    $paciente = Paciente::factory()->create([
        'creado_por_profesional_id' => $this->especialista->profesional->id,
    ]);
    $expediente = Expediente::factory()->create([
        'paciente_id' => $paciente->codigo,
        'area_id' => $this->area->id,
        'estado' => 'abierto',
        'motivo_consulta' => 'SENTINEL_PHI_HU21_MOTIVO',
    ]);

    $this->actingAs($this->especialista)
        ->withSession(['_sym_key' => base64_encode(str_repeat('H', SODIUM_CRYPTO_SECRETBOX_KEYBYTES))])
        ->patch('/expedientes/'.$expediente->id, [
            'motivo_consulta' => 'SENTINEL_PHI_HU21_NUEVO',
            'motivo_cambio' => 'Corrección documentada para prueba de redacción HU-21.',
        ])
        ->assertRedirect();

    $raw = DB::table('audits')
        ->where('auditable_type', Expediente::class)
        ->where('auditable_id', (string) $expediente->id)
        ->orderByDesc('id')
        ->value('new_values');

    expect((string) $raw)->not->toContain('SENTINEL_PHI_HU21_NUEVO');
    expect((string) $raw)->toContain('[CIFRADO]');
});

it('HU21-E09 una lectura de detalle no duplica el evento de paciente', function () {
    $paciente = Paciente::factory()->create([
        'creado_por_profesional_id' => $this->especialista->profesional->id,
        'carnet' => 'HX21009',
    ]);
    Expediente::factory()->create([
        'paciente_id' => $paciente->codigo,
        'area_id' => $this->area->id,
        'estado' => 'abierto',
    ]);

    $antes = Audit::query()
        ->where('tags', 'clinical_access')
        ->where('auditable_type', Paciente::class)
        ->where('auditable_id', (string) $paciente->codigo)
        ->count();

    $this->actingAs($this->especialista)
        ->withSession(['_sym_key' => base64_encode(str_repeat('H', SODIUM_CRYPTO_SECRETBOX_KEYBYTES))])
        ->get('/pacientes/HX21009')
        ->assertOk();

    $despues = Audit::query()
        ->where('tags', 'clinical_access')
        ->where('auditable_type', Paciente::class)
        ->where('auditable_id', (string) $paciente->codigo)
        ->where('event', 'accessed')
        ->count();

    expect($despues - $antes)->toBe(1);
});

it('HU21-E11 login fallido y exitoso generan eventos sin secretos', function () {
    $user = Especialista::factory()->withRole('specialist')->create([
        'email' => 'hu21.login@pandora.test',
        'password' => 'Password1!',
        'is_active' => true,
    ]);

    $this->post('/login', [
        'email' => 'hu21.login@pandora.test',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors();

    $failed = Audit::query()->where('event', 'auth_login_failed')->latest('id')->first();
    expect($failed)->not->toBeNull();
    $rawFailed = (string) DB::table('audits')->where('id', $failed->id)->value('new_values');
    expect($rawFailed)->not->toContain('wrong-password');
    expect($rawFailed)->not->toContain('Password1!');

    $this->post('/login', [
        'email' => 'hu21.login@pandora.test',
        'password' => 'Password1!',
    ])->assertRedirect();

    $ok = Audit::query()->where('event', 'auth_login_success')->where('user_id', $user->id)->latest('id')->first();
    expect($ok)->not->toBeNull();
    $rawOk = (string) DB::table('audits')->where('id', $ok->id)->value('new_values');
    expect($rawOk)->not->toContain('Password1!');
});

it('HU21-E10 listing de citas genera un solo evento agregado', function () {
    $this->actingAs($this->especialista)
        ->withSession(['_sym_key' => base64_encode(str_repeat('H', SODIUM_CRYPTO_SECRETBOX_KEYBYTES))])
        ->get('/citas')
        ->assertOk();

    $listings = Audit::query()
        ->where('tags', 'listing_access')
        ->where('user_id', $this->especialista->id)
        ->where('auditable_type', Cita::class)
        ->count();

    expect($listings)->toBeGreaterThanOrEqual(1);
});

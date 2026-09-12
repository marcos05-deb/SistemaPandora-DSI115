<?php

declare(strict_types=1);

use App\Models\Area;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Models\Especialista;
use Illuminate\Support\Facades\DB;
use App\Services\Crypto\KeyDerivationService;



beforeEach(function () {
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
    session(['_sym_key' => str_repeat('a', 32)]);
    
    $this->area = Area::factory()->create(['nombre' => 'Area Especializada Test']);
    
    // Configurar key derivation salt
    $kdf = new KeyDerivationService();
    $salt = $kdf->generateSalt();
    
    $this->roleSpecialist = \App\Models\Role::firstOrCreate(['slug' => 'specialist'], [
        'nombre' => 'Specialist',
        'description' => 'Especialista',
        'is_active' => true,
    ]);

    // Crear Especialista
    $this->especialista = Especialista::factory()->create([
        'password' => 'P@ssw0rd1234',
        'kdf_salt' => $salt,
        'is_active' => true,
    ]);
    
    $this->especialista->roles()->attach($this->roleSpecialist->id);

    // Asignar area mediante Profesional
    $this->profesional = \App\Models\Profesional::factory()->create([
        'user_id' => $this->especialista->id,
        'area_id' => $this->area->id,
    ]);
    
    $this->paciente = Paciente::factory()->create();
    $this->expediente = Expediente::factory()->create([
        'paciente_id' => $this->paciente->codigo,
        'area_id' => $this->area->id,
        'estado' => 'abierto',
    ]);
});

test('los campos de consulta (incluyendo json) se almacenan cifrados y son ilegibles en la base de datos cruda', function () {
    // Arrange: Datos de la primera consulta
    $this->actingAs($this->especialista);
    $evaluacionInicial = [
        'apariencia_externa' => 'Paciente aliñado',
        'voz' => 'Tono normal',
        'patrones_habla' => 'Fluido, sin alteraciones',
        'expresiones_faciales' => 'Congruentes con el afecto',
        'ademanes' => 'Pocos, acordes',
        'actitudes_tratamiento' => 'Colaborador',
        'impresion' => 'Impresión clínica favorable',
        'plan_tratamiento' => 'Psicoterapia breve',
        'pronostico' => 'Favorable'
    ];
    
    $payload = [
        'motivo_consulta' => 'Sintomas de ansiedad aguda',
        'notas_clinicas' => 'El paciente reporta insomnio persistente',
        'diagnostico' => 'Ansiedad Generalizada',
        'plan_atencion' => 'Psicoterapia breve con seguimiento semanal',
        'tecnica_utilizada' => 'Entrevista Semiestructurada',
        'fecha_consulta' => now()->toDateString(),
        'evaluacion_inicial' => $evaluacionInicial,
    ];

    // Act: Enviar la peticion a la API
    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('consultas.store', $this->expediente->id), $payload);
    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    // Comprobacion 1: Eloquent descifra correctamente
    session(['_sym_key' => str_repeat('a', 32)]);
    $consulta = \App\Models\Consulta::first();
    expect($consulta->motivo_consulta)->toBe('Sintomas de ansiedad aguda')
        ->and($consulta->plan_atencion)->toBe('Psicoterapia breve con seguimiento semanal')
        ->and($consulta->evaluacion_inicial)->toBe($evaluacionInicial);

    // Comprobacion 2: DB cruda está cifrada
    $rawConsulta = DB::table('consultas')->where('id', $consulta->id)->first();
    
    // Verificamos que los textos originales no existen crudos
    expect($rawConsulta->motivo_consulta)->not->toContain('ansiedad aguda')
        ->and($rawConsulta->notas_clinicas)->not->toContain('insomnio')
        ->and($rawConsulta->diagnostico)->not->toContain('Ansiedad Generalizada')
        ->and($rawConsulta->plan_atencion)->not->toContain('seguimiento semanal')
        ->and($rawConsulta->tecnica_utilizada)->not->toContain('Semiestructurada')
        ->and($rawConsulta->evaluacion_inicial)->not->toContain('Paciente aliñado')
        ->and($rawConsulta->evaluacion_inicial)->not->toContain('Psicoterapia breve');
        
    // Verificamos que tienen aspecto de base64 (cifrado libsodium)
    expect(base64_decode($rawConsulta->evaluacion_inicial, true))->not->toBeFalse()
        ->and(base64_decode($rawConsulta->plan_atencion, true))->not->toBeFalse();
});

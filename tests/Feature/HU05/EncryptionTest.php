<?php

namespace Tests\Feature\HU05;

use App\Models\Especialista;
use App\Models\Paciente;
use App\Models\ContactoPaciente;
use App\Models\Expediente;
use App\Models\Area;
use App\Services\Crypto\EncryptionContextService;
use App\Exceptions\DecryptionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EncryptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
        $this->artisan('db:seed', ['--class' => 'AreaSeeder']);
    }

    public function test_encrypted_field_cast_encrypts_and_decrypts_data()
    {
        $area = Area::first();
        
        $userId = DB::table('users')->insertGetId([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $profesionalId = DB::table('profesionales')->insertGetId([
            'user_id' => $userId,
            'area_id' => $area->id,
            'especialidad' => 'Test Especialidad',
            'numero_registro' => '12345',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $facultadId = DB::table('facultades')->insertGetId([
            'nombre' => 'Test Facultad',
        ]);

        $carreraId = DB::table('carreras')->insertGetId([
            'facultad_id' => $facultadId,
            'nombre' => 'Test Carrera',
        ]);
        
        // Mock session key
        $key = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        session()->put('_sym_key', base64_encode($key));

        // Use standard model creation
        $paciente = Paciente::create([
            'carnet' => 'TEST-001',
            'carrera_id' => $carreraId,
            'creado_por_profesional_id' => $profesionalId,
            'fecha_nacimiento' => '1990-01-01',
            'sexo' => 'F',
            'estado_civil' => 'Soltero',
        ]);

        $this->assertEquals('1990-01-01', $paciente->fecha_nacimiento);

        // Verify it's encrypted in the DB
        $raw = DB::table('pacientes')->where('codigo', $paciente->codigo)->first();
        $this->assertNotEquals('1990-01-01', $raw->fecha_nacimiento);
        $this->assertTrue(base64_decode($raw->fecha_nacimiento, true) !== false);

        // Verify decryption throws exception if session key is missing
        session()->forget('_sym_key');
        
        $this->expectException(DecryptionException::class);
        $paciente->refresh();
        $dummy = $paciente->fecha_nacimiento;
    }
}

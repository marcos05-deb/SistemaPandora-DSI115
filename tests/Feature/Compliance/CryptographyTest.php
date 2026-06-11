<?php

namespace Tests\Feature\Compliance;

use App\Models\Especialista;
use App\Models\Paciente;
use App\Exceptions\DecryptionException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Illuminate\Support\Facades\Session;

#[Group('hipaa')]
#[Group('owasp-v6')]
class CryptographyTest extends ComplianceTestCase
{
    private $especialista;

    protected function setUp(): void
    {
        parent::setUp();
        // Creamos especialista con password 'password_segura' (definido en factory)
        $this->especialista = Especialista::factory()->withProfesional()->create([
            'must_change_password' => false
        ]);
    }

    public function test_derivacion_y_persistencia_de_llaves_con_purga()
    {
        if (config('session.driver') !== 'database') {
            $this->markTestSkipped('Este test requiere el driver de sesión database para interrogar la tabla de sesiones purgada.');
        }

        $response = $this->post('/login', [
            'email' => $this->especialista->email,
            'password' => 'password_segura',
        ]);
        
        $response->assertRedirect('/dashboard');
        
        $this->assertTrue(session()->has('_sym_key'));
        $sessionId = Session::getId();

        $paciente = Paciente::factory()->create([
            'nombre_completo' => 'Maria Fernandez',
            'creado_por_profesional_id' => $this->especialista->profesional->id,
        ]);

        $this->assertEquals('Maria Fernandez', $paciente->nombre_completo);

        // Hacemos Logout
        $this->post('/logout');
        
        $this->assertFalse(session()->has('_sym_key'));

        // Verificamos que el registro de sesión fue purgado/invalidado en DB
        $sessionDb = DB::table('sessions')->where('id', $sessionId)->first();
        $this->assertNull($sessionDb, 'El ID de sesión no fue destruido en la base de datos tras el logout.');

        // Intentamos leer el paciente desencriptándolo sin llave
        $pacienteRefresh = Paciente::query()->where('codigo', $paciente->codigo)->first();
        
        try {
            $nombre = $pacienteRefresh->nombre_completo; // Esta propiedad dispara el Cast
            $this->fail('La propiedad nombre_completo debió arrojar DecryptionException al no haber llave.');
        } catch (\Exception $e) {
            $this->assertInstanceOf(DecryptionException::class, $e, 'La excepción debe ser estrictamente de DecryptionException (Fallo matemático).');
        }
    }

    public function test_asercion_de_cifrado_en_reposo()
    {
        $this->post('/login', [
            'email' => $this->especialista->email,
            'password' => 'password_segura',
        ]);

        $paciente = Paciente::factory()->create([
            'nombre_completo' => 'Juan Perez',
            'creado_por_profesional_id' => $this->especialista->profesional->id,
        ]);

        $rawData = DB::selectOne('SELECT nombre_completo FROM pacientes WHERE codigo = ?', [$paciente->codigo]);

        $this->assertNotEquals('Juan Perez', $rawData->nombre_completo);
        $this->assertStringNotContainsString('Juan Perez', $rawData->nombre_completo);
        
        // Verificación estructural Sodium (Base64 válido + longitud mayor a Nonce de 24 bytes)
        $decoded = base64_decode($rawData->nombre_completo, true);
        $this->assertNotFalse($decoded, 'El payload no es Base64 estricto válido.');
        $this->assertGreaterThan(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, strlen($decoded), 'El payload decodificado no tiene tamaño suficiente para contener el Nonce de 24 bytes.');

        // Intentamos descifrar matemáticamente con una llave artificial
        $fakeKey = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $fakeKey);
        $this->assertFalse($plaintext, 'El payload pudo descifrarse con una llave falsa.');
    }

    public function test_rotacion_obligatoria_otp()
    {
        $especialistaOtp = Especialista::factory()->create([
            'must_change_password' => true,
        ]);

        $this->post('/login', [
            'email' => $especialistaOtp->email,
            'password' => 'password_segura',
        ]);

        // Intentar consumir un endpoint clínico
        $response = $this->get('/pacientes');
        
        $response->assertStatus(302);
        $this->assertStringContainsString('password', $response->headers->get('Location') ?? '');
    }
}

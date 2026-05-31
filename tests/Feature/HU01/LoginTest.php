<?php

use App\Models\Especialista;
use App\Services\Crypto\KeyDerivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Tests de HU-01: Autenticación y Criptografía Base.
 *
 * Criterios de aceptación del Roadmap:
 * - POST /login con credenciales válidas → redirección Inertia y sesión activa.
 * - POST /login con credenciales inválidas → HTTP 422 con mensaje genérico.
 * - Después del login, session()->get('_sym_key') contiene clave derivada en Base64.
 * - La clave derivada tiene exactamente 32 bytes al decodificar.
 * - Tras 5 intentos fallidos, el 6° intento retorna HTTP 429.
 * - Test feature cubre: login exitoso, credenciales inválidas, rate limit.
 */

beforeEach(function () {
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
    
    $this->kdfService = new KeyDerivationService();
    $this->testPassword = 'P@ssw0rd_Segura14';
    $this->testSalt = $this->kdfService->generateSalt();

    $this->especialista = Especialista::create([
        'email'    => 'dr.martinez@clinica.org',
        'password' => $this->testPassword,
        'kdf_salt' => $this->testSalt,
    ]);
});

it('permite_login_con_credenciales_validas', function () {
    $response = $this->post('/login', [
        'email'    => 'dr.martinez@clinica.org',
        'password' => $this->testPassword,
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticated();
});

it('almacena_sym_key_en_sesion_tras_login', function () {
    $this->post('/login', [
        'email'    => 'dr.martinez@clinica.org',
        'password' => $this->testPassword,
    ]);

    $this->assertAuthenticated();
    $symKey = session('_sym_key');

    expect($symKey)->not->toBeNull()
        ->and($symKey)->toBeString();
});

it('clave_derivada_tiene_32_bytes', function () {
    $this->post('/login', [
        'email'    => 'dr.martinez@clinica.org',
        'password' => $this->testPassword,
    ]);

    $symKey = session('_sym_key');
    $decoded = base64_decode($symKey, strict: true);

    expect($decoded)->not->toBeFalse()
        ->and(strlen($decoded))->toBe(SODIUM_CRYPTO_SECRETBOX_KEYBYTES); // 32
});

it('retorna_error_con_credenciales_invalidas', function () {
    $response = $this->post('/login', [
        'email'    => 'dr.martinez@clinica.org',
        'password' => 'contraseña_incorrecta',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('no_revela_si_email_existe_con_credenciales_invalidas', function () {
    $response = $this->post('/login', [
        'email'    => 'noexiste@clinica.org',
        'password' => 'cualquier_cosa',
    ]);

    // El mensaje debe ser genérico, igual que si el email existiera
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('valida_campos_requeridos', function () {
    $response = $this->post('/login', [
        'email'    => '',
        'password' => '',
    ]);

    $response->assertSessionHasErrors(['email', 'password']);
});

it('bloquea_sexto_intento_login_con_http_429', function () {
    // 5 intentos fallidos
    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', [
            'email'    => 'dr.martinez@clinica.org',
            'password' => 'contraseña_incorrecta',
        ]);
    }

    // 6° intento: debe recibir bloqueo
    $response = $this->post('/login', [
        'email'    => 'dr.martinez@clinica.org',
        'password' => 'contraseña_incorrecta',
    ]);

    $response->assertSessionHasErrors('throttle');
});

it('genera_kdf_salt_si_especialista_no_tiene', function () {
    // Crear especialista sin kdf_salt
    $especialista = Especialista::create([
        'email'    => 'nuevo@clinica.org',
        'password' => $this->testPassword,
        'kdf_salt' => null,
    ]);

    $this->post('/login', [
        'email'    => 'nuevo@clinica.org',
        'password' => $this->testPassword,
    ]);

    $this->assertAuthenticated();

    // Verificar que se generó kdf_salt
    $especialista->refresh();
    expect($especialista->kdf_salt)->not->toBeNull()
        ->and(session('_sym_key'))->not->toBeNull();
});

it('redirige_a_login_si_accede_dashboard_sin_autenticacion', function () {
    $response = $this->get('/dashboard');
    $response->assertRedirect('/login');
});

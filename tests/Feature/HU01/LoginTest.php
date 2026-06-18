<?php

use App\Models\Especialista;
use App\Services\Crypto\KeyDerivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Tests de HU-01: Autenticación JWT y Criptografía Base.
 *
 * Criterios de aceptación:
 * - POST /login con credenciales válidas → redirección y cookie JWT (pandora_token).
 * - POST /login con credenciales inválidas → HTTP 422 con mensaje genérico.
 * - El token JWT expira en 8 horas (480 min).
 * - Después del login, session()->get('_sym_key') contiene clave derivada en Base64.
 * - La clave derivada tiene exactamente 32 bytes al decodificar.
 * - Bloqueo de cuenta tras 3 intentos fallidos (15 min).
 * - Tras 3 intentos fallidos, el 4° intento bloquea la cuenta.
 * - Login exitoso resetea el contador de intentos fallidos.
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

it('emite_cookie_jwt_tras_login_exitoso', function () {
    $response = $this->post('/login', [
        'email'    => 'dr.martinez@clinica.org',
        'password' => $this->testPassword,
    ]);

    $response->assertRedirect('/dashboard');
    $response->assertCookie('pandora_token');
    $this->assertAuthenticated();
});

it('cookie_jwt_tiene_expiracion_de_8_horas', function () {
    $response = $this->post('/login', [
        'email'    => 'dr.martinez@clinica.org',
        'password' => $this->testPassword,
    ]);

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($c) => $c->getName() === 'pandora_token');

    expect($cookie)->not->toBeNull();
    $expiresInMinutes = ($cookie->getExpiresTime() - time()) / 60;
    expect($expiresInMinutes)->toBeGreaterThan(475);
    expect($expiresInMinutes)->toBeLessThan(485);
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

it('bloquea_cuenta_tras_3_intentos_fallidos', function () {
    for ($i = 0; $i < 3; $i++) {
        $this->post('/login', [
            'email'    => 'dr.martinez@clinica.org',
            'password' => 'contraseña_incorrecta',
        ]);
    }

    $this->especialista->refresh();
    expect($this->especialista->locked_until)->not->toBeNull();

    $response = $this->withHeaders(['Referer' => '/login'])
        ->post('/login', [
            'email'    => 'dr.martinez@clinica.org',
            'password' => $this->testPassword,
        ]);

    $response->assertRedirect('/login');
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('login_exitoso_resetea_intentos_fallidos', function () {
    $this->post('/login', [
        'email'    => 'dr.martinez@clinica.org',
        'password' => 'contraseña_incorrecta',
    ]);

    $this->especialista->refresh();
    expect($this->especialista->failed_login_attempts)->toBe(1);

    $this->post('/login', [
        'email'    => 'dr.martinez@clinica.org',
        'password' => $this->testPassword,
    ]);

    $this->especialista->refresh();
    expect($this->especialista->failed_login_attempts)->toBe(0);
    expect($this->especialista->locked_until)->toBeNull();
});

it('genera_kdf_salt_si_especialista_no_tiene', function () {
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

    $especialista->refresh();
    expect($especialista->kdf_salt)->not->toBeNull()
        ->and(session('_sym_key'))->not->toBeNull();
});

it('redirige_a_login_si_accede_dashboard_sin_autenticacion', function () {
    $response = $this->get('/dashboard');
    $response->assertRedirect('/login');
});

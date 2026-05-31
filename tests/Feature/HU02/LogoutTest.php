<?php

use App\Models\Especialista;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Tests de HU-02: Cierre de Sesión.
 *
 * Criterios de aceptación del Roadmap:
 * - POST /logout retorna redirección a /login.
 * - Tras el logout, cualquier request autenticado retorna redirección a /login.
 * - session()->has('_sym_key') retorna false tras el logout.
 * - Test feature cubre: logout exitoso, request post-logout rechazado.
 */

beforeEach(function () {
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
    
    $this->testPassword = 'P@ssw0rd_Segura14';
    $this->especialista = Especialista::create([
        'email'    => 'dr.logout@clinica.org',
        'password' => $this->testPassword,
    ]);
});

it('destruye_la_sesion_y_redirige_a_login', function () {
    $this->actingAs($this->especialista);
    
    // Simulamos que la clave simétrica está en sesión
    session(['_sym_key' => base64_encode('fake_sym_key_1234567890123456789')]);

    $response = $this->post('/logout');

    $response->assertRedirect('/login');
    $this->assertGuest();
    expect(session()->has('_sym_key'))->toBeFalse();
});

it('rechaza_peticion_autenticada_despues_de_logout', function () {
    $this->actingAs($this->especialista);
    
    // Primero hacemos logout
    $this->post('/logout');

    // Intentamos acceder a ruta protegida
    $response = $this->get('/dashboard');
    $response->assertRedirect('/login');
});

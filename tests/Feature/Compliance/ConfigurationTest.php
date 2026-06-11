<?php

namespace Tests\Feature\Compliance;

use App\Models\Especialista;
use PHPUnit\Framework\Attributes\Group;

#[Group('owasp-v14')]
class ConfigurationTest extends ComplianceTestCase
{
    private $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Especialista::factory()->withRole('sysadmin')->create();
    }

    public function test_rate_limiting_estricto()
    {
        if (config('cache.default') === 'array') {
            $this->markTestSkipped('Requiere cache persistente (Redis/Database) para que el Rate Limiter cuente entre peticiones en pruebas.');
        }

        // El límite suele ser 5 para login según Laravel Breeze/Fortify
        // Vamos a probar la limitación global o de login con el número exacto
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/login', ['email' => 'nonexistent@test.com', 'password' => 'wrong']);
            $response->assertStatus(302);
        }

        $response = $this->post('/login', ['email' => 'nonexistent@test.com', 'password' => 'wrong']);
        $response->assertStatus(429);
    }

    public function test_xss_y_csp_en_produccion()
    {
        // Fuerza entorno de producción para habilitar encabezados estrictos si aplica
        config()->set('app.env', 'production');

        $response = $this->get('/login');
        
        $cspHeader = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($cspHeader, 'Falta el encabezado Content-Security-Policy');

        $this->assertStringNotContainsString("unsafe-inline", $cspHeader, 'La política CSP en producción contiene unsafe-inline.');
        $this->assertStringContainsString("object-src 'none'", $cspHeader, 'Falta restricción estricta object-src.');
        $this->assertStringContainsString("frame-ancestors 'none'", $cspHeader, 'Falta protección contra clickjacking (frame-ancestors).');
    }

    public function test_ausencia_de_contrasenas_flash()
    {
        $this->actingAs($this->admin);

        // Simulamos la creación de un usuario. Ajusta la URL a la real de tu sistema.
        $response = $this->post('/admin/users', [
            'nombre' => 'Test User',
            'email' => 'test_flash@test.com',
            'role_id' => 1,
            'area_id' => 1,
        ]);

        // Verificamos que la petición tuvo éxito (sea 201 o 302 redirección)
        $this->assertTrue(in_array($response->status(), [201, 302]), 'El endpoint devolvió un error (falso positivo). Status: ' . $response->status());

        // Verificar explícitamente que no se filtran secretos en sesión
        $this->assertFalse(session()->has('generated_password'));
        $this->assertFalse(session()->has('password'));
    }

}

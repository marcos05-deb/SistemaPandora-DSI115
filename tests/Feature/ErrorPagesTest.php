<?php

namespace Tests\Feature;

use App\Models\Especialista;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_ruta_inexistente_muestra_pagina_404_de_pandora(): void
    {
        $this->get('/ruta-que-no-existe-pandora-xyz')
            ->assertStatus(404)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', 404)
            );
    }

    public function test_acceso_admin_sin_permiso_muestra_pagina_403(): void
    {
        $user = Especialista::factory()->create([
            'email' => 'especialista-error-page@pandora.com',
        ]);
        $user->roles()->attach(Role::firstOrCreate(
            ['slug' => 'specialist'],
            ['nombre' => 'Especialista', 'nivel' => 10]
        ));

        $this->actingAs($user)
            ->get('/admin/users')
            ->assertStatus(403)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', 403)
            );
    }

    public function test_respuesta_json_no_usa_pagina_inertia(): void
    {
        $this->getJson('/ruta-que-no-existe-pandora-xyz')
            ->assertStatus(404)
            ->assertJsonMissing(['component' => 'Error']);
    }
}

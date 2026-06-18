<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Especialista;
use App\Models\Profesional;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProfesionalFactory extends Factory
{
    protected $model = Profesional::class;

    public function definition(): array
    {
        return [
            // Generalmente user_id y area_id se sobreescriben al llamar desde EspecialistaFactory
            // Pero proveemos defaults para que pueda instanciarse independientemente
            'user_id' => Especialista::factory(),
            'area_id' => Area::factory(),
            'especialidad' => $this->faker->jobTitle(),
            'numero_registro' => $this->faker->numerify('JVPM-####'),
        ];
    }
}

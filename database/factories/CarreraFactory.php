<?php

namespace Database\Factories;

use App\Models\Carrera;
use App\Models\Facultad;
use Illuminate\Database\Eloquent\Factories\Factory;

class CarreraFactory extends Factory
{
    protected $model = Carrera::class;

    public function definition(): array
    {
        return [
            'nombre' => 'Carrera de ' . $this->faker->jobTitle(),
            'facultad_id' => Facultad::factory(),
        ];
    }
}

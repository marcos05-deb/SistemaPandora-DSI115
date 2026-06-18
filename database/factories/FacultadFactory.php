<?php

namespace Database\Factories;

use App\Models\Facultad;
use Illuminate\Database\Eloquent\Factories\Factory;

class FacultadFactory extends Factory
{
    protected $model = Facultad::class;

    public function definition(): array
    {
        return [
            'nombre' => 'Facultad de ' . $this->faker->jobTitle(),
        ];
    }
}

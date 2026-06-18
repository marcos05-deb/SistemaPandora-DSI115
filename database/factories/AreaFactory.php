<?php

namespace Database\Factories;

use App\Models\Area;
use Illuminate\Database\Eloquent\Factories\Factory;

class AreaFactory extends Factory
{
    protected $model = Area::class;

    public function definition(): array
    {
        return [
            'nombre' => $this->faker->unique()->word() . ' Clínica',
            'requiere_aprobacion_estricta' => false,
        ];
    }
}

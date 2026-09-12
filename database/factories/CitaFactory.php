<?php

namespace Database\Factories;

use App\Models\Cita;
use App\Models\Expediente;
use App\Models\Profesional;
use App\Models\Area;
use Illuminate\Database\Eloquent\Factories\Factory;

class CitaFactory extends Factory
{
    protected $model = Cita::class;

    public function definition(): array
    {
        return [
            'expediente_id' => null, // Deberá pasarse explícitamente en el create
            'profesional_id' => null,
            'area_id' => null,
            'fecha_hora' => $this->faker->dateTimeBetween('now', '+1 month'),
            'estado' => 'programada',
            'motivo' => $this->faker->sentence(),
        ];
    }
}

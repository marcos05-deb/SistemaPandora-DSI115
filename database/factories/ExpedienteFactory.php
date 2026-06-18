<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Expediente;
use App\Models\Paciente;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpedienteFactory extends Factory
{
    protected $model = Expediente::class;

    public function definition(): array
    {
        return [
            'paciente_id' => Paciente::factory(),
            'area_id' => Area::factory(),
            'motivo_consulta' => $this->faker->sentence(),
            'notas_clinicas' => $this->faker->paragraph(),
            'diagnostico' => $this->faker->sentence(),
        ];
    }
}

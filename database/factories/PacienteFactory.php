<?php

namespace Database\Factories;

use App\Models\Carrera;
use App\Models\Paciente;
use App\Models\Profesional;
use Illuminate\Database\Eloquent\Factories\Factory;

class PacienteFactory extends Factory
{
    protected $model = Paciente::class;

    public function definition(): array
    {
        return [
            'carnet' => strtoupper($this->faker->regexify('[A-Z]{2}[0-9]{5}')),
            'carrera_id' => Carrera::factory(),
            'creado_por_profesional_id' => Profesional::factory(),
            'sexo' => $this->faker->randomElement(['M', 'F']),
            'estado_civil' => $this->faker->randomElement(['Soltero', 'Casado', 'Divorciado']),
            'nombre_completo' => $this->faker->name(),
            'direccion' => $this->faker->address(),
            'fecha_nacimiento' => $this->faker->date('Y-m-d', '-18 years'),
            'profesion_ocupacion' => 'Estudiante',
            'fecha_primera_consulta' => now()->format('Y-m-d'),
            'referido_por' => null,
            'llevado_por' => null,
            'motivo_consulta' => $this->faker->sentence(),
        ];
    }
}

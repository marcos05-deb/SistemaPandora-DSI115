<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Facultad;
use App\Models\Carrera;

class FacultadCarreraSeeder extends Seeder
{
    public function run(): void
    {
        $facultades = [
            'Facultad de Ciencias Agronomicas',
            'Facultad de Ciencias Economicas',
            'Facultad de Ciencias Naturales y Matematica',
            'Facultad de Ciencias y Humanidades',
            'Facultad de Ingenieria y Arquitectura',
            'Facultad de Jurisprudencia y Ciencias Sociales',
            'Facultad de Medicina',
            'Facultad de Odontologia',
            'Facultad de Quimica y Farmacia',
            'Facultad Multidisciplinaria de Occidente',
            'Facultad Multidisciplinaria Oriental',
            'Facultad Multidisciplinaria Paracentral',
            'Oficinas Centrales',
            'Institucion Externa',
            'Otra / No especificada',
        ];

        foreach ($facultades as $nombre) {
            Facultad::firstOrCreate(['nombre' => $nombre]);
        }

        $carreras = [
            'Facultad de Ingenieria y Arquitectura' => [
                'Arquitectura',
                'Ingenieria Civil',
                'Ingenieria de Sistemas Informaticos',
                'Ingenieria Electrica',
                'Ingenieria Industrial',
                'Ingenieria Mecanica',
                'Ingenieria Quimica e Ingenieria de Alimentos',
                'Unidad de Ciencias Basicas',
                'Unidad de Planificacion',
            ],
            'Facultad de Medicina' => [
                'Escuela de Ciencias de la Salud',
                'Escuela de Medicina',
                'Escuela de Postgrado',
                'Administracion Academica',
            ],
            'Oficinas Centrales' => [
                'Secretaria de Oficinas Centrales',
            ],
            'Institucion Externa' => [
                'Instituto Tecnologico de Costa Rica',
                'Universidad Nacional de Costa Rica',
                'Universidad de San Carlos de Guatemala',
                'Universidad Nacional de Ingenieria de Nicaragua',
                'Universidad Nacional de Nicaragua',
                'Universidad Tecnologica Centroamericana',
                'Universidad Nacional Autonoma de Nicaragua',
            ],
            'Otra / No especificada' => [
                'General / No especificada',
            ],
        ];

        foreach ($carreras as $facultadNombre => $nombresCarreras) {
            $facultad = Facultad::where('nombre', $facultadNombre)->first();

            if (!$facultad) {
                continue;
            }

            foreach ($nombresCarreras as $nombre) {
                Carrera::firstOrCreate([
                    'facultad_id' => $facultad->id,
                    'nombre' => $nombre,
                ]);
            }
        }
    }
}

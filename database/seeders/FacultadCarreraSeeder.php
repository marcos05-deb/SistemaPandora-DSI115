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
            'Facultad de Ciencias Agronómicas',
            'Facultad de Ciencias Económicas',
            'Facultad de Ciencias Naturales y Matemática',
            'Facultad de Ciencias y Humanidades',
            'Facultad de Ingeniería y Arquitectura',
            'Facultad de Jurisprudencia y Ciencias Sociales',
            'Facultad de Medicina',
            'Facultad de Odontología',
            'Facultad de Química y Farmacia',
            'Facultad Multidisciplinaria de Occidente',
            'Facultad Multidisciplinaria Oriental',
            'Facultad Multidisciplinaria Paracentral',
            'Oficinas Centrales',
            'Institución Externa',
            'Otra / No especificada',
        ];

        foreach ($facultades as $nombre) {
            Facultad::firstOrCreate(['nombre' => $nombre]);
        }

        $carreras = [
            'Facultad de Ciencias Agronómicas' => [
                'Ingeniería Agronómica',
                'Ingeniería Agroindustrial',
                'Licenciatura en Medicina Veterinaria y Zootecnia',
                'Ingeniería Geológica',
                'Ingeniería Agroindustrial — modalidad semipresencial',
            ],
            'Facultad de Ciencias Económicas' => [
                'Licenciatura en Economía',
                'Licenciatura en Contaduría Pública',
                'Licenciatura en Administración de Empresas',
                'Licenciatura en Mercadeo Internacional',
                'Licenciatura en Mercadeo Internacional — modalidad en línea',
            ],
            'Facultad de Ciencias Naturales y Matemática' => [
                'Licenciatura en Geofísica',
                'Licenciatura en Matemática',
                'Licenciatura en Estadística y Ciencia de Datos',
                'Licenciatura en Ciencias Químicas',
                'Licenciatura en Física',
                'Licenciatura en Biología',
                'Licenciatura en Biología Marina',
                'Profesorado en Matemática para Tercer Ciclo de Educación Básica y Educación Media',
                'Licenciatura en Enseñanza de la Matemática — modalidad a distancia',
                'Licenciatura en Enseñanza de las Ciencias Naturales — modalidad a distancia',
            ],
            'Facultad de Ciencias y Humanidades' => [
                'Licenciatura en Filosofía',
                'Licenciatura en Sociología',
                'Licenciatura en Antropología Sociocultural',
                'Licenciatura en Historia',
                'Licenciatura en Trabajo Social',
                'Licenciatura en Diseño Digital y Multimedia',
                'Licenciatura en Diseño Publicitario e Ilustración',
                'Licenciatura en Dibujo y Pintura',
                'Licenciatura en Cerámica y Escultura',
                'Licenciatura en Lenguas Modernas, especialidad en la Enseñanza del Inglés y Francés',
                'Licenciatura en Psicología',
                'Licenciatura en Letras',
                'Licenciatura en Biblioteconomía y Gestión de la Información',
                'Técnico en Bibliotecología',
                'Licenciatura en Periodismo',
                'Profesorado en Lenguaje y Literatura para Tercer Ciclo de Educación Básica y Educación Media',
                'Licenciatura en Enseñanza del Inglés — modalidad a distancia',
                'Licenciatura en Educación con especialidad en Administración y Gestión Educativa — modalidad semipresencial',
                'Licenciatura en Educación con especialidad en Entornos Virtuales para el Aprendizaje — modalidad semipresencial',
                'Licenciatura en Ciencias Aplicadas al Deporte, Educación Física y Recreación — modalidad semipresencial',
            ],
            'Facultad de Ingeniería y Arquitectura' => [
                'Arquitectura',
                'Ingeniería Civil',
                'Ingeniería Industrial',
                'Ingeniería Mecánica',
                'Ingeniería Eléctrica',
                'Ingeniería Química',
                'Ingeniería de Alimentos',
                'Ingeniería de Sistemas Informáticos',
                'Ingeniería Industrial — modalidad en línea',
                'Ingeniería de Sistemas Informáticos — modalidad en línea',
            ],
            'Facultad de Jurisprudencia y Ciencias Sociales' => [
                'Licenciatura en Ciencias Jurídicas',
                'Licenciatura en Relaciones Internacionales',
                'Licenciatura en Ciencia Política',
            ],
            'Facultad de Medicina' => [
                'Doctorado en Medicina',
                'Licenciatura en Laboratorio Clínico',
                'Licenciatura en Radiología e Imágenes',
                'Licenciatura en Nutrición',
                'Licenciatura en Anestesiología y Terapia Respiratoria',
                'Licenciatura en Educación para la Salud',
                'Licenciatura en Salud Materno Infantil',
                'Licenciatura en Fisioterapia',
                'Licenciatura en Terapia Ocupacional',
                'Licenciatura en Salud Ambiental',
                'Licenciatura en Optometría',
                'Licenciatura en Enfermería',
            ],
            'Facultad de Odontología' => [
                'Doctorado en Cirugía Dental',
            ],
            'Facultad de Química y Farmacia' => [
                'Licenciatura en Química y Farmacia',
                'Técnico en Farmacia Asistencial',
            ],
            'Facultad Multidisciplinaria de Occidente' => [
                'Arquitectura',
                'Doctorado en Medicina',
                'Licenciatura en Anestesiología y Medicina Perioperatoria — modalidad semipresencial',
                'Ingeniería Civil',
                'Ingeniería Industrial',
                'Ingeniería de Sistemas Informáticos',
                'Ingeniería en Desarrollo de Software — modalidad en línea',
                'Licenciatura en Ciencias Jurídicas',
                'Licenciatura en Sociología',
                'Licenciatura en Psicología',
                'Licenciatura en Idioma Inglés, opción Enseñanza',
                'Licenciatura en Ciencias del Lenguaje y Literatura',
                'Licenciatura en Educación con especialidad en Administración y Gestión Educativa',
                'Licenciatura en Química y Farmacia',
                'Licenciatura en Contaduría Pública',
                'Licenciatura en Administración de Empresas',
                'Licenciatura en Mercadeo Internacional',
                'Licenciatura en Geofísica',
                'Licenciatura en Biología',
                'Licenciatura en Estadística y Ciencia de Datos',
                'Licenciatura en Estadística',
                'Licenciatura en Ciencias Químicas',
                'Profesorado en Matemática para Tercer Ciclo de Educación Básica y Educación Media',
            ],
            'Facultad Multidisciplinaria Oriental' => [
                'Arquitectura',
                'Doctorado en Medicina',
                'Ingeniería Agronómica',
                'Ingeniería Civil',
                'Ingeniería Industrial',
                'Ingeniería Mecánica',
                'Ingeniería Eléctrica',
                'Ingeniería de Sistemas Informáticos',
                'Licenciatura en Laboratorio Clínico',
                'Licenciatura en Fisioterapia',
                'Licenciatura en Ciencias Jurídicas',
                'Licenciatura en Educación Inicial y Parvularia',
                'Licenciatura en Psicología',
                'Licenciatura en Trabajo Social',
                'Licenciatura en Letras',
                'Licenciatura en Idiomas Francés e Inglés',
                'Licenciatura en Química y Farmacia',
                'Licenciatura en Economía',
                'Licenciatura en Contaduría Pública',
                'Licenciatura en Administración de Empresas',
                'Licenciatura en Mercadeo Internacional',
                'Licenciatura en Logística Comercial Internacional — modalidad en línea',
                'Licenciatura en Matemática',
                'Licenciatura en Ciencias Químicas',
                'Licenciatura en Física',
                'Licenciatura en Biología',
                'Profesorado en Educación Inicial y Parvularia',
                'Profesorado en Idioma Inglés para Tercer Ciclo de Educación Básica y Educación Media',
                'Profesorado en Ciencias Sociales para Tercer Ciclo de Educación Básica y Educación Media',
                'Profesorado en Matemática para Tercer Ciclo de Educación Básica y Educación Media',
                'Profesorado en Biología para Tercer Ciclo de Educación Básica y Educación Media',
                'Profesorado en Física para Tercer Ciclo de Educación Básica y Educación Media',
                'Profesorado en Química para Tercer Ciclo de Educación Básica y Educación Media',
                'Técnico en Veterinaria y Zootecnia',
                'Técnico en Agricultura Sostenible',
                'Técnico en Turismo Ecológico y Cultural',
                'Técnico en Gestión del Desarrollo Territorial',
            ],
            'Facultad Multidisciplinaria Paracentral' => [
                'Ingeniería Agronómica',
                'Ingeniería Agroindustrial',
                'Ingeniería de Sistemas Informáticos',
                'Licenciatura en Trabajo Social',
                'Licenciatura en Contaduría Pública',
                'Licenciatura en Mercadeo Internacional',
                'Licenciatura en Administración de Empresas',
                'Licenciatura en Enseñanza de Idiomas Extranjeros, especialidad Inglés-Francés',
                'Profesorado en Educación Inicial y Parvularia',
                'Profesorado en Matemática para Tercer Ciclo de Educación Básica y Educación Media',
                'Profesorado en Idioma Inglés para Tercer Ciclo de Educación Básica y Educación Media',
            ],
            'Oficinas Centrales' => [
                'Secretaría de Oficinas Centrales',
            ],
            'Institución Externa' => [
                'Instituto Tecnológico de Costa Rica',
                'Universidad Nacional de Costa Rica',
                'Universidad de San Carlos de Guatemala',
                'Universidad Nacional de Ingeniería de Nicaragua',
                'Universidad Nacional de Nicaragua',
                'Universidad Tecnológica Centroamericana',
                'Universidad Nacional Autónoma de Nicaragua',
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

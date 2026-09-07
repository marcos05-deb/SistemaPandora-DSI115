<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Carrera;
use App\Models\ContactoPaciente;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\Consulta;
use Illuminate\Database\Seeder;

class ExpedienteSeeder extends Seeder
{

    public function run(): void
    {
        if (Expediente::count() > 0) {
            $this->command?->info('Expedientes ya existen. Saltando ExpedienteSeeder.');
            return;
        }

        $areas = [
            'psicologia'       => Area::where('nombre', 'Psicología')->first(),
            'medicina_general' => Area::where('nombre', 'Medicina General')->first(),
            'nutricion'        => Area::where('nombre', 'Nutrición')->first(),
            'trabajo_social'   => Area::where('nombre', 'Trabajo Social')->first(),
        ];

        $profByEmail = [];
        foreach (Profesional::with('especialista')->get() as $prof) {
            $profByEmail[$prof->especialista->email] = $prof;
        }

        $psicosocial = $profByEmail['psicosocial@pandora.com'] ?? null;
        $especialista = $profByEmail['especialista@pandora.com'] ?? null;
        $especialistaNutri = $profByEmail['especialista-nutri@pandora.com'] ?? null;

        if (!$psicosocial || !$especialista || !$especialistaNutri) {
            $this->command?->warn('Faltan profesionales. Ejecute DatabaseSeeder primero.');
            return;
        }

        // Mapa: area_key => profesional creador del paciente
        $creadorPorArea = [
            'psicologia'       => $psicosocial,
            'medicina_general' => $especialista,
            'nutricion'        => $especialistaNutri,
            'trabajo_social'   => $psicosocial,
        ];

        $pacientes = [
            [
                'carnet' => 'MT20045',
                'carrera_nombre' => 'Ingeniería de Sistemas Informáticos',
                'facultad_nombre' => 'Facultad de Ingeniería y Arquitectura',
                'sexo' => 'M',
                'estado_civil' => 'Soltero',
                'nombre_completo' => 'Carlos Ernesto Martínez Hernández',
                'direccion' => 'Colonia San Benito, Calle Los Pinos #42, San Salvador',
                'fecha_nacimiento' => '2006-03-15',
                'profesion_ocupacion' => 'Estudiante',
                'fecha_primera_consulta' => '2026-02-10',
                'motivo_consulta' => 'Desde hace dos meses presenta dificultad para concentrarse en clases, palpitaciones antes de exámenes, insomnio ocasional y sensación de ahogo. Refiere que los síntomas iniciaron tras recibir bajas calificaciones en el ciclo anterior.',
                'contactos' => [
                    ['nombre_completo' => 'Roberto Martínez', 'parentesco' => 'Padre', 'telefono_personal' => '7855-1234', 'telefono_casa' => '2234-5678', 'direccion' => 'Colonia San Benito, Calle Los Pinos #42, San Salvador', 'es_responsable' => false],
                    ['nombre_completo' => 'Carmen Hernández de Martínez', 'parentesco' => 'Madre', 'telefono_personal' => '7988-4321', 'direccion' => 'Colonia San Benito, Calle Los Pinos #42, San Salvador', 'es_responsable' => true],
                ],
                'expediente' => [
                    'area_key' => 'psicologia',
                    'motivo_consulta' => 'Ansiedad por rendimiento académico de 2 meses de evolución',
                    'notas_clinicas' => 'Paciente masculino de 20 años, estudiante de tercer año de Ingeniería en Sistemas Informáticos. Se presenta a consulta por cuadro de ansiedad de dos meses de evolución. Refiere factores estresores académicos: carga excesiva de tareas, presión por mantener beca y temor a decepcionar a sus padres. Escala de Hamilton para Ansiedad (HAM-A): 18 puntos (ansiedad moderada). Signos vitales normales: FC 78 lpm, TA 110/70 mmHg, FR 16 rpm. Se observa inquietud psicomotora leve durante la consulta. No presenta ideación suicida. Se recomienda terapia cognitivo-conductual semanal, técnicas de respiración diafragmática y seguimiento en 15 días.',
                    'diagnostico' => 'Trastorno de ansiedad generalizada (F41.1)',
                ],
            ],
            [
                'carnet' => 'FL22067',
                'carrera_nombre' => 'Licenciatura en Psicología',
                'facultad_nombre' => 'Facultad de Ciencias y Humanidades',
                'sexo' => 'F',
                'estado_civil' => 'Soltero',
                'nombre_completo' => 'Andrea Michelle Flores Ramírez',
                'direccion' => 'Residencial Altavista, Pasaje 3, Casa 12B, Mejicanos, San Salvador',
                'fecha_nacimiento' => '2004-07-22',
                'profesion_ocupacion' => 'Estudiante',
                'fecha_primera_consulta' => '2026-01-15',
                'motivo_consulta' => 'Cefalea pulsátil unilateral derecha de 6 meses de evolución, acompañada de fotofobia y náuseas. Episodios de 4 a 6 horas de duración, 2 a 3 veces por semana. Empeora con el estrés académico en época de exámenes.',
                'contactos' => [
                    ['nombre_completo' => 'Francisco Flores', 'parentesco' => 'Padre', 'telefono_personal' => '7622-9988', 'telefono_casa' => '2267-3344', 'direccion' => 'Residencial Altavista, Pasaje 3, Casa 12B, Mejicanos', 'es_responsable' => true],
                    ['nombre_completo' => 'María Ramírez de Flores', 'parentesco' => 'Madre', 'telefono_personal' => '7455-1122', 'direccion' => 'Residencial Altavista, Pasaje 3, Casa 12B, Mejicanos', 'es_responsable' => false],
                ],
                'expediente' => [
                    'area_key' => 'medicina_general',
                    'motivo_consulta' => 'Cefalea tensional crónica de 6 meses de evolución',
                    'notas_clinicas' => 'Paciente femenina de 22 años, estudiante de cuarto año de Psicología. Refiere cefalea con características de migraña sin aura desde hace 6 meses. Describe dolor pulsátil unilateral derecho, intensidad 7/10 en escala EVA, acompañado de fotofobia y náuseas sin vómito. Desencadenantes identificados: privación de sueño en época de exámenes, ayuno prolongado y exposición a pantallas. Exploración neurológica sin alteraciones. Fondo de ojo normal. Pares craneales conservados. No signos meníngeos. Se indica ibuprofeno 400 mg en fase aguda, modificaciones en higiene de sueño, evitar ayunos prolongados y control en 1 mes.',
                    'diagnostico' => 'Migraña sin aura (G43.0)',
                ],
            ],
            [
                'carnet' => 'RM24033',
                'carrera_nombre' => 'Doctorado en Medicina',
                'facultad_nombre' => 'Facultad de Medicina',
                'sexo' => 'M',
                'estado_civil' => 'Soltero',
                'nombre_completo' => 'José David Ramírez López',
                'direccion' => 'Colonia Médica, Avenida Las Gardenias #88, San Salvador',
                'fecha_nacimiento' => '2002-11-08',
                'profesion_ocupacion' => 'Estudiante',
                'fecha_primera_consulta' => '2026-03-01',
                'motivo_consulta' => 'Pérdida de peso de 8 kg en 3 meses sin dieta ni ejercicio. Fatiga generalizada. Refiere turnos de hospital que le impiden comer a horas regulares. Bajo consumo de frutas y verduras.',
                'contactos' => [
                    ['nombre_completo' => 'David Ramírez', 'parentesco' => 'Padre', 'telefono_personal' => '7766-5544', 'telefono_casa' => '2211-7788', 'direccion' => 'Colonia Médica, Av. Las Gardenias #88, San Salvador', 'es_responsable' => false],
                    ['nombre_completo' => 'Ana López de Ramírez', 'parentesco' => 'Madre', 'telefono_personal' => '7899-3322', 'direccion' => 'Colonia Médica, Av. Las Gardenias #88, San Salvador', 'es_responsable' => true],
                ],
                'expediente' => [
                    'area_key' => 'nutricion',
                    'motivo_consulta' => 'Pérdida de peso involuntaria y desorden alimentario',
                    'notas_clinicas' => 'Paciente masculino de 24 años, interno de medicina. Peso actual 56 kg, talla 1.75 m, IMC 18.2 (bajo peso). Refiere pérdida de 8 kg en los últimos 3 meses. Ingesta calórica estimada en 1400 kcal/día, muy por debajo de sus requerimientos basales. Patrón alimentario desordenado por horarios clínicos rotativos. Omite desayuno frecuentemente. Bajo consumo de proteínas, frutas y verduras. Se realiza evaluación antropométrica completa y se prescribe plan nutricional de 2400 kcal/día fraccionado en 5 tiempos de comida, suplementación con Ensure dos veces al día y control nutricional quincenal.',
                    'diagnostico' => 'Desnutrición calórico-proteica leve (E44.1) asociada a estrés académico',
                ],
            ],
            [
                'carnet' => 'RV19089',
                'carrera_nombre' => 'Licenciatura en Trabajo Social',
                'facultad_nombre' => 'Facultad de Ciencias y Humanidades',
                'sexo' => 'F',
                'estado_civil' => 'Soltero',
                'nombre_completo' => 'Karla Vanessa Rivas Morales',
                'direccion' => 'Comunidad La Esperanza, Calle Principal, Casa sin número, Soyapango, San Salvador',
                'fecha_nacimiento' => '2007-01-30',
                'profesion_ocupacion' => 'Estudiante',
                'fecha_primera_consulta' => '2026-02-22',
                'motivo_consulta' => 'Situación económica familiar precaria. Padre desempleado desde hace 4 meses. Dificultad para costear transporte y materiales universitarios. Ha considerado abandonar la carrera. Episodios de llanto frecuente.',
                'contactos' => [
                    ['nombre_completo' => 'Antonio Rivas', 'parentesco' => 'Padre', 'telefono_personal' => '7123-9988', 'telefono_casa' => '--', 'direccion' => 'Comunidad La Esperanza, Soyapango', 'es_responsable' => false],
                    ['nombre_completo' => 'Marta Morales de Rivas', 'parentesco' => 'Madre', 'telefono_personal' => '7566-2233', 'direccion' => 'Comunidad La Esperanza, Soyapango', 'es_responsable' => true],
                ],
                'expediente' => [
                    'area_key' => 'trabajo_social',
                    'motivo_consulta' => 'Dificultades socioeconómicas que afectan la permanencia universitaria',
                    'notas_clinicas' => 'Paciente femenina de 19 años, primer año de Licenciatura en Trabajo Social. Vive en zona de alto riesgo social en Soyapango. Ambos padres con educación primaria incompleta. Es la primera persona de su familia en acceder a educación superior. Refiere que su padre perdió el empleo hace 4 meses, lo que ha generado una crisis económica familiar. Manifiesta episodios de llanto frecuente por la incertidumbre sobre su futuro académico. Se identifican factores protectores: buen promedio académico (8.2), red de apoyo de compañeras de clase, motivación intrínseca elevada. Se gestiona solicitud de exoneración de matrícula y beca de transporte ante Bienestar Estudiantil. Se programa acompañamiento psicosocial quincenal.',
                    'diagnostico' => 'Riesgo de deserción universitaria por determinantes sociales (Z55.9)',
                ],
            ],
            [
                'carnet' => 'SR26015',
                'carrera_nombre' => 'Ingeniería Civil',
                'facultad_nombre' => 'Facultad de Ingeniería y Arquitectura',
                'sexo' => 'M',
                'estado_civil' => 'Casado',
                'nombre_completo' => 'Miguel Ángel Sorto Rodríguez',
                'direccion' => 'Urbanización Montecarmelo, Block C, Apartamento 204, Santa Tecla, La Libertad',
                'fecha_nacimiento' => '2000-05-18',
                'profesion_ocupacion' => 'Estudiante y Técnico en Construcción',
                'fecha_primera_consulta' => '2026-01-08',
                'motivo_consulta' => 'Dolor lumbar de 8 meses de evolución tras accidente en motocicleta. Dolor que limita permanecer sentado más de 45 minutos en clases. Dificultad para cargar mochila con laptop y libros.',
                'contactos' => [
                    ['nombre_completo' => 'Katherine Sorto', 'parentesco' => 'Tutor', 'telefono_personal' => '7344-5566', 'telefono_casa' => '2288-9900', 'direccion' => 'Urb. Montecarmelo, Block C, Apto 204, Santa Tecla', 'es_responsable' => true],
                    ['nombre_completo' => 'Manuel Sorto', 'parentesco' => 'Padre', 'telefono_personal' => '7677-8899', 'direccion' => 'Calle El Calvario #33, San Juan Opico, La Libertad', 'es_responsable' => false],
                ],
                'expediente' => [
                    'area_key' => 'medicina_general',
                    'motivo_consulta' => 'Dolor lumbar crónico de 8 meses post-accidente de tránsito',
                    'notas_clinicas' => 'Paciente masculino de 26 años, estudiante de cuarto año de Ingeniería Civil. Antecedente de accidente de tránsito en motocicleta hace 8 meses con contusión lumbar directa. RMN de columna lumbosacra muestra hernia discal posterolateral izquierda L4-L5 de 4 mm sin compromiso radicular franco. Dolor referido a región glútea izquierda. Escala EVA: 6/10 en sedestación prolongada, 3/10 en bipedestación. Test de Lasègue negativo. Reflejos osteotendinosos conservados. Se prescribe programa de fisioterapia: ejercicios de fortalecimiento del core abdominal y paravertebral (3 sesiones por semana durante 8 semanas), reeducación postural, uso de silla ergonómica en aulas. Se recomienda mochila con ruedas para evitar carga lumbar.',
                    'diagnostico' => 'Lumbalgia mecánica crónica por hernia discal L4-L5 (M51.2)',
                ],
            ],
            [
                'carnet' => 'HE21042',
                'carrera_nombre' => 'Licenciatura en Enfermería',
                'facultad_nombre' => 'Facultad de Medicina',
                'sexo' => 'F',
                'estado_civil' => 'Unión Libre',
                'nombre_completo' => 'Brenda Lissette Hernández Castro',
                'direccion' => 'Colonia Santa Lucía, Avenida Los Próceres, Casa 27, San Miguel',
                'fecha_nacimiento' => '2005-09-12',
                'profesion_ocupacion' => 'Estudiante',
                'fecha_primera_consulta' => '2026-04-05',
                'motivo_consulta' => 'Crisis de pareja de 3 meses. Discusiones constantes con su compañero sentimental. Disminución del rendimiento académico de 8.5 a 6.8. Anhedonia e insomnio de conciliación. Refiere sentirse sin energía para estudiar.',
                'contactos' => [
                    ['nombre_completo' => 'Marta Castro de Hernández', 'parentesco' => 'Madre', 'telefono_personal' => '7600-4455', 'telefono_casa' => '2661-2233', 'direccion' => 'Col. Santa Lucía, Av. Los Próceres, Casa 27, San Miguel', 'es_responsable' => true],
                    ['nombre_completo' => 'René Hernández', 'parentesco' => 'Padre', 'telefono_personal' => '7899-0011', 'direccion' => 'Col. Santa Lucía, Av. Los Próceres, Casa 27, San Miguel', 'es_responsable' => false],
                    ['nombre_completo' => 'Licda. Patricia Gómez', 'parentesco' => 'Tutor', 'telefono_personal' => '7255-6789', 'direccion' => 'Departamento de Bienestar Estudiantil UES San Miguel', 'es_responsable' => false],
                ],
                'expediente' => [
                    'area_key' => 'psicologia',
                    'motivo_consulta' => 'Crisis de pareja y bajo rendimiento académico',
                    'notas_clinicas' => 'Paciente femenina de 21 años, tercer año de Licenciatura en Enfermería. Convive con pareja sentimental desde hace 1 año. Reporta ciclo de violencia psicológica progresiva: insultos, control de redes sociales, prohibición de reuniones con amistades, descalificaciones sobre su capacidad académica. PHQ-9: 16 puntos (depresión moderada-severa). Refiere anhedonia, llanto fácil, insomnio de conciliación y pensamientos de minusvalía. No presenta ideación suicida activa. Se trabaja en sesión: psicoeducación sobre ciclos de violencia de género, identificación de señales de alerta, plan de seguridad personal, fortalecimiento de red de apoyo familiar. Se deriva a grupo de apoyo psicosocial para mujeres universitarias. Se recomienda valoración por psiquiatría para posible tratamiento farmacológico coadyuvante.',
                    'diagnostico' => 'Episodio depresivo moderado (F32.1) en contexto de violencia de pareja',
                ],
            ],
            [
                'carnet' => 'ME23077',
                'carrera_nombre' => 'Arquitectura',
                'facultad_nombre' => 'Facultad de Ingeniería y Arquitectura',
                'sexo' => 'M',
                'estado_civil' => 'Soltero',
                'nombre_completo' => 'Oscar Daniel Mejía Torres',
                'direccion' => 'Colonia Escalón, Calle El Mirador #215, San Salvador',
                'fecha_nacimiento' => '2003-04-05',
                'profesion_ocupacion' => 'Estudiante',
                'fecha_primera_consulta' => '2026-05-10',
                'motivo_consulta' => 'Insomnio de 3 meses de evolución. Dificultad para conciliar el sueño con latencia de 2 a 3 horas. Despertares frecuentes. Promedio de 4 horas de sueño por noche. Somnolencia diurna que afecta su rendimiento en clases de taller de diseño.',
                'contactos' => [
                    ['nombre_completo' => 'Daniel Mejía', 'parentesco' => 'Padre', 'telefono_personal' => '7733-1122', 'telefono_casa' => '2244-5566', 'direccion' => 'Col. Escalón, Calle El Mirador #215, San Salvador', 'es_responsable' => true],
                    ['nombre_completo' => 'Cecilia Torres de Mejía', 'parentesco' => 'Madre', 'telefono_personal' => '7455-3344', 'direccion' => 'Col. Escalón, Calle El Mirador #215, San Salvador', 'es_responsable' => false],
                ],
                'expediente' => [
                    'area_key' => 'medicina_general',
                    'motivo_consulta' => 'Insomnio persistente de 3 meses de evolución',
                    'notas_clinicas' => 'Paciente masculino de 23 años, estudiante de cuarto año de Arquitectura. Índice de severidad del insomnio (ISI): 18 puntos (insomnio clínico moderado). Higiene de sueño deficiente: uso de teléfono celular en cama hasta las 2:00 AM, consumo de café después de las 18:00 hrs (3-4 tazas diarias), siestas vespertinas de 2 horas de duración. No consume alcohol ni tabaco. Examen físico sin hallazgos relevantes. Se prescribe protocolo de higiene de sueño: restricción de tiempo en cama a 6 horas (00:00-06:00), eliminación de pantallas 1 hora antes de dormir, suspensión de cafeína después de las 17:00 hrs, exposición a luz solar matutina 30 minutos. Se recomienda registro diario de sueño por 21 días. Control en 3 semanas.',
                    'diagnostico' => 'Insomnio crónico primario (G47.0)',
                ],
            ],
            [
                'carnet' => 'CR18055',
                'carrera_nombre' => 'Licenciatura en Economía',
                'facultad_nombre' => 'Facultad de Ciencias Económicas',
                'sexo' => 'F',
                'estado_civil' => 'Soltero',
                'nombre_completo' => 'Sofía Alejandra Cruz Martínez',
                'direccion' => 'Barrio El Calvario, 4a Avenida Sur #12, Santa Ana',
                'fecha_nacimiento' => '2008-06-28',
                'profesion_ocupacion' => 'Estudiante',
                'fecha_primera_consulta' => '2026-05-18',
                'motivo_consulta' => 'Refiere acoso por parte de un compañero de clase desde hace 4 meses. Mensajes inapropiados por redes sociales, comentarios sobre su apariencia física en el aula. Ha faltado a 3 clases por temor. Promedio bajó de 8.0 a 6.5.',
                'contactos' => [
                    ['nombre_completo' => 'Lorena Martínez de Cruz', 'parentesco' => 'Madre', 'telefono_personal' => '7011-5566', 'telefono_casa' => '2440-1122', 'direccion' => 'Barrio El Calvario, 4a Av. Sur #12, Santa Ana', 'es_responsable' => true],
                    ['nombre_completo' => 'José Cruz', 'parentesco' => 'Padre', 'telefono_personal' => '7422-8899', 'direccion' => 'Barrio El Calvario, 4a Av. Sur #12, Santa Ana', 'es_responsable' => false],
                ],
                'expediente' => [
                    'area_key' => 'trabajo_social',
                    'motivo_consulta' => 'Acoso por parte de compañero de clase en entorno universitario',
                    'notas_clinicas' => 'Paciente femenina de 18 años, primer año de Licenciatura en Economía. Describe episodios sistemáticos de acoso sexual verbal y escrito por parte de un compañero de sección durante los últimos 4 meses. Incluye mensajes inapropiados a través de redes sociales, comentarios sexualizantes sobre su apariencia física en el aula y seguimiento a la salida de clases. Presenta sintomatología ansiosa: taquicardia antes de ingresar a clases, conductas de evitación (no asiste a cafetería, biblioteca), ha faltado a 3 clases. No ha interpuesto denuncia formal por temor a represalias. Se orienta sobre el Reglamento Disciplinario de la UES y el protocolo universitario de atención a víctimas de violencia de género. Se coordina con la Fiscalía Universitaria para acompañamiento en el proceso de denuncia. Se solicita cambio de sección con carácter de urgencia a la administración académica.',
                    'diagnostico' => 'Trastorno de ansiedad no especificado (F41.9) secundario a acoso universitario',
                ],
            ],
            [
                'carnet' => 'QU27011',
                'carrera_nombre' => 'Licenciatura en Ciencias Jurídicas',
                'facultad_nombre' => 'Facultad de Jurisprudencia y Ciencias Sociales',
                'sexo' => 'M',
                'estado_civil' => 'Divorciado',
                'nombre_completo' => 'Luis Fernando Quintanilla Orellana',
                'direccion' => 'Colonia Miramonte, Calle Los Almendros, Pasaje B, Casa 8, San Salvador',
                'fecha_nacimiento' => '1999-12-03',
                'profesion_ocupacion' => 'Estudiante y Asistente Legal',
                'fecha_primera_consulta' => '2026-04-20',
                'motivo_consulta' => 'Proceso de divorcio finalizado hace 2 meses. Refiere tristeza persistente, desinterés en actividades que antes disfrutaba, dificultad para concentrarse en lecturas jurídicas. Ha reprobado 2 materias este ciclo.',
                'contactos' => [
                    ['nombre_completo' => 'Carmen Orellana', 'parentesco' => 'Madre', 'telefono_personal' => '7788-9900', 'telefono_casa' => '2233-4455', 'direccion' => 'Col. Miramonte, Pasaje B, Casa 8, San Salvador', 'es_responsable' => true],
                    ['nombre_completo' => 'Fernando Quintanilla', 'parentesco' => 'Padre', 'telefono_personal' => '7566-7744', 'direccion' => 'Col. Miramonte, Pasaje B, Casa 8, San Salvador', 'es_responsable' => false],
                    ['nombre_completo' => 'Gabriela Rodríguez', 'parentesco' => 'Otro', 'telefono_personal' => '7321-5544', 'direccion' => 'Santa Tecla, La Libertad', 'es_responsable' => false],
                ],
                'expediente' => [
                    'area_key' => 'psicologia',
                    'motivo_consulta' => 'Estrés post-divorcio afectando concentración y rendimiento académico',
                    'notas_clinicas' => 'Paciente masculino de 27 años, quinto año de Licenciatura en Ciencias Jurídicas. Divorciado hace 2 meses tras 4 años de matrimonio. Refiere conflicto por custodia compartida de hija de 3 años, actualmente en mediación familiar. Inventario de Depresión de Beck-II: 22 puntos (depresión moderada). Síntomas principales: tristeza persistente, anhedonia, fatiga, sentimientos de culpa, disminución de la concentración. Ha reprobado Derecho Procesal Civil y Derecho de Familia este ciclo académico. Se identifica factor protector importante: motivación por su hija para continuar y finalizar la carrera. Se inicia psicoterapia cognitivo-conductual con enfoque en elaboración del duelo por separación, reestructuración cognitiva de pensamientos automáticos negativos y técnicas de activación conductual. Se recomienda programar tiempo de calidad con su hija y retomar actividad física moderada 3 veces por semana.',
                    'diagnostico' => 'Trastorno adaptativo con estado de ánimo depresivo (F43.21)',
                ],
            ],
            [
                'carnet' => 'PI20063',
                'carrera_nombre' => 'Licenciatura en Mercadeo Internacional',
                'facultad_nombre' => 'Facultad de Ciencias Económicas',
                'sexo' => 'F',
                'estado_civil' => 'Soltero',
                'nombre_completo' => 'Gabriela María Pineda Recinos',
                'direccion' => 'Residencial San Francisco, Avenida Central, Edificio D, Apartamento 3B, San Salvador',
                'fecha_nacimiento' => '2006-08-14',
                'profesion_ocupacion' => 'Estudiante',
                'fecha_primera_consulta' => '2026-03-12',
                'motivo_consulta' => 'Preocupación por atracones alimentarios desde hace 1 año. Episodios donde consume grandes cantidades de comida en corto tiempo, seguidos de culpa intensa. Ocurren 3 a 4 veces por semana en época de exámenes. No realiza conductas compensatorias.',
                'contactos' => [
                    ['nombre_completo' => 'Ricardo Pineda', 'parentesco' => 'Padre', 'telefono_personal' => '7611-2233', 'telefono_casa' => '2255-6677', 'direccion' => 'Res. San Francisco, Edif. D, Apto 3B, San Salvador', 'es_responsable' => true],
                    ['nombre_completo' => 'Claudia Recinos de Pineda', 'parentesco' => 'Madre', 'telefono_personal' => '7966-8877', 'direccion' => 'Res. San Francisco, Edif. D, Apto 3B, San Salvador', 'es_responsable' => false],
                ],
                'expediente' => [
                    'area_key' => 'nutricion',
                    'motivo_consulta' => 'Trastorno por atracones alimentarios',
                    'notas_clinicas' => 'Paciente femenina de 20 años, segundo año de Licenciatura en Mercadeo Internacional. Peso actual 72 kg, talla 1.62 m, IMC 27.4 (sobrepeso grado I). Refiere episodios de atracones desde hace 1 año, 3 a 4 veces por semana, con mayor frecuencia en período de exámenes. Describe ingesta de cantidades objetivamente grandes de alimentos en período corto (menos de 2 horas), con sensación de pérdida de control. Posterior a los atracones experimenta culpa intensa y vergüenza. No realiza conductas compensatorias (no vómitos, no laxantes, no ejercicio excesivo). Escala BES (Binge Eating Scale): 24 puntos (atracones moderados-severos). Desencadenantes identificados: ansiedad académica, comentarios críticos de familiares sobre su peso corporal, aburrimiento nocturno. Se elabora plan nutricional de 1800 kcal/día con distribución en 5 tiempos, énfasis en horarios regulares y registro diario de alimentación y estado emocional.',
                    'diagnostico' => 'Trastorno de atracones (F50.8)',
                ],
            ],
        ];

        foreach ($pacientes as $index => $data) {
            $carrera = Carrera::where('nombre', $data['carrera_nombre'])
                ->whereHas('facultad', function ($q) use ($data) {
                    $q->where('nombre', $data['facultad_nombre']);
                })->first();

            if (!$carrera) {
                $this->command?->warn("Carrera no encontrada: {$data['carrera_nombre']} en {$data['facultad_nombre']}");
                continue;
            }

            $profesional = $creadorPorArea[$data['expediente']['area_key']];

            $paciente = Paciente::create([
                'carnet' => $data['carnet'],
                'carrera_id' => $carrera->id,
                'creado_por_profesional_id' => $profesional->id,
                'sexo' => $data['sexo'],
                'estado_civil' => $data['estado_civil'],
                'nombre_completo' => $data['nombre_completo'],
                'direccion' => $data['direccion'],
                'fecha_nacimiento' => $data['fecha_nacimiento'],
                'profesion_ocupacion' => $data['profesion_ocupacion'],
                'fecha_primera_consulta' => $data['fecha_primera_consulta'],
                'motivo_consulta' => $data['motivo_consulta'],
            ]);

            foreach ($data['contactos'] as $contactoData) {
                ContactoPaciente::create([
                    'paciente_id' => $paciente->codigo,
                    'nombre_completo' => $contactoData['nombre_completo'],
                    'parentesco' => $contactoData['parentesco'],
                    'telefono_personal' => $contactoData['telefono_personal'],
                    'telefono_casa' => $contactoData['telefono_casa'] ?? null,
                    'direccion' => $contactoData['direccion'] ?? null,
                    'es_responsable' => $contactoData['es_responsable'] ?? false,
                ]);
            }

            if ($index >= 6) {
                continue; // Dejamos estos 4 pacientes sin expediente, listos para que el usuario los cree manualmente
            }

            $expData = $data['expediente'];
            $area = $areas[$expData['area_key']];

            if ($area) {
                $expediente = Expediente::create([
                    'paciente_id' => $paciente->codigo,
                    'area_id' => $area->id,
                    'motivo_consulta' => $expData['motivo_consulta'],
                    'notas_clinicas' => $expData['notas_clinicas'],
                    'diagnostico' => $expData['diagnostico'],
                ]);

                // Actualizar paciente con las etiquetas para simular que se añadieron en su registro
                $paciente->update([
                    'etiquetas_motivo' => ['Violencia Familiar', 'Problemas de Adaptación']
                ]);

                if ($index >= 4) {
                    continue; // Dejamos estos 2 pacientes con expediente, pero sin consultas ni citas
                }

                // Generar consulta asociada para que US-09 tenga datos visuales en la línea de tiempo
                Consulta::create([
                    'expediente_id' => $expediente->id,
                    'profesional_id' => $profesional->id,
                    'fecha_consulta' => $paciente->fecha_primera_consulta,
                    'motivo_consulta' => 'Evaluación inicial: ' . $expData['motivo_consulta'],
                    'notas_clinicas' => $expData['notas_clinicas'],
                    'diagnostico' => $expData['diagnostico'],
                    'tecnica_utilizada' => 'Entrevista y Observación Clínica',
                    'evaluacion_inicial' => [
                        'apariencia_externa' => 'Adecuada',
                        'voz' => 'Tono normal',
                        'patrones_habla' => 'Fluido',
                        'expresiones_faciales' => 'Congruentes',
                        'ademanes' => 'Tranquilos',
                        'actitudes_tratamiento' => 'Colaborador',
                        'impresion' => 'Paciente orientado',
                        'plan_tratamiento' => 'Seguimiento quincenal',
                        'pronostico' => 'Favorable',
                    ],
                ]);
                
                // Generar Cita programada para pruebas de conflicto de horario (US-11)
                // Se agenda para MT20045 con psicosocial@pandora.com (Trabajo Social / Psicología)
                if ($data['carnet'] === 'MT20045') {
                    $fechaCitaConflicto = now()->addDays(2)->setTime(10, 0)->format('Y-m-d H:i:s');
                    \App\Models\Cita::create([
                        'expediente_id' => $expediente->id,
                        'profesional_id' => $profesional->id,
                        'area_id' => $area->id,
                        'fecha_hora' => $fechaCitaConflicto,
                        'motivo' => 'Cita de seguimiento pre-agendada para test de conflicto',
                        'estado' => 'programada',
                    ]);
                    $this->command?->info("==> CITA GENERADA PARA PRUEBAS US-11 <==");
                    $this->command?->info("Paciente: {$data['nombre_completo']} ({$data['carnet']})");
                    $this->command?->info("Especialista: psicosocial@pandora.com");
                    $this->command?->info("Fecha y Hora ocupada: {$fechaCitaConflicto}");
                    $this->command?->info("==========================================");
                }

                if ($data['carnet'] === 'FL22067') {
                    // Cita pasada
                    \App\Models\Cita::create([
                        'expediente_id' => $expediente->id,
                        'profesional_id' => $profesional->id,
                        'area_id' => $area->id,
                        'fecha_hora' => now()->subDays(1)->setTime(14, 0)->format('Y-m-d H:i:s'),
                        'motivo' => 'Cita de seguimiento atrasada (Pasada)',
                        'estado' => 'programada',
                    ]);
                    
                    // Cita hoy (futura en horas)
                    \App\Models\Cita::create([
                        'expediente_id' => $expediente->id,
                        'profesional_id' => $profesional->id,
                        'area_id' => $area->id,
                        'fecha_hora' => now()->setTime(23, 59)->format('Y-m-d H:i:s'),
                        'motivo' => 'Cita para hoy más tarde (Límite Mismo Día)',
                        'estado' => 'programada',
                    ]);
                    
                    // Cita futura en días
                    \App\Models\Cita::create([
                        'expediente_id' => $expediente->id,
                        'profesional_id' => $profesional->id,
                        'area_id' => $area->id,
                        'fecha_hora' => now()->addDays(2)->setTime(9, 0)->format('Y-m-d H:i:s'),
                        'motivo' => 'Cita en días futuros (No accionable)',
                        'estado' => 'programada',
                    ]);
                    
                    $this->command?->info("==> CITAS GENERADAS PARA PRUEBAS US-12 <==");
                    $this->command?->info("Paciente: {$data['nombre_completo']} ({$data['carnet']})");
                    $this->command?->info("Especialista: {$profesional->especialista->email}");
                    $this->command?->info("Variantes: Pasada, Hoy-Futura, Futura-en-días");
                    $this->command?->info("==========================================");
                }

                if ($data['carnet'] === 'RM24033') {
                    // Cita para probar reprogramar
                    \App\Models\Cita::create([
                        'expediente_id' => $expediente->id,
                        'profesional_id' => $profesional->id,
                        'area_id' => $area->id,
                        'fecha_hora' => now()->addDays(5)->setTime(10, 0)->format('Y-m-d H:i:s'),
                        'motivo' => 'Cita de control nutricional (Para reprogramar)',
                        'estado' => 'programada',
                    ]);
                    
                    // Cita para probar cancelar
                    \App\Models\Cita::create([
                        'expediente_id' => $expediente->id,
                        'profesional_id' => $profesional->id,
                        'area_id' => $area->id,
                        'fecha_hora' => now()->addDays(6)->setTime(11, 0)->format('Y-m-d H:i:s'),
                        'motivo' => 'Cita de seguimiento (Para cancelar)',
                        'estado' => 'programada',
                    ]);
                    
                    $this->command?->info("==> CITAS GENERADAS PARA PRUEBAS US-13 <==");
                    $this->command?->info("Paciente: {$data['nombre_completo']} ({$data['carnet']})");
                    $this->command?->info("Variantes: Para Reprogramar, Para Cancelar");
                    $this->command?->info("==========================================");
                }
            }
        }

        $this->command?->info('ExpedienteSeeder: 10 pacientes, contactos y expedientes creados correctamente.');
    }
}

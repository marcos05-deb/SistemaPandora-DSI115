<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Paciente;
use Database\Seeders\Sprint3DemoSeeder;
use Illuminate\Console\Command;

/**
 * Prepara (de forma idempotente) los datos necesarios para la demostración del Sprint 3.
 * Seguro en producción: exige opt-in explícito y nunca muestra ni fija contraseñas.
 */
class PrepararSprint3Command extends Command
{
    protected $signature = 'pandora:preparar-sprint3
        {--force : Requerido en producción}
        {--reset-passwords : Restablece la contraseña de usuarios que ya existen}';

    protected $description = 'Crea (idempotente) catálogos, usuarios, rúbrica vigente y casos de demostración del Sprint 3';

    public function handle(Sprint3DemoSeeder $seeder): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('Entorno de producción: use --force para confirmar que desea preparar los datos de demostración.');

            return self::FAILURE;
        }

        $password = config('app.sprint3_demo_password');
        if (! is_string($password) || mb_strlen($password) < 12) {
            $this->error('Defina la variable de entorno SPRINT3_DEMO_PASSWORD con al menos 12 caracteres.');

            return self::FAILURE;
        }

        foreach (['app.area_key_secret' => 'AREA_KEY_SECRET', 'app.blind_index_secret' => 'BLIND_INDEX_SECRET'] as $clave => $variable) {
            if (! is_string(config($clave)) || config($clave) === '') {
                $this->error("Falta la variable de entorno {$variable}; sin ella no se puede cifrar la información clínica.");

                return self::FAILURE;
            }
        }

        $resultado = $seeder->sembrar($password, (bool) $this->option('reset-passwords'));

        $this->line('Preparación del Sprint 3 (las contraseñas no se muestran):');
        foreach ($resultado['informe'] as [$bloque, $estado]) {
            $this->line(sprintf(' - %s: %s', $bloque, $estado));
        }

        $this->newLine();
        $this->line('Pacientes de la demostración (prefijo de carnet "'.Sprint3DemoSeeder::PREFIJO_CARNET.'"):');
        $this->mostrarPaciente('HU-16 listo para alerta (principal)', Sprint3DemoSeeder::CARNETS_HU16[0]);
        foreach (array_slice(Sprint3DemoSeeder::CARNETS_HU16, 1) as $carnet) {
            $this->mostrarPaciente('HU-16 de respaldo para ensayos', $carnet);
        }
        $this->mostrarPaciente('HU-17 alerta activa', Sprint3DemoSeeder::CARNET_ALERTA_ACTIVA);
        $this->mostrarPaciente('HU-17 alerta cerrada', Sprint3DemoSeeder::CARNET_ALERTA_CERRADA);

        if ($resultado['errores']) {
            $this->error('Terminó con errores en algunos bloques; revise el detalle y vuelva a ejecutar (es idempotente).');

            return self::FAILURE;
        }

        $this->info('Sprint 3 preparado.');

        return self::SUCCESS;
    }

    private function mostrarPaciente(string $etiqueta, string $carnet): void
    {
        $codigo = Paciente::withTrashed()->where('carnet', $carnet)->value('codigo');
        $this->line(sprintf(' - %s: carnet %s, código anónimo %s', $etiqueta, $carnet, $codigo ?? '(aún no creado)'));
    }
}

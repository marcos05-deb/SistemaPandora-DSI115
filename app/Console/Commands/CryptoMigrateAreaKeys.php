<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Expediente;
use App\Models\Consulta;
use App\Models\Cita;

class CryptoMigrateAreaKeys extends Command
{
    protected $signature = 'crypto:migrate-area-keys';
    protected $description = 'Migrar criptografía de clave individual a clave por Área (Deuda C-09)';

    public function handle()
    {
        $this->info('Iniciando migración de criptografía a clave por Área...');

        // Verificar configuración
        $areaKeySecret = config('area_key_secret');
        if (!$areaKeySecret) {
            $this->error('ERROR: CONFIGURACIÓN FALTANTE');
            $this->error('Se requiere AREA_KEY_SECRET en .env');
            return 1;
        }
        $this->info('Configuración AREA_KEY_SECRET encontrada.');

        // Migrar expedientes
        $this->migrateExpedientes();

        // Migrar consultas
        $this->migrateConsultas();

        // Migrar citas
        $this->migrateCitas();

        // Verificar
        $this->verifyMigration();

        $this->info('¡Migración completada exitosamente!');
        return 0;
    }

    protected function migrateExpedientes(): void
    {
        $total = Expediente::count();
        $this->info("Total de expedientes: {$total}");

        Expediente::chunk(50, function ($expedientes) {
            foreach ($expedientes as $exp) {
                try {
                    $this->migrateField($exp, 'motivo_consulta');
                    $this->migrateField($exp, 'notas_clinicas');
                    $this->migrateField($exp, 'diagnostico');
                    $this->migrateField($exp, 'motivo_derivacion');
                    if ($exp->isDirty()) $exp->save();
                } catch (\Exception $e) {
                    Log::warning("Expediente {$exp->id}: {$e->getMessage()}");
                }
            }
        });
        $this->info("Migración de expedientes completada.");
    }

    protected function migrateConsultas(): void
    {
        $total = Consulta::count();
        $this->info("Total de consultas: {$total}");

        Consulta::chunk(50, function ($consultas) {
            foreach ($consultas as $cons) {
                try {
                    $this->migrateField($cons, 'motivo_consulta');
                    $this->migrateField($cons, 'notas_clinicas');
                    $this->migrateField($cons, 'diagnostico');
                    $this->migrateField($cons, 'plan_atencion');
                    if ($cons->isDirty()) $cons->save();
                } catch (\Exception $e) {
                    Log::warning("Consulta {$cons->id}: {$e->getMessage()}");
                }
            }
        });
        $this->info("Migración de consultas completada.");
    }

    protected function migrateCitas(): void
    {
        $total = Cita::count();
        $this->info("Total de citas: {$total}");

        Cita::chunk(50, function ($citas) {
            foreach ($citas as $cita) {
                try {
                    $this->migrateField($cita, 'motivo');
                    $this->migrateField($cita, 'motivo_cancelacion');
                    if ($cita->isDirty()) $cita->save();
                } catch (\Exception $e) {
                    Log::warning("Cita {$cita->id}: {$e->getMessage()}");
                }
            }
        });
        $this->info("Migración de citas completada.");
    }

    protected function migrateField($model, string $field): void
    {
        $valor = $model->{$field};
        if (is_null($valor) || $valor === '') return;

        try {
            $desencriptado = $this->desencriptar($valor);
            if ($desencriptado !== false) {
                $encriptado = $this->encriptarConAreaKey($desencriptado);
                $model->{$field} = $encriptado;
            }
        } catch (\Exception $e) {
            // Ya migrado o formato antiguo
        }
    }

    protected function desencriptar(string $encoded): string|false
    {
        try {
            $result = app(\App\Models\Casts\EncryptedFieldCast::class)->get(
                null, '', $encoded, []
            );
            return ($result !== null && $result !== false) ? $result : false;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function encriptarConAreaKey(string $plaintext): string
    {
        $key = base64_decode(config('area_key_secret'), true);
        $nonce = random_bytes(\Sodium\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = \Sodium\crypto_secretbox($plaintext, $nonce, $key);
        return base64_encode($nonce . $ciphertext);
    }

    protected function verifyMigration(): void
    {
        $this->info('Verificando integridad...');
        $exp = Expediente::first();
        if ($exp) {
            try {
                $m = $this->desencriptar($exp->motivo_consulta ?? '');
                $this->info($m !== false ? "OK: expediente {$exp->id}" : "WARN: no descifra {$exp->id}");
            } catch (\Exception $e) {
                $this->warn("Verificación error: {$e->getMessage()}");
            }
        }
    }
}
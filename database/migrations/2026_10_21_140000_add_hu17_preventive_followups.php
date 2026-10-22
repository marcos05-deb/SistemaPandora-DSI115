<?php

declare(strict_types=1);

use App\Services\AreaEncryptionService;
use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection('pgsql_admin');
        $applicationRole = $this->applicationRole($connection);
        $this->assertTerminalAlertsAreDemoOnly($connection);

        Schema::connection('pgsql_admin')->table('alertas_preventivas', function (Blueprint $table): void {
            $table->text('resultado_cierre')->nullable();
            $table->text('justificacion_descarte')->nullable();
            $table->timestampTz('cerrada_en')->nullable();
            $table->timestampTz('descartada_en')->nullable();
            $table->foreignUuid('cerrada_por_profesional_id')->nullable()->constrained('profesionales')->restrictOnDelete();
            $table->foreignUuid('descartada_por_profesional_id')->nullable()->constrained('profesionales')->restrictOnDelete();
        });

        $this->backfillDemoTerminalAlerts($connection);

        $connection->statement(<<<'SQL'
            ALTER TABLE alertas_preventivas
            ADD CONSTRAINT alertas_preventivas_cierre_resultado_check
            CHECK (estado <> 'cerrada' OR resultado_cierre IS NOT NULL)
        SQL);
        $connection->statement(<<<'SQL'
            ALTER TABLE alertas_preventivas
            ADD CONSTRAINT alertas_preventivas_descarte_justificacion_check
            CHECK (estado <> 'descartada' OR justificacion_descarte IS NOT NULL)
        SQL);

        Schema::connection('pgsql_admin')->create('seguimientos_preventivos', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('alerta_preventiva_id')->constrained('alertas_preventivas')->restrictOnDelete();
            $table->foreignId('area_id')->constrained('areas')->restrictOnDelete();
            $table->text('valoracion');
            $table->text('accion_preventiva');
            $table->foreignUuid('responsable_profesional_id')->constrained('profesionales')->restrictOnDelete();
            $table->date('proxima_revision');
            $table->foreignUuid('registrado_por_profesional_id')->constrained('profesionales')->restrictOnDelete();
            $table->timestampsTz();
            $table->index(['alerta_preventiva_id', 'created_at'], 'seguimientos_alerta_created_index');
        });

        $connection->statement("REVOKE ALL ON TABLE seguimientos_preventivos FROM PUBLIC, {$applicationRole}");
        $connection->statement("GRANT SELECT, INSERT ON TABLE seguimientos_preventivos TO {$applicationRole}");
    }

    public function down(): void
    {
        $connection = DB::connection('pgsql_admin');
        $this->assertNoHu17DataWouldBeLost($connection);

        Schema::connection('pgsql_admin')->dropIfExists('seguimientos_preventivos');
        $connection->statement('ALTER TABLE alertas_preventivas DROP CONSTRAINT IF EXISTS alertas_preventivas_cierre_resultado_check');
        $connection->statement('ALTER TABLE alertas_preventivas DROP CONSTRAINT IF EXISTS alertas_preventivas_descarte_justificacion_check');
        Schema::connection('pgsql_admin')->table('alertas_preventivas', function (Blueprint $table): void {
            $table->dropColumn([
                'resultado_cierre',
                'justificacion_descarte',
                'cerrada_en',
                'descartada_en',
                'cerrada_por_profesional_id',
                'descartada_por_profesional_id',
            ]);
        });
    }

    private function applicationRole(Connection $connection): string
    {
        $applicationUser = config('database.connections.pgsql.username');
        $adminUser = config('database.connections.pgsql_admin.username');

        if (! is_string($applicationUser) || trim($applicationUser) === ''
            || ! is_string($adminUser) || trim($adminUser) === ''
            || $applicationUser === $adminUser) {
            throw new RuntimeException('Se requieren roles pgsql y pgsql_admin distintos para proteger los seguimientos preventivos.');
        }

        $quotedRole = $connection->selectOne('SELECT quote_ident(?) AS role_name', [$applicationUser])?->role_name;

        if (! is_string($quotedRole) || $quotedRole === '') {
            throw new RuntimeException('No se pudo resolver el rol de aplicación para proteger los seguimientos preventivos.');
        }

        return $quotedRole;
    }

    private function assertTerminalAlertsAreDemoOnly(Connection $connection): void
    {
        $schema = Schema::connection('pgsql_admin');
        if (! $schema->hasTable('alertas_preventivas') || ! $schema->hasTable('rubrica_versiones')) {
            return;
        }

        $hasClinicalTerminalAlert = $connection->table('alertas_preventivas as alerta')
            ->join('rubrica_versiones as version', 'version.id', '=', 'alerta.rubrica_version_id')
            ->whereIn('alerta.estado', ['cerrada', 'descartada'])
            ->where('version.es_demo', false)
            ->exists();

        if ($hasClinicalTerminalAlert) {
            throw new RuntimeException('HU-17 requiere documentar cierres/descarte clínicos existentes antes de la migración.');
        }
    }

    private function backfillDemoTerminalAlerts(Connection $connection): void
    {
        $alerts = $connection->table('alertas_preventivas as alerta')
            ->join('rubrica_versiones as version', 'version.id', '=', 'alerta.rubrica_version_id')
            ->where('version.es_demo', true)
            ->whereIn('alerta.estado', ['cerrada', 'descartada'])
            ->select([
                'alerta.id',
                'alerta.area_id',
                'alerta.estado',
                'alerta.generada_en',
                'alerta.responsable_profesional_id',
            ])
            ->get();

        if ($alerts->isEmpty()) {
            return;
        }

        $encryption = app(AreaEncryptionService::class);
        foreach ($alerts as $alerta) {
            $values = $alerta->estado === 'cerrada'
                ? [
                    'resultado_cierre' => $encryption->encrypt('DEMO: resultado documentado para el cierre de ejemplo.', $alerta->area_id),
                    'cerrada_en' => $alerta->generada_en ?? now(),
                    'cerrada_por_profesional_id' => $alerta->responsable_profesional_id,
                ]
                : [
                    'justificacion_descarte' => $encryption->encrypt('DEMO: justificación documentada para el descarte de ejemplo.', $alerta->area_id),
                    'descartada_en' => $alerta->generada_en ?? now(),
                    'descartada_por_profesional_id' => $alerta->responsable_profesional_id,
                ];

            $connection->table('alertas_preventivas')->where('id', $alerta->id)->update($values);
        }
    }

    private function assertNoHu17DataWouldBeLost(Connection $connection): void
    {
        $schema = Schema::connection('pgsql_admin');

        if ($schema->hasTable('seguimientos_preventivos') && $connection->table('seguimientos_preventivos')->exists()) {
            throw new RuntimeException('HU-17 no puede revertirse mientras existan seguimientos preventivos.');
        }

        if ($schema->hasTable('alertas_preventivas') && $connection->table('alertas_preventivas')
            ->whereIn('estado', ['cerrada', 'descartada'])
            ->orWhereNotNull('resultado_cierre')
            ->orWhereNotNull('justificacion_descarte')
            ->orWhereNotNull('cerrada_en')
            ->orWhereNotNull('descartada_en')
            ->orWhereNotNull('cerrada_por_profesional_id')
            ->orWhereNotNull('descartada_por_profesional_id')
            ->exists()) {
            throw new RuntimeException('HU-17 no puede revertirse mientras existan cierres o descartes de alertas.');
        }
    }
};

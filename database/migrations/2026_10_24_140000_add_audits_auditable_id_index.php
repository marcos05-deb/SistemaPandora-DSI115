<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql_admin')->table('audits', function (Blueprint $table): void {
            if (! $this->indexExists('audits_auditable_id_index')) {
                $table->index('auditable_id', 'audits_auditable_id_index');
            }
            if (! $this->indexExists('audits_event_created_at_index')) {
                $table->index(['event', 'created_at'], 'audits_event_created_at_index');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql_admin')->table('audits', function (Blueprint $table): void {
            if ($this->indexExists('audits_event_created_at_index')) {
                $table->dropIndex('audits_event_created_at_index');
            }
            if ($this->indexExists('audits_auditable_id_index')) {
                $table->dropIndex('audits_auditable_id_index');
            }
        });
    }

    private function indexExists(string $name): bool
    {
        $row = DB::connection('pgsql_admin')->selectOne(
            'SELECT 1 AS ok FROM pg_indexes WHERE schemaname = current_schema() AND indexname = ?',
            [$name]
        );

        return $row !== null;
    }
};

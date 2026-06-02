<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('crm_stage_mappings')) {
            return;
        }

        DB::table('crm_stage_mappings')
            ->whereIn('source_type', ['solicitud_procedimiento', 'consulta_examenes'])
            ->whereIn('source_state', ['llamado', 'turno_llamado', 'turno-llamado'])
            ->update([
                'is_active' => false,
                'updated_at' => now()->toDateTimeString(),
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('crm_stage_mappings')) {
            return;
        }

        DB::table('crm_stage_mappings')
            ->whereIn('source_type', ['solicitud_procedimiento', 'consulta_examenes'])
            ->whereIn('source_state', ['llamado', 'turno_llamado', 'turno-llamado'])
            ->update([
                'is_active' => true,
                'updated_at' => now()->toDateTimeString(),
            ]);
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_proposals') && !Schema::hasColumn('crm_proposals', 'crm_opportunity_id')) {
            Schema::table('crm_proposals', function (Blueprint $table): void {
                $table->unsignedBigInteger('crm_opportunity_id')->nullable()->index()->after('lead_id');
            });
        }

        if (Schema::hasTable('solicitud_crm_detalles') && !Schema::hasColumn('solicitud_crm_detalles', 'crm_opportunity_id')) {
            Schema::table('solicitud_crm_detalles', function (Blueprint $table): void {
                $table->unsignedBigInteger('crm_opportunity_id')->nullable()->index()->after('crm_lead_id');
            });
        }

        if (Schema::hasTable('examen_crm_detalles') && !Schema::hasColumn('examen_crm_detalles', 'crm_opportunity_id')) {
            Schema::table('examen_crm_detalles', function (Blueprint $table): void {
                $table->unsignedBigInteger('crm_opportunity_id')->nullable()->index()->after('crm_lead_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['crm_proposals', 'solicitud_crm_detalles', 'examen_crm_detalles'] as $tableName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'crm_opportunity_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                try {
                    $table->dropIndex($tableName . '_crm_opportunity_id_index');
                } catch (Throwable) {
                    // Some legacy databases may have a manually named index or no index.
                }
                $table->dropColumn('crm_opportunity_id');
            });
        }
    }
};

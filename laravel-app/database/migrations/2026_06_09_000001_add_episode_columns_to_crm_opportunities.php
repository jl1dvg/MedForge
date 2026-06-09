<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_opportunities', function (Blueprint $table): void {
            $table->string('procedure_group', 100)->nullable()->after('afiliacion_tipo');
            $table->enum('lateralidad', ['OD', 'OI', 'AO'])->nullable()->after('procedure_group');
            $table->timestamp('episode_started_at')->nullable()->after('lateralidad');
            $table->unsignedBigInteger('previous_opportunity_id')->nullable()->after('episode_started_at');
            $table->enum('opportunity_type', ['recurrente', 'unica', 'diagnostico'])->nullable()->after('previous_opportunity_id');
            $table->tinyInteger('continuity_flag')->default(0)->after('opportunity_type');

            $table->index('procedure_group',          'idx_crm_opp_proc_group');
            $table->index('lateralidad',              'idx_crm_opp_lateralidad');
            $table->index('episode_started_at',       'idx_crm_opp_episode_at');
            $table->index('previous_opportunity_id',  'idx_crm_opp_prev_opp');
            $table->index('opportunity_type',         'idx_crm_opp_type');
        });
    }

    public function down(): void
    {
        Schema::table('crm_opportunities', function (Blueprint $table): void {
            $table->dropIndex('idx_crm_opp_proc_group');
            $table->dropIndex('idx_crm_opp_lateralidad');
            $table->dropIndex('idx_crm_opp_episode_at');
            $table->dropIndex('idx_crm_opp_prev_opp');
            $table->dropIndex('idx_crm_opp_type');

            $table->dropColumn([
                'procedure_group',
                'lateralidad',
                'episode_started_at',
                'previous_opportunity_id',
                'opportunity_type',
                'continuity_flag',
            ]);
        });
    }
};

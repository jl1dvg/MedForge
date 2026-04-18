<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('whatsapp_ai_agent_runs')) {
            return;
        }

        Schema::table('whatsapp_ai_agent_runs', function (Blueprint $table): void {
            if (!Schema::hasColumn('whatsapp_ai_agent_runs', 'decision')) {
                $table->string('decision', 32)->nullable()->after('suggested_handoff');
            }
            if (!Schema::hasColumn('whatsapp_ai_agent_runs', 'fallback_used')) {
                $table->boolean('fallback_used')->default(false)->after('decision');
            }
            if (!Schema::hasColumn('whatsapp_ai_agent_runs', 'handoff_reasons')) {
                $table->json('handoff_reasons')->nullable()->after('fallback_used');
            }
            if (!Schema::hasColumn('whatsapp_ai_agent_runs', 'scorecard')) {
                $table->json('scorecard')->nullable()->after('handoff_reasons');
            }
            if (!Schema::hasColumn('whatsapp_ai_agent_runs', 'evaluation')) {
                $table->json('evaluation')->nullable()->after('scorecard');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('whatsapp_ai_agent_runs')) {
            return;
        }

        Schema::table('whatsapp_ai_agent_runs', function (Blueprint $table): void {
            foreach (['evaluation', 'scorecard', 'handoff_reasons', 'fallback_used', 'decision'] as $column) {
                if (Schema::hasColumn('whatsapp_ai_agent_runs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

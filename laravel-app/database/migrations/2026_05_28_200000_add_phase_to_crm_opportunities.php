<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_opportunities', function (Blueprint $table): void {
            // phase: operational (ejecutivos) | commercial (equipo comercial)
            $table->string('phase', 20)->default('operational')->after('stage');
            // Last time any activity (clinical or manual) was registered
            $table->timestamp('last_activity_at')->nullable()->after('assigned_to');
            // When this opportunity should auto-escalate to commercial
            $table->timestamp('escalation_at')->nullable()->after('last_activity_at');

            $table->index(['phase']);
            $table->index(['escalation_at']);
            $table->index(['last_activity_at']);
        });
    }

    public function down(): void
    {
        Schema::table('crm_opportunities', function (Blueprint $table): void {
            $table->dropIndex(['phase']);
            $table->dropIndex(['escalation_at']);
            $table->dropIndex(['last_activity_at']);
            $table->dropColumn(['phase', 'last_activity_at', 'escalation_at']);
        });
    }
};

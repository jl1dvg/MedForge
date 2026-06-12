<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_leads', function (Blueprint $table): void {
            $table->unsignedBigInteger('crm_intent_lead_id')
                ->nullable()
                ->after('crm_lead_id');

            $table->index('crm_intent_lead_id', 'idx_wa_leads_intent_lead');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_leads', function (Blueprint $table): void {
            $table->dropIndex('idx_wa_leads_intent_lead');
            $table->dropColumn('crm_intent_lead_id');
        });
    }
};

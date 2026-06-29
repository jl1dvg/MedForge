<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('whatsapp_handoffs')) {
            return;
        }

        Schema::table('whatsapp_handoffs', function (Blueprint $table): void {
            $table->index(
                ['status', 'assigned_agent_id', 'topic', 'queued_at'],
                'idx_wa_handoff_hot_auto_assign'
            );
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('whatsapp_handoffs')) {
            return;
        }

        Schema::table('whatsapp_handoffs', function (Blueprint $table): void {
            $table->dropIndex('idx_wa_handoff_hot_auto_assign');
        });
    }
};

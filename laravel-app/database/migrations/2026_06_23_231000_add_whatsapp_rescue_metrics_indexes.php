<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_handoff_events')) {
            Schema::table('whatsapp_handoff_events', function (Blueprint $table): void {
                $table->index(['event_type', 'created_at', 'handoff_id'], 'idx_wa_handoff_events_rescue');
            });
        }

        if (Schema::hasTable('whatsapp_sigcenter_bookings')) {
            Schema::table('whatsapp_sigcenter_bookings', function (Blueprint $table): void {
                $table->index(['conversation_id', 'status', 'booked_at'], 'idx_wa_bookings_rescue');
            });
        }

        if (Schema::hasTable('whatsapp_appointment_reminders')) {
            Schema::table('whatsapp_appointment_reminders', function (Blueprint $table): void {
                $table->index(['status', 'sent_at', 'responded_at'], 'idx_wa_reminders_confirmation');
                $table->index(['status', 'failed_at', 'created_at'], 'idx_wa_reminders_failure');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('whatsapp_appointment_reminders')) {
            Schema::table('whatsapp_appointment_reminders', function (Blueprint $table): void {
                $table->dropIndex('idx_wa_reminders_failure');
                $table->dropIndex('idx_wa_reminders_confirmation');
            });
        }

        if (Schema::hasTable('whatsapp_sigcenter_bookings')) {
            Schema::table('whatsapp_sigcenter_bookings', function (Blueprint $table): void {
                $table->dropIndex('idx_wa_bookings_rescue');
            });
        }

        if (Schema::hasTable('whatsapp_handoff_events')) {
            Schema::table('whatsapp_handoff_events', function (Blueprint $table): void {
                $table->dropIndex('idx_wa_handoff_events_rescue');
            });
        }
    }
};

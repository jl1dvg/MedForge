<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('whatsapp_appointment_reminders')) {
            return;
        }

        DB::statement("ALTER TABLE whatsapp_appointment_reminders MODIFY source_type VARCHAR(64) NOT NULL");
    }

    public function down(): void
    {
        if (!Schema::hasTable('whatsapp_appointment_reminders')) {
            return;
        }

        DB::statement("ALTER TABLE whatsapp_appointment_reminders MODIFY source_type VARCHAR(24) NOT NULL");
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('whatsapp_autoresponder_step_actions')) {
            return;
        }

        if (!Schema::hasColumn('whatsapp_autoresponder_step_actions', 'action_type')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement(
                'ALTER TABLE `whatsapp_autoresponder_step_actions` MODIFY `action_type` VARCHAR(64) NOT NULL'
            );

            return;
        }

        if ($driver === 'sqlite') {
            // Tests build this table directly and already use a plain string column.
            return;
        }
    }

    public function down(): void
    {
        // No-op: this migration corrects a legacy production schema mismatch.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('whatsapp_conversation_attributions')) {
            return;
        }

        $db = DB::getDatabaseName();
        $exists = DB::selectOne(
            'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$db, 'whatsapp_conversation_attributions', 'idx_wa_conv_attr_platform']
        );

        if (!$exists) {
            Schema::table('whatsapp_conversation_attributions', function (Blueprint $table): void {
                $table->index('platform', 'idx_wa_conv_attr_platform');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('whatsapp_conversation_attributions')) {
            return;
        }

        $db = DB::getDatabaseName();
        $exists = DB::selectOne(
            'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$db, 'whatsapp_conversation_attributions', 'idx_wa_conv_attr_platform']
        );

        if ($exists) {
            Schema::table('whatsapp_conversation_attributions', function (Blueprint $table): void {
                $table->dropIndex('idx_wa_conv_attr_platform');
            });
        }
    }
};

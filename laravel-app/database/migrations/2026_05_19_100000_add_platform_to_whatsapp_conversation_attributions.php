<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('whatsapp_conversation_attributions')) {
            return;
        }

        Schema::table('whatsapp_conversation_attributions', function (Blueprint $table): void {
            if (!Schema::hasColumn('whatsapp_conversation_attributions', 'platform')) {
                $table->string('platform', 32)->nullable()->after('source_type');
            }
            if (!$this->hasIndex('idx_wa_conv_attr_platform')) {
                $table->index('platform', 'idx_wa_conv_attr_platform');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('whatsapp_conversation_attributions')) {
            return;
        }

        Schema::table('whatsapp_conversation_attributions', function (Blueprint $table): void {
            if ($this->hasIndex('idx_wa_conv_attr_platform')) {
                $table->dropIndex('idx_wa_conv_attr_platform');
            }
            if (Schema::hasColumn('whatsapp_conversation_attributions', 'platform')) {
                $table->dropColumn('platform');
            }
        });
    }

    private function hasIndex(string $indexName): bool
    {
        $db = \Illuminate\Support\Facades\DB::getDatabaseName();
        return (bool) \Illuminate\Support\Facades\DB::selectOne(
            'SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$db, 'whatsapp_conversation_attributions', $indexName]
        );
    }
};

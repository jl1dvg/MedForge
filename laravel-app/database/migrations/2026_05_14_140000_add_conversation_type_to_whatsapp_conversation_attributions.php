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
            if (!Schema::hasColumn('whatsapp_conversation_attributions', 'conversation_type')) {
                $table->string('conversation_type', 64)->nullable()->after('initial_intent');
                $table->index('conversation_type', 'idx_wa_conv_attr_conversation_type');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('whatsapp_conversation_attributions')) {
            return;
        }

        Schema::table('whatsapp_conversation_attributions', function (Blueprint $table): void {
            if (Schema::hasColumn('whatsapp_conversation_attributions', 'conversation_type')) {
                $table->dropIndex('idx_wa_conv_attr_conversation_type');
                $table->dropColumn('conversation_type');
            }
        });
    }
};

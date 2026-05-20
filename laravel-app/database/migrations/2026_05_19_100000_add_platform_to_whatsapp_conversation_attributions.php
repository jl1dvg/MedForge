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
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('whatsapp_conversation_attributions')) {
            return;
        }

        Schema::table('whatsapp_conversation_attributions', function (Blueprint $table): void {
            if (Schema::hasColumn('whatsapp_conversation_attributions', 'platform')) {
                $table->dropColumn('platform');
            }
        });
    }
};

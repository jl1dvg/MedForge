<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table): void {
            if (!Schema::hasColumn('whatsapp_conversations', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('handoff_requested_at');
            }

            if (!Schema::hasColumn('whatsapp_conversations', 'closed_by_user_id')) {
                $table->unsignedBigInteger('closed_by_user_id')->nullable()->index()->after('closed_at');
            }

            if (!Schema::hasColumn('whatsapp_conversations', 'close_reason')) {
                $table->string('close_reason', 64)->nullable()->index()->after('closed_by_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table): void {
            foreach (['close_reason', 'closed_by_user_id', 'closed_at'] as $column) {
                if (Schema::hasColumn('whatsapp_conversations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

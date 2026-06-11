<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table): void {
            $table->string('sender_type', 16)->nullable();
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->index(['sender_type', 'sender_id'], 'idx_wa_messages_sender');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table): void {
            $table->dropIndex('idx_wa_messages_sender');
            $table->dropColumn(['sender_type', 'sender_id']);
        });
    }
};

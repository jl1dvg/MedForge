<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('whatsapp_quick_replies')) {
            Schema::create('whatsapp_quick_replies', function (Blueprint $table): void {
                $table->id();
                $table->string('title', 120);
                $table->string('shortcut', 64)->nullable();
                $table->text('body');
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['is_active', 'title'], 'idx_wa_quick_replies_active_title');
                $table->index('shortcut', 'idx_wa_quick_replies_shortcut');
            });
        }

        if (!Schema::hasTable('whatsapp_conversation_notes')) {
            Schema::create('whatsapp_conversation_notes', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('conversation_id');
                $table->unsignedBigInteger('author_user_id')->nullable();
                $table->text('body');
                $table->timestamps();

                $table->index(['conversation_id', 'id'], 'idx_wa_conversation_notes_conv_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversation_notes');
        Schema::dropIfExists('whatsapp_quick_replies');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('whatsapp_conversations')) {
            Schema::create('whatsapp_conversations', function (Blueprint $table): void {
                $table->id();
                $table->string('wa_number', 32)->unique();
                $table->string('display_name', 191)->nullable();
                $table->string('patient_hc_number', 64)->nullable();
                $table->string('patient_full_name', 191)->nullable();
                $table->timestamp('last_message_at')->nullable();
                $table->string('last_message_direction', 32)->nullable();
                $table->string('last_message_type', 64)->nullable();
                $table->string('last_message_preview', 512)->nullable();
                $table->boolean('needs_human')->default(false);
                $table->text('handoff_notes')->nullable();
                $table->unsignedBigInteger('handoff_role_id')->nullable();
                $table->unsignedBigInteger('assigned_user_id')->nullable();
                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('handoff_requested_at')->nullable();
                $table->unsignedInteger('unread_count')->default(0);
                $table->timestamps();

                $table->index(['needs_human', 'updated_at'], 'idx_wa_conv_human_updated');
                $table->index('assigned_user_id', 'idx_wa_conv_assigned_user');
                $table->index('handoff_role_id', 'idx_wa_conv_handoff_role');
                $table->index('last_message_at', 'idx_wa_conv_last_message_at');
            });
        }

        if (!Schema::hasTable('whatsapp_messages')) {
            Schema::create('whatsapp_messages', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('conversation_id');
                $table->string('wa_message_id', 191)->nullable();
                $table->string('direction', 16);
                $table->string('message_type', 64)->default('text');
                $table->longText('body')->nullable();
                $table->json('raw_payload')->nullable();
                $table->string('status', 32)->nullable();
                $table->timestamp('message_timestamp')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->unique('wa_message_id', 'uq_wa_messages_wa_message_id');
                $table->index(['conversation_id', 'id'], 'idx_wa_messages_conv_id');
                $table->index('message_timestamp', 'idx_wa_messages_timestamp');
                $table->index('direction', 'idx_wa_messages_direction');

                $table->foreign('conversation_id', 'fk_wa_messages_conversation')
                    ->references('id')
                    ->on('whatsapp_conversations')
                    ->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('whatsapp_contact_consent')) {
            Schema::create('whatsapp_contact_consent', function (Blueprint $table): void {
                $table->id();
                $table->string('wa_number', 32);
                $table->string('cedula', 32);
                $table->string('patient_hc_number', 64)->nullable();
                $table->string('patient_full_name', 191)->nullable();
                $table->string('consent_status', 24)->default('pending');
                $table->string('consent_source', 64)->default('whatsapp');
                $table->timestamp('consent_asked_at')->nullable();
                $table->timestamp('consent_responded_at')->nullable();
                $table->json('extra_payload')->nullable();
                $table->timestamps();

                $table->unique(['wa_number', 'cedula'], 'uq_wa_consent_number_cedula');
                $table->index('consent_status', 'idx_wa_consent_status');
            });
        }

        if (!Schema::hasTable('whatsapp_inbox_messages')) {
            Schema::create('whatsapp_inbox_messages', function (Blueprint $table): void {
                $table->id();
                $table->string('wa_number', 32);
                $table->string('direction', 16);
                $table->string('message_type', 64)->default('text');
                $table->longText('message_body');
                $table->string('message_id', 191)->nullable();
                $table->longText('payload')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->index(['wa_number', 'id'], 'idx_wa_inbox_number_id');
                $table->index('message_id', 'idx_wa_inbox_message_id');
            });
        }

        if (!Schema::hasTable('whatsapp_handoffs')) {
            Schema::create('whatsapp_handoffs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('conversation_id');
                $table->string('wa_number', 32);
                $table->string('status', 24)->default('queued');
                $table->string('priority', 24)->default('normal');
                $table->string('topic', 191)->nullable();
                $table->unsignedBigInteger('handoff_role_id')->nullable();
                $table->unsignedBigInteger('assigned_agent_id')->nullable();
                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('assigned_until')->nullable();
                $table->timestamp('queued_at')->nullable();
                $table->timestamp('last_activity_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['conversation_id', 'status'], 'idx_wa_handoff_conv_status');
                $table->index(['status', 'assigned_until'], 'idx_wa_handoff_status_until');
                $table->index('assigned_agent_id', 'idx_wa_handoff_assigned_agent');

                $table->foreign('conversation_id', 'fk_wa_handoff_conversation')
                    ->references('id')
                    ->on('whatsapp_conversations')
                    ->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('whatsapp_handoff_events')) {
            Schema::create('whatsapp_handoff_events', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('handoff_id');
                $table->string('event_type', 64);
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['handoff_id', 'created_at'], 'idx_wa_handoff_events_handoff_created');
                $table->index('event_type', 'idx_wa_handoff_events_type');

                $table->foreign('handoff_id', 'fk_wa_handoff_events_handoff')
                    ->references('id')
                    ->on('whatsapp_handoffs')
                    ->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('whatsapp_agent_presence')) {
            Schema::create('whatsapp_agent_presence', function (Blueprint $table): void {
                $table->unsignedBigInteger('user_id')->primary();
                $table->string('status', 24)->default('available');
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

                $table->index('status', 'idx_wa_agent_presence_status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_agent_presence');
        Schema::dropIfExists('whatsapp_handoff_events');
        Schema::dropIfExists('whatsapp_handoffs');
        Schema::dropIfExists('whatsapp_inbox_messages');
        Schema::dropIfExists('whatsapp_contact_consent');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_conversations');
    }
};

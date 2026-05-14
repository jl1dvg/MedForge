<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('whatsapp_conversation_attributions')) {
            Schema::create('whatsapp_conversation_attributions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('conversation_id');
                $table->unsignedBigInteger('first_message_id')->nullable();
                $table->unsignedBigInteger('first_inbound_message_id')->nullable();
                $table->string('source_category', 48)->default('unknown');
                $table->string('source_type', 64)->nullable();
                $table->string('source_id', 191)->nullable();
                $table->text('source_url')->nullable();
                $table->string('media_type', 64)->nullable();
                $table->string('headline', 191)->nullable();
                $table->text('body')->nullable();
                $table->text('video_url')->nullable();
                $table->text('thumbnail_url')->nullable();
                $table->string('ctwa_clid', 255)->nullable();
                $table->text('welcome_message_text')->nullable();
                $table->string('profile_name', 191)->nullable();
                $table->string('initial_intent', 64)->nullable();
                $table->string('patient_segment', 64)->nullable();
                $table->string('patient_hc_number', 64)->nullable();
                $table->timestamp('last_clinical_touch_at')->nullable();
                $table->timestamp('first_seen_at')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique('conversation_id', 'uq_wa_conv_attr_conversation');
                $table->index(['source_category', 'first_seen_at'], 'idx_wa_conv_attr_source_seen');
                $table->index(['source_id', 'first_seen_at'], 'idx_wa_conv_attr_source_id_seen');
                $table->index('initial_intent', 'idx_wa_conv_attr_initial_intent');
                $table->index('patient_segment', 'idx_wa_conv_attr_patient_segment');
            });
        }

        $this->normalizeConversationIdColumnType();
        $this->ensureConversationForeignKey();
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversation_attributions');
    }

    private function normalizeConversationIdColumnType(): void
    {
        if (!Schema::hasTable('whatsapp_conversations') || !Schema::hasTable('whatsapp_conversation_attributions')) {
            return;
        }

        $column = DB::selectOne("SHOW COLUMNS FROM `whatsapp_conversations` LIKE 'id'");
        $type = strtolower((string) ($column->Type ?? 'bigint(20) unsigned'));
        if ($type === '') {
            $type = 'bigint(20) unsigned';
        }

        DB::statement('ALTER TABLE `whatsapp_conversation_attributions` MODIFY `conversation_id` ' . $type . ' NOT NULL');
    }

    private function ensureConversationForeignKey(): void
    {
        if (!Schema::hasTable('whatsapp_conversations') || !Schema::hasTable('whatsapp_conversation_attributions')) {
            return;
        }

        $database = DB::getDatabaseName();
        $existing = DB::selectOne(
            'SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME = ?
             LIMIT 1',
            [$database, 'whatsapp_conversation_attributions', 'conversation_id', 'whatsapp_conversations']
        );

        if ($existing !== null) {
            return;
        }

        Schema::table('whatsapp_conversation_attributions', function (Blueprint $table): void {
            $table->foreign('conversation_id', 'fk_wa_conv_attr_conversation')
                ->references('id')
                ->on('whatsapp_conversations')
                ->cascadeOnDelete();
        });
    }
};

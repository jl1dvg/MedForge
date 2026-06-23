<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_audit_logs', function (Blueprint $table): void {
            $table->id();

            // Referencias
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->unsignedBigInteger('message_id')->nullable();
            $table->string('wa_number', 32)->nullable();
            $table->string('patient_hc_number', 64)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();

            // Clasificación del evento
            $table->string('event_type', 64);
            $table->enum('severity', ['info', 'warning', 'error', 'critical'])->default('info');

            // Descripción
            $table->string('summary', 512)->nullable();
            $table->json('payload')->nullable();

            // Bot decision tracking
            $table->string('scenario_id', 191)->nullable();
            $table->string('node_id', 191)->nullable();
            $table->string('action_type', 64)->nullable();

            // Estado antes/después
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();

            // Error tracking
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->string('meta_request_id', 191)->nullable();

            // Timing con millisegundos
            $table->timestamp('occurred_at', 3)->useCurrent();
            $table->timestamps();

            // Índices
            $table->index(['conversation_id', 'occurred_at'], 'idx_wa_audit_conversation');
            $table->index(['event_type', 'occurred_at'], 'idx_wa_audit_event_type');
            $table->index(['severity', 'occurred_at'], 'idx_wa_audit_severity');
            $table->index('occurred_at', 'idx_wa_audit_occurred_at');
            $table->index('wa_number', 'idx_wa_audit_wa_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_audit_logs');
    }
};

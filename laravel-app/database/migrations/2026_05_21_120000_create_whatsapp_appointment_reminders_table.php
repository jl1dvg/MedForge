<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_appointment_reminders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->string('wa_number', 32)->nullable();
            $table->string('hc_number', 64);
            $table->unsignedBigInteger('form_id');
            $table->string('source_type', 24);
            $table->string('template_code', 191);
            $table->string('reminder_window', 16);
            $table->string('dedupe_key', 191)->unique();
            $table->dateTime('event_at');
            $table->string('status', 24)->default('pending');
            $table->string('template_message_id', 191)->nullable();
            $table->json('payload')->nullable();
            $table->string('response_value', 64)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['hc_number', 'status']);
            $table->index(['wa_number', 'status']);
            $table->index(['source_type', 'reminder_window']);
            $table->index(['event_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_appointment_reminders');
    }
};

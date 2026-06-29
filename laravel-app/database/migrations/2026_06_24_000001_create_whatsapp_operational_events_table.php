<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_operational_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('handoff_id')->nullable();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedBigInteger('reminder_id')->nullable();
            $table->unsignedBigInteger('message_id')->nullable();
            $table->string('event_type', 96);
            $table->string('event_group', 48);
            $table->dateTime('event_at');
            $table->string('actor_type', 32)->default('system');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('producer', 96);
            $table->string('bucket', 48)->nullable();
            $table->string('topic', 96)->nullable();
            $table->decimal('priority_score', 8, 2)->nullable();
            $table->string('wa_number', 32)->nullable();
            $table->string('patient_hc_number', 64)->nullable();
            $table->string('reason', 191)->nullable();
            $table->json('payload')->nullable();
            $table->string('idempotency_key', 191)->unique();
            $table->timestamps();

            $table->index(['conversation_id', 'event_at'], 'wa_operational_events_conversation_at_idx');
            $table->index(['event_type', 'event_at'], 'wa_operational_events_type_at_idx');
            $table->index(['handoff_id', 'event_at'], 'wa_operational_events_handoff_at_idx');
            $table->index('booking_id', 'wa_operational_events_booking_idx');
            $table->index(['wa_number', 'event_at'], 'wa_operational_events_wa_at_idx');
            $table->index(['patient_hc_number', 'event_at'], 'wa_operational_events_hc_at_idx');
            $table->index(['bucket', 'event_at'], 'wa_operational_events_bucket_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_operational_events');
    }
};

<?php

namespace Tests\Feature;

use App\Modules\Whatsapp\Services\WhatsappOperationalAttributionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappOperationalAttributionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('whatsapp_operational_booking_attributions');
        Schema::dropIfExists('whatsapp_operational_events');
        Schema::dropIfExists('procedimiento_proyectado');
        Schema::dropIfExists('patient_data');
        Schema::dropIfExists('whatsapp_sigcenter_bookings');
        Schema::dropIfExists('whatsapp_handoff_events');
        Schema::dropIfExists('whatsapp_handoffs');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_conversations');

        Schema::create('whatsapp_conversations', function (Blueprint $table): void {
            $table->id();
            $table->string('wa_number', 32)->index();
            $table->string('patient_hc_number', 64)->nullable()->index();
            $table->boolean('needs_human')->default(false);
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('handoff_requested_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_handoffs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->index();
            $table->string('wa_number', 32)->index();
            $table->string('status', 24)->default('queued');
            $table->string('topic', 191)->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_handoff_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('handoff_id')->index();
            $table->string('event_type', 64)->index();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('whatsapp_operational_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->index();
            $table->unsignedBigInteger('handoff_id')->nullable()->index();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedBigInteger('reminder_id')->nullable();
            $table->unsignedBigInteger('message_id')->nullable();
            $table->string('event_type', 96)->index();
            $table->string('event_group', 48);
            $table->dateTime('event_at')->index();
            $table->string('actor_type', 32)->default('system');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('producer', 96);
            $table->string('bucket', 48)->nullable();
            $table->string('topic', 96)->nullable();
            $table->decimal('priority_score', 8, 2)->nullable();
            $table->string('wa_number', 32)->nullable()->index();
            $table->string('patient_hc_number', 64)->nullable()->index();
            $table->string('reason', 191)->nullable();
            $table->json('payload')->nullable();
            $table->string('idempotency_key', 191)->unique();
            $table->timestamps();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->index();
            $table->string('direction', 16);
            $table->string('sender_type', 32)->nullable();
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->timestamp('message_timestamp')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_sigcenter_bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->nullable()->index();
            $table->string('wa_number', 32)->index();
            $table->string('status', 32)->default('created');
            $table->string('patient_hc_number', 64)->nullable()->index();
            $table->timestamp('booked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('procedimiento_proyectado', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('form_id')->unique();
            $table->string('hc_number', 64)->index();
            $table->date('fecha');
            $table->time('hora')->nullable();
            $table->string('sede_departamento', 191)->nullable();
            $table->string('medico_nombre', 191)->nullable();
            $table->string('procedimiento_nombre', 191)->nullable();
            $table->boolean('sigcenter_present')->default(true);
            $table->timestamps();
        });

        Schema::create('whatsapp_operational_booking_attributions', function (Blueprint $table): void {
            $table->id();
            $table->string('booking_source', 48)->default('bot_api');
            $table->string('observed_booking_key', 191)->unique();
            $table->unsignedBigInteger('booking_id')->nullable()->index();
            $table->unsignedBigInteger('form_id')->nullable()->index();
            $table->unsignedBigInteger('booking_conversation_id')->nullable();
            $table->unsignedBigInteger('attributed_conversation_id')->nullable();
            $table->unsignedBigInteger('handoff_id')->nullable();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->string('event_type', 64);
            $table->string('attribution_method', 64);
            $table->string('confidence', 24);
            $table->timestamp('event_at')->nullable();
            $table->timestamp('booking_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_booking_with_prior_auto_assigned_same_conversation_is_high_confidence(): void
    {
        $bookingAt = Carbon::parse('2026-06-24 10:00:00');
        $this->seedConversation(1, '593001', 'HC-1');
        $this->seedHandoff(10, 1, '593001');
        $this->seedEvent(100, 10, 'auto_assigned', $bookingAt->copy()->subHours(2));
        $this->seedBooking(1000, 1, '593001', 'HC-1', $bookingAt);

        $result = app(WhatsappOperationalAttributionService::class)
            ->refresh(Carbon::parse('2026-06-24'), Carbon::parse('2026-06-25'));

        $this->assertSame(1, $result['created']);
        $this->assertDatabaseHas('whatsapp_operational_booking_attributions', [
            'booking_source' => 'bot_api',
            'observed_booking_key' => 'whatsapp_sigcenter_bookings:1000',
            'booking_id' => 1000,
            'event_id' => 100,
            'event_type' => 'auto_assigned',
            'attribution_method' => 'same_conversation_7d',
            'confidence' => 'high',
        ]);
    }

    public function test_booking_without_prior_operational_event_is_not_attributed(): void
    {
        $this->seedConversation(1, '593001', 'HC-1');
        $this->seedBooking(1000, 1, '593001', 'HC-1', Carbon::parse('2026-06-24 10:00:00'));

        app(WhatsappOperationalAttributionService::class)
            ->refresh(Carbon::parse('2026-06-24'), Carbon::parse('2026-06-25'));

        $this->assertDatabaseCount('whatsapp_operational_booking_attributions', 0);
    }

    public function test_event_after_booking_is_not_attributed(): void
    {
        $bookingAt = Carbon::parse('2026-06-24 10:00:00');
        $this->seedConversation(1, '593001', 'HC-1');
        $this->seedHandoff(10, 1, '593001');
        $this->seedEvent(100, 10, 'auto_assigned', $bookingAt->copy()->addMinute());
        $this->seedBooking(1000, 1, '593001', 'HC-1', $bookingAt);

        app(WhatsappOperationalAttributionService::class)
            ->refresh(Carbon::parse('2026-06-24'), Carbon::parse('2026-06-25'));

        $this->assertDatabaseCount('whatsapp_operational_booking_attributions', 0);
    }

    public function test_expired_is_system_event_and_does_not_count_as_rescue_attribution(): void
    {
        $bookingAt = Carbon::parse('2026-06-24 10:00:00');
        $this->seedConversation(1, '593001', 'HC-1');
        $this->seedHandoff(10, 1, '593001');
        $this->seedEvent(100, 10, 'expired', $bookingAt->copy()->subHour());
        $this->seedBooking(1000, 1, '593001', 'HC-1', $bookingAt);

        app(WhatsappOperationalAttributionService::class)
            ->refresh(Carbon::parse('2026-06-24'), Carbon::parse('2026-06-25'));

        $this->assertDatabaseCount('whatsapp_operational_booking_attributions', 0);
    }

    public function test_patient_match_outside_72_hour_window_is_not_attributed(): void
    {
        $bookingAt = Carbon::parse('2026-06-24 10:00:00');
        $this->seedConversation(1, '593001', 'HC-77');
        $this->seedConversation(2, '593002', 'HC-77');
        $this->seedHandoff(10, 1, '593001');
        $this->seedEvent(100, 10, 'auto_assigned', $bookingAt->copy()->subDays(4));
        $this->seedBooking(1000, 2, '593002', 'HC-77', $bookingAt);

        app(WhatsappOperationalAttributionService::class)
            ->refresh(Carbon::parse('2026-06-24'), Carbon::parse('2026-06-25'));

        $this->assertDatabaseCount('whatsapp_operational_booking_attributions', 0);
    }

    public function test_same_patient_match_within_72_hours_is_medium_confidence(): void
    {
        $bookingAt = Carbon::parse('2026-06-24 10:00:00');
        $this->seedConversation(1, '593001', 'HC-77');
        $this->seedConversation(2, '593002', 'HC-77');
        $this->seedHandoff(10, 1, '593001');
        $this->seedEvent(100, 10, 'supervisor_alerted', $bookingAt->copy()->subHours(24));
        $this->seedBooking(1000, 2, '593002', 'HC-77', $bookingAt);

        app(WhatsappOperationalAttributionService::class)
            ->refresh(Carbon::parse('2026-06-24'), Carbon::parse('2026-06-25'));

        $this->assertDatabaseHas('whatsapp_operational_booking_attributions', [
            'booking_id' => 1000,
            'event_type' => 'supervisor_alerted',
            'attribution_method' => 'same_patient_hc_number_72h',
            'confidence' => 'medium',
        ]);
    }

    public function test_same_wa_number_match_within_72_hours_is_medium_confidence(): void
    {
        $bookingAt = Carbon::parse('2026-06-24 10:00:00');
        $this->seedConversation(1, '593001', 'HC-1');
        $this->seedConversation(2, '593001', 'HC-2');
        $this->seedHandoff(10, 1, '593001');
        $this->seedEvent(100, 10, 'requeued', $bookingAt->copy()->subHours(12));
        $this->seedBooking(1000, 2, '593001', 'HC-2', $bookingAt);

        app(WhatsappOperationalAttributionService::class)
            ->refresh(Carbon::parse('2026-06-24'), Carbon::parse('2026-06-25'));

        $this->assertDatabaseHas('whatsapp_operational_booking_attributions', [
            'booking_id' => 1000,
            'event_type' => 'handoff_requeued',
            'attribution_method' => 'same_wa_number_72h',
            'confidence' => 'medium',
        ]);
    }

    public function test_manual_attributed_appointment_with_prior_operational_event_is_attributed(): void
    {
        $bookingAt = Carbon::parse('2026-06-24 10:00:00');
        $this->seedConversation(1, '593001', 'HC-1');
        $this->seedHandoff(10, 1, '593001');
        $this->seedEvent(100, 10, 'agent_taken', $bookingAt->copy()->subHour());
        $this->seedHumanMessage(1, 5, $bookingAt->copy()->subHour());
        $this->seedManualAppointment(777, 'HC-1', $bookingAt);

        $result = app(WhatsappOperationalAttributionService::class)
            ->refresh(Carbon::parse('2026-06-24'), Carbon::parse('2026-06-25'));

        $this->assertSame(1, $result['created']);
        $this->assertDatabaseHas('whatsapp_operational_booking_attributions', [
            'booking_source' => 'manual_sigcenter',
            'observed_booking_key' => 'procedimiento_proyectado:777',
            'booking_id' => null,
            'form_id' => 777,
            'event_type' => 'agent_taken',
            'attribution_method' => 'same_conversation_7d',
            'confidence' => 'high',
        ]);
    }

    private function seedConversation(int $id, string $waNumber, ?string $patientHcNumber): void
    {
        DB::table('whatsapp_conversations')->insert([
            'id' => $id,
            'wa_number' => $waNumber,
            'patient_hc_number' => $patientHcNumber,
            'needs_human' => true,
            'assigned_user_id' => null,
            'assigned_at' => null,
            'handoff_requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedHandoff(int $id, int $conversationId, string $waNumber): void
    {
        DB::table('whatsapp_handoffs')->insert([
            'id' => $id,
            'conversation_id' => $conversationId,
            'wa_number' => $waNumber,
            'status' => 'queued',
            'topic' => 'captacion_agendar',
            'queued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedEvent(int $id, int $handoffId, string $eventType, Carbon $createdAt): void
    {
        $handoff = DB::table('whatsapp_handoffs')->where('id', $handoffId)->first();
        $conversation = $handoff !== null
            ? DB::table('whatsapp_conversations')->where('id', $handoff->conversation_id)->first()
            : null;

        DB::table('whatsapp_handoff_events')->insert([
            'id' => $id,
            'handoff_id' => $handoffId,
            'event_type' => $eventType,
            'created_at' => $createdAt,
        ]);

        if ($handoff !== null && $conversation !== null && $eventType !== 'expired') {
            DB::table('whatsapp_operational_events')->insert([
                'id' => $id,
                'conversation_id' => (int) $handoff->conversation_id,
                'handoff_id' => $handoffId,
                'event_type' => $eventType === 'requeued' ? 'handoff_requeued' : $eventType,
                'event_group' => $eventType === 'auto_assigned' || $eventType === 'agent_taken' ? 'assignment' : 'handoff',
                'event_at' => $createdAt,
                'actor_type' => 'system',
                'producer' => 'test',
                'topic' => $handoff->topic,
                'wa_number' => $conversation->wa_number,
                'patient_hc_number' => $conversation->patient_hc_number,
                'idempotency_key' => 'test-event:' . $id,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }

    private function seedBooking(int $id, ?int $conversationId, string $waNumber, ?string $patientHcNumber, Carbon $bookedAt): void
    {
        DB::table('whatsapp_sigcenter_bookings')->insert([
            'id' => $id,
            'conversation_id' => $conversationId,
            'wa_number' => $waNumber,
            'status' => 'created',
            'patient_hc_number' => $patientHcNumber,
            'booked_at' => $bookedAt,
            'created_at' => $bookedAt,
            'updated_at' => $bookedAt,
        ]);
    }

    private function seedHumanMessage(int $conversationId, int $agentId, Carbon $at): void
    {
        DB::table('whatsapp_messages')->insert([
            'conversation_id' => $conversationId,
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'sender_id' => $agentId,
            'message_timestamp' => $at,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    private function seedManualAppointment(int $formId, string $hcNumber, Carbon $createdAt): void
    {
        DB::table('procedimiento_proyectado')->insert([
            'form_id' => $formId,
            'hc_number' => $hcNumber,
            'fecha' => $createdAt->copy()->addDay()->toDateString(),
            'hora' => '10:30:00',
            'sede_departamento' => 'CIVE',
            'medico_nombre' => 'Dr. Test',
            'procedimiento_nombre' => 'Consulta',
            'sigcenter_present' => true,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}

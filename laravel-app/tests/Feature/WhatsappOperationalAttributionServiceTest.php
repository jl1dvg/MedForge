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
        Schema::dropIfExists('whatsapp_sigcenter_bookings');
        Schema::dropIfExists('whatsapp_handoff_events');
        Schema::dropIfExists('whatsapp_handoffs');
        Schema::dropIfExists('whatsapp_conversations');

        Schema::create('whatsapp_conversations', function (Blueprint $table): void {
            $table->id();
            $table->string('wa_number', 32)->index();
            $table->string('patient_hc_number', 64)->nullable()->index();
            $table->boolean('needs_human')->default(false);
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

        Schema::create('whatsapp_sigcenter_bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->nullable()->index();
            $table->string('wa_number', 32)->index();
            $table->string('status', 32)->default('created');
            $table->string('patient_hc_number', 64)->nullable()->index();
            $table->timestamp('booked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_operational_booking_attributions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('booking_id')->unique();
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

    private function seedConversation(int $id, string $waNumber, ?string $patientHcNumber): void
    {
        DB::table('whatsapp_conversations')->insert([
            'id' => $id,
            'wa_number' => $waNumber,
            'patient_hc_number' => $patientHcNumber,
            'needs_human' => true,
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
        DB::table('whatsapp_handoff_events')->insert([
            'id' => $id,
            'handoff_id' => $handoffId,
            'event_type' => $eventType,
            'created_at' => $createdAt,
        ]);
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
}

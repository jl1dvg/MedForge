<?php

namespace Tests\Feature;

use App\Modules\Whatsapp\Services\WhatsappOperationalBaselineService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappOperationalBaselineServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('whatsapp_operational_snapshots');
        Schema::dropIfExists('whatsapp_operational_booking_attributions');
        Schema::dropIfExists('whatsapp_operational_events');
        Schema::dropIfExists('whatsapp_appointment_reminders');
        Schema::dropIfExists('procedimiento_proyectado');
        Schema::dropIfExists('patient_data');
        Schema::dropIfExists('whatsapp_sigcenter_bookings');
        Schema::dropIfExists('whatsapp_handoff_events');
        Schema::dropIfExists('whatsapp_handoffs');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_conversation_attributions');
        Schema::dropIfExists('whatsapp_conversations');

        Schema::create('whatsapp_conversations', function (Blueprint $table): void {
            $table->id();
            $table->string('wa_number', 32)->unique();
            $table->string('display_name', 191)->nullable();
            $table->string('patient_hc_number', 64)->nullable();
            $table->string('patient_full_name', 191)->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->string('last_message_direction', 32)->nullable();
            $table->boolean('needs_human')->default(false);
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('handoff_requested_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_conversation_attributions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->unique();
            $table->string('source_category', 48)->default('unknown');
            $table->string('initial_intent', 64)->nullable();
            $table->string('patient_segment', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('direction', 16);
            $table->string('sender_type', 32)->nullable();
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->longText('body')->nullable();
            $table->timestamp('message_timestamp')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_handoffs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('wa_number', 32);
            $table->string('status', 24)->default('queued');
            $table->string('priority', 24)->default('normal');
            $table->string('topic', 191)->nullable();
            $table->unsignedBigInteger('assigned_agent_id')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_handoff_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('handoff_id');
            $table->string('event_type', 64);
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

        Schema::create('whatsapp_sigcenter_bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->string('wa_number', 32);
            $table->string('status', 32)->default('created');
            $table->string('patient_hc_number', 64)->nullable();
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

        Schema::create('whatsapp_appointment_reminders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->string('wa_number', 32)->nullable();
            $table->string('hc_number', 64);
            $table->unsignedBigInteger('form_id');
            $table->string('source_type', 64);
            $table->string('template_code', 191);
            $table->string('reminder_window', 16);
            $table->string('dedupe_key', 191)->unique();
            $table->dateTime('event_at');
            $table->string('status', 24)->default('pending');
            $table->string('response_value', 64)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->text('notes')->nullable();
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

        Schema::create('whatsapp_operational_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->date('snapshot_date')->unique();
            $table->json('payload');
            $table->unsignedInteger('hot_open_total')->default(0);
            $table->unsignedInteger('hot_open_unassigned')->default(0);
            $table->unsignedInteger('hot_open_assigned')->default(0);
            $table->unsignedInteger('hot_open_booked')->default(0);
            $table->unsignedInteger('hot_needs_template_total')->default(0);
            $table->unsignedInteger('hot_needs_template_booked')->default(0);
            $table->unsignedInteger('rescue_total')->default(0);
            $table->unsignedInteger('rescue_booked')->default(0);
            $table->unsignedInteger('backlog_total')->default(0);
            $table->unsignedInteger('lost_total')->default(0);
            $table->unsignedInteger('rescued_bookings')->default(0);
            $table->unsignedInteger('autoassigned_bookings')->default(0);
            $table->unsignedInteger('reminder_confirmations')->default(0);
            $table->unsignedInteger('reminder_failures')->default(0);
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_it_builds_operational_baseline_by_bucket(): void
    {
        Carbon::setTestNow('2026-06-24 12:00:00');

        $this->seedOpportunity(1, now()->subHours(2), 'captacion_agendar', latestInboundAt: now()->subHour(), source: 'ad');
        $this->seedOpportunity(2, now()->subHours(4), 'captacion_agendar', latestInboundAt: now()->subHours(28), source: 'organic_direct', assignedUserId: 9);
        $this->seedOpportunity(3, now()->subDays(2), 'operacion_reagenda', latestInboundAt: now()->subDays(2), source: 'campaign_outbound', bookedAt: now()->subHour());
        $this->seedOpportunity(4, now()->subDays(10), 'faq_escalada', latestInboundAt: now()->subDays(10), source: 'ad');
        $this->seedOpportunity(5, now()->subDays(35), 'agenda_sin_disponibilidad', latestInboundAt: now()->subDays(35), source: 'patient_return');

        $this->seedEvent(3, 'auto_assigned', now()->subDays(2)->addHour());
        $this->seedEvent(3, 'requeued', now()->subDays(2)->addMinutes(10));
        $this->seedBookingAttribution('bot_api', 'whatsapp_sigcenter_bookings:1', 1, null, 3, 3, 1, 'auto_assigned', now()->subDays(2)->addHour(), now()->subHour());
        $this->seedOutbound(3, now()->subDays(2)->addHours(2));
        $this->seedOutbound(3, now()->subHours(2));
        $this->seedManualAppointment(500, 'HC-3', now()->subHour());
        $this->seedReminder(3, 'responded', now()->subHours(2), 'confirmar');
        $this->seedReminder(4, 'failed', now()->subHours(3), null, 'Meta rejected template');

        $baseline = (new WhatsappOperationalBaselineService())->baseline(
            Carbon::parse('2026-06-24'),
            now(),
            false
        );

        $this->assertSame(1, $baseline['buckets']['hot_open']['total_conversations']);
        $this->assertSame(1, $baseline['buckets']['hot_open']['unassigned']);
        $this->assertSame(1, $baseline['buckets']['hot_needs_template']['total_conversations']);
        $this->assertSame(1, $baseline['buckets']['hot_needs_template']['assigned']);
        $this->assertSame(1, $baseline['buckets']['rescue']['total_conversations']);
        $this->assertSame(1, $baseline['buckets']['rescue']['with_booking']);
        $this->assertSame(100.0, $baseline['buckets']['rescue']['conversion_rate']);
        $this->assertSame(1, $baseline['buckets']['backlog']['total_conversations']);
        $this->assertSame(1, $baseline['buckets']['lost']['total_conversations']);
        $this->assertSame(1, $baseline['bookings_after_operational_intervention']['total']);
        $this->assertSame(1, $baseline['bookings_after_operational_intervention']['by_event']['auto_assigned']);
        $this->assertSame(1, $baseline['observed_bot_bookings']);
        $this->assertSame(1, $baseline['observed_manual_bookings']);
        $this->assertSame(1, $baseline['inferred_attributed_appointments']);
        $this->assertSame(0, $baseline['operational_interventions_without_observed_booking']);
        $this->assertSame(1, $baseline['buckets']['rescue']['conversion_after_autoassign']);
        $this->assertSame(1, $baseline['buckets']['rescue']['conversion_after_requeue']);
        $this->assertSame(1, $baseline['reminders']['confirmed']);
        $this->assertSame(1, $baseline['reminders']['failed']);

        Carbon::setTestNow();
    }

    public function test_baseline_exposes_service_classification_metrics(): void
    {
        Carbon::setTestNow('2026-06-24 12:00:00');

        $this->seedOpportunity(1, now()->subHours(2), 'captacion_agendar', latestInboundAt: now()->subHour());

        // Ophthalmology consult — primary, independent
        $this->seedManualAppointment(600, 'HC-1', now()->subHour());
        // Optometry same HC same date → companion
        $this->seedManualAppointmentWithProcedure(601, 'HC-1', now()->subMinutes(50),
            now()->subHour()->copy()->addDay()->toDateString(), '08:30:00', 'CONSULTA OPTOMETRIA');

        $baseline = (new WhatsappOperationalBaselineService())->baseline(
            Carbon::parse('2026-06-24'),
            now(),
            false
        );

        $this->assertArrayHasKey('converted_conversations', $baseline);
        $this->assertArrayHasKey('total_attributed_services', $baseline);
        $this->assertArrayHasKey('companion_services', $baseline);
        $this->assertArrayHasKey('independent_attributed_services', $baseline);
        $this->assertArrayHasKey('primary_clinical_appointments', $baseline);
        $this->assertArrayHasKey('diagnostic_services', $baseline);
        $this->assertArrayHasKey('follow_up_reviews', $baseline);
        $this->assertArrayHasKey('preop_or_anesthesia_services', $baseline);
        $this->assertArrayHasKey('other_attributed_services', $baseline);

        // Existing metrics must still exist
        $this->assertArrayHasKey('observed_bot_bookings', $baseline);
        $this->assertArrayHasKey('observed_manual_bookings', $baseline);
        $this->assertArrayHasKey('inferred_attributed_appointments', $baseline);
        $this->assertArrayHasKey('operational_interventions_without_observed_booking', $baseline);

        // The booking_observability sub-key must also carry them
        $obs = $baseline['booking_observability'];
        $this->assertArrayHasKey('converted_conversations', $obs);
        $this->assertArrayHasKey('companion_services', $obs);

        Carbon::setTestNow();
    }

    public function test_command_outputs_json_and_persists_snapshot(): void
    {
        Carbon::setTestNow('2026-06-24 12:00:00');
        $this->seedOpportunity(1, now()->subHours(2), 'captacion_agendar', latestInboundAt: now()->subHour());

        Artisan::call('whatsapp:operational-baseline', [
            '--date' => '2026-06-24',
            '--json' => true,
            '--persist' => true,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('"hot_open"', $output);
        $this->assertStringContainsString('"bookings_after_operational_intervention"', $output);

        $snapshot = DB::table('whatsapp_operational_snapshots')
            ->where('snapshot_date', '2026-06-24')
            ->first();

        $this->assertNotNull($snapshot);
        $this->assertSame(1, (int) $snapshot->hot_open_total);
        $this->assertSame(1, (int) $snapshot->hot_open_unassigned);
        $this->assertStringContainsString('"bucket_order"', (string) $snapshot->payload);

        Carbon::setTestNow();
    }

    private function seedOpportunity(
        int $id,
        Carbon $queuedAt,
        string $topic,
        ?Carbon $latestInboundAt = null,
        ?Carbon $bookedAt = null,
        string $source = 'unknown',
        ?int $assignedUserId = null,
    ): void {
        $waNumber = '593000' . $id;
        DB::table('whatsapp_conversations')->insert([
            'id' => $id,
            'wa_number' => $waNumber,
            'display_name' => 'Paciente ' . $id,
            'patient_hc_number' => 'HC-' . $id,
            'patient_full_name' => 'Paciente ' . $id,
            'last_message_at' => $latestInboundAt ?? $queuedAt,
            'last_message_direction' => 'inbound',
            'needs_human' => true,
            'assigned_user_id' => $assignedUserId,
            'assigned_at' => $assignedUserId !== null ? $queuedAt->copy()->addMinutes(5) : null,
            'handoff_requested_at' => $queuedAt,
            'created_at' => $queuedAt,
            'updated_at' => $queuedAt,
        ]);

        DB::table('whatsapp_conversation_attributions')->insert([
            'conversation_id' => $id,
            'source_category' => $source,
            'initial_intent' => $topic === 'captacion_agendar' ? 'booking' : 'other',
            'patient_segment' => $source === 'patient_return' ? 'retorno' : 'captacion',
            'created_at' => $queuedAt,
            'updated_at' => $queuedAt,
        ]);

        DB::table('whatsapp_messages')->insert([
            'conversation_id' => $id,
            'direction' => 'inbound',
            'sender_type' => null,
            'sender_id' => null,
            'body' => 'Necesito agendar',
            'message_timestamp' => $latestInboundAt ?? $queuedAt,
            'created_at' => $latestInboundAt ?? $queuedAt,
            'updated_at' => $latestInboundAt ?? $queuedAt,
        ]);

        DB::table('whatsapp_handoffs')->insert([
            'id' => $id,
            'conversation_id' => $id,
            'wa_number' => $waNumber,
            'status' => $assignedUserId !== null ? 'assigned' : 'queued',
            'priority' => 'high',
            'topic' => $topic,
            'assigned_agent_id' => $assignedUserId,
            'assigned_at' => $assignedUserId !== null ? $queuedAt->copy()->addMinutes(5) : null,
            'queued_at' => $queuedAt,
            'created_at' => $queuedAt,
            'updated_at' => $queuedAt,
        ]);

        if ($bookedAt !== null) {
            DB::table('whatsapp_sigcenter_bookings')->insert([
                'conversation_id' => $id,
                'wa_number' => $waNumber,
                'status' => 'created',
                'patient_hc_number' => 'HC-' . $id,
                'booked_at' => $bookedAt,
                'created_at' => $bookedAt,
                'updated_at' => $bookedAt,
            ]);
        }
    }

    private function seedEvent(int $handoffId, string $type, Carbon $createdAt): void
    {
        DB::table('whatsapp_handoff_events')->insert([
            'handoff_id' => $handoffId,
            'event_type' => $type,
            'created_at' => $createdAt,
        ]);

        $handoff = DB::table('whatsapp_handoffs')->where('id', $handoffId)->first();
        $conversation = $handoff !== null
            ? DB::table('whatsapp_conversations')->where('id', $handoff->conversation_id)->first()
            : null;
        if ($handoff !== null && $conversation !== null) {
            $canonicalType = $type === 'requeued' ? 'handoff_requeued' : $type;
            DB::table('whatsapp_operational_events')->insert([
                'conversation_id' => (int) $handoff->conversation_id,
                'handoff_id' => $handoffId,
                'event_type' => $canonicalType,
                'event_group' => $canonicalType === 'auto_assigned' ? 'assignment' : 'handoff',
                'event_at' => $createdAt,
                'actor_type' => 'system',
                'producer' => 'test',
                'topic' => $handoff->topic,
                'wa_number' => $conversation->wa_number,
                'patient_hc_number' => $conversation->patient_hc_number,
                'idempotency_key' => 'baseline-event:' . $handoffId . ':' . $canonicalType,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }

    private function seedOutbound(int $conversationId, Carbon $at): void
    {
        DB::table('whatsapp_messages')->insert([
            'conversation_id' => $conversationId,
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'sender_id' => 9,
            'body' => 'Le ayudo a agendar',
            'message_timestamp' => $at,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    private function seedReminder(int $id, string $status, Carbon $at, ?string $response, ?string $notes = null): void
    {
        DB::table('whatsapp_appointment_reminders')->insert([
            'conversation_id' => $id,
            'wa_number' => '593000' . $id,
            'hc_number' => 'HC-' . $id,
            'form_id' => $id,
            'source_type' => 'consulta',
            'template_code' => 'recordatorio',
            'reminder_window' => '24h',
            'dedupe_key' => 'reminder-' . $id,
            'event_at' => $at->copy()->addDay(),
            'status' => $status,
            'response_value' => $response,
            'sent_at' => $status === 'failed' ? null : $at,
            'failed_at' => $status === 'failed' ? $at : null,
            'responded_at' => $response !== null ? $at->copy()->addMinutes(5) : null,
            'notes' => $notes,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    private function seedBookingAttribution(
        string $source,
        string $observedKey,
        ?int $bookingId,
        ?int $formId,
        int $conversationId,
        int $handoffId,
        int $eventId,
        string $eventType,
        Carbon $eventAt,
        Carbon $bookingAt,
    ): void {
        DB::table('whatsapp_operational_booking_attributions')->insert([
            'booking_source' => $source,
            'observed_booking_key' => $observedKey,
            'booking_id' => $bookingId,
            'form_id' => $formId,
            'booking_conversation_id' => $conversationId,
            'attributed_conversation_id' => $conversationId,
            'handoff_id' => $handoffId,
            'event_id' => $eventId,
            'event_type' => $eventType,
            'attribution_method' => 'same_conversation_7d',
            'confidence' => 'high',
            'event_at' => $eventAt,
            'booking_at' => $bookingAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedManualAppointment(int $formId, string $hcNumber, Carbon $createdAt): void
    {
        $this->seedManualAppointmentWithProcedure($formId, $hcNumber, $createdAt, $createdAt->copy()->addDay()->toDateString(), '09:30:00', 'Consulta oftalmológica');
    }

    private function seedManualAppointmentWithProcedure(int $formId, string $hcNumber, Carbon $createdAt, string $date, string $hora, string $procedure): void
    {
        DB::table('procedimiento_proyectado')->insert([
            'form_id' => $formId,
            'hc_number' => $hcNumber,
            'fecha' => $date,
            'hora' => $hora,
            'sede_departamento' => 'CIVE',
            'medico_nombre' => 'Dr. Manual',
            'procedimiento_nombre' => $procedure,
            'sigcenter_present' => true,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}

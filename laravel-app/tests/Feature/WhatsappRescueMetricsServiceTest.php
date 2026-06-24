<?php

namespace Tests\Feature;

use App\Modules\Whatsapp\Services\WhatsappRescueMetricsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappRescueMetricsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('whatsapp_appointment_reminders');
        Schema::dropIfExists('whatsapp_sigcenter_bookings');
        Schema::dropIfExists('whatsapp_handoff_events');
        Schema::dropIfExists('whatsapp_handoffs');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_conversations');

        Schema::create('whatsapp_conversations', function (Blueprint $table): void {
            $table->id();
            $table->string('wa_number', 32)->unique();
            $table->string('patient_hc_number', 64)->nullable();
            $table->boolean('needs_human')->default(false);
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('direction', 16);
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

        Schema::create('whatsapp_sigcenter_bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->string('wa_number', 32);
            $table->string('status', 32)->default('created');
            $table->string('patient_hc_number', 64)->nullable();
            $table->timestamp('booked_at')->nullable();
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
    }

    public function test_it_measures_rescue_events_through_response_and_booking(): void
    {
        $base = Carbon::parse('2026-06-23 09:00:00');
        $this->seedConversation(1, '593111', 'HC1');
        $this->seedConversation(2, '593222', 'HC2');
        $this->seedConversation(3, '593333', 'HC3');

        $this->seedHandoff(10, 1, 'captacion_agendar', $base);
        $this->seedEvent(10, 'requeued', $base->copy()->addMinute());
        $this->seedEvent(10, 'auto_assigned', $base->copy()->addMinutes(3));
        $this->seedOutbound(1, $base->copy()->addMinutes(6));
        $this->seedBooking(1, '593111', $base->copy()->addMinutes(20));

        $this->seedHandoff(20, 2, 'faq_escalada', $base);
        $this->seedEvent(20, 'abandonment_escalated', $base->copy()->addMinutes(2));
        $this->seedEvent(20, 'auto_assigned', $base->copy()->addMinutes(5));
        $this->seedBooking(2, '593222', $base->copy()->addMinutes(30));

        $this->seedHandoff(30, 3, 'agenda_sin_disponibilidad', $base);

        $metrics = (new WhatsappRescueMetricsService())->summary($base, $base->copy()->addDay());

        $this->assertSame(1, $metrics['handoffs']['requeued_to_auto_assigned']);
        $this->assertSame(2, $metrics['handoffs']['auto_assigned_to_booking']);
        $this->assertSame(1, $metrics['handoffs']['auto_assigned_to_first_response']);
        $this->assertSame(1, $metrics['handoffs']['abandonment_escalated_to_assigned']);
        $this->assertSame(1, $metrics['handoffs']['abandonment_escalated_to_booking']);
        $this->assertSame(3, $metrics['hot_opportunities']['total']);
        $this->assertSame(2, $metrics['hot_opportunities']['booked']);
    }

    public function test_it_measures_reminder_confirmation_and_failures(): void
    {
        $base = Carbon::parse('2026-06-23 09:00:00');

        DB::table('whatsapp_appointment_reminders')->insert([
            [
                'conversation_id' => 1,
                'wa_number' => '593111',
                'hc_number' => 'HC1',
                'form_id' => 100,
                'source_type' => 'consulta',
                'template_code' => 'recordatorio',
                'reminder_window' => '24h',
                'dedupe_key' => 'a',
                'event_at' => $base->copy()->addDay(),
                'status' => 'responded',
                'response_value' => 'confirmar',
                'sent_at' => $base,
                'failed_at' => null,
                'responded_at' => $base->copy()->addMinutes(10),
                'notes' => null,
                'created_at' => $base,
                'updated_at' => $base,
            ],
            [
                'conversation_id' => 2,
                'wa_number' => '593222',
                'hc_number' => 'HC2',
                'form_id' => 101,
                'source_type' => 'imagenes',
                'template_code' => 'recordatorio',
                'reminder_window' => '2h',
                'dedupe_key' => 'b',
                'event_at' => $base->copy()->addHours(2),
                'status' => 'failed',
                'response_value' => null,
                'sent_at' => null,
                'failed_at' => $base->copy()->addMinute(),
                'responded_at' => null,
                'notes' => 'Meta error: invalid phone number',
                'created_at' => $base,
                'updated_at' => $base,
            ],
        ]);

        $metrics = (new WhatsappRescueMetricsService())->summary($base, $base->copy()->addDay());

        $this->assertSame(1, $metrics['reminders']['sent_to_confirmation']);
        $this->assertSame(1, $metrics['reminders']['failed']);
        $this->assertSame(1, $metrics['reminders']['failure_reasons']['Meta error: invalid phone number']);
    }

    public function test_it_outputs_rescue_metrics_from_console(): void
    {
        $base = Carbon::parse('2026-06-23 09:00:00');
        $this->seedConversation(1, '593111', 'HC1');
        $this->seedHandoff(10, 1, 'captacion_agendar', $base);
        $this->seedEvent(10, 'auto_assigned', $base->copy()->addMinute());
        $this->seedBooking(1, '593111', $base->copy()->addMinutes(15));

        Artisan::call('whatsapp:rescue-metrics', [
            '--from' => '2026-06-23',
            '--to' => '2026-06-24',
        ]);

        $output = Artisan::output();

        $this->assertStringContainsString('auto_assigned_to_booking', $output);
        $this->assertStringContainsString('hot_opportunities', $output);
    }

    private function seedConversation(int $id, string $waNumber, string $hc): void
    {
        DB::table('whatsapp_conversations')->insert([
            'id' => $id,
            'wa_number' => $waNumber,
            'patient_hc_number' => $hc,
            'needs_human' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedHandoff(int $id, int $conversationId, string $topic, Carbon $queuedAt): void
    {
        DB::table('whatsapp_handoffs')->insert([
            'id' => $id,
            'conversation_id' => $conversationId,
            'wa_number' => '593' . $conversationId,
            'status' => 'queued',
            'priority' => 'high',
            'topic' => $topic,
            'queued_at' => $queuedAt,
            'created_at' => $queuedAt,
            'updated_at' => $queuedAt,
        ]);
    }

    private function seedEvent(int $handoffId, string $eventType, Carbon $createdAt): void
    {
        DB::table('whatsapp_handoff_events')->insert([
            'handoff_id' => $handoffId,
            'event_type' => $eventType,
            'created_at' => $createdAt,
        ]);
    }

    private function seedOutbound(int $conversationId, Carbon $sentAt): void
    {
        DB::table('whatsapp_messages')->insert([
            'conversation_id' => $conversationId,
            'direction' => 'outbound',
            'body' => 'Hola, te ayudo',
            'message_timestamp' => $sentAt,
            'created_at' => $sentAt,
            'updated_at' => $sentAt,
        ]);
    }

    private function seedBooking(int $conversationId, string $waNumber, Carbon $bookedAt): void
    {
        DB::table('whatsapp_sigcenter_bookings')->insert([
            'conversation_id' => $conversationId,
            'wa_number' => $waNumber,
            'status' => 'created',
            'booked_at' => $bookedAt,
            'created_at' => $bookedAt,
            'updated_at' => $bookedAt,
        ]);
    }
}

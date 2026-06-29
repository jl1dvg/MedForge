<?php

namespace Tests\Feature;

use App\Modules\Whatsapp\Services\WhatsappDailyRescueReportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappDailyRescueReportServiceTest extends TestCase
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

    public function test_it_builds_daily_rescue_report_by_operational_bucket(): void
    {
        Carbon::setTestNow('2026-06-24 12:00:00');
        $from = Carbon::parse('2026-06-24 00:00:00');
        $to = Carbon::parse('2026-06-25 00:00:00');

        $this->seedOpportunity(1, '593001', now()->subHours(2), 'captacion_agendar', latestInboundAt: now()->subHours(1));
        $this->seedOpportunity(2, '593002', now()->subHours(3), 'captacion_agendar', latestInboundAt: now()->subHours(25));
        $this->seedOpportunity(3, '593003', now()->subDays(2), 'agenda_sin_disponibilidad', latestInboundAt: now()->subDays(2));
        $this->seedOpportunity(4, '593004', now()->subDays(10), 'faq_escalada', latestInboundAt: now()->subDays(10));
        $this->seedOpportunity(5, '593005', now()->subDays(35), 'operacion_reagenda', latestInboundAt: now()->subDays(35));
        $this->seedOpportunity(6, '593006', now()->subHours(4), 'captacion_agendar', latestInboundAt: now()->subHours(2), bookedAt: now()->subHour());

        $this->seedEvent(1, 'auto_assigned', now()->subHours(1));
        $this->seedEvent(3, 'assigned', now()->subHours(2));
        $this->seedEvent(3, 'requeued', now()->subHours(3));
        $this->seedReminder(1, 'responded', now()->subHours(4), 'confirmar');
        $this->seedReminder(2, 'failed', now()->subHours(5), null, 'Meta invalid phone');

        $report = (new WhatsappDailyRescueReportService())->summary($from, $to, now());

        $this->assertSame(1, $report['buckets']['hot_open']['open']);
        $this->assertSame(1, $report['buckets']['hot_open']['booked']);
        $this->assertSame(1, $report['buckets']['hot_needs_template']['open']);
        $this->assertSame(1, $report['buckets']['rescue']['open']);
        $this->assertSame(1, $report['buckets']['backlog']['open']);
        $this->assertSame(1, $report['buckets']['lost']['open']);
        $this->assertSame(1, $report['operations']['auto_assigned']);
        $this->assertSame(1, $report['operations']['agent_taken']);
        $this->assertSame(1, $report['operations']['bookings_created']);
        $this->assertSame(1, $report['reminders']['sent']);
        $this->assertSame(1, $report['reminders']['confirmed']);
        $this->assertSame(1, $report['reminders']['failed']);
        $this->assertSame(50.0, $report['rates']['assignment_rate']);
        $this->assertSame(50.0, $report['rates']['rescue_rate']);

        Carbon::setTestNow();
    }

    public function test_daily_rescue_report_command_outputs_json(): void
    {
        Carbon::setTestNow('2026-06-24 12:00:00');
        $this->seedOpportunity(1, '593001', now()->subHours(2), 'captacion_agendar', latestInboundAt: now()->subHour());

        Artisan::call('whatsapp:daily-rescue-report', [
            '--date' => '2026-06-24',
            '--json' => true,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('"hot_open"', $output);
        $this->assertStringContainsString('"assignment_rate"', $output);

        Carbon::setTestNow();
    }

    private function seedOpportunity(
        int $id,
        string $waNumber,
        Carbon $queuedAt,
        string $topic,
        ?Carbon $latestInboundAt = null,
        ?Carbon $bookedAt = null,
    ): void {
        DB::table('whatsapp_conversations')->insert([
            'id' => $id,
            'wa_number' => $waNumber,
            'display_name' => 'Paciente ' . $id,
            'patient_hc_number' => 'HC-' . $id,
            'last_message_at' => $latestInboundAt ?? $queuedAt,
            'last_message_direction' => 'inbound',
            'needs_human' => true,
            'assigned_user_id' => null,
            'handoff_requested_at' => $queuedAt,
            'created_at' => $queuedAt,
            'updated_at' => $queuedAt,
        ]);

        DB::table('whatsapp_messages')->insert([
            'conversation_id' => $id,
            'direction' => 'inbound',
            'body' => 'Necesito agendar',
            'message_timestamp' => $latestInboundAt ?? $queuedAt,
            'created_at' => $latestInboundAt ?? $queuedAt,
            'updated_at' => $latestInboundAt ?? $queuedAt,
        ]);

        DB::table('whatsapp_handoffs')->insert([
            'id' => $id,
            'conversation_id' => $id,
            'wa_number' => $waNumber,
            'status' => 'queued',
            'priority' => 'high',
            'topic' => $topic,
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
    }

    private function seedReminder(int $id, string $status, Carbon $at, ?string $response, ?string $notes = null): void
    {
        DB::table('whatsapp_appointment_reminders')->insert([
            'conversation_id' => $id,
            'wa_number' => '59300' . $id,
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
}

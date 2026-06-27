<?php

namespace Tests\Feature;

use App\Modules\Whatsapp\Services\WhatsappOperationalAlertService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappOperationalAlertServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'whatsapp_operational_booking_attributions',
            'whatsapp_messages',
            'whatsapp_handoff_events',
            'whatsapp_operational_events',
            'whatsapp_handoffs',
            'whatsapp_conversations',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre')->default('');
            $table->string('first_name')->default('');
            $table->string('last_name')->default('');
            $table->string('username')->default('');
            $table->string('password')->default('');
        });

        Schema::create('whatsapp_conversations', function (Blueprint $table): void {
            $table->id();
            $table->string('wa_number', 32)->unique();
            $table->string('patient_hc_number', 64)->nullable();
            $table->boolean('needs_human')->default(true);
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('handoff_requested_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_handoffs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('wa_number', 32);
            $table->string('status', 24)->default('queued');
            $table->string('topic', 191)->nullable();
            $table->string('priority', 24)->default('normal');
            $table->unsignedBigInteger('assigned_agent_id')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('direction', 16);
            $table->timestamp('message_timestamp')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_handoff_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('handoff_id');
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->string('event_type', 64);
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('source', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_operational_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('event_type', 64);
            $table->string('source', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_operational_booking_attributions', function (Blueprint $table): void {
            $table->id();
            $table->string('booking_source', 48)->default('bot_api');
            $table->string('observed_booking_key', 191)->unique();
            $table->unsignedBigInteger('attributed_conversation_id')->nullable();
            $table->unsignedBigInteger('form_id')->nullable();
            $table->string('event_type', 64);
            $table->string('attribution_method', 64);
            $table->string('confidence', 24);
            $table->timestamps();
        });
    }

    // ── Core alert rules ──────────────────────────────────────────────────────

    public function test_hot_open_unassigned_generates_hot_unassigned_alert(): void
    {
        Carbon::setTestNow('2026-06-26 10:00:00');
        $this->seedConversation(1, now()->subMinutes(30), 'captacion_agendar', null, 'high');
        $this->seedInbound(1, now()->subMinutes(20));

        $result = $this->runAlerts();

        $alert = $this->findAlert($result, 1, WhatsappOperationalAlertService::ALERT_HOT_UNASSIGNED);
        $this->assertNotNull($alert, 'Must generate hot_unassigned alert');
        $this->assertSame('high', $alert['severity'], 'wait < 60 min → high');
        $this->assertSame('hot_open', $alert['bucket']);
        $this->assertSame('assign_now', $alert['recommended_action']);
        $this->assertNull($alert['assigned_user_id']);

        Carbon::setTestNow();
    }

    public function test_hot_open_unassigned_60min_is_critical(): void
    {
        Carbon::setTestNow('2026-06-26 10:00:00');
        $this->seedConversation(1, now()->subMinutes(90), 'captacion_agendar', null, 'high');
        $this->seedInbound(1, now()->subMinutes(60));

        $result = $this->runAlerts();

        $alert = $this->findAlert($result, 1, WhatsappOperationalAlertService::ALERT_HOT_UNASSIGNED);
        $this->assertNotNull($alert);
        $this->assertSame('critical', $alert['severity'], 'wait ≥ 60 min → critical');

        Carbon::setTestNow();
    }

    public function test_supervisor_review_generates_supervisor_sla_breach_alert(): void
    {
        Carbon::setTestNow('2026-06-26 10:00:00');
        // Assigned agent but no human outbound response past SLA (120 min)
        // waiting_minutes = 150 ≥ 120 → triggers supervisor_review, severity = high (≥120, <240)
        $this->seedConversation(2, now()->subMinutes(150), 'operacion_cita_vigente', agentId: 5, priority: 'high');
        $this->seedInbound(2, now()->subMinutes(120));
        // No outbound message → supervisor_review

        $result = $this->runAlerts();

        $alert = $this->findAlert($result, 2, WhatsappOperationalAlertService::ALERT_SUPERVISOR_SLA);
        $this->assertNotNull($alert, 'Must generate supervisor_sla_breach alert');
        $this->assertSame('high', $alert['severity'], '150 min ≥ 120 → high');

        Carbon::setTestNow();
    }

    public function test_supervisor_review_240min_is_critical(): void
    {
        Carbon::setTestNow('2026-06-26 10:00:00');
        $this->seedConversation(3, now()->subMinutes(300), 'captacion_agendar', agentId: 5, priority: 'high');
        $this->seedInbound(3, now()->subMinutes(250));

        $result = $this->runAlerts();

        $alert = $this->findAlert($result, 3, WhatsappOperationalAlertService::ALERT_SUPERVISOR_SLA);
        $this->assertNotNull($alert);
        $this->assertSame('critical', $alert['severity'], 'wait ≥ 240 min → critical');

        Carbon::setTestNow();
    }

    public function test_rescue_bucket_generates_rescue_aging_alert(): void
    {
        Carbon::setTestNow('2026-06-26 10:00:00');
        // queued 3 days ago → rescue bucket (1440 < age ≤ 7*1440), inbound stale
        $this->seedConversation(4, now()->subDays(3), 'captacion_agendar', null, 'normal');
        $this->seedInbound(4, now()->subDays(3));

        $result = $this->runAlerts();

        $alert = $this->findAlert($result, 4, WhatsappOperationalAlertService::ALERT_RESCUE_AGING);
        $this->assertNotNull($alert, 'Must generate rescue_aging alert');
        $this->assertSame('medium', $alert['severity'], '3 days < 5 days → medium');
        $this->assertSame('rescue', $alert['bucket']);

        Carbon::setTestNow();
    }

    public function test_rescue_5days_is_high_severity(): void
    {
        Carbon::setTestNow('2026-06-26 10:00:00');
        $this->seedConversation(5, now()->subDays(6), 'captacion_agendar', null, 'normal');
        $this->seedInbound(5, now()->subDays(6));

        $result = $this->runAlerts();

        $alert = $this->findAlert($result, 5, WhatsappOperationalAlertService::ALERT_RESCUE_AGING);
        $this->assertNotNull($alert);
        $this->assertSame('high', $alert['severity'], '6 days ≥ 5 days → high');

        Carbon::setTestNow();
    }

    public function test_agenda_sin_disponibilidad_urgent_generates_no_availability_alert(): void
    {
        Carbon::setTestNow('2026-06-26 10:00:00');
        $this->seedConversation(6, now()->subMinutes(30), 'agenda_sin_disponibilidad', null, 'urgent');
        $this->seedInbound(6, now()->subMinutes(20));

        $result = $this->runAlerts();

        $alert = $this->findAlert($result, 6, WhatsappOperationalAlertService::ALERT_NO_AVAILABILITY);
        $this->assertNotNull($alert, 'Must generate no_availability alert for urgent priority');
        $this->assertSame('medium', $alert['severity']);

        Carbon::setTestNow();
    }

    public function test_agenda_sin_disponibilidad_repeated_is_high(): void
    {
        Carbon::setTestNow('2026-06-26 10:00:00');
        $this->seedConversation(7, now()->subMinutes(30), 'agenda_sin_disponibilidad', null, 'normal');
        $this->seedInbound(7, now()->subMinutes(20));

        // Seed a second handoff for same conversation → repeat_count = 2
        DB::table('whatsapp_handoffs')->insert([
            'id'              => 70,
            'conversation_id' => 7,
            'wa_number'       => '5930007',
            'status'          => 'expired',
            'topic'           => 'agenda_sin_disponibilidad',
            'priority'        => 'normal',
            'queued_at'       => now()->subDays(3),
            'created_at'      => now()->subDays(3),
            'updated_at'      => now()->subDays(3),
        ]);

        $result = $this->runAlerts();

        $alert = $this->findAlert($result, 7, WhatsappOperationalAlertService::ALERT_NO_AVAILABILITY);
        $this->assertNotNull($alert);
        $this->assertSame('high', $alert['severity'], 'repeat ≥ 2 → high');

        Carbon::setTestNow();
    }

    public function test_faq_escalada_urgent_generates_ambiguous_faq_alert(): void
    {
        Carbon::setTestNow('2026-06-26 10:00:00');
        $this->seedConversation(8, now()->subMinutes(30), 'faq_escalada', null, 'urgent');
        $this->seedInbound(8, now()->subMinutes(20));

        $result = $this->runAlerts();

        $alert = $this->findAlert($result, 8, WhatsappOperationalAlertService::ALERT_AMBIGUOUS_FAQ);
        $this->assertNotNull($alert, 'Must generate ambiguous_urgent_faq alert');
        $this->assertSame('medium', $alert['severity']);

        Carbon::setTestNow();
    }

    // ── Read-only guarantee ───────────────────────────────────────────────────

    public function test_alert_service_does_not_write_conversations(): void
    {
        Carbon::setTestNow('2026-06-26 10:00:00');
        $this->seedConversation(10, now()->subMinutes(30), 'captacion_agendar', null, 'high');
        $this->seedInbound(10, now()->subMinutes(20));
        $before = DB::table('whatsapp_conversations')->where('id', 10)->value('assigned_user_id');

        $result = $this->runAlerts();

        $this->assertNull($before);
        $this->assertNull(DB::table('whatsapp_conversations')->where('id', 10)->value('assigned_user_id'));
        $this->assertSame(0, $result['db_writes']);
        $this->assertTrue($result['read_only']);

        Carbon::setTestNow();
    }

    public function test_alert_service_does_not_write_handoffs(): void
    {
        Carbon::setTestNow('2026-06-26 10:00:00');
        $this->seedConversation(11, now()->subMinutes(30), 'captacion_agendar', null, 'high');
        $this->seedInbound(11, now()->subMinutes(20));

        $this->runAlerts();

        $this->assertDatabaseHas('whatsapp_handoffs', ['conversation_id' => 11, 'status' => 'queued']);

        Carbon::setTestNow();
    }

    public function test_alert_service_does_not_insert_handoff_events(): void
    {
        Carbon::setTestNow('2026-06-26 10:00:00');
        $this->seedConversation(12, now()->subMinutes(30), 'captacion_agendar', null, 'high');
        $this->seedInbound(12, now()->subMinutes(20));
        $before = DB::table('whatsapp_handoff_events')->count();

        $this->runAlerts();

        $this->assertSame($before, DB::table('whatsapp_handoff_events')->count());

        Carbon::setTestNow();
    }

    public function test_alert_service_does_not_insert_operational_events(): void
    {
        Carbon::setTestNow('2026-06-26 10:00:00');
        $this->seedConversation(13, now()->subMinutes(30), 'captacion_agendar', null, 'high');
        $this->seedInbound(13, now()->subMinutes(20));
        $before = DB::table('whatsapp_operational_events')->count();

        $this->runAlerts();

        $this->assertSame($before, DB::table('whatsapp_operational_events')->count());

        Carbon::setTestNow();
    }

    // ── JSON command ──────────────────────────────────────────────────────────

    public function test_json_command_returns_read_only_true_and_zero_db_writes(): void
    {
        Carbon::setTestNow('2026-06-26 10:00:00');
        $this->seedConversation(20, now()->subMinutes(30), 'captacion_agendar', null, 'high');
        $this->seedInbound(20, now()->subMinutes(20));

        Artisan::call('whatsapp:operational-alerts', [
            '--date'  => '2026-06-26',
            '--json'  => true,
            '--limit' => 50,
        ]);
        $output = Artisan::output();

        $json = json_decode($output, true);
        $this->assertIsArray($json, 'Output must be valid JSON');
        $this->assertTrue($json['read_only'] ?? false, 'read_only must be true');
        $this->assertSame(0, $json['db_writes'] ?? -1, 'db_writes must be 0');
        $this->assertSame('read_only', $json['mode'] ?? '');
        $this->assertArrayHasKey('summary', $json);
        $this->assertArrayHasKey('by_type', $json);
        $this->assertArrayHasKey('alerts', $json);

        Carbon::setTestNow();
    }

    // ── Filters ───────────────────────────────────────────────────────────────

    public function test_category_filter_excludes_non_matching(): void
    {
        Carbon::setTestNow('2026-06-26 10:00:00');
        // captacion topic → category = captacion
        $this->seedConversation(30, now()->subMinutes(30), 'captacion_agendar', null, 'high');
        $this->seedInbound(30, now()->subMinutes(20));

        $result = app(WhatsappOperationalAlertService::class)->alerts([
            'date'     => '2026-06-26',
            'category' => 'operacion',
        ]);

        $this->assertSame(0, $result['alerts_total'], 'captacion alerts should be excluded when filtering operacion');

        Carbon::setTestNow();
    }

    public function test_severity_filter_excludes_non_matching(): void
    {
        Carbon::setTestNow('2026-06-26 10:00:00');
        // 30-min wait → high severity, not critical
        $this->seedConversation(31, now()->subMinutes(30), 'captacion_agendar', null, 'high');
        $this->seedInbound(31, now()->subMinutes(20));

        $result = app(WhatsappOperationalAlertService::class)->alerts([
            'date'     => '2026-06-26',
            'severity' => 'critical',
        ]);

        $this->assertSame(0, $result['alerts_total'], 'high alerts should be excluded when filtering critical');

        Carbon::setTestNow();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function runAlerts(): array
    {
        return app(WhatsappOperationalAlertService::class)->alerts(['date' => now()->toDateString()]);
    }

    /**
     * @param array<string,mixed> $result
     * @return array<string,mixed>|null
     */
    private function findAlert(array $result, int $convId, string $alertType): ?array
    {
        foreach ($result['alerts'] as $a) {
            if ((int) $a['conversation_id'] === $convId && $a['alert_type'] === $alertType) {
                return $a;
            }
        }

        return null;
    }

    private function seedConversation(int $id, Carbon $queuedAt, string $topic, ?int $agentId, string $priority = 'normal'): void
    {
        DB::table('whatsapp_conversations')->insert([
            'id'                   => $id,
            'wa_number'            => '593000' . $id,
            'patient_hc_number'    => 'HC-' . $id,
            'needs_human'          => true,
            'assigned_user_id'     => $agentId,
            'last_message_at'      => $queuedAt,
            'handoff_requested_at' => $queuedAt,
            'created_at'           => $queuedAt,
            'updated_at'           => $queuedAt,
        ]);

        DB::table('whatsapp_handoffs')->insert([
            'id'              => $id,
            'conversation_id' => $id,
            'wa_number'       => '593000' . $id,
            'status'          => $agentId !== null ? 'assigned' : 'queued',
            'topic'           => $topic,
            'priority'        => $priority,
            'assigned_agent_id' => $agentId,
            'assigned_at'     => $agentId !== null ? $queuedAt->copy()->addMinutes(5) : null,
            'queued_at'       => $queuedAt,
            'created_at'      => $queuedAt,
            'updated_at'      => $queuedAt,
        ]);
    }

    private function seedInbound(int $conversationId, Carbon $at): void
    {
        DB::table('whatsapp_messages')->insert([
            'conversation_id'  => $conversationId,
            'direction'        => 'inbound',
            'message_timestamp' => $at,
            'created_at'       => $at,
            'updated_at'       => $at,
        ]);
    }
}

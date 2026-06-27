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
            $table->string('display_name', 191)->nullable();
            $table->string('patient_hc_number', 64)->nullable();
            $table->string('patient_full_name', 191)->nullable();
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
        // asOf = endOfDay('2026-06-26') = 23:59:59; align now() so subMinutes arithmetic matches
        Carbon::setTestNow('2026-06-26 23:59:59');
        // queued 30 min before asOf → waiting = 30 min < 60 → high
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
        Carbon::setTestNow('2026-06-26 23:59:59');
        // queued 90 min before asOf → waiting = 90 min ≥ 60 → critical
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
        Carbon::setTestNow('2026-06-26 23:59:59');
        // queued 150 min before asOf → waiting = 150 ≥ 120, < 240 → high severity
        // assigned agent + no outbound → supervisor_review fires
        $this->seedConversation(2, now()->subMinutes(150), 'operacion_cita_vigente', agentId: 5, priority: 'high');
        $this->seedInbound(2, now()->subMinutes(120));

        $result = $this->runAlerts();

        $alert = $this->findAlert($result, 2, WhatsappOperationalAlertService::ALERT_SUPERVISOR_SLA);
        $this->assertNotNull($alert, 'Must generate supervisor_sla_breach alert');
        $this->assertSame('high', $alert['severity'], '150 min ≥ 120 → high');

        Carbon::setTestNow();
    }

    public function test_supervisor_review_240min_is_critical(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        // queued 300 min before asOf → waiting = 300 ≥ 240 → critical
        $this->seedConversation(3, now()->subMinutes(300), 'captacion_agendar', agentId: 5, priority: 'high');
        $this->seedInbound(3, now()->subMinutes(250));

        $result = $this->runAlerts();

        $alert = $this->findAlert($result, 3, WhatsappOperationalAlertService::ALERT_SUPERVISOR_SLA);
        $this->assertNotNull($alert);
        $this->assertSame('critical', $alert['severity'], 'wait ≥ 240 min → critical');

        Carbon::setTestNow();
    }

    public function test_rescue_bucket_with_rescue_followup_generates_rescue_aging_alert(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        // queued 2 days ago → rescue bucket, inbound stale → rescue_followup action
        // 2 days = 2880 min: < 3*1440=4320 → medium severity
        $this->seedConversation(4, now()->subDays(2), 'captacion_agendar', null, 'normal');
        $this->seedInbound(4, now()->subDays(2));

        $result = $this->runAlerts();

        $alert = $this->findAlert($result, 4, WhatsappOperationalAlertService::ALERT_RESCUE_AGING);
        $this->assertNotNull($alert, 'rescue + rescue_followup must generate rescue_aging');
        $this->assertSame('medium', $alert['severity'], '2 days < 3 days → medium');
        $this->assertSame('rescue', $alert['bucket']);

        Carbon::setTestNow();
    }

    public function test_rescue_3days_is_high_severity(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        // 3 days = 4320 min ≥ 3*1440 → high
        $this->seedConversation(40, now()->subDays(3), 'captacion_agendar', null, 'normal');
        $this->seedInbound(40, now()->subDays(3));

        $result = $this->runAlerts();

        $alert = $this->findAlert($result, 40, WhatsappOperationalAlertService::ALERT_RESCUE_AGING);
        $this->assertNotNull($alert);
        $this->assertSame('high', $alert['severity'], '3 days ≥ 3-day threshold → high');

        Carbon::setTestNow();
    }

    public function test_rescue_5days_is_critical_severity(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        // 6 days = 8640 min ≥ 5*1440 → critical
        $this->seedConversation(5, now()->subDays(6), 'captacion_agendar', null, 'normal');
        $this->seedInbound(5, now()->subDays(6));

        $result = $this->runAlerts();

        $alert = $this->findAlert($result, 5, WhatsappOperationalAlertService::ALERT_RESCUE_AGING);
        $this->assertNotNull($alert);
        $this->assertSame('critical', $alert['severity'], '6 days ≥ 5-day threshold → critical');

        Carbon::setTestNow();
    }

    public function test_agenda_sin_disponibilidad_urgent_without_repeat_does_not_generate_alert(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        // hot_needs_template (inbound outside 24h window) → send_template_or_review → Rule 4 checked
        // but repeat_count = 1 (only current handoff) → no alert
        $this->seedConversation(6, now()->subMinutes(30), 'agenda_sin_disponibilidad', null, 'urgent');
        $this->seedInbound(6, now()->subDays(2)); // outside 24h window → hot_needs_template

        $result = $this->runAlerts();

        $alert = $this->findAlert($result, 6, WhatsappOperationalAlertService::ALERT_NO_AVAILABILITY);
        $this->assertNull($alert, 'no_availability requires repeat_count >= 2');

        Carbon::setTestNow();
    }

    public function test_agenda_sin_disponibilidad_repeated_is_high(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        // hot_needs_template: queued 30 min ago, inbound 2 days ago (outside 24h window)
        // → send_template_or_review → Rule 1 does NOT fire → Rule 4 is evaluated
        $this->seedConversation(7, now()->subMinutes(30), 'agenda_sin_disponibilidad', null, 'normal');
        $this->seedInbound(7, now()->subDays(2));

        // Extra handoff with status='closed' so conversationRows subquery (status IN queued/assigned/expired)
        // selects the main handoff (id=7), not this one. Still counted by buildRepeatMap.
        DB::table('whatsapp_handoffs')->insert([
            'id'              => 70,
            'conversation_id' => 7,
            'wa_number'       => '5930007',
            'status'          => 'closed',
            'topic'           => 'agenda_sin_disponibilidad',
            'priority'        => 'normal',
            'queued_at'       => now()->subDays(3),
            'created_at'      => now()->subDays(3),
            'updated_at'      => now()->subDays(3),
        ]);

        $result = $this->runAlerts();

        $alert = $this->findAlert($result, 7, WhatsappOperationalAlertService::ALERT_NO_AVAILABILITY);
        $this->assertNotNull($alert, 'repeat_count >= 2 in hot_needs_template must generate no_availability_repeated');
        $this->assertSame('high', $alert['severity'], 'repeat ≥ 2 → high');

        Carbon::setTestNow();
    }

    public function test_faq_escalada_urgent_generates_ambiguous_faq_alert(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        // hot_needs_template: inbound outside 24h window → send_template_or_review
        // → Rule 1 (assign_now) does NOT fire → Rule 5 (ambiguous_faq) is evaluated
        $this->seedConversation(8, now()->subMinutes(30), 'faq_escalada', null, 'urgent');
        $this->seedInbound(8, now()->subDays(2)); // outside 24h → hot_needs_template

        $result = $this->runAlerts();

        $alert = $this->findAlert($result, 8, WhatsappOperationalAlertService::ALERT_AMBIGUOUS_FAQ);
        $this->assertNotNull($alert, 'Must generate ambiguous_urgent_faq alert');
        $this->assertSame('medium', $alert['severity']);

        Carbon::setTestNow();
    }

    // ── Read-only guarantee ───────────────────────────────────────────────────

    public function test_alert_service_does_not_write_conversations(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
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
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedConversation(11, now()->subMinutes(30), 'captacion_agendar', null, 'high');
        $this->seedInbound(11, now()->subMinutes(20));

        $this->runAlerts();

        $this->assertDatabaseHas('whatsapp_handoffs', ['conversation_id' => 11, 'status' => 'queued']);

        Carbon::setTestNow();
    }

    public function test_alert_service_does_not_insert_handoff_events(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedConversation(12, now()->subMinutes(30), 'captacion_agendar', null, 'high');
        $this->seedInbound(12, now()->subMinutes(20));
        $before = DB::table('whatsapp_handoff_events')->count();

        $this->runAlerts();

        $this->assertSame($before, DB::table('whatsapp_handoff_events')->count());

        Carbon::setTestNow();
    }

    public function test_alert_service_does_not_insert_operational_events(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
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
        Carbon::setTestNow('2026-06-26 23:59:59');
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

    // ── Calibration guards ────────────────────────────────────────────────────

    public function test_no_action_converted_does_not_generate_rescue_aging_alert(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        // rescue bucket (2 days old) but has booking attribution → no_action_converted → no alert
        $this->seedConversation(50, now()->subDays(2), 'captacion_agendar', null, 'normal');
        $this->seedInbound(50, now()->subDays(2));

        // bot_api booking → category = ophthalmology_consult → hasPrimary=true → no_action_converted
        DB::table('whatsapp_operational_booking_attributions')->insert([
            'id'                          => 50,
            'booking_source'              => 'bot_api',
            'observed_booking_key'        => 'key-conv-50',
            'attributed_conversation_id'  => 50,
            'event_type'                  => 'booking_created',
            'attribution_method'          => 'direct',
            'confidence'                  => 'high',
            'created_at'                  => now()->subDays(1),
            'updated_at'                  => now()->subDays(1),
        ]);

        $result = $this->runAlerts();

        $alert = $this->findAlert($result, 50, WhatsappOperationalAlertService::ALERT_RESCUE_AGING);
        $this->assertNull($alert, 'no_action_converted must not generate rescue_aging');

        Carbon::setTestNow();
    }

    public function test_no_action_already_handled_does_not_generate_alert(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        // hot_open + assigned + has human response → no_action_already_handled → no alert
        $this->seedConversation(51, now()->subMinutes(30), 'captacion_agendar', 5, 'high');
        $this->seedInbound(51, now()->subMinutes(20));
        // Outbound response after queue time → hasHumanResponse = true
        DB::table('whatsapp_messages')->insert([
            'conversation_id'   => 51,
            'direction'         => 'outbound',
            'message_timestamp' => now()->subMinutes(15),
            'created_at'        => now()->subMinutes(15),
            'updated_at'        => now()->subMinutes(15),
        ]);

        $result = $this->runAlerts();

        $this->assertNull($this->findAlert($result, 51, WhatsappOperationalAlertService::ALERT_HOT_UNASSIGNED));
        $this->assertNull($this->findAlert($result, 51, WhatsappOperationalAlertService::ALERT_SUPERVISOR_SLA));

        Carbon::setTestNow();
    }

    public function test_hold_backlog_does_not_generate_rescue_aging_alert(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        // backlog bucket (8-30 days) → hold_backlog action, not rescue_followup → no rescue_aging
        $this->seedConversation(52, now()->subDays(10), 'captacion_agendar', null, 'normal');
        $this->seedInbound(52, now()->subDays(10));

        $result = $this->runAlerts();

        $alert = $this->findAlert($result, 52, WhatsappOperationalAlertService::ALERT_RESCUE_AGING);
        $this->assertNull($alert, 'hold_backlog must not generate rescue_aging');

        Carbon::setTestNow();
    }

    public function test_agenda_sin_disponibilidad_in_backlog_does_not_generate_no_availability_alert(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        // backlog bucket (10 days old) → not in allowed buckets for no_availability_repeated
        $this->seedConversation(53, now()->subDays(10), 'agenda_sin_disponibilidad', null, 'normal');
        $this->seedInbound(53, now()->subDays(10));

        // Add repeat handoffs so repeat_count >= 2
        DB::table('whatsapp_handoffs')->insert([
            'id'              => 530,
            'conversation_id' => 53,
            'wa_number'       => '5930053',
            'status'          => 'expired',
            'topic'           => 'agenda_sin_disponibilidad',
            'priority'        => 'normal',
            'queued_at'       => now()->subDays(12),
            'created_at'      => now()->subDays(12),
            'updated_at'      => now()->subDays(12),
        ]);

        $result = $this->runAlerts();

        $alert = $this->findAlert($result, 53, WhatsappOperationalAlertService::ALERT_NO_AVAILABILITY);
        $this->assertNull($alert, 'backlog bucket must not generate no_availability_repeated');

        Carbon::setTestNow();
    }

    // ── Pagination / summary ──────────────────────────────────────────────────

    public function test_summary_only_returns_no_alerts_array_items(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedConversation(60, now()->subMinutes(30), 'captacion_agendar', null, 'high');
        $this->seedInbound(60, now()->subMinutes(20));

        $result = app(WhatsappOperationalAlertService::class)->alerts([
            'date'         => '2026-06-26',
            'summary_only' => true,
        ]);

        $this->assertArrayHasKey('summary', $result);
        $this->assertSame([], $result['alerts'], 'summary_only must return empty alerts[]');
        $this->assertSame(0, $result['alerts_returned'], 'alerts_returned must be 0 when summary_only');
        $this->assertGreaterThanOrEqual(1, $result['alerts_total'], 'alerts_total must still count actual alerts');

        Carbon::setTestNow();
    }

    public function test_json_response_contains_alerts_returned_and_truncated(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedConversation(61, now()->subMinutes(30), 'captacion_agendar', null, 'high');
        $this->seedInbound(61, now()->subMinutes(20));

        $result = app(WhatsappOperationalAlertService::class)->alerts(['date' => '2026-06-26']);

        $this->assertArrayHasKey('alerts_returned', $result);
        $this->assertArrayHasKey('truncated', $result);
        $this->assertIsInt($result['alerts_returned']);
        $this->assertIsBool($result['truncated']);

        Carbon::setTestNow();
    }

    public function test_command_summary_flag_returns_empty_alerts(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedConversation(62, now()->subMinutes(30), 'captacion_agendar', null, 'high');
        $this->seedInbound(62, now()->subMinutes(20));

        Artisan::call('whatsapp:operational-alerts', [
            '--date'    => '2026-06-26',
            '--json'    => true,
            '--summary' => true,
        ]);
        $json = json_decode(Artisan::output(), true);

        $this->assertIsArray($json);
        $this->assertSame([], $json['alerts'] ?? ['notempty'], '--summary must return empty alerts[]');
        $this->assertSame(0, $json['alerts_returned'] ?? -1);
        $this->assertArrayHasKey('summary', $json);

        Carbon::setTestNow();
    }

    // ── Filters ───────────────────────────────────────────────────────────────

    public function test_category_filter_excludes_non_matching(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
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
        Carbon::setTestNow('2026-06-26 23:59:59');
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
        return app(WhatsappOperationalAlertService::class)->alerts(['date' => '2026-06-26', 'include_items' => true]);
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

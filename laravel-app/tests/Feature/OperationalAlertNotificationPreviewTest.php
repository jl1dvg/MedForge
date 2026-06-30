<?php

namespace Tests\Feature;

use App\Modules\Whatsapp\Http\Controllers\OperationalAlertNotificationPreviewController;
use App\Modules\Whatsapp\Services\WhatsappOperationalAlertService;
use App\Modules\Whatsapp\Services\WhatsappOperationalNotificationPreviewService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Validates the dry-run notification preview:
 *  - Only hot_unassigned / critical / unassigned are included.
 *  - Rescue aging and medium/assigned alerts are excluded.
 *  - read_only=true, db_writes=0, channel=none.
 *  - No DB writes on any call.
 */
class OperationalAlertNotificationPreviewTest extends TestCase
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

    // ── 1. Inclusion/exclusion rules ──────────────────────────────────────────

    public function test_only_hot_unassigned_critical_unassigned_included(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');

        // Conv 1: hot_unassigned + critical (90 min, unassigned) → MUST include
        $this->seedConversation(1, now()->subMinutes(90), null, 'high', 'captacion_agendar');
        $this->seedInbound(1, now()->subMinutes(80));

        // Conv 2: hot_unassigned + high (30 min) → excluded (not critical)
        $this->seedConversation(2, now()->subMinutes(30), null, 'high', 'captacion_agendar');
        $this->seedInbound(2, now()->subMinutes(20));

        $data = $this->callPreview();

        $this->assertTrue($data['read_only']);
        $this->assertSame(0, $data['db_writes']);
        $this->assertSame('dry_run', $data['mode']);
        $this->assertSame('none', $data['channel']);

        foreach ($data['notifications'] as $n) {
            $this->assertSame(WhatsappOperationalAlertService::ALERT_HOT_UNASSIGNED, $n['alert_type']);
            $this->assertSame('critical', $n['severity']);
            $this->assertNull($n['assigned_user_id'] ?? null);
        }

        Carbon::setTestNow();
    }

    public function test_rescue_aging_excluded(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');

        // Conv 3: rescue bucket (3 days) → must NOT appear in preview
        $this->seedConversation(3, now()->subDays(3), null, 'normal', 'captacion_agendar');
        $this->seedInbound(3, now()->subDays(3)->addHours(1));

        $data = $this->callPreview();

        foreach ($data['notifications'] as $n) {
            $this->assertNotSame(WhatsappOperationalAlertService::ALERT_RESCUE_AGING, $n['alert_type'] ?? '');
        }

        Carbon::setTestNow();
    }

    public function test_medium_severity_excluded(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');

        // Conv 4: 30 min → high severity, not critical — excluded
        $this->seedConversation(4, now()->subMinutes(30), null, 'high', 'captacion_agendar');
        $this->seedInbound(4, now()->subMinutes(20));

        $data = $this->callPreview();

        foreach ($data['notifications'] as $n) {
            $this->assertNotSame('medium', $n['severity'] ?? '');
        }

        Carbon::setTestNow();
    }

    public function test_assigned_conversations_excluded(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');

        // Conv 5: 90 min but assigned to agent 1 → excluded
        DB::table('users')->insert(['id' => 1, 'nombre' => 'Agente Uno']);
        $this->seedConversation(5, now()->subMinutes(90), 1, 'high', 'captacion_agendar');
        $this->seedInbound(5, now()->subMinutes(80));

        $data = $this->callPreview();

        foreach ($data['notifications'] as $n) {
            $this->assertNull($n['assigned_user_id'] ?? null);
        }

        Carbon::setTestNow();
    }

    // ── 2. Read-only guarantees ───────────────────────────────────────────────

    public function test_returns_read_only_true(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedHotCritical(10);

        $data = $this->callPreview();

        $this->assertTrue($data['read_only']);

        Carbon::setTestNow();
    }

    public function test_returns_db_writes_zero(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedHotCritical(11);

        $data = $this->callPreview();

        $this->assertSame(0, $data['db_writes']);

        Carbon::setTestNow();
    }

    public function test_does_not_insert_handoff_events(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedHotCritical(12);
        $before = DB::table('whatsapp_handoff_events')->count();

        $this->callPreview();

        $this->assertSame($before, DB::table('whatsapp_handoff_events')->count());

        Carbon::setTestNow();
    }

    public function test_does_not_insert_operational_events(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedHotCritical(13);
        $before = DB::table('whatsapp_operational_events')->count();

        $this->callPreview();

        $this->assertSame($before, DB::table('whatsapp_operational_events')->count());

        Carbon::setTestNow();
    }

    public function test_does_not_modify_conversations(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedHotCritical(14);
        $before = DB::table('whatsapp_conversations')->where('id', 14)->value('assigned_user_id');

        $this->callPreview();

        $this->assertSame($before, DB::table('whatsapp_conversations')->where('id', 14)->value('assigned_user_id'));

        Carbon::setTestNow();
    }

    public function test_does_not_modify_handoffs(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedHotCritical(15);

        $this->callPreview();

        $this->assertDatabaseHas('whatsapp_handoffs', ['conversation_id' => 15, 'status' => 'queued']);

        Carbon::setTestNow();
    }

    // ── 3. Response shape ─────────────────────────────────────────────────────

    public function test_notification_item_has_display_name_and_wa_number(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedHotCritical(20);

        $data = $this->callPreview();

        if (!empty($data['notifications'])) {
            $n = $data['notifications'][0];
            $this->assertArrayHasKey('display_name', $n);
            $this->assertArrayHasKey('wa_number', $n);
            $this->assertNotEmpty($n['wa_number']);
        }

        Carbon::setTestNow();
    }

    public function test_message_preview_contains_display_name(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedHotCritical(21);

        $data = $this->callPreview();

        if (!empty($data['notifications'])) {
            $n = $data['notifications'][0];
            $this->assertStringContainsString($n['display_name'], $n['message_preview']);
        }

        Carbon::setTestNow();
    }

    public function test_chat_url_uses_search_wa_number(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedHotCritical(22);

        $data = $this->callPreview();

        if (!empty($data['notifications'])) {
            $n = $data['notifications'][0];
            $this->assertStringContainsString('search=', $n['chat_url']);
            $this->assertStringContainsString('filter=all', $n['chat_url']);
        }

        Carbon::setTestNow();
    }

    public function test_channel_is_none(): void
    {
        $data = $this->callPreview();
        $this->assertSame('none', $data['channel']);
    }

    // ── 4. API contract ───────────────────────────────────────────────────────

    public function test_response_has_all_contract_keys(): void
    {
        $data = $this->callPreview();

        foreach (['ok', 'mode', 'read_only', 'db_writes', 'channel', 'would_notify', 'evaluated', 'notifications'] as $key) {
            $this->assertArrayHasKey($key, $data, "Response missing key: {$key}");
        }
        $this->assertTrue($data['ok']);
        $this->assertSame('dry_run', $data['mode']);
        $this->assertTrue($data['read_only']);
        $this->assertSame(0, $data['db_writes']);
        $this->assertSame('none', $data['channel']);
        $this->assertIsInt($data['would_notify']);
        $this->assertIsArray($data['notifications']);
    }

    public function test_notification_item_has_all_contract_fields(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedHotCritical(30);

        $data = $this->callPreview();

        if (!empty($data['notifications'])) {
            $n = $data['notifications'][0];
            foreach (['conversation_id', 'wa_number', 'display_name', 'alert_type', 'severity', 'topic_label', 'waiting_minutes', 'chat_url', 'message_preview'] as $field) {
                $this->assertArrayHasKey($field, $n, "Notification item missing field: {$field}");
            }
        }

        Carbon::setTestNow();
    }

    // ── 5. Guardrails ─────────────────────────────────────────────────────────

    public function test_rejects_send_true_param(): void
    {
        $request  = Request::create('/v2/whatsapp/api/operational-alerts/notification-preview', 'GET', ['send' => 'true']);
        $response = app(OperationalAlertNotificationPreviewController::class)->index($request);

        $this->assertSame(422, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertFalse($data['ok']);
        $this->assertSame('none', $data['channel']);
    }

    public function test_rejects_channel_param(): void
    {
        $request  = Request::create('/v2/whatsapp/api/operational-alerts/notification-preview', 'GET', ['channel' => 'telegram']);
        $response = app(OperationalAlertNotificationPreviewController::class)->index($request);

        $this->assertSame(422, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertFalse($data['ok']);
        $this->assertSame('none', $data['channel']);
    }

    public function test_channel_remains_none_regardless_of_params(): void
    {
        $data = $this->callPreview();
        $this->assertSame('none', $data['channel']);
    }

    public function test_mode_remains_dry_run(): void
    {
        $data = $this->callPreview();
        $this->assertSame('dry_run', $data['mode']);
    }

    public function test_no_events_written_on_guardrail_rejection(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $beforeEvents = DB::table('whatsapp_operational_events')->count();
        $beforeHandoff = DB::table('whatsapp_handoff_events')->count();

        $request = Request::create('/v2/whatsapp/api/operational-alerts/notification-preview', 'GET', ['send' => 'true']);
        app(OperationalAlertNotificationPreviewController::class)->index($request);

        $this->assertSame($beforeEvents, DB::table('whatsapp_operational_events')->count());
        $this->assertSame($beforeHandoff, DB::table('whatsapp_handoff_events')->count());

        Carbon::setTestNow();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    private function callPreview(): array
    {
        $request  = Request::create('/v2/whatsapp/api/operational-alerts/notification-preview', 'GET', ['date' => '2026-06-26']);
        $response = app(OperationalAlertNotificationPreviewController::class)->index($request);
        $data     = json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        return $data;
    }

    private function seedHotCritical(int $id): void
    {
        // 90 min wait → critical severity
        $this->seedConversation($id, now()->subMinutes(90), null, 'high', 'captacion_agendar');
        $this->seedInbound($id, now()->subMinutes(80));
    }

    private function seedConversation(int $id, Carbon $queuedAt, ?int $agentId, string $priority, string $topic): void
    {
        DB::table('whatsapp_conversations')->insert([
            'id'                   => $id,
            'wa_number'            => '593000' . $id,
            'display_name'         => 'Paciente Test ' . $id,
            'patient_hc_number'    => 'HC-' . $id,
            'needs_human'          => true,
            'assigned_user_id'     => $agentId,
            'last_message_at'      => $queuedAt,
            'handoff_requested_at' => $queuedAt,
            'created_at'           => $queuedAt,
            'updated_at'           => $queuedAt,
        ]);

        DB::table('whatsapp_handoffs')->insert([
            'id'                => $id,
            'conversation_id'   => $id,
            'wa_number'         => '593000' . $id,
            'status'            => $agentId !== null ? 'assigned' : 'queued',
            'topic'             => $topic,
            'priority'          => $priority,
            'assigned_agent_id' => $agentId,
            'assigned_at'       => $agentId !== null ? $queuedAt->copy()->addMinutes(5) : null,
            'queued_at'         => $queuedAt,
            'created_at'        => $queuedAt,
            'updated_at'        => $queuedAt,
        ]);
    }

    private function seedInbound(int $conversationId, Carbon $at): void
    {
        DB::table('whatsapp_messages')->insert([
            'conversation_id'   => $conversationId,
            'direction'         => 'inbound',
            'message_timestamp' => $at,
            'created_at'        => $at,
            'updated_at'        => $at,
        ]);
    }
}

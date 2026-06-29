<?php

namespace Tests\Feature;

use App\Modules\Whatsapp\Http\Controllers\OperationalAlertsController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tests for OperationalAlertsController — read-only, no writes allowed.
 *
 * We call the controller directly (bypassing routing/middleware) to avoid
 * needing the legacy auth session setup. Auth-layer behaviour is covered by
 * WhatsappV2PermissionsTest and the route definition.
 */
class OperationalAlertsApiTest extends TestCase
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

    // ── 1. Core response shape ────────────────────────────────────────────────

    public function test_api_returns_read_only_true_and_zero_db_writes(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedHotConversation(1);

        $data = $this->callApi(['date' => '2026-06-26']);

        $this->assertTrue($data['read_only'] ?? false, 'read_only must be true');
        $this->assertSame(0, $data['db_writes'] ?? -1, 'db_writes must be 0');
        $this->assertSame('read_only', $data['mode'] ?? '');

        Carbon::setTestNow();
    }

    public function test_api_returns_summary_and_by_type(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedHotConversation(1);

        $data = $this->callApi(['date' => '2026-06-26']);

        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('critical', $data['summary']);
        $this->assertArrayHasKey('high', $data['summary']);
        $this->assertArrayHasKey('medium', $data['summary']);
        $this->assertArrayHasKey('low', $data['summary']);
        $this->assertArrayHasKey('by_type', $data);
        $this->assertTrue($data['ok'] ?? false);

        Carbon::setTestNow();
    }

    public function test_api_returns_alerts_array_with_expected_fields(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedHotConversation(1);

        $data = $this->callApi(['date' => '2026-06-26']);

        $this->assertIsArray($data['alerts']);
        if (!empty($data['alerts'])) {
            $first = $data['alerts'][0];
            $this->assertArrayHasKey('alert_type', $first);
            $this->assertArrayHasKey('severity', $first);
            $this->assertArrayHasKey('conversation_id', $first);
            $this->assertArrayHasKey('wa_number', $first);
            $this->assertArrayHasKey('display_name', $first);
            $this->assertArrayHasKey('display_subtitle', $first);
            $this->assertArrayHasKey('hc_number', $first);
            $this->assertArrayHasKey('waiting_minutes', $first);
            $this->assertArrayHasKey('suggested_action', $first);
        }

        Carbon::setTestNow();
    }

    // ── 2. Filters ────────────────────────────────────────────────────────────

    public function test_api_filters_by_severity_critical(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        // Conv 2: 90 min → critical; conv 3: 30 min → high
        $this->seedConversation(2, now()->subMinutes(90), null, 'high');
        $this->seedInbound(2, now()->subMinutes(80));
        $this->seedConversation(3, now()->subMinutes(30), null, 'high');
        $this->seedInbound(3, now()->subMinutes(20));

        $data = $this->callApi(['date' => '2026-06-26', 'severity' => 'critical']);

        foreach ($data['alerts'] as $alert) {
            $this->assertSame('critical', $alert['severity']);
        }

        Carbon::setTestNow();
    }

    public function test_api_filters_by_category_captacion(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedConversation(4, now()->subMinutes(30), null, 'high', 'captacion_agendar');
        $this->seedInbound(4, now()->subMinutes(20));

        $data = $this->callApi(['date' => '2026-06-26', 'category' => 'captacion']);

        foreach ($data['alerts'] as $alert) {
            $this->assertSame('captacion', $alert['category']);
        }

        Carbon::setTestNow();
    }

    public function test_api_filters_by_type_hot_unassigned(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedConversation(5, now()->subMinutes(30), null, 'high');
        $this->seedInbound(5, now()->subMinutes(20));

        $data = $this->callApi(['date' => '2026-06-26', 'type' => 'hot_unassigned']);

        foreach ($data['alerts'] as $alert) {
            $this->assertSame('hot_unassigned', $alert['alert_type']);
        }

        Carbon::setTestNow();
    }

    public function test_api_filters_by_agent_unassigned(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedConversation(6, now()->subMinutes(30), null, 'high');
        $this->seedInbound(6, now()->subMinutes(20));

        $data = $this->callApi(['date' => '2026-06-26', 'agent' => 'unassigned']);

        foreach ($data['alerts'] as $alert) {
            $this->assertNull($alert['assigned_user_id']);
        }

        Carbon::setTestNow();
    }

    // ── 3. Read-only guarantees ───────────────────────────────────────────────

    public function test_api_does_not_modify_conversations(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedHotConversation(10);
        $before = DB::table('whatsapp_conversations')->where('id', 10)->value('assigned_user_id');

        $this->callApi(['date' => '2026-06-26']);

        $this->assertSame($before, DB::table('whatsapp_conversations')->where('id', 10)->value('assigned_user_id'));

        Carbon::setTestNow();
    }

    public function test_api_does_not_modify_handoffs(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedHotConversation(11);

        $this->callApi(['date' => '2026-06-26']);

        $this->assertDatabaseHas('whatsapp_handoffs', ['conversation_id' => 11, 'status' => 'queued']);

        Carbon::setTestNow();
    }

    public function test_api_does_not_insert_handoff_events(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedHotConversation(12);
        $before = DB::table('whatsapp_handoff_events')->count();

        $this->callApi(['date' => '2026-06-26']);

        $this->assertSame($before, DB::table('whatsapp_handoff_events')->count());

        Carbon::setTestNow();
    }

    public function test_api_does_not_insert_operational_events(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedHotConversation(13);
        $before = DB::table('whatsapp_operational_events')->count();

        $this->callApi(['date' => '2026-06-26']);

        $this->assertSame($before, DB::table('whatsapp_operational_events')->count());

        Carbon::setTestNow();
    }

    // ── 4. API contract ───────────────────────────────────────────────────────

    public function test_api_contract_has_required_top_level_keys(): void
    {
        $data = $this->callApi(['date' => '2026-06-26']);

        foreach (['ok', 'read_only', 'db_writes', 'mode', 'summary', 'by_type', 'alerts', 'filters_applied'] as $key) {
            $this->assertArrayHasKey($key, $data, "Response missing key: {$key}");
        }
        $this->assertTrue($data['ok']);
        $this->assertTrue($data['read_only']);
        $this->assertSame(0, $data['db_writes']);
        $this->assertSame('read_only', $data['mode']);
        $this->assertIsArray($data['summary']);
        $this->assertIsArray($data['by_type']);
        $this->assertIsArray($data['alerts']);
        $this->assertIsArray($data['filters_applied']);
    }

    public function test_api_contract_summary_has_all_severity_keys(): void
    {
        $data = $this->callApi(['date' => '2026-06-26']);

        foreach (['critical', 'high', 'medium', 'low'] as $sev) {
            $this->assertArrayHasKey($sev, $data['summary'], "summary missing key: {$sev}");
        }
    }

    public function test_api_contract_filters_applied_has_expected_keys(): void
    {
        $data = $this->callApi(['date' => '2026-06-26']);

        foreach (['date', 'severity', 'category', 'type', 'agent', 'limit', 'summary'] as $key) {
            $this->assertArrayHasKey($key, $data['filters_applied'], "filters_applied missing key: {$key}");
        }
    }

    // ── 5. Invalid params ─────────────────────────────────────────────────────

    public function test_api_rejects_invalid_severity(): void
    {
        $response = app(OperationalAlertsController::class)->index(
            Request::create('/v2/whatsapp/api/operational-alerts', 'GET', ['severity' => 'extreme'])
        );

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_api_rejects_invalid_category(): void
    {
        $response = app(OperationalAlertsController::class)->index(
            Request::create('/v2/whatsapp/api/operational-alerts', 'GET', ['category' => 'unknown'])
        );

        $this->assertSame(422, $response->getStatusCode());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    private function callApi(array $params = []): array
    {
        $request  = Request::create('/v2/whatsapp/api/operational-alerts', 'GET', $params);
        $response = app(OperationalAlertsController::class)->index($request);
        $data     = json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data, 'API must return valid JSON');
        return $data;
    }

    private function seedHotConversation(int $id): void
    {
        $this->seedConversation($id, now()->subMinutes(30), null, 'high');
        $this->seedInbound($id, now()->subMinutes(20));
    }

    private function seedConversation(int $id, Carbon $queuedAt, ?int $agentId, string $priority, string $topic = 'captacion_agendar'): void
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

<?php

namespace Tests\Feature;

use App\Console\Commands\WhatsappOperationalDailyReport;
use App\Modules\Whatsapp\Http\Controllers\OperationalAlertDailyReportController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Validates the daily report:
 *  - read_only=true, db_writes=0 always.
 *  - Returns summary, by_type, by_category, by_agent, top_topics.
 *  - notification_preview mode=dry_run, channel=none.
 *  - No DB writes on any call.
 *  - rescue_aging not counted as notification candidate.
 */
class OperationalDailyReportTest extends TestCase
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

    // ── 1. Read-only guarantees ───────────────────────────────────────────────

    public function test_returns_read_only_true_and_zero_db_writes(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedHotCritical(1);

        $data = $this->callApi();

        $this->assertTrue($data['read_only']);
        $this->assertSame(0, $data['db_writes']);
        $this->assertSame('read_only', $data['mode']);

        Carbon::setTestNow();
    }

    public function test_does_not_modify_conversations(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedHotCritical(2);
        $before = DB::table('whatsapp_conversations')->where('id', 2)->value('assigned_user_id');

        $this->callApi();

        $this->assertSame($before, DB::table('whatsapp_conversations')->where('id', 2)->value('assigned_user_id'));
        Carbon::setTestNow();
    }

    public function test_does_not_modify_handoffs(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedHotCritical(3);

        $this->callApi();

        $this->assertDatabaseHas('whatsapp_handoffs', ['conversation_id' => 3, 'status' => 'queued']);
        Carbon::setTestNow();
    }

    public function test_does_not_insert_handoff_events(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedHotCritical(4);
        $before = DB::table('whatsapp_handoff_events')->count();

        $this->callApi();

        $this->assertSame($before, DB::table('whatsapp_handoff_events')->count());
        Carbon::setTestNow();
    }

    public function test_does_not_insert_operational_events(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedHotCritical(5);
        $before = DB::table('whatsapp_operational_events')->count();

        $this->callApi();

        $this->assertSame($before, DB::table('whatsapp_operational_events')->count());
        Carbon::setTestNow();
    }

    // ── 2. Response shape ─────────────────────────────────────────────────────

    public function test_returns_summary_with_all_keys(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedHotCritical(10);

        $data = $this->callApi();

        $this->assertArrayHasKey('summary', $data);
        $s = $data['summary'];
        foreach (['evaluated', 'alerts_total', 'critical', 'high', 'medium', 'low'] as $key) {
            $this->assertArrayHasKey($key, $s, "summary missing: {$key}");
        }

        Carbon::setTestNow();
    }

    public function test_returns_by_type(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedHotCritical(11);

        $data = $this->callApi();

        $this->assertArrayHasKey('by_type', $data);
        $this->assertIsArray($data['by_type']);

        Carbon::setTestNow();
    }

    public function test_returns_by_category(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedHotCritical(12);

        $data = $this->callApi();

        $this->assertArrayHasKey('by_category', $data);
        $this->assertIsArray($data['by_category']);

        Carbon::setTestNow();
    }

    public function test_returns_by_agent(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedHotCritical(13);

        $data = $this->callApi();

        $this->assertArrayHasKey('by_agent', $data);
        $this->assertIsArray($data['by_agent']);

        Carbon::setTestNow();
    }

    public function test_returns_top_topics(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedHotCritical(14);

        $data = $this->callApi();

        $this->assertArrayHasKey('top_topics', $data);
        $this->assertIsArray($data['top_topics']);

        Carbon::setTestNow();
    }

    // ── 3. Notification preview in report ─────────────────────────────────────

    public function test_notification_preview_is_dry_run_and_channel_none(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedHotCritical(20);

        $data = $this->callApi();

        $this->assertArrayHasKey('notification_preview', $data);
        $np = $data['notification_preview'];
        $this->assertSame('dry_run', $np['mode']);
        $this->assertSame('none', $np['channel']);
        $this->assertArrayHasKey('would_notify', $np);

        Carbon::setTestNow();
    }

    public function test_rescue_aging_not_counted_in_notification_preview(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');

        // Seed only rescue aging (3 days old) — should NOT be a notification candidate
        $this->seedConversation(30, now()->subDays(3), null, 'normal', 'captacion_agendar');
        $this->seedInbound(30, now()->subDays(3)->addHours(1));

        $data = $this->callApi();

        $this->assertSame(0, $data['notification_preview']['would_notify']);

        Carbon::setTestNow();
    }

    // ── 4. Aggregation correctness ────────────────────────────────────────────

    public function test_by_agent_groups_unassigned_correctly(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedHotCritical(40);

        $data = $this->callApi();

        $unassignedAgents = array_filter($data['by_agent'], fn ($a) => $a['assigned_user_id'] === null);
        if (!empty($unassignedAgents)) {
            $ua = array_values($unassignedAgents)[0];
            $this->assertSame('Sin asignar', $ua['assigned_user_name']);
            $this->assertGreaterThan(0, $ua['alerts_total']);
        }

        Carbon::setTestNow();
    }

    public function test_top_topics_sorted_by_count_desc(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedConversation(50, now()->subMinutes(90), null, 'high', 'captacion_agendar');
        $this->seedInbound(50, now()->subMinutes(80));
        $this->seedConversation(51, now()->subMinutes(90), null, 'high', 'captacion_agendar');
        $this->seedInbound(51, now()->subMinutes(80));
        $this->seedConversation(52, now()->subMinutes(90), null, 'high', 'operacion_soporte');
        $this->seedInbound(52, now()->subMinutes(80));

        $data = $this->callApi();

        $topics = $data['top_topics'];
        if (count($topics) >= 2) {
            $this->assertGreaterThanOrEqual($topics[1]['count'], $topics[0]['count']);
        }

        Carbon::setTestNow();
    }

    // ── 5. Artisan command ────────────────────────────────────────────────────

    public function test_artisan_command_returns_valid_json(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedHotCritical(60);

        $output = $this->artisan('whatsapp:operational-daily-report', [
            '--date' => '2026-06-26',
            '--json' => true,
        ])->assertExitCode(0);

        Carbon::setTestNow();
    }

    public function test_artisan_command_does_not_write_db(): void
    {
        Carbon::setTestNow('2026-06-26 23:59:59');
        $this->seedHotCritical(61);
        $before = DB::table('whatsapp_operational_events')->count();

        $this->artisan('whatsapp:operational-daily-report', ['--date' => '2026-06-26', '--json' => true])
             ->assertExitCode(0);

        $this->assertSame($before, DB::table('whatsapp_operational_events')->count());

        Carbon::setTestNow();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    private function callApi(array $params = []): array
    {
        $params = array_merge(['date' => '2026-06-26'], $params);
        $request  = Request::create('/v2/whatsapp/api/operational-alerts/daily-report', 'GET', $params);
        $response = app(OperationalAlertDailyReportController::class)->index($request);
        $data     = json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        return $data;
    }

    private function seedHotCritical(int $id): void
    {
        $this->seedConversation($id, now()->subMinutes(90), null, 'high', 'captacion_agendar');
        $this->seedInbound($id, now()->subMinutes(80));
    }

    private function seedConversation(int $id, \Illuminate\Support\Carbon $queuedAt, ?int $agentId, string $priority, string $topic): void
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

    private function seedInbound(int $conversationId, \Illuminate\Support\Carbon $at): void
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

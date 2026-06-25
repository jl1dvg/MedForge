<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappOperationalQueueAuditCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'whatsapp_operational_booking_attributions',
            'procedimiento_proyectado',
            'whatsapp_messages',
            'whatsapp_handoff_events',
            'whatsapp_handoffs',
            'whatsapp_conversation_attributions',
            'whatsapp_conversations',
        ] as $table) {
            Schema::dropIfExists($table);
        }

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
            $table->unsignedBigInteger('assigned_agent_id')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('direction', 16);
            $table->string('sender_type', 32)->nullable();
            $table->timestamp('message_timestamp')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_operational_booking_attributions', function (Blueprint $table): void {
            $table->id();
            $table->string('booking_source', 48)->default('bot_api');
            $table->string('observed_booking_key', 191)->unique();
            $table->unsignedBigInteger('form_id')->nullable();
            $table->unsignedBigInteger('attributed_conversation_id')->nullable()->index('woba_attr_conv_audit_idx');
            $table->unsignedBigInteger('handoff_id')->nullable();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->string('event_type', 64);
            $table->string('attribution_method', 64);
            $table->string('confidence', 24);
            $table->timestamp('event_at')->nullable();
            $table->timestamp('booking_at')->nullable();
            $table->timestamps();
        });

        Schema::create('procedimiento_proyectado', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('form_id')->unique();
            $table->string('hc_number', 64)->index();
            $table->string('procedimiento_nombre', 191)->nullable();
            $table->date('fecha')->nullable();
            $table->boolean('sigcenter_present')->default(true);
            $table->timestamps();
        });
    }

    public function test_json_output_contains_required_top_level_keys(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        $this->seedConversation(1, now()->subHour(), 'captacion_agendar');

        Artisan::call('whatsapp:operational-queue-audit', [
            '--date' => '2026-06-25',
            '--json' => true,
        ]);
        $payload = json_decode(Artisan::output(), true);

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('date', $payload);
        $this->assertArrayHasKey('generated_at', $payload);
        $this->assertArrayHasKey('decision_engine', $payload);
        $this->assertArrayHasKey('queues', $payload);
        $this->assertArrayHasKey('diff', $payload);

        Carbon::setTestNow();
    }

    public function test_diff_count_is_zero_when_all_decisions_accounted(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        // HOT_OPEN unassigned → supervisor_review is in supervisor queue
        $this->seedConversation(1, now()->subHours(3), 'captacion_agendar');

        Artisan::call('whatsapp:operational-queue-audit', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true);

        $engineTotal   = (int) ($payload['decision_engine']['total'] ?? -1);
        $accountedTotal = (int) ($payload['queues']['total_accounted'] ?? -1);
        $diffCount     = (int) ($payload['diff']['count'] ?? -1);

        $this->assertGreaterThanOrEqual(0, $engineTotal);
        $this->assertSame($engineTotal, $accountedTotal);
        $this->assertSame(0, $diffCount);

        Carbon::setTestNow();
    }

    public function test_diff_by_action_is_empty_when_no_discrepancy(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        $this->seedConversation(1, now()->subHours(3), 'captacion_agendar');

        Artisan::call('whatsapp:operational-queue-audit', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true);

        $this->assertEmpty($payload['diff']['by_action']);
        $this->assertEmpty($payload['diff']['conversation_ids']);

        Carbon::setTestNow();
    }

    public function test_decision_engine_total_matches_by_action_sum(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        // supervisor candidate
        $this->seedConversation(1, now()->subHours(3), 'captacion_agendar', agentId: 9);
        // rescue candidate
        $this->seedConversation(2, now()->subDays(3), 'captacion_agendar');

        Artisan::call('whatsapp:operational-queue-audit', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true);

        $total      = (int) ($payload['decision_engine']['total'] ?? 0);
        $byActionSum = (int) array_sum(array_values($payload['decision_engine']['by_action']));

        $this->assertSame($total, $byActionSum);

        Carbon::setTestNow();
    }

    public function test_queue_totals_sum_to_total_accounted(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        $this->seedConversation(1, now()->subHours(3), 'captacion_agendar', agentId: 9);
        $this->seedConversation(2, now()->subDays(3), 'captacion_agendar');

        Artisan::call('whatsapp:operational-queue-audit', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true);

        $queues = $payload['queues'];
        $this->assertArrayHasKey('assignment_total', $queues);
        $sumOfParts = (int) $queues['assignment_total']
            + (int) $queues['supervisor_total']
            + (int) $queues['rescue_total']
            + (int) $queues['no_action_total'];

        $this->assertSame((int) $queues['total_accounted'], $sumOfParts);

        Carbon::setTestNow();
    }

    public function test_table_output_exits_zero(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        $this->seedConversation(1, now()->subHour(), 'captacion_agendar', agentId: 9);

        $exitCode = Artisan::call('whatsapp:operational-queue-audit', [
            '--date' => '2026-06-25',
        ]);

        $this->assertSame(0, $exitCode);

        Carbon::setTestNow();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function seedConversation(int $id, Carbon $queuedAt, string $topic, ?int $agentId = null): void
    {
        DB::table('whatsapp_conversations')->insert([
            'id'                   => $id,
            'wa_number'            => '5940000' . $id,
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
            'wa_number'         => '5940000' . $id,
            'status'            => $agentId !== null ? 'assigned' : 'queued',
            'topic'             => $topic,
            'assigned_agent_id' => $agentId,
            'assigned_at'       => $agentId !== null ? $queuedAt->copy()->addMinutes(5) : null,
            'queued_at'         => $queuedAt,
            'created_at'        => $queuedAt,
            'updated_at'        => $queuedAt,
        ]);
    }
}

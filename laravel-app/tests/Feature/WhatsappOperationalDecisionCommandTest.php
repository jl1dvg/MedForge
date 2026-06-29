<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappOperationalDecisionCommandTest extends TestCase
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
            $table->string('display_name', 191)->nullable();
            $table->string('patient_full_name', 191)->nullable();
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
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedBigInteger('form_id')->nullable();
            $table->unsignedBigInteger('attributed_conversation_id')->nullable()->index('woba_attr_conv_cmd_idx');
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

    public function test_command_outputs_valid_json(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        $this->seedConversation(1, now()->subHour(), 'captacion_agendar');

        Artisan::call('whatsapp:operational-decisions', [
            '--date' => '2026-06-25',
            '--json' => true,
        ]);

        $output = Artisan::output();
        $payload = json_decode($output, true);

        $this->assertIsArray($payload);
        $this->assertSame('2026-06-25', $payload['date']);

        Carbon::setTestNow();
    }

    public function test_json_summary_contains_required_keys(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        $this->seedConversation(1, now()->subHour(), 'captacion_agendar');

        Artisan::call('whatsapp:operational-decisions', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true);

        $summary = $payload['summary'];
        $this->assertArrayHasKey('by_recommended_action', $summary);
        $this->assertArrayHasKey('eligible_for_autoassign', $summary);
        $this->assertArrayHasKey('eligible_for_rescue', $summary);
        $this->assertArrayHasKey('eligible_for_supervisor_alert', $summary);
        $this->assertArrayHasKey('already_converted', $summary);
        $this->assertArrayHasKey('total_evaluated', $summary);

        Carbon::setTestNow();
    }

    public function test_decisions_list_is_present_in_output(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        $this->seedConversation(1, now()->subHour(), 'captacion_agendar');

        Artisan::call('whatsapp:operational-decisions', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true);

        $this->assertArrayHasKey('decisions', $payload);
        $this->assertIsArray($payload['decisions']);
        $this->assertCount(1, $payload['decisions']);
        $this->assertSame(1, $payload['decisions'][0]['conversation_id']);

        Carbon::setTestNow();
    }

    public function test_command_exits_zero_with_no_conversations(): void
    {
        $exitCode = Artisan::call('whatsapp:operational-decisions', ['--json' => true]);
        $payload  = json_decode(Artisan::output(), true);

        $this->assertSame(0, $exitCode);
        $this->assertSame(0, $payload['summary']['total_evaluated']);
    }

    public function test_summary_only_omits_decisions_key(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        $this->seedConversation(1, now()->subHour(), 'captacion_agendar');

        Artisan::call('whatsapp:operational-decisions', ['--summary-only' => true, '--json' => true]);
        $payload = json_decode(Artisan::output(), true);

        $this->assertArrayHasKey('summary', $payload);
        $this->assertArrayNotHasKey('decisions', $payload);
        $this->assertArrayHasKey('by_recommended_action', $payload['summary']);

        Carbon::setTestNow();
    }

    public function test_filter_by_action_returns_only_matching_decisions(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        // HOT_OPEN unassigned → assign_now
        $this->seedConversation(1, now()->subHour(), 'captacion_agendar');
        // RESCUE → rescue_followup
        $this->seedConversation(2, now()->subDays(3), 'captacion_agendar');
        $this->seedInbound(1, now()->subMinutes(30));

        Artisan::call('whatsapp:operational-decisions', ['--action' => 'assign_now', '--json' => true]);
        $payload = json_decode(Artisan::output(), true);

        $this->assertNotEmpty($payload['decisions']);
        foreach ($payload['decisions'] as $d) {
            $this->assertSame('assign_now', $d['recommended_action']);
        }

        Carbon::setTestNow();
    }

    public function test_filter_by_bucket_returns_only_matching_decisions(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        $this->seedConversation(1, now()->subHour(), 'captacion_agendar');
        $this->seedInbound(1, now()->subMinutes(20));
        // backlog
        $this->seedConversation(2, now()->subDays(12), 'captacion_agendar');

        Artisan::call('whatsapp:operational-decisions', ['--bucket' => 'hot_open', '--json' => true]);
        $payload = json_decode(Artisan::output(), true);

        $this->assertNotEmpty($payload['decisions']);
        foreach ($payload['decisions'] as $d) {
            $this->assertSame('hot_open', $d['bucket']);
        }

        Carbon::setTestNow();
    }

    public function test_filter_by_priority_returns_only_matching_decisions(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        $this->seedConversation(1, now()->subHour(), 'captacion_agendar');
        $this->seedInbound(1, now()->subMinutes(20));

        Artisan::call('whatsapp:operational-decisions', ['--priority' => 'high', '--json' => true]);
        $payload = json_decode(Artisan::output(), true);

        foreach ($payload['decisions'] as $d) {
            $this->assertSame('high', $d['priority']);
        }

        Carbon::setTestNow();
    }

    public function test_filter_by_risk_returns_only_matching_decisions(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        // HOT_OPEN unassigned, window open → risk=medium
        $this->seedConversation(1, now()->subHour(), 'captacion_agendar');
        $this->seedInbound(1, now()->subMinutes(20));
        // RESCUE 5+ days → risk=high
        $this->seedConversation(2, now()->subDays(6), 'captacion_agendar');

        Artisan::call('whatsapp:operational-decisions', ['--risk' => 'high', '--json' => true]);
        $payload = json_decode(Artisan::output(), true);

        $this->assertNotEmpty($payload['decisions']);
        foreach ($payload['decisions'] as $d) {
            $this->assertSame('high', $d['risk_level']);
        }

        Carbon::setTestNow();
    }

    public function test_limit_caps_decisions_but_summary_reflects_full_filtered_set(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        // Three HOT_OPEN unassigned → all assign_now
        foreach ([1, 2, 3] as $i) {
            $this->seedConversation($i, now()->subHour(), 'captacion_agendar');
            $this->seedInbound($i, now()->subMinutes(10 + $i));
        }

        Artisan::call('whatsapp:operational-decisions', ['--limit' => '2', '--json' => true]);
        $payload = json_decode(Artisan::output(), true);

        $this->assertCount(2, $payload['decisions']);
        // summary must reflect all 3 (full filtered set before limit)
        $this->assertSame(3, $payload['summary']['total_evaluated']);

        Carbon::setTestNow();
    }

    public function test_combined_filters_apply_and(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        // HOT_OPEN unassigned → assign_now, hot_open
        $this->seedConversation(1, now()->subHour(), 'captacion_agendar');
        $this->seedInbound(1, now()->subMinutes(20));
        // RESCUE → rescue_followup, rescue
        $this->seedConversation(2, now()->subDays(3), 'captacion_agendar');

        Artisan::call('whatsapp:operational-decisions', [
            '--bucket' => 'rescue',
            '--action' => 'assign_now',
            '--json'   => true,
        ]);
        $payload = json_decode(Artisan::output(), true);

        // assign_now is never issued for rescue bucket → 0 results
        $this->assertSame(0, $payload['summary']['total_evaluated']);
        $this->assertEmpty($payload['decisions']);

        Carbon::setTestNow();
    }

    public function test_table_output_runs_without_error(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        $this->seedConversation(1, now()->subHour(), 'captacion_agendar');
        $this->seedInbound(1, now()->subMinutes(20));

        $exitCode = Artisan::call('whatsapp:operational-decisions', ['--action' => 'assign_now']);

        $this->assertSame(0, $exitCode);

        Carbon::setTestNow();
    }

    private function seedConversation(int $id, Carbon $queuedAt, string $topic): void
    {
        DB::table('whatsapp_conversations')->insert([
            'id' => $id,
            'wa_number' => '593000' . $id,
            'patient_hc_number' => 'HC-' . $id,
            'needs_human' => true,
            'assigned_user_id' => null,
            'last_message_at' => $queuedAt,
            'handoff_requested_at' => $queuedAt,
            'created_at' => $queuedAt,
            'updated_at' => $queuedAt,
        ]);

        DB::table('whatsapp_handoffs')->insert([
            'id' => $id,
            'conversation_id' => $id,
            'wa_number' => '593000' . $id,
            'status' => 'queued',
            'topic' => $topic,
            'assigned_agent_id' => null,
            'queued_at' => $queuedAt,
            'created_at' => $queuedAt,
            'updated_at' => $queuedAt,
        ]);
    }

    private function seedInbound(int $conversationId, Carbon $at): void
    {
        DB::table('whatsapp_messages')->insert([
            'conversation_id' => $conversationId,
            'direction' => 'inbound',
            'sender_type' => null,
            'message_timestamp' => $at,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }
}

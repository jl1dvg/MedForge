<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappOperationalQueueCommandTest extends TestCase
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
            $table->unsignedBigInteger('attributed_conversation_id')->nullable()->index('woba_attr_conv_qc_idx');
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

    public function test_summary_only_json_returns_summary_without_items(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        $this->seedConversation(1, now()->subHour(), 'captacion_agendar', agentId: 9);
        // no outbound → supervisor_review after SLA

        Artisan::call('whatsapp:operational-queues', [
            '--summary-only' => true,
            '--json'         => true,
        ]);
        $payload = json_decode(Artisan::output(), true);

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('summary', $payload);
        $this->assertArrayNotHasKey('items', $payload);
        $this->assertArrayNotHasKey('queues', $payload);
        $this->assertArrayHasKey('supervisor_queue', $payload['summary']);
        $this->assertArrayHasKey('rescue_queue', $payload['summary']);
        $this->assertArrayHasKey('no_action', $payload['summary']);

        Carbon::setTestNow();
    }

    public function test_queue_supervisor_json_with_limit(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        // Two HOT_OPEN assigned without response → supervisor_review
        foreach ([1, 2, 3] as $i) {
            $this->seedConversation($i, now()->subHours(3), 'captacion_agendar', agentId: 9);
        }

        Artisan::call('whatsapp:operational-queues', [
            '--queue' => 'supervisor',
            '--limit' => '2',
            '--json'  => true,
        ]);
        $payload = json_decode(Artisan::output(), true);

        $this->assertSame('supervisor', $payload['queue']);
        $this->assertCount(2, $payload['items']);
        // summary must reflect all 3 (not just the limited 2)
        $this->assertSame(3, $payload['summary']['total']);

        Carbon::setTestNow();
    }

    public function test_queue_rescue_json_with_limit(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        // Rescue bucket: queued 3 days ago
        foreach ([1, 2, 3] as $i) {
            $this->seedConversation($i, now()->subDays(3), 'captacion_agendar');
        }

        Artisan::call('whatsapp:operational-queues', [
            '--queue' => 'rescue',
            '--limit' => '2',
            '--json'  => true,
        ]);
        $payload = json_decode(Artisan::output(), true);

        $this->assertSame('rescue', $payload['queue']);
        $this->assertCount(2, $payload['items']);
        $this->assertSame(3, $payload['summary']['total']);

        Carbon::setTestNow();
    }

    public function test_table_output_does_not_throw(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        $this->seedConversation(1, now()->subHour(), 'captacion_agendar', agentId: 9);

        $exitCode = Artisan::call('whatsapp:operational-queues', ['--queue' => 'all']);

        $this->assertSame(0, $exitCode);

        Carbon::setTestNow();
    }

    public function test_invalid_queue_returns_error(): void
    {
        $exitCode = Artisan::call('whatsapp:operational-queues', [
            '--queue' => 'invalid_queue',
            '--json'  => true,
        ]);

        $this->assertSame(1, $exitCode);
    }

    public function test_json_structure_queue_all(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        $this->seedConversation(1, now()->subHour(), 'captacion_agendar', agentId: 9);

        Artisan::call('whatsapp:operational-queues', ['--queue' => 'all', '--json' => true]);
        $payload = json_decode(Artisan::output(), true);

        $this->assertArrayHasKey('date', $payload);
        $this->assertArrayHasKey('generated_at', $payload);
        $this->assertArrayHasKey('summary', $payload);
        $this->assertArrayHasKey('queues', $payload);
        $this->assertArrayHasKey('assignment', $payload['queues']);
        $this->assertArrayHasKey('supervisor', $payload['queues']);
        $this->assertArrayHasKey('rescue', $payload['queues']);

        Carbon::setTestNow();
    }

    public function test_queue_assignment_json_returns_only_assign_now(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        // unassigned HOT_OPEN with inbound → assign_now
        $this->seedConversation(1, now()->subHour(), 'captacion_agendar');
        $this->seedInbound(1, now()->subMinutes(30));

        Artisan::call('whatsapp:operational-queues', [
            '--queue' => 'assignment',
            '--json'  => true,
        ]);
        $payload = json_decode(Artisan::output(), true);

        $this->assertSame('assignment', $payload['queue']);
        $this->assertIsArray($payload['items']);
        $this->assertArrayHasKey('assignment_queue', $payload['summary']);

        Carbon::setTestNow();
    }

    public function test_summary_includes_assignment_queue_key(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        $this->seedConversation(1, now()->subHour(), 'captacion_agendar', agentId: 9);

        Artisan::call('whatsapp:operational-queues', ['--summary-only' => true, '--json' => true]);
        $payload = json_decode(Artisan::output(), true);

        $this->assertArrayHasKey('assignment_queue', $payload['summary']);
        $this->assertArrayHasKey('supervisor_queue', $payload['summary']);
        $this->assertArrayHasKey('rescue_queue', $payload['summary']);
        $this->assertArrayHasKey('no_action', $payload['summary']);

        Carbon::setTestNow();
    }

    public function test_invalid_queue_assignment_variant_still_valid(): void
    {
        $exitCode = Artisan::call('whatsapp:operational-queues', [
            '--queue' => 'assignment',
            '--json'  => true,
        ]);

        $this->assertSame(0, $exitCode);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function seedInbound(int $conversationId, Carbon $at): void
    {
        DB::table('whatsapp_messages')->insert([
            'conversation_id'   => $conversationId,
            'direction'         => 'inbound',
            'sender_type'       => null,
            'message_timestamp' => $at,
            'created_at'        => $at,
            'updated_at'        => $at,
        ]);
    }

    private function seedConversation(int $id, Carbon $queuedAt, string $topic, ?int $agentId = null): void
    {
        DB::table('whatsapp_conversations')->insert([
            'id' => $id,
            'wa_number' => '5930000' . $id,
            'patient_hc_number' => 'HC-' . $id,
            'needs_human' => true,
            'assigned_user_id' => $agentId,
            'last_message_at' => $queuedAt,
            'handoff_requested_at' => $queuedAt,
            'created_at' => $queuedAt,
            'updated_at' => $queuedAt,
        ]);

        DB::table('whatsapp_handoffs')->insert([
            'id' => $id,
            'conversation_id' => $id,
            'wa_number' => '5930000' . $id,
            'status' => $agentId !== null ? 'assigned' : 'queued',
            'topic' => $topic,
            'assigned_agent_id' => $agentId,
            'assigned_at' => $agentId !== null ? $queuedAt->copy()->addMinutes(5) : null,
            'queued_at' => $queuedAt,
            'created_at' => $queuedAt,
            'updated_at' => $queuedAt,
        ]);
    }
}

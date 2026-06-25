<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappOperationalQueuesApiTest extends TestCase
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
            $table->unsignedBigInteger('attributed_conversation_id')->nullable()->index('woba_attr_conv_api_idx');
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

    // ── 1. summary_only devuelve ok + summary ─────────────────────────────

    public function test_summary_only_returns_ok_and_summary(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        $this->seedConversation(1, now()->subHour(), 'captacion_agendar', agentId: 9);

        $response = $this->withoutMiddleware()
            ->getJson('/v2/whatsapp/api/operational-queues?summary_only=1');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure([
                'ok',
                'data' => ['date', 'generated_at', 'summary'],
                'meta' => ['read_only', 'source'],
            ]);

        $this->assertArrayNotHasKey('items', $response->json('data'));
        $this->assertArrayNotHasKey('queues', $response->json('data'));

        Carbon::setTestNow();
    }

    // ── 2. queue=assignment devuelve queue=assignment e items ──────────────

    public function test_queue_assignment_returns_assignment_items(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        $this->seedConversation(1, now()->subHour(), 'captacion_agendar');
        $this->seedInbound(1, now()->subMinutes(30));

        $response = $this->withoutMiddleware()
            ->getJson('/v2/whatsapp/api/operational-queues?queue=assignment');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.queue', 'assignment')
            ->assertJsonStructure(['ok', 'data' => ['queue', 'summary', 'items'], 'meta']);

        Carbon::setTestNow();
    }

    // ── 3. queue=supervisor devuelve queue=supervisor ──────────────────────

    public function test_queue_supervisor_returns_supervisor_items(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        $this->seedConversation(1, now()->subHours(3), 'captacion_agendar', agentId: 9);

        $response = $this->withoutMiddleware()
            ->getJson('/v2/whatsapp/api/operational-queues?queue=supervisor');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.queue', 'supervisor')
            ->assertJsonStructure(['ok', 'data' => ['queue', 'summary', 'items']]);

        Carbon::setTestNow();
    }

    // ── 4. queue=rescue devuelve queue=rescue ─────────────────────────────

    public function test_queue_rescue_returns_rescue_items(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        $this->seedConversation(1, now()->subDays(3), 'captacion_agendar');

        $response = $this->withoutMiddleware()
            ->getJson('/v2/whatsapp/api/operational-queues?queue=rescue');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.queue', 'rescue')
            ->assertJsonStructure(['ok', 'data' => ['queue', 'summary', 'items']]);

        Carbon::setTestNow();
    }

    // ── 5. queue=all devuelve queues.assignment, .supervisor, .rescue ──────

    public function test_queue_all_returns_all_queue_buckets(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        $this->seedConversation(1, now()->subHour(), 'captacion_agendar', agentId: 9);

        $response = $this->withoutMiddleware()
            ->getJson('/v2/whatsapp/api/operational-queues?queue=all');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure([
                'ok',
                'data' => [
                    'summary',
                    'queues' => ['assignment', 'supervisor', 'rescue'],
                    'items',
                ],
                'meta',
            ]);

        Carbon::setTestNow();
    }

    // ── 6. queue inválida devuelve 422 ────────────────────────────────────

    public function test_invalid_queue_returns_422(): void
    {
        $response = $this->withoutMiddleware()
            ->getJson('/v2/whatsapp/api/operational-queues?queue=bogus');

        $response->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonStructure(['ok', 'message', 'errors' => ['queue']]);
    }

    // ── 7. date inválida devuelve 422 ────────────────────────────────────

    public function test_invalid_date_returns_422(): void
    {
        $response = $this->withoutMiddleware()
            ->getJson('/v2/whatsapp/api/operational-queues?date=not-a-date');

        $response->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonStructure(['ok', 'message', 'errors' => ['date']]);
    }

    // ── 8. limit inválido devuelve 422 ───────────────────────────────────

    public function test_invalid_limit_returns_422(): void
    {
        $response = $this->withoutMiddleware()
            ->getJson('/v2/whatsapp/api/operational-queues?limit=0');

        $response->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonStructure(['ok', 'message', 'errors' => ['limit']]);
    }

    public function test_negative_limit_returns_422(): void
    {
        $response = $this->withoutMiddleware()
            ->getJson('/v2/whatsapp/api/operational-queues?limit=-5');

        $response->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    // ── 9. meta.read_only = true siempre ─────────────────────────────────

    public function test_response_includes_meta_read_only_true(): void
    {
        $response = $this->withoutMiddleware()
            ->getJson('/v2/whatsapp/api/operational-queues');

        $response->assertOk()
            ->assertJsonPath('meta.read_only', true);
    }

    // ── 10. endpoint no modifica datos ───────────────────────────────────

    public function test_endpoint_does_not_modify_data(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        $this->seedConversation(1, now()->subHour(), 'captacion_agendar', agentId: 9);

        $before = DB::table('whatsapp_conversations')->count();

        $this->withoutMiddleware()->getJson('/v2/whatsapp/api/operational-queues?queue=all');

        $after = DB::table('whatsapp_conversations')->count();
        $this->assertSame($before, $after);

        Carbon::setTestNow();
    }

    // ── 11. limit aplica en items pero no en summary ──────────────────────

    public function test_limit_applies_to_items_but_not_summary(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        foreach ([1, 2, 3] as $i) {
            $this->seedConversation($i, now()->subHours(3), 'captacion_agendar', agentId: 9);
        }

        $response = $this->withoutMiddleware()
            ->getJson('/v2/whatsapp/api/operational-queues?queue=supervisor&limit=1');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.items'));
        $this->assertSame(3, $response->json('data.summary.total'));

        Carbon::setTestNow();
    }

    // ── 12. meta incluye queue y limit cuando se especifican ─────────────

    public function test_meta_includes_queue_and_limit(): void
    {
        $response = $this->withoutMiddleware()
            ->getJson('/v2/whatsapp/api/operational-queues?queue=rescue&limit=5');

        $response->assertOk()
            ->assertJsonPath('meta.queue', 'rescue')
            ->assertJsonPath('meta.limit', 5)
            ->assertJsonPath('meta.read_only', true);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function seedConversation(int $id, Carbon $queuedAt, string $topic, ?int $agentId = null): void
    {
        DB::table('whatsapp_conversations')->insert([
            'id'                   => $id,
            'wa_number'            => '5950000' . $id,
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
            'wa_number'         => '5950000' . $id,
            'status'            => $agentId !== null ? 'assigned' : 'queued',
            'topic'             => $topic,
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
            'sender_type'       => null,
            'message_timestamp' => $at,
            'created_at'        => $at,
            'updated_at'        => $at,
        ]);
    }
}

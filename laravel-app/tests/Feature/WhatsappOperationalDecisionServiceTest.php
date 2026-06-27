<?php

namespace Tests\Feature;

use App\Modules\Whatsapp\Services\WhatsappOperationalDecisionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappOperationalDecisionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'whatsapp_operational_booking_attributions',
            'procedimiento_proyectado',
            'whatsapp_sigcenter_bookings',
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
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->timestamp('message_timestamp')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_operational_booking_attributions', function (Blueprint $table): void {
            $table->id();
            $table->string('booking_source', 48)->default('bot_api');
            $table->string('observed_booking_key', 191)->unique();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedBigInteger('form_id')->nullable();
            $table->unsignedBigInteger('booking_conversation_id')->nullable();
            $table->unsignedBigInteger('attributed_conversation_id')->nullable()->index('woba_attr_conv_dec_idx');
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

    public function test_hot_open_unassigned_recommends_assign_now(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        $this->seedConversation(1, now()->subHour(), 'captacion_agendar', null);
        $this->seedInbound(1, now()->subMinutes(30));

        $result = $this->evaluate();

        $d = $this->findDecision($result, 1);
        $this->assertSame(WhatsappOperationalDecisionService::ACTION_ASSIGN_NOW, $d['recommended_action']);
        $this->assertSame('high', $d['priority']);
        $this->assertTrue($d['eligible_for_autoassign']);
        $this->assertFalse($d['eligible_for_supervisor_alert']);

        Carbon::setTestNow();
    }

    public function test_hot_open_assigned_with_no_response_past_sla_recommends_supervisor(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        $this->seedConversation(1, now()->subHours(3), 'captacion_agendar', agentId: 9);
        $this->seedInbound(1, now()->subMinutes(30));
        // No outbound message seeded

        $result = $this->evaluate();

        $d = $this->findDecision($result, 1);
        $this->assertSame(WhatsappOperationalDecisionService::ACTION_SUPERVISOR_REVIEW, $d['recommended_action']);
        $this->assertTrue($d['eligible_for_supervisor_alert']);
        $this->assertFalse($d['eligible_for_autoassign']);

        Carbon::setTestNow();
    }

    public function test_hot_needs_template_recommends_send_template(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        // queued 5 hours ago, latest inbound 25 hours ago (no active window)
        $this->seedConversation(1, now()->subHours(5), 'captacion_agendar', null, latestInboundAt: now()->subHours(25));

        $result = $this->evaluate();

        $d = $this->findDecision($result, 1);
        $this->assertSame(WhatsappOperationalDecisionService::ACTION_SEND_TEMPLATE, $d['recommended_action']);
        $this->assertTrue($d['eligible_for_rescue']);
        $this->assertFalse($d['eligible_for_autoassign']);

        Carbon::setTestNow();
    }

    public function test_rescue_bucket_recommends_rescue_followup(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        $this->seedConversation(1, now()->subDays(3), 'captacion_agendar', null);

        $result = $this->evaluate();

        $d = $this->findDecision($result, 1);
        $this->assertSame(WhatsappOperationalDecisionService::ACTION_RESCUE_FOLLOWUP, $d['recommended_action']);
        $this->assertTrue($d['eligible_for_rescue']);
        $this->assertFalse($d['eligible_for_autoassign']);

        Carbon::setTestNow();
    }

    public function test_backlog_bucket_recommends_hold(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        $this->seedConversation(1, now()->subDays(12), 'captacion_agendar', null);

        $result = $this->evaluate();

        $d = $this->findDecision($result, 1);
        $this->assertSame(WhatsappOperationalDecisionService::ACTION_HOLD_BACKLOG, $d['recommended_action']);
        $this->assertFalse($d['eligible_for_autoassign']);
        $this->assertFalse($d['eligible_for_rescue']);
        $this->assertFalse($d['eligible_for_supervisor_alert']);

        Carbon::setTestNow();
    }

    public function test_lost_bucket_recommends_no_action(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        $this->seedConversation(1, now()->subDays(35), 'captacion_agendar', null);

        $result = $this->evaluate();

        $d = $this->findDecision($result, 1);
        $this->assertSame(WhatsappOperationalDecisionService::ACTION_NO_ACTION_LOST, $d['recommended_action']);
        $this->assertSame('closed', $d['risk_level']);
        $this->assertFalse($d['eligible_for_autoassign']);

        Carbon::setTestNow();
    }

    public function test_conversation_with_primary_clinical_appointment_is_converted(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        $this->seedConversation(1, now()->subHour(), 'captacion_agendar', null);
        $this->seedInbound(1, now()->subMinutes(30));

        // Attribution to a primary clinical appointment (ophthalmology consult)
        DB::table('procedimiento_proyectado')->insert([
            'form_id' => 100,
            'hc_number' => 'HC-1',
            'procedimiento_nombre' => 'CONSULTA OFTALMOLOGICA',
            'fecha' => now()->addDay()->toDateString(),
            'sigcenter_present' => true,
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);
        DB::table('whatsapp_operational_booking_attributions')->insert([
            'booking_source' => 'manual_sigcenter',
            'observed_booking_key' => 'procedimiento_proyectado:100',
            'form_id' => 100,
            'attributed_conversation_id' => 1,
            'event_type' => 'auto_assigned',
            'attribution_method' => 'same_conversation_7d',
            'confidence' => 'high',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->evaluate();

        $d = $this->findDecision($result, 1);
        $this->assertSame(WhatsappOperationalDecisionService::ACTION_NO_ACTION_CONVERTED, $d['recommended_action']);
        $this->assertTrue($d['has_attributed_booking']);
        $this->assertTrue($d['has_primary_clinical_appointment']);
        $this->assertFalse($d['eligible_for_autoassign']);
        $this->assertSame(1, $result['summary']['already_converted']);

        Carbon::setTestNow();
    }

    public function test_summary_contains_required_keys(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');
        $result = $this->evaluate();

        $summary = $result['summary'];
        $this->assertArrayHasKey('total_evaluated', $summary);
        $this->assertArrayHasKey('by_recommended_action', $summary);
        $this->assertArrayHasKey('by_priority', $summary);
        $this->assertArrayHasKey('by_risk_level', $summary);
        $this->assertArrayHasKey('eligible_for_autoassign', $summary);
        $this->assertArrayHasKey('eligible_for_rescue', $summary);
        $this->assertArrayHasKey('eligible_for_supervisor_alert', $summary);
        $this->assertArrayHasKey('already_converted', $summary);

        Carbon::setTestNow();
    }

    private function evaluate(): array
    {
        return app(WhatsappOperationalDecisionService::class)->evaluate(now());
    }

    /**
     * @param array<string,mixed> $result
     * @return array<string,mixed>
     */
    private function findDecision(array $result, int $convId): array
    {
        foreach ($result['decisions'] as $d) {
            if ((int) $d['conversation_id'] === $convId) {
                return $d;
            }
        }
        $this->fail("Decision for conversation {$convId} not found in result.");
    }

    private function seedConversation(int $id, Carbon $queuedAt, string $topic, ?int $agentId, ?Carbon $latestInboundAt = null): void
    {
        DB::table('whatsapp_conversations')->insert([
            'id' => $id,
            'wa_number' => '593000' . $id,
            'patient_hc_number' => 'HC-' . $id,
            'needs_human' => true,
            'assigned_user_id' => $agentId,
            'last_message_at' => $latestInboundAt ?? $queuedAt,
            'handoff_requested_at' => $queuedAt,
            'created_at' => $queuedAt,
            'updated_at' => $queuedAt,
        ]);

        DB::table('whatsapp_handoffs')->insert([
            'id' => $id,
            'conversation_id' => $id,
            'wa_number' => '593000' . $id,
            'status' => $agentId !== null ? 'assigned' : 'queued',
            'topic' => $topic,
            'assigned_agent_id' => $agentId,
            'assigned_at' => $agentId !== null ? $queuedAt->copy()->addMinutes(5) : null,
            'queued_at' => $queuedAt,
            'created_at' => $queuedAt,
            'updated_at' => $queuedAt,
        ]);

        if ($latestInboundAt !== null) {
            $this->seedInbound($id, $latestInboundAt);
        }
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

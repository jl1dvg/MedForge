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
}

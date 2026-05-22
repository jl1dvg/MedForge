<?php

namespace Tests\Feature;

use App\Modules\Whatsapp\Services\ConversationAbandonmentMonitorService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappAbandonmentMonitorServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('whatsapp_handoff_events');
        Schema::dropIfExists('whatsapp_handoffs');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_autoresponder_sessions');
        Schema::dropIfExists('whatsapp_conversations');
        Schema::dropIfExists('app_settings');

        Schema::create('app_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_conversations', function (Blueprint $table): void {
            $table->id();
            $table->string('wa_number', 32)->unique();
            $table->string('display_name', 191)->nullable();
            $table->string('patient_hc_number', 64)->nullable();
            $table->string('patient_full_name', 191)->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->string('last_message_direction', 32)->nullable();
            $table->string('last_message_type', 64)->nullable();
            $table->string('last_message_preview', 512)->nullable();
            $table->boolean('needs_human')->default(false);
            $table->text('handoff_notes')->nullable();
            $table->unsignedBigInteger('handoff_role_id')->nullable();
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('handoff_requested_at')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamps();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('wa_message_id', 191)->nullable();
            $table->string('direction', 16);
            $table->string('message_type', 64)->default('text');
            $table->longText('body')->nullable();
            $table->json('raw_payload')->nullable();
            $table->string('status', 32)->nullable();
            $table->timestamp('message_timestamp')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_autoresponder_sessions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('wa_number');
            $table->string('scenario_id')->nullable();
            $table->string('node_id')->nullable();
            $table->string('awaiting')->nullable();
            $table->json('context')->nullable();
            $table->json('last_payload')->nullable();
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_handoffs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('wa_number', 32);
            $table->string('status', 24)->default('queued');
            $table->string('priority', 24)->default('normal');
            $table->string('topic', 64)->nullable();
            $table->unsignedBigInteger('handoff_role_id')->nullable();
            $table->unsignedBigInteger('assigned_agent_id')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('assigned_until')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_handoff_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('handoff_id');
            $table->string('event_type', 64);
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        config()->set('whatsapp.migration.automation.enabled', true);
        config()->set('whatsapp.migration.automation.dry_run', true);
        config()->set('whatsapp.migration.abandonment_monitor.thresholds.consentimiento_pendiente', 15);
        config()->set('whatsapp.migration.abandonment_monitor.thresholds.agenda', 12);
        config()->set('whatsapp.migration.abandonment_monitor.thresholds.confirmacion', 10);
        config()->set('whatsapp.migration.abandonment_monitor.followup_minutes.low_intent', 10);
        config()->set('whatsapp.migration.abandonment_monitor.followup_minutes.high_intent', 10);
    }

    public function test_it_nudges_and_then_closes_low_intent_abandonment(): void
    {
        \DB::table('whatsapp_conversations')->insert([
            'id' => 101,
            'wa_number' => '593999000101',
            'display_name' => 'Paciente Low Intent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_autoresponder_sessions')->insert([
            'conversation_id' => 101,
            'wa_number' => '593999000101',
            'context' => json_encode(['state' => 'consentimiento_pendiente']),
            'last_payload' => json_encode([]),
            'last_interaction_at' => now()->subMinutes(20),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(ConversationAbandonmentMonitorService::class);

        $first = $service->scan();
        $this->assertSame(1, $first['nudged']);
        $this->assertDatabaseHas('whatsapp_messages', [
            'conversation_id' => 101,
            'direction' => 'outbound',
            'message_type' => 'text',
        ]);

        \DB::table('whatsapp_autoresponder_sessions')
            ->where('conversation_id', 101)
            ->update([
                'context' => json_encode([
                    'state' => 'consentimiento_pendiente',
                    'abandonment_monitor' => [
                        'nudged_at' => now()->subMinutes(11)->toISOString(),
                        'nudged_count' => 1,
                        'abandonment_status' => 'nudged',
                    ],
                ]),
            ]);

        $second = $service->scan();
        $this->assertSame(1, $second['closed']);

        $context = \DB::table('whatsapp_autoresponder_sessions')->where('conversation_id', 101)->value('context');
        $decoded = json_decode((string) $context, true);

        $this->assertSame('closed', data_get($decoded, 'abandonment_monitor.abandonment_status'));
        $this->assertSame('abandono_consentimiento', data_get($decoded, 'abandonment_monitor.closed_reason'));
        $this->assertDatabaseCount('whatsapp_handoffs', 0);
    }

    public function test_it_nudges_and_then_escalates_high_intent_abandonment(): void
    {
        \DB::table('whatsapp_conversations')->insert([
            'id' => 102,
            'wa_number' => '593999000102',
            'display_name' => 'Paciente High Intent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_autoresponder_sessions')->insert([
            'conversation_id' => 102,
            'wa_number' => '593999000102',
            'context' => json_encode(['state' => 'agenda_confirmar_cita']),
            'last_payload' => json_encode([]),
            'last_interaction_at' => now()->subMinutes(15),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(ConversationAbandonmentMonitorService::class);
        $service->scan();

        \DB::table('whatsapp_autoresponder_sessions')
            ->where('conversation_id', 102)
            ->update([
                'context' => json_encode([
                    'state' => 'agenda_confirmar_cita',
                    'abandonment_monitor' => [
                        'nudged_at' => now()->subMinutes(11)->toISOString(),
                        'nudged_count' => 1,
                        'abandonment_status' => 'nudged',
                    ],
                ]),
            ]);

        $second = $service->scan();
        $this->assertSame(1, $second['enqueued']);
        $this->assertDatabaseHas('whatsapp_handoffs', [
            'conversation_id' => 102,
            'status' => 'queued',
        ]);
        $this->assertDatabaseHas('whatsapp_conversations', [
            'id' => 102,
            'needs_human' => 1,
        ]);
    }
}

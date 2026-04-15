<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappWebhookControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('whatsapp_inbox_messages');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_conversations');
        Schema::dropIfExists('whatsapp_flow_shadow_runs');
        Schema::dropIfExists('whatsapp_autoresponder_sessions');
        Schema::dropIfExists('whatsapp_autoresponder_flow_versions');
        Schema::dropIfExists('whatsapp_autoresponder_flows');
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

        Schema::create('whatsapp_inbox_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('wa_number', 32);
            $table->string('direction', 16);
            $table->string('message_type', 64)->default('text');
            $table->longText('message_body');
            $table->string('message_id', 191)->nullable();
            $table->longText('payload')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('whatsapp_autoresponder_flows', function (Blueprint $table): void {
            $table->id();
            $table->string('flow_key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->string('timezone')->nullable();
            $table->timestamp('active_from')->nullable();
            $table->timestamp('active_until')->nullable();
            $table->unsignedBigInteger('active_version_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_autoresponder_flow_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('flow_id');
            $table->unsignedInteger('version');
            $table->string('status')->default('draft');
            $table->text('changelog')->nullable();
            $table->json('audience_filters')->nullable();
            $table->json('entry_settings')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('published_by')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
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

        Schema::create('whatsapp_flow_shadow_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 32)->default('webhook');
            $table->string('wa_number', 32)->nullable();
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->string('inbound_message_id', 191)->nullable();
            $table->longText('message_text')->nullable();
            $table->boolean('same_match')->default(false);
            $table->boolean('same_scenario')->default(false);
            $table->boolean('same_handoff')->default(false);
            $table->boolean('same_action_types')->default(false);
            $table->json('input_payload')->nullable();
            $table->json('parity_payload')->nullable();
            $table->json('laravel_payload')->nullable();
            $table->json('legacy_payload')->nullable();
            $table->timestamps();
        });

        \DB::table('app_settings')->insert([
            ['name' => 'whatsapp_webhook_verify_token', 'value' => 'verify-me', 'created_at' => now(), 'updated_at' => now()],
        ]);

        config()->set('whatsapp.migration.enabled', true);
        config()->set('whatsapp.migration.api.webhook_enabled', true);
        config()->set('whatsapp.migration.automation.dry_run', true);
    }

    public function test_it_verifies_the_webhook_like_legacy(): void
    {
        $response = $this->get('/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=12345');

        $response->assertOk();
        $this->assertSame('12345', $response->getContent());
    }

    public function test_it_persists_incoming_messages_idempotently_like_legacy(): void
    {
        $payload = [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'contacts' => [[
                            'wa_id' => '593999111222',
                            'profile' => ['name' => 'Paciente Demo'],
                        ]],
                        'messages' => [[
                            'from' => '593999111222',
                            'id' => 'wamid.inbound.1',
                            'timestamp' => '1712745600',
                            'type' => 'interactive',
                            'interactive' => [
                                'type' => 'button_reply',
                                'button_reply' => [
                                    'id' => 'confirmar',
                                    'title' => 'Confirmar',
                                ],
                            ],
                        ]],
                    ],
                ]],
            ]],
        ];

        $this->postJson('/whatsapp/webhook', $payload)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.messages_persisted', 1);

        $this->postJson('/whatsapp/webhook', $payload)
            ->assertOk()
            ->assertJsonPath('data.messages_persisted', 0);

        $this->assertDatabaseCount('whatsapp_conversations', 1);
        $this->assertDatabaseCount('whatsapp_messages', 1);
        $this->assertDatabaseCount('whatsapp_inbox_messages', 1);

        $this->assertDatabaseHas('whatsapp_conversations', [
            'wa_number' => '593999111222',
            'display_name' => 'Paciente Demo',
            'last_message_direction' => 'inbound',
            'last_message_type' => 'interactive',
            'last_message_preview' => 'confirmar',
            'unread_count' => 1,
        ]);

        $this->assertDatabaseHas('whatsapp_messages', [
            'wa_message_id' => 'wamid.inbound.1',
            'direction' => 'inbound',
            'message_type' => 'interactive',
            'body' => 'confirmar',
            'status' => 'received',
        ]);

        $this->assertDatabaseHas('whatsapp_inbox_messages', [
            'wa_number' => '593999111222',
            'direction' => 'incoming',
            'message_type' => 'interactive',
            'message_body' => 'confirmar',
            'message_id' => 'wamid.inbound.1',
        ]);
    }

    public function test_it_applies_status_updates_like_legacy(): void
    {
        \DB::table('whatsapp_conversations')->insert([
            'id' => 81,
            'wa_number' => '593999111222',
            'display_name' => 'Paciente Demo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_messages')->insert([
            'conversation_id' => 81,
            'wa_message_id' => 'wamid.outbound.1',
            'direction' => 'outbound',
            'message_type' => 'text',
            'body' => 'Hola',
            'status' => 'sent',
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'statuses' => [
                            [
                                'id' => 'wamid.outbound.1',
                                'status' => 'delivered',
                                'timestamp' => '1712745660',
                            ],
                            [
                                'id' => 'wamid.outbound.1',
                                'status' => 'read',
                                'timestamp' => '1712745720',
                            ],
                        ],
                    ],
                ]],
            ]],
        ];

        $this->postJson('/whatsapp/webhook', $payload)
            ->assertOk()
            ->assertJsonPath('data.statuses_applied', 2);

        $this->assertDatabaseHas('whatsapp_messages', [
            'wa_message_id' => 'wamid.outbound.1',
            'status' => 'read',
        ]);

        $message = \DB::table('whatsapp_messages')
            ->where('wa_message_id', 'wamid.outbound.1')
            ->first();

        $this->assertNotNull($message?->delivered_at);
        $this->assertNotNull($message?->read_at);
    }

    public function test_it_records_shadow_run_for_inbound_automation_when_enabled(): void
    {
        config()->set('whatsapp.migration.automation.enabled', true);
        config()->set('whatsapp.migration.automation.compare_with_legacy', true);

        $payload = [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'contacts' => [[
                            'wa_id' => '593999111222',
                            'profile' => ['name' => 'Paciente Demo'],
                        ]],
                        'messages' => [[
                            'from' => '593999111222',
                            'id' => 'wamid.shadow.1',
                            'timestamp' => '1712745600',
                            'type' => 'text',
                            'text' => [
                                'body' => 'hola',
                            ],
                        ]],
                    ],
                ]],
            ]],
        ];

        $this->postJson('/whatsapp/webhook', $payload)
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseCount('whatsapp_flow_shadow_runs', 1);
        $this->assertDatabaseHas('whatsapp_flow_shadow_runs', [
            'source' => 'webhook_dry_run',
            'wa_number' => '593999111222',
            'inbound_message_id' => 'wamid.shadow.1',
            'message_text' => 'hola',
        ]);
    }

    public function test_it_backfills_shadow_runs_from_inbound_messages_table(): void
    {
        config()->set('whatsapp.migration.automation.enabled', true);
        config()->set('whatsapp.migration.automation.compare_with_legacy', true);

        \DB::table('whatsapp_conversations')->insert([
            'id' => 91,
            'wa_number' => '593999111777',
            'display_name' => 'Paciente Sync',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_messages')->insert([
            'conversation_id' => 91,
            'wa_message_id' => 'wamid.sync.1',
            'direction' => 'inbound',
            'message_type' => 'text',
            'body' => 'hola',
            'raw_payload' => json_encode([
                'id' => 'wamid.sync.1',
                'from' => '593999111777',
                'text' => ['body' => 'hola'],
                'type' => 'text',
            ]),
            'message_timestamp' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('whatsapp:flowmaker-shadow-sync', ['--limit' => 10])
            ->expectsOutputToContain('processed')
            ->assertExitCode(0);

        $this->assertDatabaseHas('whatsapp_flow_shadow_runs', [
            'source' => 'db_sync_dry_run',
            'wa_number' => '593999111777',
            'inbound_message_id' => 'wamid.sync.1',
            'message_text' => 'hola',
        ]);
    }
}

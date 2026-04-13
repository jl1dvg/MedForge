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

        \DB::table('app_settings')->insert([
            ['name' => 'whatsapp_webhook_verify_token', 'value' => 'verify-me', 'created_at' => now(), 'updated_at' => now()],
        ]);

        config()->set('whatsapp.migration.enabled', true);
        config()->set('whatsapp.migration.api.webhook_enabled', true);
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
}

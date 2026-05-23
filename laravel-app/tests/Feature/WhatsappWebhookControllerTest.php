<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappWebhookControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('whatsapp_inbox_messages');
        Schema::dropIfExists('whatsapp_sigcenter_bookings');
        Schema::dropIfExists('whatsapp_handoffs');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_conversations');
        Schema::dropIfExists('whatsapp_flow_shadow_runs');
        Schema::dropIfExists('whatsapp_autoresponder_sessions');
        Schema::dropIfExists('whatsapp_autoresponder_flow_versions');
        Schema::dropIfExists('whatsapp_autoresponder_flows');
        Schema::dropIfExists('patient_data');
        Schema::dropIfExists('users');
        Schema::dropIfExists('app_settings');

        Schema::create('app_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('category')->nullable();
            $table->string('name')->unique();
            $table->text('value')->nullable();
            $table->string('type')->nullable();
            $table->boolean('autoload')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username');
            $table->string('nombre')->default('');
            $table->string('email')->nullable();
            $table->string('profile_photo')->nullable();
            $table->string('especialidad')->default('');
            $table->string('subespecialidad')->nullable();
            $table->unsignedBigInteger('id_trabajador')->nullable();
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

        Schema::create('whatsapp_handoffs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('wa_number', 32);
            $table->string('status', 32)->default('queued');
            $table->string('priority', 32)->default('normal');
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

        Schema::create('whatsapp_sigcenter_bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->string('wa_number', 32);
            $table->string('inbound_message_id', 191)->nullable();
            $table->string('status', 32)->default('created');
            $table->string('patient_hc_number', 64)->nullable();
            $table->string('patient_full_name', 191)->nullable();
            $table->string('sigcenter_agenda_id', 64)->nullable();
            $table->string('trabajador_id', 64)->nullable();
            $table->string('medico_nombre', 191)->nullable();
            $table->string('sede_id', 64)->nullable();
            $table->string('sede_nombre', 191)->nullable();
            $table->string('procedimiento_id', 64)->nullable();
            $table->string('procedimiento_nombre', 191)->nullable();
            $table->timestamp('fecha_inicio')->nullable();
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('booked_at')->nullable();
            $table->timestamps();
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

        Schema::create('patient_data', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable();
            $table->string('lname')->default('');
            $table->string('lname2')->nullable();
            $table->string('fname')->default('');
            $table->string('mname')->nullable();
            $table->string('celular')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        \DB::table('app_settings')->insert([
            ['name' => 'whatsapp_webhook_verify_token', 'value' => 'verify-me', 'created_at' => now(), 'updated_at' => now()],
        ]);

        config()->set('whatsapp.migration.enabled', true);
        config()->set('whatsapp.migration.api.webhook_enabled', true);
        config()->set('whatsapp.migration.automation.enabled', false);
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

    public function test_it_executes_published_flowmaker_runtime_from_laravel_webhook(): void
    {
        config()->set('whatsapp.migration.automation.enabled', true);
        config()->set('whatsapp.migration.automation.dry_run', true);

        \DB::table('users')->insert([
            [
                'username' => 'retina',
                'nombre' => 'Retina Demo',
                'especialidad' => 'Cirujano Oftalmólogo',
                'subespecialidad' => 'Retina y Vítreo',
                'id_trabajador' => 1001,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'username' => 'cornea',
                'nombre' => 'Cornea Demo',
                'especialidad' => 'Cirujano Oftalmólogo',
                'subespecialidad' => 'Córnea',
                'id_trabajador' => 1002,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $flowId = \DB::table('whatsapp_autoresponder_flows')->insertGetId([
            'flow_key' => 'default',
            'name' => 'Flujo principal de WhatsApp',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $versionId = \DB::table('whatsapp_autoresponder_flow_versions')->insertGetId([
            'flow_id' => $flowId,
            'version' => 1,
            'status' => 'published',
            'entry_settings' => json_encode([
                'flow' => [
                    'name' => 'Flujo principal de WhatsApp',
                    'scenarios' => [[
                        'id' => 'especialidades',
                        'name' => 'Especialidades',
                        'stage' => 'custom',
                        'status' => 'published',
                        'conditions' => [
                            ['type' => 'message_contains', 'value' => 'especialidades0'],
                        ],
                        'actions' => [[
                            'type' => 'sigcenter_agenda',
                            'operation' => 'list_specialties',
                            'especialidad' => 'Cirujano Oftalmólogo',
                            'send_result' => true,
                            'list_body' => '¿Qué especialidad oftalmológica necesitas?',
                            'list_button_text' => 'Ver opciones',
                            'list_section_title' => 'Especialidades',
                            'save_response_as' => 'subespecialidad',
                            'next_state' => 'agenda_esperando_subespecialidad',
                            'store_result_as' => 'agenda_especialidades',
                        ]],
                    ]],
                ],
            ], JSON_UNESCAPED_UNICODE),
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_autoresponder_flows')
            ->where('id', $flowId)
            ->update(['active_version_id' => $versionId]);

        $payload = [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'contacts' => [[
                            'wa_id' => '593999111444',
                            'profile' => ['name' => 'Paciente Flow'],
                        ]],
                        'messages' => [[
                            'from' => '593999111444',
                            'id' => 'wamid.flowmaker.1',
                            'timestamp' => '1712745600',
                            'type' => 'text',
                            'text' => ['body' => 'especialidades0'],
                        ]],
                    ],
                ]],
            ]],
        ];

        $this->postJson('/whatsapp/webhook', $payload)
            ->assertOk()
            ->assertJsonPath('data.messages_persisted', 1)
            ->assertJsonPath('data.automation_runs', 1)
            ->assertJsonPath('data.automation_messages_sent', 1);

        $this->assertDatabaseHas('whatsapp_autoresponder_sessions', [
            'wa_number' => '593999111444',
            'scenario_id' => 'especialidades',
            'awaiting' => 'input',
        ]);

        $session = \DB::table('whatsapp_autoresponder_sessions')
            ->where('wa_number', '593999111444')
            ->first();
        $context = json_decode((string) $session?->context, true);

        $this->assertSame('agenda_esperando_subespecialidad', $context['state'] ?? null);
        $this->assertSame('subespecialidad', $context['awaiting_field'] ?? null);

        $this->assertDatabaseHas('whatsapp_messages', [
            'wa_message_id' => 'wamid.flowmaker.1',
            'direction' => 'inbound',
            'body' => 'especialidades0',
        ]);

        $this->assertDatabaseHas('whatsapp_messages', [
            'direction' => 'outbound',
            'message_type' => 'interactive',
            'body' => '¿Qué especialidad oftalmológica necesitas?',
            'status' => 'accepted',
        ]);
    }

    public function test_it_captures_list_reply_and_continues_scheduling_flow(): void
    {
        config()->set('whatsapp.migration.automation.enabled', true);
        config()->set('whatsapp.migration.automation.dry_run', true);

        \DB::table('users')->insert([
            [
                'username' => 'general1',
                'nombre' => 'Doctora General Uno',
                'especialidad' => 'Cirujano Oftalmólogo',
                'subespecialidad' => 'oftalmologo general',
                'id_trabajador' => 2001,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'username' => 'retina2',
                'nombre' => 'Doctor Retina Dos',
                'especialidad' => 'Cirujano Oftalmólogo',
                'subespecialidad' => 'Retina y Vítreo',
                'id_trabajador' => 2002,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $flowId = \DB::table('whatsapp_autoresponder_flows')->insertGetId([
            'flow_key' => 'default',
            'name' => 'Flujo principal de WhatsApp',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $versionId = \DB::table('whatsapp_autoresponder_flow_versions')->insertGetId([
            'flow_id' => $flowId,
            'version' => 1,
            'status' => 'published',
            'entry_settings' => json_encode([
                'flow' => [
                    'name' => 'Flujo principal de WhatsApp',
                    'scenarios' => [
                        [
                            'id' => 'especialidades',
                            'name' => 'Especialidades',
                            'stage' => 'custom',
                            'status' => 'published',
                            'conditions' => [
                                ['type' => 'message_contains', 'value' => 'especialidades0'],
                            ],
                            'actions' => [[
                                'type' => 'sigcenter_agenda',
                                'operation' => 'list_specialties',
                                'especialidad' => 'Cirujano Oftalmólogo',
                                'send_result' => true,
                                'list_body' => '¿Qué especialidad oftalmológica necesitas?',
                                'list_button_text' => 'Ver opciones',
                                'list_section_title' => 'Especialidades',
                                'save_response_as' => 'subespecialidad',
                                'next_state' => 'agenda_esperando_subespecialidad',
                                'store_result_as' => 'agenda_especialidades',
                            ]],
                        ],
                        [
                            'id' => 'medicos',
                            'name' => 'Médicos',
                            'stage' => 'custom',
                            'status' => 'published',
                            'conditions' => [
                                ['type' => 'state_is', 'value' => 'agenda_esperando_subespecialidad'],
                            ],
                            'actions' => [[
                                'type' => 'sigcenter_agenda',
                                'operation' => 'list_doctors',
                                'especialidad' => 'Cirujano Oftalmólogo',
                                'send_result' => true,
                                'list_body' => 'Elige el médico con el que deseas agendar.',
                                'list_button_text' => 'Ver opciones',
                                'list_section_title' => 'Médicos disponibles',
                                'save_response_as' => 'trabajador_id',
                                'next_state' => 'agenda_esperando_medico',
                                'store_result_as' => 'agenda_medicos',
                            ]],
                        ],
                        [
                            'id' => 'fallback',
                            'name' => 'Fallback',
                            'stage' => 'custom',
                            'status' => 'published',
                            'conditions' => [
                                ['type' => 'always'],
                            ],
                            'actions' => [[
                                'type' => 'send_message',
                                'message' => [
                                    'type' => 'text',
                                    'body' => 'Hola, somos CIVE. Un agente se pondrá en contacto contigo en breve.',
                                ],
                            ]],
                        ],
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE),
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_autoresponder_flows')
            ->where('id', $flowId)
            ->update(['active_version_id' => $versionId]);

        $this->postJson('/whatsapp/webhook', [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'contacts' => [[
                            'wa_id' => '593999111445',
                            'profile' => ['name' => 'Paciente Flow'],
                        ]],
                        'messages' => [[
                            'from' => '593999111445',
                            'id' => 'wamid.flowmaker.step1',
                            'timestamp' => '1712745600',
                            'type' => 'text',
                            'text' => ['body' => 'especialidades0'],
                        ]],
                    ],
                ]],
            ]],
        ])->assertOk();

        \DB::table('whatsapp_conversations')
            ->where('wa_number', '593999111445')
            ->update([
                'patient_hc_number' => '0925619736',
                'patient_full_name' => 'Paciente Validado',
                'updated_at' => now(),
            ]);

        $this->postJson('/whatsapp/webhook', [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'from' => '593999111445',
                            'id' => 'wamid.flowmaker.step2',
                            'timestamp' => '1712745660',
                            'type' => 'interactive',
                            'interactive' => [
                                'type' => 'list_reply',
                                'list_reply' => [
                                    'id' => 'oftalmologo general',
                                    'title' => 'oftalmologo general',
                                ],
                            ],
                        ]],
                    ],
                ]],
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('data.messages_persisted', 1)
            ->assertJsonPath('data.automation_runs', 1)
            ->assertJsonPath('data.automation_messages_sent', 1);

        $session = \DB::table('whatsapp_autoresponder_sessions')
            ->where('wa_number', '593999111445')
            ->first();
        $context = json_decode((string) $session?->context, true);

        $this->assertSame('agenda_esperando_medico', $context['state'] ?? null);
        $this->assertSame('oftalmologo general', $context['subespecialidad'] ?? null);
        $this->assertSame('trabajador_id', $context['awaiting_field'] ?? null);
        $this->assertTrue((bool) data_get($context, 'agenda_medicos.ready'));

        $this->assertDatabaseHas('whatsapp_messages', [
            'direction' => 'outbound',
            'message_type' => 'interactive',
            'body' => 'Elige el médico con el que deseas agendar.',
            'status' => 'accepted',
        ]);

        $this->postJson('/whatsapp/webhook', [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'from' => '593999111445',
                            'id' => 'wamid.flowmaker.step3',
                            'timestamp' => '1712745720',
                            'type' => 'interactive',
                            'interactive' => [
                                'type' => 'list_reply',
                                'list_reply' => [
                                    'id' => '2001',
                                    'title' => 'Doctora General Uno',
                                ],
                            ],
                        ]],
                    ],
                ]],
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('data.messages_persisted', 1)
            ->assertJsonPath('data.automation_runs', 1)
            ->assertJsonPath('data.automation_messages_sent', 0);

        $session = \DB::table('whatsapp_autoresponder_sessions')
            ->where('wa_number', '593999111445')
            ->first();
        $context = json_decode((string) $session?->context, true);

        $this->assertSame('2001', $context['trabajador_id'] ?? null);
        $this->assertDatabaseMissing('whatsapp_messages', [
            'direction' => 'outbound',
            'body' => 'Hola, somos CIVE. Un agente se pondrá en contacto contigo en breve.',
        ]);
    }

    public function test_it_validates_cedula_against_patient_data_and_persists_identity_context(): void
    {
        config()->set('whatsapp.migration.automation.enabled', true);
        config()->set('whatsapp.migration.automation.dry_run', true);

        \DB::table('patient_data')->insert([
            'hc_number' => '0925619736',
            'fname' => 'Jorge',
            'mname' => 'Luis',
            'lname' => 'De Vera',
            'lname2' => 'Gutiérrez',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $flowId = \DB::table('whatsapp_autoresponder_flows')->insertGetId([
            'flow_key' => 'default',
            'name' => 'Flujo principal de WhatsApp',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $versionId = \DB::table('whatsapp_autoresponder_flow_versions')->insertGetId([
            'flow_id' => $flowId,
            'version' => 1,
            'status' => 'published',
            'entry_settings' => json_encode([
                'flow' => [
                    'name' => 'Flujo principal de WhatsApp',
                    'scenarios' => [[
                        'id' => 'validar_cedula',
                        'name' => 'Validar cédula',
                        'stage' => 'validation',
                        'status' => 'published',
                        'conditions' => [
                            ['type' => 'state_is', 'value' => 'esperando_cedula'],
                            ['type' => 'message_matches', 'pattern' => '^\\d{6,10}$'],
                        ],
                        'actions' => [
                            ['type' => 'lookup_patient', 'field' => 'cedula', 'source' => 'message'],
                            [
                                'type' => 'conditional',
                                'condition' => ['type' => 'patient_found', 'value' => true],
                                'then' => [
                                    ['type' => 'send_message', 'message' => ['type' => 'text', 'body' => 'Hola {{context.patient.full_name}} 👋']],
                                    ['type' => 'set_state', 'state' => 'menu_principal'],
                                    ['type' => 'goto_menu'],
                                ],
                                'else' => [
                                    ['type' => 'upsert_patient_from_context'],
                                    ['type' => 'send_message', 'message' => ['type' => 'text', 'body' => 'Te registré con tu cédula y tu número. ¿Deseas continuar?']],
                                    ['type' => 'set_state', 'state' => 'menu_principal'],
                                ],
                            ],
                        ],
                    ]],
                ],
            ], JSON_UNESCAPED_UNICODE),
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_autoresponder_flows')
            ->where('id', $flowId)
            ->update(['active_version_id' => $versionId]);

        $conversationId = \DB::table('whatsapp_conversations')->insertGetId([
            'wa_number' => '593997190401',
            'display_name' => 'Jorge WhatsApp',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_autoresponder_sessions')->insert([
            'conversation_id' => $conversationId,
            'wa_number' => '593997190401',
            'scenario_id' => 'captura_cedula',
            'awaiting' => 'input',
            'context' => json_encode([
                'state' => 'esperando_cedula',
                'awaiting_field' => 'cedula',
                'consent' => true,
            ]),
            'last_payload' => json_encode([]),
            'last_interaction_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/whatsapp/webhook', [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'from' => '593997190401',
                            'id' => 'wamid.validation.1',
                            'timestamp' => '1712745600',
                            'type' => 'text',
                            'text' => ['body' => '0925619736'],
                        ]],
                    ],
                ]],
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('data.automation_runs', 1)
            ->assertJsonPath('data.automation_messages_sent', 2);

        $session = \DB::table('whatsapp_autoresponder_sessions')
            ->where('wa_number', '593997190401')
            ->first();
        $context = json_decode((string) $session?->context, true);

        $this->assertSame('0925619736', $context['cedula'] ?? null);
        $this->assertSame('menu_principal', $context['state'] ?? null);
        $this->assertSame('Jorge Luis De Vera Gutiérrez', data_get($context, 'patient.full_name'));

        $this->assertDatabaseHas('whatsapp_conversations', [
            'wa_number' => '593997190401',
            'patient_hc_number' => '0925619736',
            'patient_full_name' => 'Jorge Luis De Vera Gutiérrez',
        ]);

        $this->assertDatabaseHas('whatsapp_messages', [
            'direction' => 'outbound',
            'body' => 'Hola Jorge Luis De Vera Gutiérrez 👋',
            'status' => 'accepted',
        ]);
    }

    public function test_menu_reactivates_bot_when_conversation_is_in_unassigned_human_queue(): void
    {
        config()->set('whatsapp.migration.automation.enabled', true);
        config()->set('whatsapp.migration.automation.dry_run', true);

        $this->publishFlowmakerScenarios([
            [
                'id' => 'menu_reactivation',
                'name' => 'Reactivar menú',
                'status' => 'published',
                'conditions' => [
                    ['type' => 'message_matches', 'pattern' => '^menu$'],
                ],
                'actions' => [
                    ['type' => 'set_state', 'state' => 'menu_principal'],
                    ['type' => 'goto_menu'],
                ],
            ],
        ]);

        $conversationId = \DB::table('whatsapp_conversations')->insertGetId([
            'wa_number' => '593997190401',
            'display_name' => 'Jorge WhatsApp',
            'needs_human' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_autoresponder_sessions')->insert([
            'conversation_id' => $conversationId,
            'wa_number' => '593997190401',
            'scenario_id' => 'abandono',
            'context' => json_encode([
                'state' => 'menu_agendar_modo',
                'abandonment_monitor' => [
                    'abandonment_status' => 'closed',
                    'closed_reason' => 'abandono_agenda_temprana',
                ],
            ]),
            'last_payload' => json_encode([]),
            'last_interaction_at' => now()->subDay(),
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $this->postJson('/whatsapp/webhook', [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'from' => '593997190401',
                            'id' => 'wamid.menu.reactivation.1',
                            'timestamp' => '1712745600',
                            'type' => 'text',
                            'text' => ['body' => 'MEnú'],
                        ]],
                    ],
                ]],
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('data.automation_runs', 1)
            ->assertJsonPath('data.automation_messages_sent', 1);

        $this->assertDatabaseHas('whatsapp_conversations', [
            'wa_number' => '593997190401',
            'needs_human' => false,
        ]);

        $session = \DB::table('whatsapp_autoresponder_sessions')
            ->where('wa_number', '593997190401')
            ->first();
        $context = json_decode((string) $session?->context, true);

        $this->assertSame('menu_principal', $context['state'] ?? null);
        $this->assertArrayNotHasKey('abandonment_monitor', $context);

        $this->assertDatabaseHas('whatsapp_messages', [
            'direction' => 'outbound',
            'message_type' => 'interactive',
            'status' => 'accepted',
        ]);
    }

    public function test_interactive_bot_reply_does_not_reopen_resolved_human_handoff(): void
    {
        config()->set('whatsapp.migration.automation.enabled', true);
        config()->set('whatsapp.migration.automation.dry_run', true);

        $this->publishFlowmakerScenarios([
            [
                'id' => 'agendar_desde_menu',
                'name' => 'Agendar desde menú',
                'status' => 'published',
                'conditions' => [
                    ['type' => 'message_matches', 'pattern' => '^agendar$'],
                ],
                'actions' => [
                    ['type' => 'send_message', 'message' => ['type' => 'text', 'body' => 'Vamos a agendar tu cita.']],
                ],
            ],
        ]);

        $conversationId = \DB::table('whatsapp_conversations')->insertGetId([
            'wa_number' => '593997190401',
            'display_name' => 'Jorge WhatsApp',
            'needs_human' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_handoffs')->insert([
            'conversation_id' => $conversationId,
            'wa_number' => '593997190401',
            'status' => 'resolved',
            'assigned_agent_id' => 44,
            'queued_at' => now()->subHour(),
            'assigned_at' => now()->subHour(),
            'last_activity_at' => now()->subMinutes(30),
            'created_at' => now()->subHour(),
            'updated_at' => now()->subMinutes(30),
        ]);

        $this->postJson('/whatsapp/webhook', [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'from' => '593997190401',
                            'id' => 'wamid.interactive.agendar.1',
                            'timestamp' => '1712745600',
                            'type' => 'interactive',
                            'interactive' => [
                                'type' => 'list_reply',
                                'list_reply' => [
                                    'id' => 'agendar',
                                    'title' => '📅 Agendar cita',
                                ],
                            ],
                        ]],
                    ],
                ]],
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('data.automation_runs', 1)
            ->assertJsonPath('data.automation_messages_sent', 1);

        $this->assertDatabaseHas('whatsapp_conversations', [
            'wa_number' => '593997190401',
            'needs_human' => false,
        ]);

        $this->assertDatabaseHas('whatsapp_messages', [
            'direction' => 'outbound',
            'body' => 'Vamos a agendar tu cita.',
            'status' => 'accepted',
        ]);
    }

    public function test_after_hours_text_does_not_reopen_human_queue_and_bot_answers(): void
    {
        config()->set('whatsapp.migration.automation.enabled', true);
        config()->set('whatsapp.migration.automation.dry_run', true);

        $now = \Carbon\CarbonImmutable::parse('2026-05-22 19:30:00', 'America/Guayaquil');
        \Carbon\Carbon::setTestNow($now);
        \Carbon\CarbonImmutable::setTestNow($now);

        try {
            $this->publishFlowmakerScenarios([
                [
                    'id' => 'after_hours_bot',
                    'name' => 'Bot fuera de horario',
                    'status' => 'published',
                    'conditions' => [
                        ['type' => 'message_matches', 'pattern' => '.*'],
                    ],
                    'actions' => [
                        ['type' => 'send_message', 'message' => ['type' => 'text', 'body' => 'Estoy aquí para ayudarte.']],
                    ],
                ],
            ]);

            $conversationId = \DB::table('whatsapp_conversations')->insertGetId([
                'wa_number' => '593997190401',
                'display_name' => 'Jorge WhatsApp',
                'needs_human' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            \DB::table('whatsapp_handoffs')->insert([
                'conversation_id' => $conversationId,
                'wa_number' => '593997190401',
                'status' => 'resolved',
                'assigned_agent_id' => 44,
                'queued_at' => now()->subHour(),
                'assigned_at' => now()->subHour(),
                'last_activity_at' => now()->subMinutes(30),
                'created_at' => now()->subHour(),
                'updated_at' => now()->subMinutes(30),
            ]);

            $this->postJson('/whatsapp/webhook', [
                'entry' => [[
                    'changes' => [[
                        'value' => [
                            'messages' => [[
                                'from' => '593997190401',
                                'id' => 'wamid.after.hours.1',
                                'timestamp' => '1712745600',
                                'type' => 'text',
                                'text' => ['body' => 'tengo una duda'],
                            ]],
                        ],
                    ]],
                ]],
            ])
                ->assertOk()
                ->assertJsonPath('data.automation_runs', 1)
                ->assertJsonPath('data.automation_messages_sent', 1);

            $this->assertDatabaseHas('whatsapp_conversations', [
                'wa_number' => '593997190401',
                'needs_human' => false,
            ]);

            $this->assertDatabaseHas('whatsapp_messages', [
                'direction' => 'outbound',
                'body' => 'Estoy aquí para ayudarte.',
                'status' => 'accepted',
            ]);
        } finally {
            \Carbon\Carbon::setTestNow();
            \Carbon\CarbonImmutable::setTestNow();
        }
    }

    public function test_it_persists_media_preview_for_inbound_document_and_voice_note(): void
    {
        $payload = [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'contacts' => [[
                            'wa_id' => '593999111333',
                            'profile' => ['name' => 'Paciente Media'],
                        ]],
                        'messages' => [
                            [
                                'from' => '593999111333',
                                'id' => 'wamid.media.doc',
                                'timestamp' => '1712745600',
                                'type' => 'document',
                                'document' => [
                                    'id' => 'doc-media-id',
                                    'mime_type' => 'application/pdf',
                                    'filename' => 'orden-medica.pdf',
                                ],
                            ],
                            [
                                'from' => '593999111333',
                                'id' => 'wamid.media.voice',
                                'timestamp' => '1712745660',
                                'type' => 'audio',
                                'audio' => [
                                    'id' => 'audio-media-id',
                                    'mime_type' => 'audio/ogg',
                                    'voice' => true,
                                ],
                            ],
                        ],
                    ],
                ]],
            ]],
        ];

        $this->postJson('/whatsapp/webhook', $payload)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.messages_persisted', 2);

        $this->assertDatabaseHas('whatsapp_messages', [
            'wa_message_id' => 'wamid.media.doc',
            'message_type' => 'document',
            'body' => null,
        ]);

        $this->assertDatabaseHas('whatsapp_messages', [
            'wa_message_id' => 'wamid.media.voice',
            'message_type' => 'audio',
            'body' => null,
        ]);

        $this->assertDatabaseHas('whatsapp_inbox_messages', [
            'message_id' => 'wamid.media.doc',
            'message_type' => 'document',
            'message_body' => 'orden-medica.pdf',
        ]);

        $this->assertDatabaseHas('whatsapp_inbox_messages', [
            'message_id' => 'wamid.media.voice',
            'message_type' => 'audio',
            'message_body' => '[voice]',
        ]);

        $conversation = \DB::table('whatsapp_conversations')
            ->where('wa_number', '593999111333')
            ->first();

        $this->assertSame('audio', $conversation?->last_message_type);
        $this->assertSame('[voice]', $conversation?->last_message_preview);
        $this->assertSame(2, $conversation?->unread_count);
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

    public function test_it_renders_selected_sigcenter_labels_in_summary_without_losing_ids(): void
    {
        config()->set('whatsapp.migration.automation.enabled', true);
        config()->set('whatsapp.migration.automation.dry_run', true);

        $flowId = \DB::table('whatsapp_autoresponder_flows')->insertGetId([
            'flow_key' => 'default',
            'name' => 'Flujo principal de WhatsApp',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $versionId = \DB::table('whatsapp_autoresponder_flow_versions')->insertGetId([
            'flow_id' => $flowId,
            'version' => 1,
            'status' => 'published',
            'entry_settings' => json_encode([
                'flow' => [
                    'name' => 'Flujo principal de WhatsApp',
                    'scenarios' => [[
                        'id' => 'resumen_sede',
                        'name' => 'Resumen sede',
                        'stage' => 'custom',
                        'status' => 'published',
                        'conditions' => [
                            ['type' => 'state_is', 'value' => 'agenda_esperando_sede'],
                        ],
                        'actions' => [[
                            'type' => 'send_message',
                            'message' => [
                                'type' => 'text',
                                'body' => 'Sede: {{sede_id}}',
                            ],
                        ]],
                    ]],
                ],
            ], JSON_UNESCAPED_UNICODE),
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_autoresponder_flows')
            ->where('id', $flowId)
            ->update(['active_version_id' => $versionId]);

        $conversationId = \DB::table('whatsapp_conversations')->insertGetId([
            'wa_number' => '593999111778',
            'display_name' => 'Paciente Agenda',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_autoresponder_sessions')->insert([
            'conversation_id' => $conversationId,
            'wa_number' => '593999111778',
            'scenario_id' => 'sedes',
            'awaiting' => 'input',
            'context' => json_encode([
                'state' => 'agenda_esperando_sede',
                'awaiting_field' => 'sede_id',
                'sigcenter_sedes' => [
                    'ready' => true,
                    'data' => [
                        'sedes' => [[
                            'ID_SEDE' => '1',
                            'NOMBRE' => 'MATRIZ - CONSULTA EXTERNA',
                        ]],
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/whatsapp/webhook', [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'from' => '593999111778',
                            'id' => 'wamid.flowmaker.sede-label',
                            'timestamp' => '1712745900',
                            'type' => 'interactive',
                            'interactive' => [
                                'type' => 'list_reply',
                                'list_reply' => [
                                    'id' => '1',
                                    'title' => 'MATRIZ - CONSULTA EXTERN',
                                ],
                            ],
                        ]],
                    ],
                ]],
            ]],
        ])->assertOk();

        $session = \DB::table('whatsapp_autoresponder_sessions')
            ->where('wa_number', '593999111778')
            ->first();
        $context = json_decode((string) $session?->context, true);

        $this->assertSame('1', $context['sede_id'] ?? null);
        $this->assertSame('MATRIZ - CONSULTA EXTERNA', $context['sede_nombre'] ?? null);
        $this->assertDatabaseHas('whatsapp_messages', [
            'direction' => 'outbound',
            'body' => 'Sede: MATRIZ - CONSULTA EXTERNA',
        ]);
    }

    public function test_it_routes_natural_scheduling_intent_into_existing_agenda_flow(): void
    {
        config()->set('whatsapp.migration.automation.enabled', true);
        config()->set('whatsapp.migration.automation.dry_run', true);

        \DB::table('users')->insert([
            [
                'username' => 'retina_natural',
                'nombre' => 'Retina Natural',
                'especialidad' => 'Cirujano Oftalmólogo',
                'subespecialidad' => 'Retina y Vítreo',
                'id_trabajador' => 3001,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'username' => 'cornea_natural',
                'nombre' => 'Cornea Natural',
                'especialidad' => 'Cirujano Oftalmólogo',
                'subespecialidad' => 'Córnea',
                'id_trabajador' => 3002,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->publishFlowmakerScenarios([[
            'id' => 'especialidades',
            'name' => 'Especialidades',
            'stage' => 'custom',
            'status' => 'published',
            'conditions' => [
                ['type' => 'message_contains', 'value' => 'especialidades0'],
            ],
            'actions' => [[
                'type' => 'sigcenter_agenda',
                'operation' => 'list_specialties',
                'especialidad' => 'Cirujano Oftalmólogo',
                'send_result' => true,
                'list_body' => '¿Qué especialidad oftalmológica necesitas?',
                'list_button_text' => 'Ver opciones',
                'list_section_title' => 'Especialidades',
                'save_response_as' => 'subespecialidad',
                'next_state' => 'agenda_esperando_subespecialidad',
                'store_result_as' => 'agenda_especialidades',
            ]],
        ]]);

        $conversationId = \DB::table('whatsapp_conversations')->insertGetId([
            'wa_number' => '593999111782',
            'display_name' => 'Paciente Natural',
            'patient_hc_number' => '0955555555',
            'patient_full_name' => 'Paciente Natural',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_autoresponder_sessions')->insert([
            'conversation_id' => $conversationId,
            'wa_number' => '593999111782',
            'scenario_id' => 'menu',
            'context' => json_encode([
                'state' => 'menu_principal',
                'consent' => true,
                'cedula' => '0955555555',
            ], JSON_UNESCAPED_UNICODE),
            'last_payload' => json_encode([]),
            'last_interaction_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/whatsapp/webhook', [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'from' => '593999111782',
                            'id' => 'wamid.flowmaker.natural-agenda',
                            'timestamp' => '1712745960',
                            'type' => 'text',
                            'text' => ['body' => 'Quiero agendar una cita'],
                        ]],
                    ],
                ]],
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('data.automation_runs', 1)
            ->assertJsonPath('data.automation_messages_sent', 1);

        $session = \DB::table('whatsapp_autoresponder_sessions')
            ->where('wa_number', '593999111782')
            ->first();
        $context = json_decode((string) $session?->context, true);

        $this->assertSame('especialidades', $session?->scenario_id);
        $this->assertSame('agenda_esperando_subespecialidad', $context['state'] ?? null);
        $this->assertSame('subespecialidad', $context['awaiting_field'] ?? null);

        $this->assertDatabaseHas('whatsapp_messages', [
            'direction' => 'outbound',
            'message_type' => 'interactive',
            'body' => '¿Qué especialidad oftalmológica necesitas?',
            'status' => 'accepted',
        ]);
    }

    public function test_it_retries_consent_when_patient_is_stuck_in_pending_consent(): void
    {
        config()->set('whatsapp.migration.automation.enabled', true);
        config()->set('whatsapp.migration.automation.dry_run', true);

        $this->publishFlowmakerScenarios([]);

        $conversationId = \DB::table('whatsapp_conversations')->insertGetId([
            'wa_number' => '593999111783',
            'display_name' => 'Paciente Consent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_autoresponder_sessions')->insert([
            'conversation_id' => $conversationId,
            'wa_number' => '593999111783',
            'scenario_id' => 'primer_contacto',
            'context' => json_encode([
                'state' => 'consentimiento_pendiente',
            ], JSON_UNESCAPED_UNICODE),
            'last_payload' => json_encode([]),
            'last_interaction_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/whatsapp/webhook', [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'from' => '593999111783',
                            'id' => 'wamid.flowmaker.retry-consent',
                            'timestamp' => '1712746020',
                            'type' => 'text',
                            'text' => ['body' => 'Quiero información'],
                        ]],
                    ],
                ]],
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('data.automation_runs', 1)
            ->assertJsonPath('data.automation_messages_sent', 1);

        $session = \DB::table('whatsapp_autoresponder_sessions')
            ->where('wa_number', '593999111783')
            ->first();
        $context = json_decode((string) $session?->context, true);

        $this->assertSame('consent_retry', $session?->scenario_id);
        $this->assertSame('consentimiento_pendiente', $context['state'] ?? null);

        $this->assertDatabaseHas('whatsapp_messages', [
            'direction' => 'outbound',
            'message_type' => 'interactive',
            'body' => 'Para ayudarte con tu cita o revisar tus datos, necesito tu autorización para usar tus datos protegidos. ¿Nos autorizas?',
            'status' => 'accepted',
        ]);
    }

    public function test_it_retries_identifier_when_patient_sends_invalid_cedula(): void
    {
        config()->set('whatsapp.migration.automation.enabled', true);
        config()->set('whatsapp.migration.automation.dry_run', true);

        $this->publishFlowmakerScenarios([]);

        $conversationId = \DB::table('whatsapp_conversations')->insertGetId([
            'wa_number' => '593999111784',
            'display_name' => 'Paciente Cedula',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_autoresponder_sessions')->insert([
            'conversation_id' => $conversationId,
            'wa_number' => '593999111784',
            'scenario_id' => 'captura_cedula',
            'awaiting' => 'input',
            'context' => json_encode([
                'state' => 'esperando_cedula',
                'awaiting_field' => 'cedula',
                'consent' => true,
            ], JSON_UNESCAPED_UNICODE),
            'last_payload' => json_encode([]),
            'last_interaction_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/whatsapp/webhook', [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'from' => '593999111784',
                            'id' => 'wamid.flowmaker.retry-cedula',
                            'timestamp' => '1712746080',
                            'type' => 'text',
                            'text' => ['body' => 'mi cédula no la sé'],
                        ]],
                    ],
                ]],
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('data.automation_runs', 1)
            ->assertJsonPath('data.automation_messages_sent', 1);

        $session = \DB::table('whatsapp_autoresponder_sessions')
            ->where('wa_number', '593999111784')
            ->first();
        $context = json_decode((string) $session?->context, true);

        $this->assertSame('cedula_retry', $session?->scenario_id);
        $this->assertSame('esperando_cedula', $context['state'] ?? null);
        $this->assertSame('cedula', $context['awaiting_field'] ?? null);

        $this->assertDatabaseHas('whatsapp_messages', [
            'direction' => 'outbound',
            'body' => 'Para continuar necesito tu número de cédula en formato válido. Escríbelo con 6 a 10 dígitos, sin espacios ni guiones. Si prefieres apoyo, escribe AYUDA.',
        ]);
    }

    public function test_it_prioritizes_recovery_over_generic_fallback(): void
    {
        config()->set('whatsapp.migration.automation.enabled', true);
        config()->set('whatsapp.migration.automation.dry_run', true);

        \DB::table('users')->insert([
            [
                'username' => 'retina_fallback',
                'nombre' => 'Retina Fallback',
                'especialidad' => 'Cirujano Oftalmólogo',
                'subespecialidad' => 'Retina y Vítreo',
                'id_trabajador' => 4001,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->publishFlowmakerScenarios([
            [
                'id' => 'especialidades',
                'name' => 'Especialidades',
                'stage' => 'custom',
                'status' => 'published',
                'conditions' => [
                    ['type' => 'message_contains', 'value' => 'especialidades0'],
                ],
                'actions' => [[
                    'type' => 'sigcenter_agenda',
                    'operation' => 'list_specialties',
                    'especialidad' => 'Cirujano Oftalmólogo',
                    'send_result' => true,
                    'list_body' => '¿Qué especialidad oftalmológica necesitas?',
                    'list_button_text' => 'Ver opciones',
                    'list_section_title' => 'Especialidades',
                    'save_response_as' => 'subespecialidad',
                    'next_state' => 'agenda_esperando_subespecialidad',
                    'store_result_as' => 'agenda_especialidades',
                ]],
            ],
            [
                'id' => 'fallback',
                'name' => 'Fallback',
                'stage' => 'custom',
                'status' => 'published',
                'conditions' => [
                    ['type' => 'always'],
                ],
                'actions' => [[
                    'type' => 'send_message',
                    'message' => [
                        'type' => 'text',
                        'body' => 'Hola, somos CIVE. Un agente se pondrá en contacto contigo en breve.',
                    ],
                ]],
            ],
        ]);

        $conversationId = \DB::table('whatsapp_conversations')->insertGetId([
            'wa_number' => '593999111785',
            'display_name' => 'Paciente Fallback',
            'patient_hc_number' => '0966666666',
            'patient_full_name' => 'Paciente Fallback',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_autoresponder_sessions')->insert([
            'conversation_id' => $conversationId,
            'wa_number' => '593999111785',
            'scenario_id' => 'menu',
            'context' => json_encode([
                'state' => 'menu_principal',
                'consent' => true,
                'cedula' => '0966666666',
            ], JSON_UNESCAPED_UNICODE),
            'last_payload' => json_encode([]),
            'last_interaction_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/whatsapp/webhook', [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'from' => '593999111785',
                            'id' => 'wamid.flowmaker.fallback-priority',
                            'timestamp' => '1712746140',
                            'type' => 'text',
                            'text' => ['body' => 'Quiero agendar una cita'],
                        ]],
                    ],
                ]],
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('data.automation_runs', 1)
            ->assertJsonPath('data.automation_messages_sent', 1);

        $session = \DB::table('whatsapp_autoresponder_sessions')
            ->where('wa_number', '593999111785')
            ->first();

        $this->assertSame('especialidades', $session?->scenario_id);
        $this->assertDatabaseMissing('whatsapp_messages', [
            'direction' => 'outbound',
            'body' => 'Hola, somos CIVE. Un agente se pondrá en contacto contigo en breve.',
        ]);
        $this->assertDatabaseHas('whatsapp_messages', [
            'direction' => 'outbound',
            'body' => '¿Qué especialidad oftalmológica necesitas?',
        ]);
    }

    public function test_it_records_sigcenter_booking_and_sends_success_message_after_confirmation(): void
    {
        config()->set('whatsapp.migration.automation.enabled', true);
        config()->set('whatsapp.migration.automation.dry_run', true);

        Http::fake([
            'sigcenter.ddns.net:18093/*' => Http::response([
                'estado' => 200,
                'msj' => 'OK',
                'agenda_id' => 'AG-123',
            ], 200),
        ]);

        $flowId = \DB::table('whatsapp_autoresponder_flows')->insertGetId([
            'flow_key' => 'default',
            'name' => 'Flujo principal de WhatsApp',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $versionId = \DB::table('whatsapp_autoresponder_flow_versions')->insertGetId([
            'flow_id' => $flowId,
            'version' => 1,
            'status' => 'published',
            'entry_settings' => json_encode([
                'flow' => [
                    'name' => 'Flujo principal de WhatsApp',
                    'scenarios' => [[
                        'id' => 'crear_cita',
                        'name' => 'Crear cita',
                        'stage' => 'custom',
                        'status' => 'published',
                        'conditions' => [
                            ['type' => 'state_is', 'value' => 'agenda_confirmar_cita'],
                            ['type' => 'message_contains', 'keywords' => ['confirmar']],
                        ],
                        'actions' => [[
                            'type' => 'sigcenter_agenda',
                            'operation' => 'book_appointment',
                            'company_id' => 113,
                            'requires_confirmation' => true,
                        ]],
                    ]],
                ],
            ], JSON_UNESCAPED_UNICODE),
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_autoresponder_flows')
            ->where('id', $flowId)
            ->update(['active_version_id' => $versionId]);

        $conversationId = \DB::table('whatsapp_conversations')->insertGetId([
            'wa_number' => '593999111779',
            'display_name' => 'Paciente Agenda',
            'patient_hc_number' => '0922222222',
            'patient_full_name' => 'Paciente Agenda',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_autoresponder_sessions')->insert([
            'conversation_id' => $conversationId,
            'wa_number' => '593999111779',
            'scenario_id' => 'resumen',
            'context' => json_encode([
                'state' => 'agenda_confirmar_cita',
                'cedula' => '0922222222',
                'trabajador_id' => '77',
                'medico_nombre' => 'Dr. Agenda',
                'sede_id' => '1',
                'sede_nombre' => 'Villa Club',
                'procedimiento_id' => '530',
                'procedimiento_nombre' => 'Consulta nuevo',
                'fecha' => '2026-05-08',
                'fecha_inicio' => '2026-05-08 13:00:00',
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/whatsapp/webhook', [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'from' => '593999111779',
                            'id' => 'wamid.flowmaker.booking-confirm',
                            'timestamp' => '1712745900',
                            'type' => 'text',
                            'text' => ['body' => 'Confirmar'],
                        ]],
                    ],
                ]],
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('data.automation_runs', 1)
            ->assertJsonPath('data.automation_messages_sent', 1);

        $this->assertDatabaseHas('whatsapp_sigcenter_bookings', [
            'wa_number' => '593999111779',
            'inbound_message_id' => 'wamid.flowmaker.booking-confirm',
            'status' => 'created',
            'patient_hc_number' => '0922222222',
            'sede_id' => '1',
            'sede_nombre' => 'Villa Club',
            'procedimiento_id' => '530',
            'procedimiento_nombre' => 'Consulta nuevo',
        ]);

        $this->assertDatabaseHas('whatsapp_messages', [
            'direction' => 'outbound',
            'body' => "Tu cita ha sido agendada exitosamente.\n\nFecha: 2026-05-08\nHorario: 2026-05-08 13:00:00\nSede: Villa Club\nProcedimiento: Consulta nuevo\n\nTe esperamos.",
        ]);
    }

    public function test_it_blocks_new_booking_when_patient_has_active_whatsapp_booking(): void
    {
        config()->set('whatsapp.migration.automation.enabled', true);
        config()->set('whatsapp.migration.automation.dry_run', true);

        $this->publishFlowmakerScenarios([[
            'id' => 'especialidades',
            'name' => 'Especialidades',
            'stage' => 'custom',
            'status' => 'published',
            'conditions' => [
                ['type' => 'message_contains', 'value' => 'agendar cita'],
            ],
            'actions' => [[
                'type' => 'sigcenter_agenda',
                'operation' => 'list_specialties',
                'send_result' => true,
            ]],
        ]]);

        $conversationId = \DB::table('whatsapp_conversations')->insertGetId([
            'wa_number' => '593999111780',
            'display_name' => 'Paciente Duplicado',
            'patient_hc_number' => '0933333333',
            'patient_full_name' => 'Paciente Duplicado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $fechaDuplicada = now()->addDay()->format('Y-m-d H:i:s');

        \DB::table('whatsapp_sigcenter_bookings')->insert([
            'conversation_id' => $conversationId,
            'wa_number' => '593999111780',
            'status' => 'created',
            'patient_hc_number' => '0933333333',
            'patient_full_name' => 'Paciente Duplicado',
            'sede_id' => '1',
            'sede_nombre' => 'Villa Club',
            'procedimiento_id' => '530',
            'procedimiento_nombre' => 'Consulta nuevo',
            'fecha_inicio' => $fechaDuplicada,
            'booked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/whatsapp/webhook', [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'from' => '593999111780',
                            'id' => 'wamid.flowmaker.duplicate-booking',
                            'timestamp' => '1712745900',
                            'type' => 'text',
                            'text' => ['body' => 'Agendar cita'],
                        ]],
                    ],
                ]],
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('data.automation_runs', 1)
            ->assertJsonPath('data.automation_messages_sent', 1);

        $this->assertDatabaseHas('whatsapp_messages', [
            'direction' => 'outbound',
            'body' => "Ya tienes una cita vigente registrada desde WhatsApp:\nFecha: {$fechaDuplicada}\nSede: Villa Club\nProcedimiento: Consulta nuevo.\n\nPara evitar duplicados no puedo crear otra cita. Si deseas cambiarla, escribe CANCELAR CITA o REAGENDAR CITA y un agente te ayudará.",
        ]);
    }

    public function test_it_cancels_active_booking_after_patient_confirmation(): void
    {
        config()->set('whatsapp.migration.automation.enabled', true);
        config()->set('whatsapp.migration.automation.dry_run', true);

        Http::fake([
            'sigcenter.ddns.net:18093/*' => Http::response([
                'code' => '200',
                'msg' => 'CANCELACION CON EXITO',
                'cancelado' => 'Su cita ha sido cancelada',
            ], 200),
        ]);

        $this->publishFlowmakerScenarios([]);

        $conversationId = \DB::table('whatsapp_conversations')->insertGetId([
            'wa_number' => '593999111781',
            'display_name' => 'Paciente Cancelar',
            'patient_hc_number' => '0944444444',
            'patient_full_name' => 'Paciente Cancelar',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $fechaCancelacion = now()->addDays(2)->format('Y-m-d H:i:s');

        \DB::table('whatsapp_sigcenter_bookings')->insert([
            'conversation_id' => $conversationId,
            'wa_number' => '593999111781',
            'status' => 'created',
            'patient_hc_number' => '0944444444',
            'patient_full_name' => 'Paciente Cancelar',
            'sigcenter_agenda_id' => 'AG-999',
            'sede_id' => '16',
            'sede_nombre' => 'Ceibos',
            'procedimiento_id' => '531',
            'procedimiento_nombre' => 'Cita Médica',
            'fecha_inicio' => $fechaCancelacion,
            'booked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/whatsapp/webhook', [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'from' => '593999111781',
                            'id' => 'wamid.flowmaker.cancel-booking',
                            'timestamp' => '1712745900',
                            'type' => 'text',
                            'text' => ['body' => 'Cancelar cita'],
                        ]],
                    ],
                ]],
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('data.automation_runs', 1)
            ->assertJsonPath('data.automation_messages_sent', 1);

        $this->assertDatabaseHas('whatsapp_messages', [
            'direction' => 'outbound',
            'body' => "Antes de cancelar, confirma esta acción sobre tu cita:\nFecha: {$fechaCancelacion}\nSede: Ceibos\nProcedimiento: Cita Médica.\n\n¿Deseas cancelar esta cita?",
        ]);

        $this->postJson('/whatsapp/webhook', [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'from' => '593999111781',
                            'id' => 'wamid.flowmaker.cancel-booking-confirm',
                            'timestamp' => '1712745901',
                            'type' => 'interactive',
                            'interactive' => [
                                'type' => 'button_reply',
                                'button_reply' => [
                                    'id' => 'confirmar_cancelacion',
                                    'title' => 'Sí cancelar',
                                ],
                            ],
                        ]],
                    ],
                ]],
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('data.automation_runs', 1)
            ->assertJsonPath('data.automation_messages_sent', 1);

        $this->assertDatabaseHas('whatsapp_messages', [
            'direction' => 'outbound',
            'body' => "Tu cita fue cancelada exitosamente:\nFecha: {$fechaCancelacion}\nSede: Ceibos\nProcedimiento: Cita Médica.\n\nSi necesitas agendar una nueva cita, escribe HOLA o MENU.",
        ]);

        $this->assertDatabaseHas('whatsapp_sigcenter_bookings', [
            'wa_number' => '593999111781',
            'inbound_message_id' => 'wamid.flowmaker.cancel-booking-confirm',
            'status' => 'cancelled',
            'sigcenter_agenda_id' => 'AG-999',
        ]);
    }

    public function test_it_falls_back_to_get_when_sigcenter_cancel_rejects_post(): void
    {
        config()->set('whatsapp.migration.automation.enabled', true);
        config()->set('whatsapp.migration.automation.dry_run', true);

        Http::fake([
            'sigcenter.ddns.net:18093/*' => function (\Illuminate\Http\Client\Request $request) {
                if ($request->method() === 'POST') {
                    return Http::response([], 405);
                }

                if ($request->method() === 'GET') {
                    return Http::response([
                        'code' => '200',
                        'msg' => 'CANCELACION CON EXITO',
                        'cancelado' => 'Su cita ha sido cancelada',
                    ], 200);
                }

                return Http::response([], 500);
            },
        ]);

        $this->publishFlowmakerScenarios([]);

        $conversationId = \DB::table('whatsapp_conversations')->insertGetId([
            'wa_number' => '593999111782',
            'display_name' => 'Paciente Cancelar Fallback',
            'patient_hc_number' => '0955555555',
            'patient_full_name' => 'Paciente Cancelar Fallback',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $fechaCancelacion = now()->addDays(3)->format('Y-m-d H:i:s');

        \DB::table('whatsapp_sigcenter_bookings')->insert([
            'conversation_id' => $conversationId,
            'wa_number' => '593999111782',
            'status' => 'created',
            'patient_hc_number' => '0955555555',
            'patient_full_name' => 'Paciente Cancelar Fallback',
            'sigcenter_agenda_id' => 'AG-1000',
            'sede_id' => '1',
            'sede_nombre' => 'Villa Club',
            'procedimiento_id' => '530',
            'procedimiento_nombre' => 'Consulta nuevo',
            'fecha_inicio' => $fechaCancelacion,
            'booked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/whatsapp/webhook', [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'from' => '593999111782',
                            'id' => 'wamid.flowmaker.cancel-booking-fallback',
                            'timestamp' => '1712745900',
                            'type' => 'text',
                            'text' => ['body' => 'Cancelar cita'],
                        ]],
                    ],
                ]],
            ]],
        ])->assertOk();

        $this->postJson('/whatsapp/webhook', [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'from' => '593999111782',
                            'id' => 'wamid.flowmaker.cancel-booking-fallback-confirm',
                            'timestamp' => '1712745901',
                            'type' => 'interactive',
                            'interactive' => [
                                'type' => 'button_reply',
                                'button_reply' => [
                                    'id' => 'confirmar_cancelacion',
                                    'title' => 'Sí cancelar',
                                ],
                            ],
                        ]],
                    ],
                ]],
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('whatsapp_messages', [
            'direction' => 'outbound',
            'body' => "Tu cita fue cancelada exitosamente:\nFecha: {$fechaCancelacion}\nSede: Villa Club\nProcedimiento: Consulta nuevo.\n\nSi necesitas agendar una nueva cita, escribe HOLA o MENU.",
        ]);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            return $request->url() === 'https://sigcenter.ddns.net:18093/restful/api-agenda/cancelar-cita'
                && $request->method() === 'GET'
                && ($request['agenda_id'] ?? null) === 'AG-1000';
        });
    }

    /**
     * @param array<int, array<string, mixed>> $scenarios
     */
    private function publishFlowmakerScenarios(array $scenarios): void
    {
        $flowId = \DB::table('whatsapp_autoresponder_flows')->insertGetId([
            'flow_key' => 'default',
            'name' => 'Flujo principal de WhatsApp',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $versionId = \DB::table('whatsapp_autoresponder_flow_versions')->insertGetId([
            'flow_id' => $flowId,
            'version' => 1,
            'status' => 'published',
            'entry_settings' => json_encode([
                'flow' => [
                    'name' => 'Flujo principal de WhatsApp',
                    'scenarios' => $scenarios,
                ],
            ], JSON_UNESCAPED_UNICODE),
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_autoresponder_flows')
            ->where('id', $flowId)
            ->update(['active_version_id' => $versionId]);
    }

    public function test_it_invalidates_queue_open_cache_when_handoff_setting_changes(): void
    {
        // Primear el caché manualmente
        \Illuminate\Support\Facades\Cache::put('whatsapp.queue_open_status', true, 60);
        $this->assertTrue(\Illuminate\Support\Facades\Cache::has('whatsapp.queue_open_status'));

        $service = app(\App\Modules\Settings\Services\SettingsService::class);
        $service->upsert('whatsapp_handoff_business_timezone', 'America/Lima', 'whatsapp');

        $this->assertFalse(\Illuminate\Support\Facades\Cache::has('whatsapp.queue_open_status'));
    }

    public function test_it_does_not_invalidate_queue_cache_for_non_handoff_settings(): void
    {
        \Illuminate\Support\Facades\Cache::put('whatsapp.queue_open_status', true, 60);

        $service = app(\App\Modules\Settings\Services\SettingsService::class);
        $service->upsert('whatsapp_cloud_api_token', 'abc123', 'whatsapp');

        $this->assertTrue(\Illuminate\Support\Facades\Cache::has('whatsapp.queue_open_status'));
    }

    public function test_it_responds_to_audio_message_with_text_only_notice(): void
    {
        Http::fake([
            '*graph.facebook.com*' => Http::response(['messages' => [['id' => 'wamid.out.1']]], 200),
        ]);

        config()->set('whatsapp.migration.automation.enabled', true);
        config()->set('whatsapp.migration.automation.dry_run', false);
        config()->set('whatsapp.migration.api.phone_number_id', '123456');
        config()->set('whatsapp.migration.api.token', 'test-token');

        \DB::table('app_settings')->insert([
            ['name' => 'whatsapp_cloud_enabled', 'value' => '1', 'category' => 'whatsapp', 'type' => 'text', 'autoload' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'whatsapp_cloud_phone_number_id', 'value' => '123456', 'category' => 'whatsapp', 'type' => 'text', 'autoload' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'whatsapp_cloud_access_token', 'value' => 'test-token', 'category' => 'whatsapp', 'type' => 'text', 'autoload' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $payload = [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'contacts' => [[
                            'wa_id' => '593999888777',
                            'profile' => ['name' => 'Paciente Audio'],
                        ]],
                        'messages' => [[
                            'from' => '593999888777',
                            'id' => 'wamid.audio.1',
                            'timestamp' => '1712745600',
                            'type' => 'audio',
                            'audio' => ['id' => 'audio_media_id_001'],
                        ]],
                    ],
                ]],
            ]],
        ];

        $response = $this->postJson('/whatsapp/webhook', $payload);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.messages_persisted', 1)
            ->assertJsonPath('data.automation_runs', 1)
            ->assertJsonPath('data.automation_messages_sent', 1);

        // El mensaje de audio fue persistido correctamente
        $this->assertDatabaseHas('whatsapp_messages', [
            'direction' => 'inbound',
            'message_type' => 'audio',
        ]);

        // Verificar que se intentó enviar la respuesta de "solo proceso texto"
        Http::assertSent(function ($request) {
            $body = $request->data();
            return str_contains($body['text']['body'] ?? '', 'MENU');
        });
    }

    public function test_it_does_not_respond_to_audio_when_conversation_is_assigned_to_agent(): void
    {
        Http::fake([
            '*graph.facebook.com*' => Http::response(['messages' => [['id' => 'wamid.out.2']]], 200),
        ]);

        config()->set('whatsapp.migration.automation.enabled', true);
        config()->set('whatsapp.migration.api.phone_number_id', '123456');
        config()->set('whatsapp.migration.api.token', 'test-token');

        // Crear conversación asignada a un agente humano
        \DB::table('whatsapp_conversations')->insert([
            'wa_number' => '593999777666',
            'needs_human' => false,
            'assigned_user_id' => 999, // agente asignado
            'unread_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insertar horario abierto para que humanQueueIsOpen() = true
        \DB::table('app_settings')->insert([
            ['name' => 'whatsapp_handoff_business_timezone', 'value' => 'America/Guayaquil', 'category' => 'whatsapp', 'type' => 'text', 'autoload' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'whatsapp_handoff_business_schedule', 'value' => '', 'category' => 'whatsapp', 'type' => 'text', 'autoload' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'whatsapp_handoff_business_start', 'value' => '00:00', 'category' => 'whatsapp', 'type' => 'text', 'autoload' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'whatsapp_handoff_business_end', 'value' => '00:00', 'category' => 'whatsapp', 'type' => 'text', 'autoload' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $payload = [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'from' => '593999777666',
                            'id' => 'wamid.audio.assigned',
                            'timestamp' => '1712745600',
                            'type' => 'audio',
                            'audio' => ['id' => 'audio_media_id_002'],
                        ]],
                    ],
                ]],
            ]],
        ];

        $response = $this->postJson('/whatsapp/webhook', $payload);

        $response->assertOk()
            ->assertJsonPath('data.automation_runs', 0)
            ->assertJsonPath('data.automation_messages_sent', 0);

        // El bot NO envió respuesta (el agente maneja la conversación)
        Http::assertNothingSent();
    }

    public function test_it_re_asks_when_user_sends_unrecognized_text_during_cancel_confirmation(): void
    {
        Http::fake([
            '*graph.facebook.com*' => Http::response(['messages' => [['id' => 'wamid.out.3']]], 200),
        ]);

        config()->set('whatsapp.migration.automation.enabled', true);
        config()->set('whatsapp.migration.automation.dry_run', false);
        config()->set('whatsapp.migration.api.phone_number_id', '123456');
        config()->set('whatsapp.migration.api.token', 'test-token');

        \DB::table('app_settings')->insert([
            ['name' => 'whatsapp_cloud_enabled', 'value' => '1', 'category' => 'whatsapp', 'type' => 'text', 'autoload' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'whatsapp_cloud_phone_number_id', 'value' => '123456', 'category' => 'whatsapp', 'type' => 'text', 'autoload' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'whatsapp_cloud_access_token', 'value' => 'test-token', 'category' => 'whatsapp', 'type' => 'text', 'autoload' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Conversación con estado de cancelación pendiente
        \DB::table('whatsapp_conversations')->insert([
            'wa_number' => '593999555444',
            'needs_human' => false,
            'assigned_user_id' => null,
            'unread_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $conversation = \DB::table('whatsapp_conversations')->where('wa_number', '593999555444')->first();

        \DB::table('whatsapp_autoresponder_sessions')->insert([
            'wa_number' => '593999555444',
            'conversation_id' => $conversation->id,
            'scenario_id' => 'agenda_cancelar',
            'awaiting' => null,
            'context' => json_encode([
                'state' => 'agenda_confirmar_cancelacion',
                'sigcenter_agenda_id' => '42',
            ]),
            'last_interaction_at' => now()->subMinutes(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Registrar una cita activa en sigcenter bookings
        \DB::table('whatsapp_sigcenter_bookings')->insert([
            'conversation_id' => $conversation->id,
            'wa_number' => '593999555444',
            'sigcenter_agenda_id' => 42,
            'status' => 'created',
            'booked_at' => now()->subDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Usuario manda texto ambiguo que no es ni SÍ ni NO
        $payload = [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'from' => '593999555444',
                            'id' => 'wamid.cancel.ambiguous',
                            'timestamp' => (string) now()->timestamp,
                            'type' => 'text',
                            'text' => ['body' => 'ok lo pienso'],
                        ]],
                    ],
                ]],
            ]],
        ];

        $response = $this->postJson('/whatsapp/webhook', $payload);

        $response->assertOk()
            ->assertJsonPath('data.automation_runs', 1)
            ->assertJsonPath('data.automation_messages_sent', 1);

        // El bot re-preguntó (no mostró el menú principal ni otro escenario)
        Http::assertSent(function ($request) {
            $body = $request->data();
            return str_contains($body['text']['body'] ?? '', 'SÍ') &&
                   str_contains($body['text']['body'] ?? '', 'NO');
        });

        // El estado de la sesión NO cambió a menu_principal
        $session = \DB::table('whatsapp_autoresponder_sessions')
            ->where('conversation_id', $conversation->id)
            ->first();
        $ctx = json_decode($session->context, true);
        $this->assertSame('agenda_confirmar_cancelacion', $ctx['state']);
    }
}

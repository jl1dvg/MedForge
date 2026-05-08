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
        Schema::dropIfExists('patient_data');
        Schema::dropIfExists('users');
        Schema::dropIfExists('app_settings');

        Schema::create('app_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->text('value')->nullable();
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
}

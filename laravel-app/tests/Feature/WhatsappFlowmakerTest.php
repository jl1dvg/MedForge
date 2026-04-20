<?php

namespace Tests\Feature;

use App\Http\Middleware\LegacySessionBridge;
use App\Http\Middleware\RequireLegacyPermission;
use App\Http\Middleware\RequireLegacySession;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappFlowmakerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'whatsapp_messages',
            'whatsapp_conversations',
            'whatsapp_message_templates',
            'patient_data',
            'whatsapp_ai_agent_runs',
            'whatsapp_knowledge_documents',
            'whatsapp_autoresponder_sessions',
            'whatsapp_autoresponder_step_transitions',
            'whatsapp_autoresponder_step_actions',
            'whatsapp_autoresponder_steps',
            'whatsapp_autoresponder_schedules',
            'whatsapp_autoresponder_version_filters',
            'whatsapp_autoresponder_flow_versions',
            'whatsapp_autoresponder_flows',
            'whatsapp_flow_shadow_runs',
            'roles',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username');
            $table->string('first_name')->default('');
            $table->string('last_name')->default('');
            $table->string('nombre')->default('');
            $table->string('email')->nullable();
            $table->string('profile_photo')->nullable();
            $table->text('permisos')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
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

        Schema::create('whatsapp_autoresponder_steps', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('flow_version_id');
            $table->string('step_key');
            $table->string('step_type');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('order_index')->default(0);
            $table->boolean('is_entry_point')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_autoresponder_step_actions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('step_id');
            $table->string('action_type');
            $table->unsignedBigInteger('template_revision_id')->nullable();
            $table->text('message_body')->nullable();
            $table->string('media_url')->nullable();
            $table->unsignedInteger('delay_seconds')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('order_index')->default(0);
            $table->timestamps();
        });

        Schema::create('whatsapp_autoresponder_step_transitions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('step_id');
            $table->unsignedBigInteger('target_step_id')->nullable();
            $table->string('condition_label')->nullable();
            $table->string('condition_type')->default('always');
            $table->json('condition_payload')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->timestamps();
        });

        Schema::create('whatsapp_autoresponder_version_filters', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('flow_version_id');
            $table->string('filter_type');
            $table->string('operator');
            $table->json('value')->nullable();
            $table->boolean('is_exclusion')->default(false);
            $table->unsignedInteger('order_index')->default(0);
            $table->timestamps();
        });

        Schema::create('whatsapp_autoresponder_schedules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('flow_version_id');
            $table->unsignedInteger('day_of_week')->nullable();
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->string('timezone')->nullable();
            $table->boolean('allow_holidays')->default(false);
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

        Schema::create('whatsapp_knowledge_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 191);
            $table->string('slug', 191)->unique();
            $table->text('summary')->nullable();
            $table->longText('content');
            $table->string('status', 32)->default('draft');
            $table->string('source_type', 32)->default('manual');
            $table->string('source_label', 191)->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_conversations', function (Blueprint $table): void {
            $table->id();
            $table->string('wa_number');
            $table->string('display_name')->nullable();
            $table->string('patient_hc_number')->nullable();
            $table->string('patient_full_name')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->string('last_message_direction')->nullable();
            $table->string('last_message_type')->nullable();
            $table->text('last_message_preview')->nullable();
            $table->boolean('needs_human')->default(true);
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
            $table->string('wa_message_id')->nullable();
            $table->string('direction');
            $table->string('message_type');
            $table->text('body')->nullable();
            $table->json('raw_payload')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('message_timestamp')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_message_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('template_code');
            $table->string('display_name');
            $table->string('language')->default('es');
            $table->string('category')->default('utility');
            $table->string('status')->default('approved');
            $table->unsignedBigInteger('current_revision_id')->nullable();
            $table->string('wa_business_account')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('approval_requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
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

        Schema::create('whatsapp_ai_agent_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('wa_number', 32)->nullable();
            $table->string('scenario_id', 191)->nullable();
            $table->unsignedInteger('action_index')->default(0);
            $table->longText('input_text')->nullable();
            $table->json('filters')->nullable();
            $table->json('matched_documents')->nullable();
            $table->longText('response_text')->nullable();
            $table->string('classification', 64)->nullable();
            $table->decimal('confidence', 5, 2)->default(0);
            $table->boolean('suggested_handoff')->default(false);
            $table->string('decision', 32)->nullable();
            $table->boolean('fallback_used')->default(false);
            $table->json('handoff_reasons')->nullable();
            $table->json('scorecard')->nullable();
            $table->json('evaluation')->nullable();
            $table->json('context_before')->nullable();
            $table->json('context_after')->nullable();
            $table->string('source', 32)->default('preview');
            $table->timestamps();
        });

        \DB::table('roles')->insert([
            'id' => 1,
            'name' => 'Call Center',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('users')->insert([
            'id' => 44,
            'username' => 'wa.admin',
            'first_name' => 'Jorge',
            'last_name' => 'Vera',
            'nombre' => 'Jorge Vera',
            'email' => 'jorge@example.test',
            'profile_photo' => null,
            'permisos' => json_encode(['whatsapp.manage', 'whatsapp.autoresponder.manage']),
            'role_id' => 1,
        ]);

        \DB::table('whatsapp_autoresponder_sessions')->insert([
            'conversation_id' => 1,
            'wa_number' => '593999111222',
            'scenario_id' => 'primer_contacto',
            'node_id' => 'primer_contacto',
            'awaiting' => 'response',
            'context' => json_encode(['hc' => 'HC-001']),
            'last_payload' => json_encode(['body' => 'Hola']),
            'last_interaction_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_conversations')->insert([
            'id' => 1,
            'wa_number' => '593999111222',
            'display_name' => 'Paciente Demo',
            'patient_hc_number' => 'HC-001',
            'patient_full_name' => 'Paciente Demo',
            'last_message_at' => now(),
            'last_message_direction' => 'inbound',
            'last_message_type' => 'text',
            'last_message_preview' => 'hola',
            'needs_human' => 1,
            'assigned_user_id' => 44,
            'assigned_at' => now(),
            'unread_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_messages')->insert([
            'conversation_id' => 1,
            'wa_message_id' => 'wamid.demo.1',
            'direction' => 'inbound',
            'message_type' => 'text',
            'body' => 'hola',
            'raw_payload' => json_encode(['text' => ['body' => 'hola']]),
            'message_timestamp' => now()->subMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_message_templates')->insert([
            'id' => 1,
            'template_code' => 'recordatorio_cita',
            'display_name' => 'Recordatorio Cita',
            'language' => 'es',
            'category' => 'utility',
            'status' => 'approved',
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('patient_data')->insert([
            'id' => 1,
            'hc_number' => 'HC-001',
            'lname' => 'Demo',
            'fname' => 'Paciente',
            'celular' => '593999111222',
            'email' => 'paciente@example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_knowledge_documents')->insert([
            'title' => 'FAQ Agenda',
            'slug' => 'faq-agenda',
            'summary' => 'Información operativa para agendar citas y orientar al paciente.',
            'content' => 'Para agendar una cita se debe validar la sede, disponibilidad y antecedentes básicos del paciente.',
            'status' => 'published',
            'source_type' => 'manual',
            'source_label' => 'Flowmaker KB',
            'metadata' => json_encode([
                'sede' => 'Matriz',
                'especialidad' => 'Oftalmología',
                'tipo_contenido' => 'faq',
                'audiencia' => 'paciente',
                'vigencia' => 'vigente',
            ]),
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        config()->set('whatsapp.migration.enabled', true);
        config()->set('whatsapp.migration.ui.enabled', true);
        config()->set('whatsapp.migration.api.read_enabled', true);
        config()->set('whatsapp.migration.api.write_enabled', true);
        config()->set('whatsapp.migration.automation.enabled', true);
        config()->set('whatsapp.migration.automation.compare_with_legacy', true);
        config()->set('whatsapp.migration.automation.fallback_to_legacy', true);
        config()->set('whatsapp.migration.automation.dry_run', true);

        $this->actingAs(User::query()->findOrFail(44));
    }

    public function test_it_returns_flowmaker_contract(): void
    {
        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->getJson('/v2/whatsapp/api/flowmaker/contract');

        $response
            ->assertOk()
            ->assertJsonPath('constraints.buttonLimit', 3)
            ->assertJsonPath('storage.flow_key', 'default');
    }

    public function test_it_publishes_flowmaker_version_in_laravel_tables(): void
    {
        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->postJson('/v2/whatsapp/api/flowmaker/publish', $this->defaultFlowPayload());

        $response
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('active_version.version', 1);

        $this->assertDatabaseCount('whatsapp_autoresponder_flows', 1);
        $this->assertDatabaseCount('whatsapp_autoresponder_flow_versions', 1);
        $this->assertDatabaseCount('whatsapp_autoresponder_steps', 2);
        $this->assertDatabaseCount('whatsapp_autoresponder_step_actions', 2);
        $this->assertDatabaseCount('whatsapp_autoresponder_step_transitions', 1);
    }

    public function test_it_publishes_flowmaker_version_with_ai_agent_action(): void
    {
        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->postJson('/v2/whatsapp/api/flowmaker/publish', [
                'flow' => [
                    'name' => 'Flow IA',
                    'description' => 'Publicación con AI Agent',
                    'settings' => ['timezone' => 'America/Guayaquil'],
                    'scenarios' => [
                        [
                            'id' => 'ia_preview',
                            'name' => 'IA Preview',
                            'description' => 'Nodo IA',
                            'stage' => 'custom',
                            'actions' => [
                                [
                                    'type' => 'ai_agent',
                                    'instructions' => 'Responder con grounding controlado.',
                                    'tools' => ['conversation_state', 'window_status'],
                                    'kb_filters' => [
                                        'tipo_contenido' => 'faq',
                                        'audiencia' => 'paciente',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('active_version.version', 1);

        $this->assertDatabaseHas('whatsapp_autoresponder_step_actions', [
            'action_type' => 'ai_agent',
        ]);
    }

    public function test_it_does_not_publish_draft_scenarios_into_runtime_tables(): void
    {
        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->postJson('/v2/whatsapp/api/flowmaker/publish', [
                'flow' => [
                    'name' => 'Flow con draft',
                    'description' => 'Solo publica escenarios activos',
                    'settings' => ['timezone' => 'America/Guayaquil'],
                    'scenarios' => [
                        [
                            'id' => 'escenario_activo',
                            'name' => 'Escenario activo',
                            'status' => 'published',
                            'stage' => 'arrival',
                            'actions' => [
                                [
                                    'type' => 'send_message',
                                    'message' => ['type' => 'text', 'body' => 'Hola desde activo'],
                                ],
                            ],
                        ],
                        [
                            'id' => 'escenario_borrador',
                            'name' => 'Escenario borrador',
                            'status' => 'draft',
                            'stage' => 'custom',
                            'conditions' => [
                                ['type' => 'message_contains', 'keywords' => ['borrador']],
                            ],
                            'actions' => [
                                [
                                    'type' => 'send_message',
                                    'message' => ['type' => 'text', 'body' => 'Hola desde draft'],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $this->assertDatabaseHas('whatsapp_autoresponder_steps', [
            'step_key' => 'escenario_activo',
        ]);
        $this->assertDatabaseMissing('whatsapp_autoresponder_steps', [
            'step_key' => 'escenario_borrador',
        ]);
    }

    public function test_it_simulates_message_against_active_flow(): void
    {
        $this->publishDefaultFlow();

        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->getJson('/v2/whatsapp/api/flowmaker/simulate?wa_number=593999111222&text=hola');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('matched', true)
            ->assertJsonPath('scenario.id', 'primer_contacto')
            ->assertJsonPath('actions.0.type', 'send_message');
    }

    public function test_it_compares_laravel_flow_against_legacy_source(): void
    {
        $this->publishDefaultFlow();

        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->getJson('/v2/whatsapp/api/flowmaker/compare?wa_number=593999111222&text=hola');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('sources.legacy', 'flow_tables')
            ->assertJsonPath('parity.same_match', true)
            ->assertJsonPath('parity.same_scenario', true)
            ->assertJsonPath('parity.same_action_types', true);
    }

    public function test_it_runs_shadow_compare_from_console(): void
    {
        $this->publishDefaultFlow();

        $this->artisan('whatsapp:flowmaker-shadow', [
            'wa_number' => '593999111222',
            'text' => 'hola',
        ])
            ->expectsOutputToContain('flow_tables')
            ->expectsOutputToContain('same_scenario')
            ->assertExitCode(0);
    }

    public function test_it_renders_flowmaker_ui_page(): void
    {
        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->get('/v2/whatsapp/flowmaker');

        $response
            ->assertOk()
            ->assertSee('Flowmaker y automatización')
            ->assertSee('Escenarios')
            ->assertSee('Configuración del escenario')
            ->assertSee('Secuencia de acciones')
            ->assertSee('Agregar acción')
            ->assertSee('Sesiones activas')
            ->assertSee('Publicar JSON')
            ->assertSee('Simular mensaje')
            ->assertSee('Comparar con legacy')
            ->assertSee('Fase 6 está lista para cierre')
            ->assertSee('Shadow runs recientes')
            ->assertSee('AI Agent preview');
    }

    public function test_it_previews_ai_agent_actions_and_logs_runs(): void
    {
        $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->postJson('/v2/whatsapp/api/flowmaker/publish', [
                'flow' => [
                    'name' => 'AI Agent preview',
                    'description' => 'Preview con KB',
                    'settings' => ['timezone' => 'America/Guayaquil'],
                    'scenarios' => [
                        [
                            'id' => 'faq_agenda',
                            'name' => 'FAQ Agenda',
                            'description' => 'Nodo IA',
                            'stage' => 'custom',
                            'conditions' => [
                                ['type' => 'message_contains', 'keywords' => ['cita', 'agendar']],
                            ],
                            'actions' => [
                                [
                                    'type' => 'ai_agent',
                                    'instructions' => 'Responder con grounding de agenda.',
                                    'kb_filters' => [
                                        'tipo_contenido' => 'faq',
                                        'audiencia' => 'paciente',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ])
            ->assertOk();

        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->getJson('/v2/whatsapp/api/flowmaker/simulate?wa_number=593999111222&text=agendar una cita');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('matched', true)
            ->assertJsonPath('scenario.id', 'faq_agenda')
            ->assertJsonPath('actions.0.type', 'ai_agent')
            ->assertJsonPath('actions.0.decision', 'respond')
            ->assertJsonPath('actions.0.fallback_used', false)
            ->assertJsonPath('actions.0.classification', 'scheduling')
            ->assertJsonPath('actions.0.tools.window_status.state', 'window_open')
            ->assertJsonPath('actions.0.tools.conversation_state.ownership_state', 'assigned')
            ->assertJsonPath('actions.0.tools.suggest_template.suggested.code', 'recordatorio_cita')
            ->assertJsonPath('actions.0.tools.search_patient.matches.0.hc_number', 'HC-001')
            ->assertJsonPath('actions.0.evaluation.grounding.status', 'strong')
            ->assertJsonPath('actions.0.evaluation.safety.status', 'safe');

        $this->assertDatabaseCount('whatsapp_ai_agent_runs', 1);
        $this->assertDatabaseHas('whatsapp_ai_agent_runs', [
            'scenario_id' => 'faq_agenda',
            'decision' => 'respond',
            'fallback_used' => 0,
        ]);

        $runs = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->getJson('/v2/whatsapp/api/flowmaker/ai-runs');

        $runs
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.scenario_id', 'faq_agenda')
            ->assertJsonPath('data.0.classification', 'scheduling')
            ->assertJsonPath('data.0.decision', 'respond');
    }

    public function test_it_falls_back_and_records_guardrail_reasons_for_low_confidence_ai_runs(): void
    {
        $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->postJson('/v2/whatsapp/api/flowmaker/publish', [
                'flow' => [
                    'name' => 'AI Agent guardrails',
                    'description' => 'Preview con fallback explícito',
                    'settings' => ['timezone' => 'America/Guayaquil'],
                    'scenarios' => [
                        [
                            'id' => 'ayuda_humana',
                            'name' => 'Ayuda humana',
                            'description' => 'Nodo IA con guardrails',
                            'stage' => 'custom',
                            'conditions' => [
                                ['type' => 'message_contains', 'keywords' => ['ayuda']],
                            ],
                            'actions' => [
                                [
                                    'type' => 'ai_agent',
                                    'instructions' => 'Responder solo si hay grounding.',
                                    'kb_filters' => [
                                        'tipo_contenido' => 'consentimiento',
                                    ],
                                    'handoff' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ])
            ->assertOk();

        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->getJson('/v2/whatsapp/api/flowmaker/simulate?wa_number=593999111222&text=ayuda');

        $response
            ->assertOk()
            ->assertJsonPath('actions.0.type', 'ai_agent')
            ->assertJsonPath('actions.0.decision', 'fallback_handoff')
            ->assertJsonPath('actions.0.fallback_used', true)
            ->assertJsonPath('actions.0.suggested_handoff', true)
            ->assertJsonPath('actions.0.handoff_reasons.0', 'node_requested_handoff')
            ->assertJsonPath('actions.0.evaluation.grounding.status', 'weak')
            ->assertJsonPath('handoff_requested', true);

        $run = \DB::table('whatsapp_ai_agent_runs')->first();
        $this->assertNotNull($run);
        $this->assertSame('fallback_handoff', $run->decision);
    }

    public function test_it_lists_recent_shadow_runs(): void
    {
        \DB::table('whatsapp_flow_shadow_runs')->insert([
            'source' => 'webhook_dry_run',
            'wa_number' => '593999111222',
            'conversation_id' => 1,
            'inbound_message_id' => 'wamid.shadow.1',
            'message_text' => 'hola',
            'same_match' => 1,
            'same_scenario' => 0,
            'same_handoff' => 1,
            'same_action_types' => 1,
            'input_payload' => json_encode(['wa_number' => '593999111222', 'text' => 'hola']),
            'parity_payload' => json_encode([
                'same_match' => true,
                'same_scenario' => false,
                'same_handoff' => true,
                'same_action_types' => true,
                'mismatch_reasons' => ['scenario'],
                'dry_run' => true,
                'execution_preview' => [
                    'mode' => 'dry_run',
                    'action_types' => ['send_message'],
                ],
            ]),
            'laravel_payload' => json_encode([
                'scenario' => ['id' => 'primer_contacto'],
                'execution_preview' => [
                    'mode' => 'dry_run',
                    'action_types' => ['send_message'],
                ],
            ]),
            'legacy_payload' => json_encode(['scenario' => ['id' => 'menu_principal']]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->getJson('/v2/whatsapp/api/flowmaker/shadow-runs?mismatches_only=1');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.inbound_message_id', 'wamid.shadow.1')
            ->assertJsonPath('data.0.parity.same_scenario', false)
            ->assertJsonPath('data.0.parity.mismatch_reasons.0', 'scenario')
            ->assertJsonPath('data.0.execution_mode', 'dry_run')
            ->assertJsonPath('data.0.execution_preview.action_types.0', 'send_message')
            ->assertJsonPath('data.0.laravel_scenario', 'primer_contacto')
            ->assertJsonPath('data.0.legacy_scenario', 'menu_principal');
    }

    public function test_it_summarizes_shadow_runtime_mismatches(): void
    {
        \DB::table('whatsapp_flow_shadow_runs')->insert([
            [
                'source' => 'webhook_dry_run',
                'wa_number' => '593999111222',
                'conversation_id' => 1,
                'inbound_message_id' => 'wamid.shadow.1',
                'message_text' => 'hola',
                'same_match' => 1,
                'same_scenario' => 0,
                'same_handoff' => 1,
                'same_action_types' => 1,
                'input_payload' => json_encode(['wa_number' => '593999111222', 'text' => 'hola']),
                'parity_payload' => json_encode([
                    'same_match' => true,
                    'same_scenario' => false,
                    'same_handoff' => true,
                    'same_action_types' => true,
                    'mismatch_reasons' => ['scenario'],
                    'dry_run' => true,
                    'execution_preview' => ['mode' => 'dry_run'],
                ]),
                'laravel_payload' => json_encode(['scenario' => ['id' => 'primer_contacto']]),
                'legacy_payload' => json_encode(['scenario' => ['id' => 'menu_principal']]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'source' => 'webhook_dry_run',
                'wa_number' => '593999111333',
                'conversation_id' => 2,
                'inbound_message_id' => 'wamid.shadow.2',
                'message_text' => 'cita',
                'same_match' => 0,
                'same_scenario' => 0,
                'same_handoff' => 1,
                'same_action_types' => 0,
                'input_payload' => json_encode(['wa_number' => '593999111333', 'text' => 'cita']),
                'parity_payload' => json_encode([
                    'same_match' => false,
                    'same_scenario' => false,
                    'same_handoff' => true,
                    'same_action_types' => false,
                    'mismatch_reasons' => ['match', 'scenario', 'action_types'],
                    'dry_run' => true,
                    'execution_preview' => ['mode' => 'dry_run'],
                ]),
                'laravel_payload' => json_encode(['scenario' => ['id' => 'primer_contacto']]),
                'legacy_payload' => json_encode(['scenario' => ['id' => 'agendar_cita']]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->getJson('/v2/whatsapp/api/flowmaker/shadow-summary?limit=100');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.total_runs', 2)
            ->assertJsonPath('data.mismatch_runs', 2)
            ->assertJsonPath('data.dry_run_runs', 2)
            ->assertJsonPath('data.top_mismatch_reasons.0.reason', 'scenario')
            ->assertJsonPath('data.top_mismatch_reasons.0.count', 2);
    }

    public function test_it_evaluates_phase_6_readiness(): void
    {
        \DB::table('whatsapp_flow_shadow_runs')->insert([
            'source' => 'webhook_dry_run',
            'wa_number' => '593999111222',
            'conversation_id' => 1,
            'inbound_message_id' => 'wamid.shadow.ready',
            'message_text' => 'hola',
            'same_match' => 1,
            'same_scenario' => 1,
            'same_handoff' => 1,
            'same_action_types' => 1,
            'input_payload' => json_encode(['wa_number' => '593999111222', 'text' => 'hola']),
            'parity_payload' => json_encode([
                'same_match' => true,
                'same_scenario' => true,
                'same_handoff' => true,
                'same_action_types' => true,
                'mismatch_reasons' => [],
                'dry_run' => true,
                'execution_preview' => ['mode' => 'dry_run'],
            ]),
            'laravel_payload' => json_encode(['scenario' => ['id' => 'primer_contacto']]),
            'legacy_payload' => json_encode(['scenario' => ['id' => 'primer_contacto']]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->getJson('/v2/whatsapp/api/flowmaker/readiness?limit=100');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.ready_for_phase_7', false)
            ->assertJsonPath('data.blocking_checks.0', 'minimum_shadow_runs')
            ->assertJsonPath('data.checks.1.key', 'dry_run_enabled')
            ->assertJsonPath('data.checks.1.passed', true);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultFlowPayload(): array
    {
        return [
            'flow' => [
                'name' => 'Flow principal',
                'description' => 'Publicación de prueba',
                'settings' => ['timezone' => 'America/Guayaquil'],
                'scenarios' => [
                    [
                        'id' => 'primer_contacto',
                        'name' => 'Primer contacto',
                        'description' => 'Saludo',
                        'stage' => 'arrival',
                        'intercept_menu' => true,
                        'actions' => [
                            [
                                'type' => 'send_message',
                                'message' => [
                                    'type' => 'text',
                                    'body' => 'Hola desde Laravel',
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => 'menu',
                        'name' => 'Menu',
                        'description' => 'Opciones',
                        'stage' => 'menu',
                        'actions' => [
                            [
                                'type' => 'send_buttons',
                                'message' => [
                                    'type' => 'buttons',
                                    'body' => 'Elige una opción',
                                    'buttons' => [
                                        ['id' => '1', 'title' => 'Uno'],
                                        ['id' => '2', 'title' => 'Dos'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function publishDefaultFlow(): void
    {
        $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->postJson('/v2/whatsapp/api/flowmaker/publish', $this->defaultFlowPayload())
            ->assertOk();
    }
}

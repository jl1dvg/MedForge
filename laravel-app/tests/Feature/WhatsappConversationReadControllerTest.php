<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappConversationReadControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('whatsapp_sigcenter_bookings');
        Schema::dropIfExists('whatsapp_handoff_events');
        Schema::dropIfExists('whatsapp_handoffs');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_conversations');

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('permissions')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username');
            $table->string('first_name')->default('');
            $table->string('middle_name')->default('');
            $table->string('last_name')->default('');
            $table->string('second_last_name')->default('');
            $table->timestamp('birth_date')->nullable();
            $table->string('password')->default('');
            $table->string('email')->default('');
            $table->string('nombre')->default('');
            $table->string('cedula')->default('');
            $table->string('registro')->default('');
            $table->string('sede')->default('');
            $table->string('especialidad')->default('');
            $table->text('permisos')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->boolean('whatsapp_notify')->default(false);
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
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by_user_id')->nullable();
            $table->string('close_reason', 64)->nullable();
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
            $table->string('status', 24)->default('queued');
            $table->string('priority', 24)->default('normal');
            $table->string('topic', 191)->nullable();
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

        Schema::create('whatsapp_sigcenter_bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('status', 32)->default('created');
            $table->timestamps();
        });

        config()->set('whatsapp.migration.enabled', true);
        config()->set('whatsapp.migration.api.read_enabled', true);
        config()->set('whatsapp.migration.compare_with_legacy', true);

        \DB::table('roles')->insert([
            ['id' => 5, 'name' => 'Call Center', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'name' => 'Supervisor', 'created_at' => now(), 'updated_at' => now()],
        ]);

        \DB::table('users')->insert([
            [
                'id' => 7,
                'username' => 'agent.demo',
                'password' => bcrypt('secret'),
                'email' => 'agent@example.com',
                'nombre' => 'Agente Demo',
                'cedula' => '7',
                'registro' => 'RG7',
                'sede' => 'Matriz',
                'especialidad' => 'NA',
                'permisos' => null,
                'role_id' => 5,
            ],
            [
                'id' => 8,
                'username' => 'sup.demo',
                'password' => bcrypt('secret'),
                'email' => 'sup@example.com',
                'nombre' => 'Supervisor Demo',
                'cedula' => '8',
                'registro' => 'RG8',
                'sede' => 'Matriz',
                'especialidad' => 'NA',
                'permisos' => json_encode(['whatsapp.chat.supervise', 'whatsapp.manage', 'whatsapp.chat.view']),
                'role_id' => 6,
            ],
        ]);
    }

    public function test_it_lists_conversations_in_legacy_like_contract(): void
    {
        \DB::table('whatsapp_conversations')->insert([
            'wa_number' => '593999111222',
            'display_name' => 'Paciente Demo',
            'patient_hc_number' => 'HC-001',
            'patient_full_name' => 'Paciente Demo Completo',
            'last_message_preview' => 'Hola',
            'last_message_direction' => 'inbound',
            'last_message_type' => 'text',
            'needs_human' => 1,
            'unread_count' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withoutMiddleware()->getJson('/v2/whatsapp/api/conversations');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('meta.compare_with_legacy', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.wa_number', '593999111222')
            ->assertJsonPath('data.0.source', 'laravel-v2');
    }

    public function test_it_returns_conversation_detail_with_messages(): void
    {
        \DB::table('whatsapp_conversations')->insert([
            'id' => 10,
            'wa_number' => '593999111222',
            'display_name' => 'Paciente Demo',
            'patient_hc_number' => 'HC-001',
            'patient_full_name' => 'Paciente Demo Completo',
            'last_message_preview' => 'Hola',
            'last_message_direction' => 'outbound',
            'last_message_type' => 'text',
            'needs_human' => 0,
            'unread_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_messages')->insert([
            [
                'conversation_id' => 10,
                'direction' => 'inbound',
                'message_type' => 'text',
                'body' => 'Hola',
                'status' => 'received',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'conversation_id' => 10,
                'direction' => 'outbound',
                'message_type' => 'text',
                'body' => 'Buenos días',
                'status' => 'sent',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->withoutMiddleware()->getJson('/v2/whatsapp/api/conversations/10');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.id', 10)
            ->assertJsonCount(2, 'data.messages')
            ->assertJsonPath('data.messages.0.direction', 'inbound')
            ->assertJsonPath('data.messages.1.direction', 'outbound');

        $this->assertDatabaseHas('whatsapp_conversations', [
            'id' => 10,
            'unread_count' => 0,
        ]);
    }

    public function test_it_returns_trail_timestamp_label_in_guayaquil_time(): void
    {
        config()->set('app.timezone', 'UTC');

        \DB::table('whatsapp_conversations')->insert([
            'id' => 12,
            'wa_number' => '593999111214',
            'display_name' => 'Paciente Trail',
            'last_message_preview' => 'Hola',
            'last_message_direction' => 'inbound',
            'last_message_type' => 'text',
            'created_at' => '2026-05-26 20:14:06',
            'updated_at' => '2026-05-26 20:14:06',
        ]);

        $response = $this->withoutMiddleware()->getJson('/v2/whatsapp/api/conversations/12/trail');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.event_label', 'Conversación iniciada')
            ->assertJsonPath('data.0.created_at', '2026-05-26T20:14:06.000000Z')
            ->assertJsonPath('data.0.created_at_label', '26/05/2026 15:14');
    }

    public function test_it_filters_conversations_and_returns_tab_counts(): void
    {
        \DB::table('whatsapp_conversations')->insert([
            [
                'id' => 21,
                'wa_number' => '593999111221',
                'display_name' => 'Pendiente Uno',
                'needs_human' => 1,
                'assigned_user_id' => null,
                'unread_count' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 22,
                'wa_number' => '593999111222',
                'display_name' => 'Resuelto Dos',
                'needs_human' => 0,
                'assigned_user_id' => null,
                'unread_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        \DB::table('whatsapp_messages')->insert([
            [
                'conversation_id' => 21,
                'direction' => 'inbound',
                'message_type' => 'text',
                'body' => 'Hola reciente',
                'message_timestamp' => now()->subHours(2),
                'created_at' => now()->subHours(2),
                'updated_at' => now()->subHours(2),
            ],
            [
                'conversation_id' => 22,
                'direction' => 'inbound',
                'message_type' => 'text',
                'body' => 'Hola viejo',
                'message_timestamp' => now()->subDays(2),
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ],
        ]);

        $response = $this->withoutMiddleware()->getJson('/v2/whatsapp/api/conversations?filter=handoff');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 21)
            ->assertJsonPath('meta.tab_counts.all', 2)
            ->assertJsonPath('meta.tab_counts.unread', 1)
            ->assertJsonPath('meta.tab_counts.handoff', 1)
            ->assertJsonPath('meta.tab_counts.window_open', 1)
            ->assertJsonPath('meta.tab_counts.needs_template', 0)
            ->assertJsonPath('meta.tab_counts.resolved', 1);
    }

    public function test_it_exposes_operational_statuses_priority_and_new_filters(): void
    {
        $now = now();

        \DB::table('whatsapp_conversations')->insert([
            [
                'id' => 51,
                'wa_number' => '593999111251',
                'display_name' => 'Sin Agente',
                'last_message_direction' => 'inbound',
                'last_message_type' => 'text',
                'last_message_preview' => 'Necesito ayuda',
                'last_message_at' => $now,
                'needs_human' => 1,
                'assigned_user_id' => null,
                'unread_count' => 3,
                'closed_at' => null,
                'close_reason' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 52,
                'wa_number' => '593999111252',
                'display_name' => 'En Gestion',
                'last_message_direction' => 'inbound',
                'last_message_type' => 'text',
                'last_message_preview' => 'Listo',
                'last_message_at' => $now->subMinute(),
                'needs_human' => 1,
                'assigned_user_id' => 7,
                'unread_count' => 1,
                'closed_at' => null,
                'close_reason' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 53,
                'wa_number' => '593999111253',
                'display_name' => 'Esperando',
                'last_message_direction' => 'outbound',
                'last_message_type' => 'text',
                'last_message_preview' => 'Quedo atento',
                'last_message_at' => $now->subMinutes(2),
                'needs_human' => 1,
                'assigned_user_id' => 7,
                'unread_count' => 0,
                'closed_at' => null,
                'close_reason' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 54,
                'wa_number' => '593999111254',
                'display_name' => 'Resuelto',
                'last_message_direction' => 'outbound',
                'last_message_type' => null,
                'last_message_preview' => null,
                'last_message_at' => $now->subMinutes(3),
                'needs_human' => 0,
                'assigned_user_id' => null,
                'unread_count' => 0,
                'closed_at' => $now,
                'close_reason' => 'resolved',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 55,
                'wa_number' => '593999111255',
                'display_name' => 'Seguimiento',
                'last_message_direction' => 'outbound',
                'last_message_type' => null,
                'last_message_preview' => null,
                'last_message_at' => $now->subMinutes(4),
                'needs_human' => 0,
                'assigned_user_id' => null,
                'unread_count' => 0,
                'closed_at' => $now,
                'close_reason' => 'followup_closed',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 56,
                'wa_number' => '593999111256',
                'display_name' => 'Agendado',
                'last_message_direction' => 'outbound',
                'last_message_type' => null,
                'last_message_preview' => null,
                'last_message_at' => $now->subMinutes(5),
                'needs_human' => 0,
                'assigned_user_id' => null,
                'unread_count' => 0,
                'closed_at' => null,
                'close_reason' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        \DB::table('whatsapp_sigcenter_bookings')->insert([
            'conversation_id' => 56,
            'status' => 'created',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $requiresAttention = $this->withoutMiddleware()->getJson('/v2/whatsapp/api/conversations?filter=requires_attention');
        $inProgress = $this->withoutMiddleware()->getJson('/v2/whatsapp/api/conversations?filter=in_progress');
        $waitingPatient = $this->withoutMiddleware()->getJson('/v2/whatsapp/api/conversations?filter=waiting_patient');
        $scheduled = $this->withoutMiddleware()->getJson('/v2/whatsapp/api/conversations?filter=scheduled');
        $closed = $this->withoutMiddleware()->getJson('/v2/whatsapp/api/conversations?filter=closed');

        $requiresAttention
            ->assertOk()
            ->assertJsonPath('data.0.id', 51)
            ->assertJsonPath('data.0.operational_status', 'requires_attention')
            ->assertJsonPath('data.0.operational_status_label', 'Requiere atención')
            ->assertJsonPath('data.0.priority_level', 'critical')
            ->assertJsonPath('data.0.last_message_actor_label', 'Paciente')
            ->assertJsonPath('meta.tab_counts.requires_attention', 1)
            ->assertJsonPath('meta.tab_counts.in_progress', 1)
            ->assertJsonPath('meta.tab_counts.waiting_patient', 1)
            ->assertJsonPath('meta.tab_counts.scheduled', 1)
            ->assertJsonPath('meta.tab_counts.closed', 2);

        $inProgress
            ->assertOk()
            ->assertJsonPath('data.0.id', 52)
            ->assertJsonPath('data.0.operational_status', 'in_progress');

        $waitingPatient
            ->assertOk()
            ->assertJsonPath('data.0.id', 53)
            ->assertJsonPath('data.0.operational_status', 'waiting_patient');

        $scheduled
            ->assertOk()
            ->assertJsonPath('data.0.id', 56)
            ->assertJsonPath('data.0.operational_status', 'scheduled');

        $closed
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_it_filters_by_window_open_and_needs_template(): void
    {
        \DB::table('whatsapp_conversations')->insert([
            [
                'id' => 41,
                'wa_number' => '593999111241',
                'display_name' => 'Ventana abierta',
                'needs_human' => 1,
                'assigned_user_id' => 7,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 42,
                'wa_number' => '593999111242',
                'display_name' => 'Necesita plantilla',
                'needs_human' => 1,
                'assigned_user_id' => 7,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        \DB::table('whatsapp_messages')->insert([
            [
                'conversation_id' => 41,
                'direction' => 'inbound',
                'message_type' => 'text',
                'body' => 'Hola reciente',
                'message_timestamp' => now()->subHours(3),
                'created_at' => now()->subHours(3),
                'updated_at' => now()->subHours(3),
            ],
            [
                'conversation_id' => 42,
                'direction' => 'inbound',
                'message_type' => 'text',
                'body' => 'Hola antiguo',
                'message_timestamp' => now()->subDays(3),
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3),
            ],
        ]);

        $windowOpen = $this->withoutMiddleware()->getJson('/v2/whatsapp/api/conversations?filter=window_open');
        $needsTemplate = $this->withoutMiddleware()->getJson('/v2/whatsapp/api/conversations?filter=needs_template');

        $windowOpen
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 41)
            ->assertJsonPath('data.0.messaging_window_state', 'window_open')
            ->assertJsonPath('data.0.can_send_freeform', true);

        $needsTemplate
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 42)
            ->assertJsonPath('data.0.messaging_window_state', 'needs_template')
            ->assertJsonPath('data.0.can_send_freeform', false);
    }

    public function test_it_exposes_ownership_labels_and_supervisor_filters(): void
    {
        $this->actingAs(\App\Models\User::query()->findOrFail(8));

        \DB::table('whatsapp_conversations')->insert([
            [
                'id' => 31,
                'wa_number' => '593999111231',
                'display_name' => 'Paciente Uno',
                'needs_human' => 1,
                'assigned_user_id' => 7,
                'handoff_role_id' => 5,
                'unread_count' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 32,
                'wa_number' => '593999111232',
                'display_name' => 'Paciente Dos',
                'needs_human' => 1,
                'assigned_user_id' => null,
                'handoff_role_id' => 6,
                'unread_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->withoutMiddleware()->getJson('/v2/whatsapp/api/conversations?agent_id=7&role_id=5');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 31)
            ->assertJsonPath('data.0.assigned_user_name', 'Agente Demo')
            ->assertJsonPath('data.0.assigned_role_name', 'Call Center')
            ->assertJsonPath('data.0.ownership_label', 'Asignado a Agente Demo')
            ->assertJsonPath('meta.agent_id', 7)
            ->assertJsonPath('meta.role_id', 5);
    }

    public function test_hot_opportunities_returns_bucket_structure(): void
    {
        $now = now();

        // HOT: unassigned, recent handoff → high priority (unread + no agent = critical)
        \DB::table('whatsapp_conversations')->insert([
            'id' => 61,
            'wa_number' => '593999111261',
            'display_name' => 'Paciente HOT',
            'needs_human' => 1,
            'assigned_user_id' => null,
            'unread_count' => 5,
            'handoff_requested_at' => $now->copy()->subMinutes(30),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        \DB::table('whatsapp_messages')->insert([
            'conversation_id' => 61,
            'direction' => 'inbound',
            'message_type' => 'text',
            'body' => 'Hola reciente',
            'message_timestamp' => $now->copy()->subMinutes(30),
            'created_at' => $now->copy()->subMinutes(30),
            'updated_at' => $now->copy()->subMinutes(30),
        ]);
        \DB::table('whatsapp_handoffs')->insert([
            'conversation_id' => 61,
            'wa_number' => '593999111261',
            'status' => 'queued',
            'priority' => 'high',
            'topic' => 'captacion_agendar',
            'queued_at' => $now->copy()->subMinutes(30),
            'created_at' => $now->copy()->subMinutes(30),
            'updated_at' => $now->copy()->subMinutes(30),
        ]);

        // RESCUE: 24h-7d, window still open
        \DB::table('whatsapp_conversations')->insert([
            'id' => 62,
            'wa_number' => '593999111262',
            'display_name' => 'Paciente BACKLOG',
            'needs_human' => 1,
            'assigned_user_id' => null,
            'unread_count' => 0,
            'handoff_requested_at' => $now->copy()->subHours(30),
            'created_at' => $now->copy()->subHours(30),
            'updated_at' => $now->copy()->subHours(30),
        ]);
        \DB::table('whatsapp_messages')->insert([
            'conversation_id' => 62,
            'direction' => 'inbound',
            'message_type' => 'text',
            'body' => 'Mensaje reciente aunque viejo',
            'message_timestamp' => $now->copy()->subHours(2),
            'created_at' => $now->copy()->subHours(2),
            'updated_at' => $now->copy()->subHours(2),
        ]);
        \DB::table('whatsapp_handoffs')->insert([
            'conversation_id' => 62,
            'wa_number' => '593999111262',
            'status' => 'queued',
            'priority' => 'high',
            'topic' => 'faq_escalada',
            'queued_at' => $now->copy()->subHours(30),
            'created_at' => $now->copy()->subHours(30),
            'updated_at' => $now->copy()->subHours(30),
        ]);

        // LOST: >30d
        \DB::table('whatsapp_conversations')->insert([
            'id' => 63,
            'wa_number' => '593999111263',
            'display_name' => 'Paciente LOST',
            'needs_human' => 1,
            'assigned_user_id' => null,
            'unread_count' => 0,
            'handoff_requested_at' => $now->copy()->subDays(35),
            'created_at' => $now->copy()->subDays(35),
            'updated_at' => $now->copy()->subDays(35),
        ]);
        \DB::table('whatsapp_messages')->insert([
            'conversation_id' => 63,
            'direction' => 'inbound',
            'message_type' => 'text',
            'body' => 'Mensaje muy viejo',
            'message_timestamp' => $now->copy()->subDays(35),
            'created_at' => $now->copy()->subDays(35),
            'updated_at' => $now->copy()->subDays(35),
        ]);
        \DB::table('whatsapp_handoffs')->insert([
            'conversation_id' => 63,
            'wa_number' => '593999111263',
            'status' => 'queued',
            'priority' => 'high',
            'topic' => 'operacion_reagenda',
            'queued_at' => $now->copy()->subDays(35),
            'created_at' => $now->copy()->subDays(35),
            'updated_at' => $now->copy()->subDays(35),
        ]);

        $response = $this->withoutMiddleware()->getJson('/v2/whatsapp/api/hot-opportunities');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure([
                'data' => [
                    'hot_opportunities',
                    'rescue_opportunities',
                    'historical_backlog',
                    'lost_opportunities',
                    'counts' => [
                        'executive_operational',
                        'historical_debt',
                        'hot',
                        'rescue',
                        'backlog',
                        'lost',
                    ],
                    'agents',
                    'reminders',
                ],
            ]);

        $data = $response->json('data');

        // Conv 61 → HOT (unassigned + unread + recent = critical priority)
        $hotIds = array_column($data['hot_opportunities'], 'id');
        $this->assertContains(61, $hotIds, 'Conv 61 debe estar en hot_opportunities');

        // Conv 62 → RESCUE (24h-7d)
        $rescueIds = array_column($data['rescue_opportunities'], 'id');
        $this->assertContains(62, $rescueIds, 'Conv 62 debe estar en rescue_opportunities');

        // Conv 63 → LOST (24h+ unassigned, window closed/needs_template)
        $lostIds = array_column($data['lost_opportunities'], 'id');
        $this->assertContains(63, $lostIds, 'Conv 63 debe estar en lost_opportunities');

        // counts consistency
        $counts = $data['counts'];
        $this->assertSame(
            $counts['hot_open'] + $counts['hot_needs_template'] + $counts['rescue'],
            $counts['executive_operational'],
            'executive_operational debe ser hot_open + hot_needs_template + rescue'
        );
        $this->assertSame(
            $counts['backlog'] + $counts['lost'],
            $counts['historical_debt'],
            'historical_debt debe ser backlog + lost'
        );
    }

    public function test_it_exposes_queue_role_name_in_ownership_label(): void
    {
        \DB::table('whatsapp_conversations')->insert([
            'id' => 33,
            'wa_number' => '593999111233',
            'display_name' => 'Paciente Cola',
            'needs_human' => 1,
            'assigned_user_id' => null,
            'handoff_role_id' => 5,
            'unread_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withoutMiddleware()->getJson('/v2/whatsapp/api/conversations?role_id=5');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', 33)
            ->assertJsonPath('data.0.handoff_role_name', 'Call Center')
            ->assertJsonPath('data.0.ownership_label', 'En cola · Call Center');
    }

    public function test_hot_opportunities_api_returns_operational_buckets(): void
    {
        Carbon::setTestNow('2026-06-24 12:00:00');

        $this->insertQueuedConversation(71, '593999111271', 'Paciente Hot', now()->subHours(2), [
            'patient_hc_number' => 'HC-071',
            'topic' => 'captacion_agendar',
        ]);
        $this->insertQueuedConversation(76, '593999111276', 'Paciente Hot Plantilla', now()->subHours(2), [
            'patient_hc_number' => 'HC-076',
            'topic' => 'captacion_agendar',
            'latest_inbound_at' => now()->subHours(25),
        ]);
        $this->insertQueuedConversation(72, '593999111272', 'Paciente Backlog', now()->subDays(4), [
            'topic' => 'agenda_sin_disponibilidad',
        ]);
        $this->insertQueuedConversation(73, '593999111273', 'Paciente Deuda', now()->subDays(10), [
            'topic' => 'operacion_reagenda',
        ]);
        $this->insertQueuedConversation(74, '593999111274', 'Paciente Agendado', now()->subHours(1), [
            'patient_hc_number' => 'HC-074',
            'topic' => 'captacion_agendar',
        ]);
        $this->insertQueuedConversation(75, '593999111275', 'Paciente Perdido', now()->subDays(39), [
            'topic' => 'operacion_reagenda',
        ]);

        \DB::table('whatsapp_sigcenter_bookings')->insert([
            'conversation_id' => 74,
            'status' => 'created',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withoutMiddleware()->getJson('/v2/whatsapp/api/hot-opportunities');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.counts.hot_open', 1)
            ->assertJsonPath('data.counts.hot_needs_template', 1)
            ->assertJsonPath('data.counts.hot_opportunities', 1)
            ->assertJsonPath('data.counts.rescue_opportunities', 1)
            ->assertJsonPath('data.counts.historical_backlog', 1)
            ->assertJsonPath('data.counts.lost_opportunities', 1)
            ->assertJsonPath('data.counts.executive_operational', 3)
            ->assertJsonPath('data.counts.historical_debt', 2)
            ->assertJsonPath('data.kpi_scope.executive.0', 'hot_open')
            ->assertJsonPath('data.kpi_scope.executive.1', 'hot_needs_template')
            ->assertJsonPath('data.kpi_scope.executive.2', 'rescue_opportunities')
            ->assertJsonPath('data.kpi_scope.historical_debt.0', 'historical_backlog')
            ->assertJsonPath('data.kpi_scope.historical_debt.1', 'lost_opportunities')
            ->assertJsonPath('data.hot_open.0.id', 71)
            ->assertJsonPath('data.hot_needs_template.0.id', 76)
            ->assertJsonPath('data.hot_opportunities.0.id', 71)
            ->assertJsonPath('data.rescue_opportunities.0.id', 72)
            ->assertJsonPath('data.historical_backlog.0.id', 73)
            ->assertJsonPath('data.lost_opportunities.0.id', 75)
            ->assertJsonPath('data.conversations.0.id', 71);

        $this->withoutMiddleware()
            ->getJson('/v2/whatsapp/api/conversations?filter=hot_opportunities')
            ->assertOk()
            ->assertJsonPath('data.0.id', 71)
            ->assertJsonPath('meta.tab_counts.hot_opportunities', 1);

        $this->withoutMiddleware()
            ->getJson('/v2/whatsapp/api/conversations?filter=hot_needs_template')
            ->assertOk()
            ->assertJsonPath('data.0.id', 76)
            ->assertJsonPath('meta.tab_counts.hot_needs_template', 1);

        $this->withoutMiddleware()
            ->getJson('/v2/whatsapp/api/conversations?filter=rescue_opportunities')
            ->assertOk()
            ->assertJsonPath('data.0.id', 72)
            ->assertJsonPath('meta.tab_counts.rescue_opportunities', 1);

        $this->withoutMiddleware()
            ->getJson('/v2/whatsapp/api/conversations?filter=historical_backlog')
            ->assertOk()
            ->assertJsonPath('data.0.id', 73)
            ->assertJsonPath('meta.tab_counts.historical_backlog', 1);

        $this->withoutMiddleware()
            ->getJson('/v2/whatsapp/api/conversations?filter=lost_opportunities')
            ->assertOk()
            ->assertJsonPath('data.0.id', 75)
            ->assertJsonPath('meta.tab_counts.lost_opportunities', 1);

        Carbon::setTestNow();
    }

    /**
     * @param array{patient_hc_number?: string, topic?: string, latest_inbound_at?: Carbon} $options
     */
    private function insertQueuedConversation(int $id, string $waNumber, string $name, Carbon $queuedAt, array $options = []): void
    {
        $latestInboundAt = $options['latest_inbound_at'] ?? $queuedAt;

        \DB::table('whatsapp_conversations')->insert([
            'id' => $id,
            'wa_number' => $waNumber,
            'display_name' => $name,
            'patient_hc_number' => $options['patient_hc_number'] ?? null,
            'last_message_direction' => 'inbound',
            'last_message_type' => 'text',
            'last_message_preview' => 'Necesito agendar',
            'last_message_at' => $queuedAt,
            'needs_human' => 1,
            'assigned_user_id' => null,
            'handoff_requested_at' => $queuedAt,
            'unread_count' => 1,
            'created_at' => $queuedAt,
            'updated_at' => $queuedAt,
        ]);

        \DB::table('whatsapp_messages')->insert([
            'conversation_id' => $id,
            'direction' => 'inbound',
            'message_type' => 'text',
            'body' => 'Necesito agendar',
            'message_timestamp' => $latestInboundAt,
            'created_at' => $latestInboundAt,
            'updated_at' => $latestInboundAt,
        ]);

        \DB::table('whatsapp_handoffs')->insert([
            'conversation_id' => $id,
            'wa_number' => $waNumber,
            'status' => 'queued',
            'priority' => 'high',
            'topic' => $options['topic'] ?? null,
            'queued_at' => $queuedAt,
            'created_at' => $queuedAt,
            'updated_at' => $queuedAt,
        ]);
    }
}

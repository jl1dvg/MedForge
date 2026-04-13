<?php

namespace Tests\Feature;

use App\Http\Middleware\LegacySessionBridge;
use App\Http\Middleware\RequireLegacyPermission;
use App\Http\Middleware\RequireLegacySession;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappConversationOpsControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('whatsapp_handoff_events');
        Schema::dropIfExists('whatsapp_handoffs');
        Schema::dropIfExists('whatsapp_conversations');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('whatsapp_agent_presence');

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

        Schema::create('whatsapp_agent_presence', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->primary();
            $table->string('status', 24)->default('available');
            $table->timestamp('updated_at')->nullable();
        });

        \DB::table('users')->insert([
            [
                'id' => 10,
                'username' => 'agent.one',
                'password' => bcrypt('secret'),
                'email' => 'agent1@example.com',
                'nombre' => 'Agente Uno',
                'cedula' => '1',
                'registro' => 'R1',
                'sede' => 'Matriz',
                'especialidad' => 'NA',
                'permisos' => json_encode(['whatsapp.chat.view', 'whatsapp.chat.send', 'whatsapp.chat.assign']),
            ],
            [
                'id' => 11,
                'username' => 'agent.two',
                'password' => bcrypt('secret'),
                'email' => 'agent2@example.com',
                'nombre' => 'Agente Dos',
                'cedula' => '2',
                'registro' => 'R2',
                'sede' => 'Matriz',
                'especialidad' => 'NA',
                'permisos' => json_encode(['whatsapp.chat.view', 'whatsapp.chat.send']),
            ],
        ]);

        \DB::table('whatsapp_agent_presence')->insert([
            ['user_id' => 10, 'status' => 'available', 'updated_at' => now()],
            ['user_id' => 11, 'status' => 'away', 'updated_at' => now()],
        ]);

        config()->set('whatsapp.migration.enabled', true);
        config()->set('whatsapp.migration.api.write_enabled', true);
        config()->set('whatsapp.migration.api.read_enabled', true);
    }

    public function test_it_lists_agents_for_the_inbox(): void
    {
        $this->actingAs(User::query()->findOrFail(10));

        $this->withoutMiddleware([
            LegacySessionBridge::class,
            RequireLegacySession::class,
            RequireLegacyPermission::class,
        ])->getJson('/v2/whatsapp/api/agents')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Agente Dos');
    }

    public function test_it_returns_agent_workload_summary_for_supervisor(): void
    {
        \DB::table('users')->insert([
            'id' => 12,
            'username' => 'supervisor.ops',
            'password' => bcrypt('secret'),
            'email' => 'supervisor@example.com',
            'nombre' => 'Supervisor Ops',
            'cedula' => '12',
            'registro' => 'R12',
            'sede' => 'Matriz',
            'especialidad' => 'NA',
            'permisos' => json_encode(['whatsapp.chat.supervise', 'whatsapp.manage', 'whatsapp.chat.view']),
        ]);

        \DB::table('whatsapp_conversations')->insert([
            [
                'id' => 510,
                'wa_number' => '593999111510',
                'display_name' => 'Paciente A',
                'needs_human' => 1,
                'assigned_user_id' => 10,
                'unread_count' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 511,
                'wa_number' => '593999111511',
                'display_name' => 'Paciente B',
                'needs_human' => 1,
                'assigned_user_id' => 11,
                'unread_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 512,
                'wa_number' => '593999111512',
                'display_name' => 'Paciente Cola',
                'needs_human' => 1,
                'assigned_user_id' => null,
                'unread_count' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        \DB::table('whatsapp_handoffs')->insert([
            'id' => 910,
            'conversation_id' => 510,
            'wa_number' => '593999111510',
            'status' => 'assigned',
            'priority' => 'normal',
            'assigned_agent_id' => 10,
            'assigned_at' => now(),
            'assigned_until' => now()->addHour(),
            'queued_at' => now(),
            'last_activity_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::query()->findOrFail(12));

        $this->withoutMiddleware([
            LegacySessionBridge::class,
            RequireLegacySession::class,
            RequireLegacyPermission::class,
        ])->getJson('/v2/whatsapp/api/agents/summary')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.totals.queued_open_count', 1)
            ->assertJsonPath('data.totals.assigned_open_count', 2)
            ->assertJsonPath('data.totals.unread_open_count', 1)
            ->assertJsonPath('data.totals.expiring_soon_count', 1)
            ->assertJsonPath('data.agents.0.id', 10)
            ->assertJsonPath('data.agents.0.assigned_open_count', 1)
            ->assertJsonPath('data.agents.0.unread_open_count', 1);
    }

    public function test_it_assigns_a_conversation_to_the_current_agent(): void
    {
        \DB::table('whatsapp_conversations')->insert([
            'id' => 501,
            'wa_number' => '593999111222',
            'display_name' => 'Paciente Demo',
            'needs_human' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::query()->findOrFail(10));

        $this->withoutMiddleware([
            LegacySessionBridge::class,
            RequireLegacySession::class,
            RequireLegacyPermission::class,
        ])->postJson('/v2/whatsapp/api/conversations/501/assign', [])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.assigned_user_id', 10);

        $this->assertDatabaseHas('whatsapp_conversations', [
            'id' => 501,
            'assigned_user_id' => 10,
            'needs_human' => 1,
        ]);

        $this->assertDatabaseHas('whatsapp_handoffs', [
            'conversation_id' => 501,
            'assigned_agent_id' => 10,
            'status' => 'assigned',
        ]);
    }

    public function test_it_transfers_a_conversation_to_another_agent(): void
    {
        \DB::table('whatsapp_conversations')->insert([
            'id' => 502,
            'wa_number' => '593999111223',
            'display_name' => 'Paciente Transfer',
            'needs_human' => 1,
            'assigned_user_id' => 10,
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::query()->findOrFail(10));

        $this->withoutMiddleware([
            LegacySessionBridge::class,
            RequireLegacySession::class,
            RequireLegacyPermission::class,
        ])->postJson('/v2/whatsapp/api/conversations/502/transfer', [
            'user_id' => 11,
            'note' => 'Derivar a segundo nivel',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.assigned_user_id', 11);

        $this->assertDatabaseHas('whatsapp_conversations', [
            'id' => 502,
            'assigned_user_id' => 11,
            'handoff_notes' => 'Derivar a segundo nivel',
        ]);
    }

    public function test_it_closes_a_conversation(): void
    {
        \DB::table('whatsapp_conversations')->insert([
            'id' => 503,
            'wa_number' => '593999111224',
            'display_name' => 'Paciente Close',
            'needs_human' => 1,
            'assigned_user_id' => 10,
            'assigned_at' => now(),
            'unread_count' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_handoffs')->insert([
            'id' => 900,
            'conversation_id' => 503,
            'wa_number' => '593999111224',
            'status' => 'assigned',
            'priority' => 'normal',
            'assigned_agent_id' => 10,
            'assigned_at' => now(),
            'assigned_until' => now()->addDay(),
            'queued_at' => now(),
            'last_activity_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::query()->findOrFail(10));

        $this->withoutMiddleware([
            LegacySessionBridge::class,
            RequireLegacySession::class,
            RequireLegacyPermission::class,
        ])->postJson('/v2/whatsapp/api/conversations/503/close', [])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.needs_human', false);

        $this->assertDatabaseHas('whatsapp_conversations', [
            'id' => 503,
            'needs_human' => 0,
            'assigned_user_id' => null,
            'unread_count' => 0,
        ]);

        $this->assertDatabaseHas('whatsapp_handoffs', [
            'id' => 900,
            'status' => 'resolved',
        ]);
    }
}

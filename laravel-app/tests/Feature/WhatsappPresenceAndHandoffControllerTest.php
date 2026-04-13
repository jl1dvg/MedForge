<?php

namespace Tests\Feature;

use App\Http\Middleware\LegacySessionBridge;
use App\Http\Middleware\RequireLegacyPermission;
use App\Http\Middleware\RequireLegacySession;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappPresenceAndHandoffControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('whatsapp_handoff_events');
        Schema::dropIfExists('whatsapp_handoffs');
        Schema::dropIfExists('whatsapp_conversations');
        Schema::dropIfExists('whatsapp_agent_presence');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');

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

        \DB::table('roles')->insert([
            ['id' => 5, 'name' => 'Call Center', 'permissions' => null, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'name' => 'Supervisor', 'permissions' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        \DB::table('users')->insert([
            'id' => 30,
            'username' => 'supervisor.demo',
            'password' => bcrypt('secret'),
            'email' => 'sup@example.com',
            'nombre' => 'Supervisor Demo',
            'cedula' => '30',
            'registro' => 'RS',
            'sede' => 'Matriz',
            'especialidad' => 'NA',
            'permisos' => json_encode(['whatsapp.chat.supervise', 'whatsapp.chat.view', 'whatsapp.chat.send']),
        ]);

        config()->set('whatsapp.migration.enabled', true);
        config()->set('whatsapp.migration.api.read_enabled', true);
        config()->set('whatsapp.migration.api.write_enabled', true);
    }

    public function test_it_returns_and_updates_current_agent_presence(): void
    {
        $this->actingAs(User::query()->findOrFail(30));

        $this->withoutMiddleware([
            LegacySessionBridge::class,
            RequireLegacySession::class,
            RequireLegacyPermission::class,
        ])->getJson('/v2/whatsapp/api/presence')
            ->assertOk()
            ->assertJsonPath('data.status', 'available');

        $this->withoutMiddleware([
            LegacySessionBridge::class,
            RequireLegacySession::class,
            RequireLegacyPermission::class,
        ])->postJson('/v2/whatsapp/api/presence', [
            'status' => 'away',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'away');

        $this->assertDatabaseHas('whatsapp_agent_presence', [
            'user_id' => 30,
            'status' => 'away',
        ]);
    }

    public function test_it_requeues_expired_handoffs(): void
    {
        \DB::table('whatsapp_conversations')->insert([
            'id' => 700,
            'wa_number' => '593999111700',
            'display_name' => 'Paciente TTL',
            'needs_human' => 1,
            'assigned_user_id' => 30,
            'assigned_at' => now()->subDay(),
            'handoff_notes' => 'Pendiente seguimiento',
            'handoff_role_id' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_handoffs')->insert([
            'id' => 1700,
            'conversation_id' => 700,
            'wa_number' => '593999111700',
            'status' => 'assigned',
            'priority' => 'normal',
            'handoff_role_id' => 5,
            'assigned_agent_id' => 30,
            'assigned_at' => now()->subDay(),
            'assigned_until' => now()->subMinute(),
            'queued_at' => now()->subDay(),
            'last_activity_at' => now()->subMinute(),
            'notes' => 'Pendiente seguimiento',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::query()->findOrFail(30));

        $this->withoutMiddleware([
            LegacySessionBridge::class,
            RequireLegacySession::class,
            RequireLegacyPermission::class,
        ])->postJson('/v2/whatsapp/api/handoffs/requeue-expired', [])
            ->assertOk()
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.ids.0', 1700);

        $this->assertDatabaseHas('whatsapp_handoffs', [
            'id' => 1700,
            'status' => 'queued',
            'assigned_agent_id' => null,
        ]);

        $this->assertDatabaseHas('whatsapp_conversations', [
            'id' => 700,
            'assigned_user_id' => null,
            'needs_human' => 1,
            'handoff_role_id' => 5,
        ]);
    }

    public function test_it_sends_a_conversation_back_to_a_role_queue(): void
    {
        \DB::table('whatsapp_conversations')->insert([
            'id' => 701,
            'wa_number' => '593999111701',
            'display_name' => 'Paciente Cola Rol',
            'needs_human' => 1,
            'assigned_user_id' => 30,
            'assigned_at' => now()->subHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_handoffs')->insert([
            'id' => 1701,
            'conversation_id' => 701,
            'wa_number' => '593999111701',
            'status' => 'assigned',
            'priority' => 'normal',
            'assigned_agent_id' => 30,
            'assigned_at' => now()->subHour(),
            'assigned_until' => now()->addHour(),
            'queued_at' => now()->subHour(),
            'last_activity_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::query()->findOrFail(30));

        $this->withoutMiddleware([
            LegacySessionBridge::class,
            RequireLegacySession::class,
            RequireLegacyPermission::class,
        ])->postJson('/v2/whatsapp/api/conversations/701/queue-by-role', [
            'role_id' => 5,
            'note' => 'Derivar al call center',
        ])
            ->assertOk()
            ->assertJsonPath('data.assigned_user_id', null)
            ->assertJsonPath('data.handoff_role_id', 5)
            ->assertJsonPath('data.handoff_notes', 'Derivar al call center');

        $this->assertDatabaseHas('whatsapp_conversations', [
            'id' => 701,
            'assigned_user_id' => null,
            'handoff_role_id' => 5,
            'handoff_notes' => 'Derivar al call center',
        ]);

        $this->assertDatabaseHas('whatsapp_handoffs', [
            'id' => 1701,
            'status' => 'queued',
            'assigned_agent_id' => null,
            'handoff_role_id' => 5,
        ]);

        $this->assertDatabaseHas('whatsapp_handoff_events', [
            'handoff_id' => 1701,
            'event_type' => 'queued',
        ]);
    }
}

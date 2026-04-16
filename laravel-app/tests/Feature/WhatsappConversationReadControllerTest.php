<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappConversationReadControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
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
}

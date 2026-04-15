<?php

namespace Tests\Feature;

use App\Http\Middleware\LegacySessionBridge;
use App\Http\Middleware\RequireLegacyPermission;
use App\Http\Middleware\RequireLegacySession;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappProductivityControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('whatsapp_conversation_notes');
        Schema::dropIfExists('whatsapp_quick_replies');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_conversations');
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

        Schema::create('whatsapp_quick_replies', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 120);
            $table->string('shortcut', 64)->nullable();
            $table->text('body');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('whatsapp_conversation_notes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('author_user_id')->nullable();
            $table->text('body');
            $table->timestamps();
        });

        \DB::table('users')->insert([
            'id' => 71,
            'username' => 'agent.productivity',
            'password' => bcrypt('secret'),
            'email' => 'agent-productivity@example.com',
            'nombre' => 'Agente Productividad',
            'cedula' => '71',
            'registro' => 'R71',
            'sede' => 'Matriz',
            'especialidad' => 'NA',
            'permisos' => json_encode(['whatsapp.chat.view', 'whatsapp.chat.send']),
        ]);

        \DB::table('whatsapp_conversations')->insert([
            'id' => 971,
            'wa_number' => '593999111971',
            'display_name' => 'Paciente Productividad',
            'assigned_user_id' => 71,
            'needs_human' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        config()->set('whatsapp.migration.enabled', true);
        config()->set('whatsapp.migration.api.read_enabled', true);
        config()->set('whatsapp.migration.api.write_enabled', true);
        config()->set('whatsapp.migration.ui.enabled', true);

        $this->actingAs(User::query()->findOrFail(71));
    }

    public function test_it_creates_and_lists_quick_replies(): void
    {
        $create = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->postJson('/v2/whatsapp/api/quick-replies', [
                'title' => 'Saludo base',
                'shortcut' => 'saludo',
                'body' => 'Buenos días, con gusto te ayudo.',
            ]);

        $create
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.shortcut', 'saludo');

        $list = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->getJson('/v2/whatsapp/api/quick-replies');

        $list
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.title', 'Saludo base')
            ->assertJsonPath('data.0.body', 'Buenos días, con gusto te ayudo.');
    }

    public function test_it_adds_and_lists_internal_notes_for_a_conversation(): void
    {
        $create = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->postJson('/v2/whatsapp/api/conversations/971/notes', [
                'body' => 'Paciente pidió datos de facturación antes de la consulta.',
            ]);

        $create
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.conversation_id', 971);

        $list = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->getJson('/v2/whatsapp/api/conversations/971/notes');

        $list
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.body', 'Paciente pidió datos de facturación antes de la consulta.')
            ->assertJsonPath('data.0.author_name', 'Agente Productividad');
    }
}

<?php

namespace Tests\Feature;

use App\Http\Middleware\LegacySessionBridge;
use App\Http\Middleware\RequireLegacyPermission;
use App\Http\Middleware\RequireLegacySession;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappChatUiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_conversations');
        Schema::dropIfExists('whatsapp_quick_replies');
        Schema::dropIfExists('whatsapp_conversation_notes');
        Schema::dropIfExists('whatsapp_message_templates');

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
            $table->string('profile_photo')->nullable();
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

        Schema::create('whatsapp_message_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('template_code');
            $table->string('display_name');
            $table->string('language')->default('es');
            $table->string('category')->default('marketing');
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

        \DB::table('users')->insert([
            'id' => 40,
            'username' => 'agent.ui',
            'password' => bcrypt('secret'),
            'email' => 'agent-ui@example.com',
            'nombre' => 'Agente UI',
            'cedula' => '40',
            'registro' => 'R40',
            'sede' => 'Matriz',
            'especialidad' => 'NA',
            'permisos' => json_encode(['whatsapp.chat.view', 'whatsapp.chat.send']),
        ]);

        \DB::table('whatsapp_conversations')->insert([
            'id' => 840,
            'wa_number' => '593999111840',
            'display_name' => 'Paciente UI',
            'needs_human' => 1,
            'assigned_user_id' => null,
            'unread_count' => 1,
            'last_message_preview' => 'Hola desde UI',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_quick_replies')->insert([
            'title' => 'Saludo rápido',
            'shortcut' => 'saludo',
            'body' => 'Buenos días, con gusto te ayudo.',
            'created_by_user_id' => 40,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_conversation_notes')->insert([
            'conversation_id' => 840,
            'author_user_id' => 40,
            'body' => 'Paciente pendiente de confirmación.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_message_templates')->insert([
            'id' => 12,
            'template_code' => 'consent_request',
            'display_name' => 'Consentimiento',
            'language' => 'es',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        config()->set('whatsapp.migration.enabled', true);
        config()->set('whatsapp.migration.ui.enabled', true);
        config()->set('whatsapp.migration.api.read_enabled', true);
    }

    public function test_send_permission_user_can_see_take_chat_button_in_v2_ui(): void
    {
        $this->actingAs(User::query()->findOrFail(40));

        $response = $this->withoutMiddleware([
            LegacySessionBridge::class,
            RequireLegacySession::class,
            RequireLegacyPermission::class,
        ])->get('/v2/whatsapp/chat?conversation=840');

        $response
            ->assertOk()
            ->assertSee('Inbox operativo')
            ->assertSee('Respuestas rápidas')
            ->assertSee('Notas internas')
            ->assertSee('Nuevo chat')
            ->assertSee('Nuevo chat con plantilla')
            ->assertSee('Saludo rápido')
            ->assertSee('Paciente pendiente de confirmación.');
    }
}

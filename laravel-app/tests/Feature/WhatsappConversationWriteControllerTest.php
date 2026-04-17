<?php

namespace Tests\Feature;

use App\Models\User;
use App\Http\Middleware\LegacySessionBridge;
use App\Http\Middleware\RequireLegacyPermission;
use App\Http\Middleware\RequireLegacySession;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WhatsappConversationWriteControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_conversations');
        Schema::dropIfExists('whatsapp_message_templates');
        Schema::dropIfExists('whatsapp_contact_consent');
        Schema::dropIfExists('crm_leads');
        Schema::dropIfExists('patient_data');
        Schema::dropIfExists('app_settings');
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

        Schema::create('patient_data', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable();
            $table->dateTime('fecha_caducidad')->nullable();
            $table->string('lname')->default('');
            $table->string('lname2')->nullable();
            $table->string('fname')->default('');
            $table->string('mname')->nullable();
            $table->string('afiliacion')->nullable();
            $table->dateTime('fecha_nacimiento')->nullable();
            $table->string('sexo')->nullable();
            $table->string('celular')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('estado_civil')->nullable();
            $table->string('email')->nullable();
            $table->string('direccion')->nullable();
            $table->string('ocupacion')->nullable();
            $table->string('lugar_trabajo')->nullable();
            $table->string('parroquia')->nullable();
            $table->string('nacionalidad')->nullable();
            $table->string('id_procedencia')->nullable();
            $table->string('id_referido')->nullable();
            $table->string('created_by_type')->nullable();
            $table->string('created_by_identifier')->nullable();
            $table->string('updated_by_type')->nullable();
            $table->string('updated_by_identifier')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_contact_consent', function (Blueprint $table): void {
            $table->id();
            $table->string('wa_number');
            $table->string('cedula');
            $table->string('patient_hc_number')->nullable();
            $table->string('patient_full_name')->nullable();
            $table->string('consent_status')->default('pending');
            $table->string('consent_source')->default('legacy');
            $table->timestamp('consent_asked_at')->nullable();
            $table->timestamp('consent_responded_at')->nullable();
            $table->json('extra_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_leads', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('name')->default('');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('open');
            $table->string('last_stage_notified')->nullable();
            $table->timestamp('last_stage_notified_at')->nullable();
            $table->string('source')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        \DB::table('app_settings')->insert([
            ['name' => 'whatsapp_cloud_enabled', 'value' => '1', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'whatsapp_cloud_phone_number_id', 'value' => '1234567890', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'whatsapp_cloud_access_token', 'value' => 'test-token', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'whatsapp_cloud_api_version', 'value' => 'v17.0', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'whatsapp_cloud_default_country_code', 'value' => '593', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'whatsapp_chat_require_assignment_to_reply', 'value' => '1', 'created_at' => now(), 'updated_at' => now()],
        ]);

        \DB::table('users')->insert([
            'id' => 99,
            'username' => 'agent.demo',
            'password' => bcrypt('secret'),
            'email' => 'agent@example.com',
            'nombre' => 'Agente Demo',
            'cedula' => '123',
            'registro' => 'REG',
            'sede' => 'Matriz',
            'especialidad' => 'NA',
            'permisos' => json_encode(['whatsapp.chat.send', 'whatsapp.chat.view']),
            'role_id' => null,
            'whatsapp_notify' => false,
        ]);

        config()->set('whatsapp.migration.enabled', true);
        config()->set('whatsapp.migration.api.read_enabled', true);
        config()->set('whatsapp.migration.api.write_enabled', true);
        config()->set('whatsapp.transport.dry_run', false);

        $this->actingAs(User::query()->findOrFail(99));
    }

    public function test_it_sends_and_persists_an_outbound_text_message(): void
    {
        \DB::table('whatsapp_conversations')->insert([
            'id' => 44,
            'wa_number' => '0999111222',
            'display_name' => 'Paciente Demo',
            'assigned_user_id' => 99,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_messages')->insert([
            'conversation_id' => 44,
            'direction' => 'inbound',
            'message_type' => 'text',
            'body' => 'Hola',
            'status' => 'received',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messages' => [
                    ['id' => 'wamid.test.123'],
                ],
            ], 200),
        ]);

        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->postJson('/v2/whatsapp/api/conversations/44/messages', [
                'message' => 'Buenos dias',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.message.wa_message_id', 'wamid.test.123')
            ->assertJsonPath('data.message.source', 'laravel-v2');

        $this->assertDatabaseCount('whatsapp_messages', 2);
        $this->assertDatabaseHas('whatsapp_messages', [
            'conversation_id' => 44,
            'direction' => 'outbound',
            'body' => 'Buenos dias',
            'wa_message_id' => 'wamid.test.123',
        ]);
    }

    public function test_it_blocks_outbound_text_when_no_inbound_exists_to_match_legacy_rule(): void
    {
        \DB::table('whatsapp_conversations')->insert([
            'id' => 45,
            'wa_number' => '0999111222',
            'display_name' => 'Paciente Demo',
            'assigned_user_id' => 99,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->postJson('/v2/whatsapp/api/conversations/45/messages', [
                'message' => 'Buenos dias',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    public function test_it_blocks_outbound_text_when_inbound_window_is_expired(): void
    {
        \DB::table('whatsapp_conversations')->insert([
            'id' => 451,
            'wa_number' => '0999111333',
            'display_name' => 'Paciente Fuera de Ventana',
            'assigned_user_id' => 99,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_messages')->insert([
            'conversation_id' => 451,
            'direction' => 'inbound',
            'message_type' => 'text',
            'body' => 'Hola hace días',
            'status' => 'received',
            'message_timestamp' => now()->subDays(2),
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->postJson('/v2/whatsapp/api/conversations/451/messages', [
                'message' => 'Buenos dias',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error', 'Este contacto no ha iniciado conversación. Debes enviar una plantilla aprobada para abrir la ventana de 24h.');
    }

    public function test_it_blocks_outbound_text_when_conversation_is_not_taken(): void
    {
        \DB::table('whatsapp_conversations')->insert([
            'id' => 46,
            'wa_number' => '0999111222',
            'display_name' => 'Paciente Demo',
            'assigned_user_id' => null,
            'needs_human' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_messages')->insert([
            'conversation_id' => 46,
            'direction' => 'inbound',
            'message_type' => 'text',
            'body' => 'Hola',
            'status' => 'received',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->postJson('/v2/whatsapp/api/conversations/46/messages', [
                'message' => 'Buenos dias',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error', 'Debes tomar esta conversación antes de responder.');
    }

    public function test_it_sends_and_persists_an_outbound_document_message(): void
    {
        \DB::table('whatsapp_conversations')->insert([
            'id' => 47,
            'wa_number' => '0999111222',
            'display_name' => 'Paciente Demo',
            'assigned_user_id' => 99,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_messages')->insert([
            'conversation_id' => 47,
            'direction' => 'inbound',
            'message_type' => 'text',
            'body' => 'Necesito el archivo',
            'status' => 'received',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messages' => [
                    ['id' => 'wamid.test.doc.1'],
                ],
            ], 200),
        ]);

        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->postJson('/v2/whatsapp/api/conversations/47/messages', [
                'message' => 'Adjunto orden médica',
                'message_type' => 'document',
                'media_url' => 'https://example.test/orden.pdf',
                'filename' => 'orden.pdf',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.message.wa_message_id', 'wamid.test.doc.1')
            ->assertJsonPath('data.message.message_type', 'document');

        $this->assertDatabaseHas('whatsapp_messages', [
            'conversation_id' => 47,
            'direction' => 'outbound',
            'message_type' => 'document',
            'body' => 'Adjunto orden médica',
            'wa_message_id' => 'wamid.test.doc.1',
        ]);

        $conversation = \DB::table('whatsapp_conversations')->where('id', 47)->first();
        $this->assertSame('document', $conversation?->last_message_type);
        $this->assertSame('Adjunto orden médica', $conversation?->last_message_preview);
    }

    public function test_it_uploads_local_media_to_meta_before_sending_document_message(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('whatsapp-media/2026/04/orden-local.pdf', 'pdf-content');

        \DB::table('whatsapp_conversations')->insert([
            'id' => 48,
            'wa_number' => '0999111222',
            'display_name' => 'Paciente Demo',
            'assigned_user_id' => 99,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_messages')->insert([
            'conversation_id' => 48,
            'direction' => 'inbound',
            'message_type' => 'text',
            'body' => 'Necesito el archivo',
            'status' => 'received',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
            'https://graph.facebook.com/*/media' => Http::response([
                'id' => 'meta-media-123',
            ], 200),
            'https://graph.facebook.com/*/messages' => Http::response([
                'messages' => [
                    ['id' => 'wamid.test.doc.local.1'],
                ],
            ], 200),
        ]);

        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->postJson('/v2/whatsapp/api/conversations/48/messages', [
                'message' => 'Adjunto orden local',
                'message_type' => 'document',
                'media_url' => 'https://cive.consulmed.me/storage/whatsapp-media/2026/04/orden-local.pdf',
                'filename' => 'orden-local.pdf',
                'mime_type' => 'application/pdf',
                'media_disk' => 'public',
                'media_path' => 'whatsapp-media/2026/04/orden-local.pdf',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.message.wa_message_id', 'wamid.test.doc.local.1')
            ->assertJsonPath('data.message.message_type', 'document');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/media');
        });

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/messages');
        });
    }

    public function test_it_searches_contacts_in_patient_data_for_new_chat(): void
    {
        \DB::table('patient_data')->insert([
            'hc_number' => 'HC-9981',
            'fname' => 'Maria',
            'lname' => 'Lopez',
            'celular' => '0999887766',
            'email' => 'maria@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->getJson('/v2/whatsapp/api/contacts/search?q=9981');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.hc_number', 'HC-9981')
            ->assertJsonPath('data.0.wa_number', '593999887766');
    }

    public function test_it_searches_contacts_across_multiple_sources_with_existing_conversation_priority(): void
    {
        \DB::table('patient_data')->insert([
            'hc_number' => 'HC-200',
            'fname' => 'Carlos',
            'lname' => 'Mena',
            'celular' => '0999000200',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_conversations')->insert([
            'id' => 900,
            'wa_number' => '593999000200',
            'display_name' => 'Carlos Mena',
            'patient_hc_number' => 'HC-200',
            'patient_full_name' => 'Carlos Mena',
            'last_message_preview' => 'Seguimiento',
            'last_message_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('crm_leads')->insert([
            'hc_number' => 'HC-200',
            'name' => 'Carlos CRM',
            'phone' => '0999000200',
            'email' => 'carlos@example.com',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_contact_consent')->insert([
            'wa_number' => '593999000200',
            'cedula' => '0102030405',
            'patient_hc_number' => 'HC-200',
            'patient_full_name' => 'Carlos Consent',
            'consent_status' => 'accepted',
            'consent_source' => 'legacy',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->getJson('/v2/whatsapp/api/contacts/search?q=Carlos');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.wa_number', '593999000200')
            ->assertJsonPath('data.0.source', 'conversation')
            ->assertJsonPath('data.0.hc_number', 'HC-200');
    }

    public function test_it_starts_a_new_conversation_by_sending_a_template(): void
    {
        \DB::table('whatsapp_message_templates')->insert([
            'id' => 5,
            'template_code' => 'consent_request',
            'display_name' => 'Consentimiento',
            'language' => 'es',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messages' => [
                    ['id' => 'wamid.template.1'],
                ],
            ], 200),
        ]);

        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->postJson('/v2/whatsapp/api/conversations/start-template', [
                'wa_number' => '0999555444',
                'template_id' => 5,
                'contact_name' => 'Paciente Nuevo',
                'patient_full_name' => 'Paciente Nuevo',
                'patient_hc_number' => 'HC-55',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.conversation.wa_number', '593999555444')
            ->assertJsonPath('data.message.message_type', 'template')
            ->assertJsonPath('data.message.template_id', 5);

        $this->assertDatabaseHas('whatsapp_conversations', [
            'wa_number' => '593999555444',
            'assigned_user_id' => 99,
            'last_message_type' => 'template',
        ]);

        $this->assertDatabaseHas('whatsapp_messages', [
            'direction' => 'outbound',
            'message_type' => 'template',
            'wa_message_id' => 'wamid.template.1',
        ]);
    }
}

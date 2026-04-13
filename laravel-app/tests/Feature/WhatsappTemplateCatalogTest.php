<?php

namespace Tests\Feature;

use App\Http\Middleware\LegacySessionBridge;
use App\Http\Middleware\RequireLegacyPermission;
use App\Http\Middleware\RequireLegacySession;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappTemplateCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'whatsapp_template_revisions',
            'whatsapp_message_templates',
            'app_settings',
            'users',
            'roles',
        ] as $table) {
            Schema::dropIfExists($table);
        }

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

        Schema::create('app_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_message_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('template_code')->unique();
            $table->string('display_name');
            $table->string('language');
            $table->string('category');
            $table->string('status');
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

        Schema::create('whatsapp_template_revisions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('template_id');
            $table->unsignedInteger('version');
            $table->string('status');
            $table->string('header_type')->default('none');
            $table->text('header_text')->nullable();
            $table->longText('body_text');
            $table->text('footer_text')->nullable();
            $table->json('buttons')->nullable();
            $table->json('variables')->nullable();
            $table->string('quality_rating')->default('unknown');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        \DB::table('app_settings')->insert([
            ['name' => 'whatsapp_cloud_enabled', 'value' => '1', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'whatsapp_cloud_business_account_id', 'value' => 'waba-test-1', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'whatsapp_cloud_access_token', 'value' => 'token-test', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'whatsapp_cloud_api_version', 'value' => 'v17.0', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'companyname', 'value' => 'MedForge', 'created_at' => now(), 'updated_at' => now()],
        ]);

        \DB::table('users')->insert([
            'id' => 44,
            'username' => 'template.admin',
            'password' => bcrypt('secret'),
            'email' => 'template@example.com',
            'nombre' => 'Template Admin',
            'cedula' => '123',
            'registro' => 'REG',
            'sede' => 'Matriz',
            'especialidad' => 'NA',
            'permisos' => json_encode(['whatsapp.templates.manage', 'whatsapp.manage']),
            'role_id' => null,
            'whatsapp_notify' => false,
        ]);

        config()->set('whatsapp.migration.enabled', true);
        config()->set('whatsapp.migration.ui.enabled', true);
        config()->set('whatsapp.migration.api.read_enabled', true);
        config()->set('whatsapp.migration.api.write_enabled', true);

        $this->actingAs(User::query()->findOrFail(44));
    }

    public function test_it_lists_templates_from_local_cache(): void
    {
        $templateId = \DB::table('whatsapp_message_templates')->insertGetId([
            'template_code' => 'appointment_confirmation',
            'display_name' => 'Confirmación de cita',
            'language' => 'es',
            'category' => 'UTILITY',
            'status' => 'APPROVED',
            'wa_business_account' => 'waba-test-1',
            'description' => 'Recordatorio',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $revisionId = \DB::table('whatsapp_template_revisions')->insertGetId([
            'template_id' => $templateId,
            'version' => 1,
            'status' => 'approved',
            'header_type' => 'text',
            'header_text' => 'Cita confirmada',
            'body_text' => 'Hola {{1}}, tu cita es {{2}}.',
            'footer_text' => 'MedForge',
            'buttons' => json_encode([['type' => 'QUICK_REPLY', 'text' => 'Confirmar']]),
            'variables' => json_encode(['{{1}}', '{{2}}']),
            'quality_rating' => 'GREEN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_message_templates')
            ->where('id', $templateId)
            ->update(['current_revision_id' => $revisionId]);

        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->getJson('/v2/whatsapp/api/templates?search=appointment');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('meta.source', 'local-cache')
            ->assertJsonPath('data.0.name', 'appointment_confirmation')
            ->assertJsonPath('data.0.preview.header_text', 'Cita confirmada')
            ->assertJsonPath('data.0.preview.variables.0', '{{1}}')
            ->assertJsonPath('data.0.editorial_state', 'synced_meta')
            ->assertJsonPath('data.0.is_editable', false)
            ->assertJsonPath('data.0.can_clone', true)
            ->assertJsonPath('data.0.current_revision_version', 1)
            ->assertJsonPath('data.0.revision_history.0.version', 1);
    }

    public function test_it_syncs_templates_from_meta_into_local_tables(): void
    {
        $longBody = str_repeat('Paciente {{1}} debe revisar indicaciones. ', 20);

        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'data' => [
                    [
                        'id' => 'wamid-template-1',
                        'name' => 'lab_results_ready',
                        'category' => 'UTILITY',
                        'language' => 'es',
                        'status' => 'APPROVED',
                        'quality_score' => ['score' => 'GREEN'],
                        'last_updated_time' => '2026-04-12T10:00:00+00:00',
                        'components' => [
                            ['type' => 'HEADER', 'format' => 'TEXT', 'text' => 'Resultados listos'],
                            ['type' => 'BODY', 'text' => $longBody],
                            ['type' => 'FOOTER', 'text' => 'Equipo MedForge'],
                            ['type' => 'BUTTONS', 'buttons' => [
                                ['type' => 'QUICK_REPLY', 'text' => 'Ver opciones'],
                            ]],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->postJson('/v2/whatsapp/api/templates/sync', ['limit' => 50]);

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.synced', 1)
            ->assertJsonPath('data.templates.0.name', 'lab_results_ready');

        $this->assertDatabaseHas('whatsapp_message_templates', [
            'template_code' => 'lab_results_ready',
            'category' => 'UTILITY',
            'language' => 'es',
            'status' => 'APPROVED',
        ]);

        $this->assertDatabaseHas('whatsapp_template_revisions', [
            'body_text' => $longBody,
            'header_text' => 'Resultados listos',
        ]);
    }

    public function test_it_redirects_get_sync_requests_back_to_templates_ui(): void
    {
        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->get('/v2/whatsapp/api/templates/sync');

        $response->assertRedirect('/v2/whatsapp/templates');
    }

    public function test_it_creates_a_local_template_draft(): void
    {
        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->postJson('/v2/whatsapp/api/templates', [
                'name' => 'appointment_followup',
                'language' => 'es',
                'category' => 'UTILITY',
                'components' => [
                    ['type' => 'HEADER', 'format' => 'TEXT', 'text' => 'Seguimiento'],
                    ['type' => 'BODY', 'text' => 'Hola {{1}}, seguimos atentos a tu cita.'],
                    ['type' => 'FOOTER', 'text' => 'MedForge'],
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.template.name', 'appointment_followup')
            ->assertJsonPath('data.template.status', 'DRAFT');

        $this->assertDatabaseHas('whatsapp_message_templates', [
            'template_code' => 'appointment_followup',
            'status' => 'DRAFT',
        ]);

        $this->assertDatabaseHas('whatsapp_template_revisions', [
            'body_text' => 'Hola {{1}}, seguimos atentos a tu cita.',
            'status' => 'draft',
        ]);
    }

    public function test_it_updates_a_local_template_draft_with_a_new_revision(): void
    {
        $templateId = \DB::table('whatsapp_message_templates')->insertGetId([
            'template_code' => 'status_update',
            'display_name' => 'Status update',
            'language' => 'es',
            'category' => 'UTILITY',
            'status' => 'DRAFT',
            'wa_business_account' => 'waba-test-1',
            'description' => 'Base',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $revisionId = \DB::table('whatsapp_template_revisions')->insertGetId([
            'template_id' => $templateId,
            'version' => 1,
            'status' => 'draft',
            'header_type' => 'none',
            'body_text' => 'Texto original',
            'quality_rating' => 'unknown',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_message_templates')->where('id', $templateId)->update([
            'current_revision_id' => $revisionId,
        ]);

        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->postJson('/v2/whatsapp/api/templates/' . $templateId, [
                'name' => 'status_update',
                'language' => 'es',
                'category' => 'UTILITY',
                'components' => [
                    ['type' => 'BODY', 'text' => 'Texto actualizado'],
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.template.preview.body_text', 'Texto actualizado');

        $this->assertDatabaseHas('whatsapp_template_revisions', [
            'template_id' => $templateId,
            'version' => 2,
            'body_text' => 'Texto actualizado',
        ]);
    }

    public function test_it_blocks_editing_synced_meta_template_in_place(): void
    {
        $templateId = \DB::table('whatsapp_message_templates')->insertGetId([
            'template_code' => 'meta_synced_template',
            'display_name' => 'Meta synced template',
            'language' => 'es',
            'category' => 'UTILITY',
            'status' => 'APPROVED',
            'wa_business_account' => 'waba-test-1',
            'description' => 'Base remota',
            'created_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $revisionId = \DB::table('whatsapp_template_revisions')->insertGetId([
            'template_id' => $templateId,
            'version' => 1,
            'status' => 'approved',
            'header_type' => 'text',
            'header_text' => 'Header remoto',
            'body_text' => 'Texto remoto',
            'footer_text' => 'Footer remoto',
            'buttons' => json_encode([]),
            'variables' => json_encode([]),
            'quality_rating' => 'GREEN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_message_templates')
            ->where('id', $templateId)
            ->update(['current_revision_id' => $revisionId]);

        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->postJson('/v2/whatsapp/api/templates/' . $templateId, [
                'name' => 'meta_synced_template',
                'language' => 'es',
                'category' => 'UTILITY',
                'components' => [
                    ['type' => 'BODY', 'text' => 'Intento de edición'],
                ],
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    public function test_it_clones_remote_template_into_local_draft(): void
    {
        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->postJson('/v2/whatsapp/api/templates/clone', [
                'name' => 'recordatorio_cita_clon',
                'template' => [
                    'name' => 'recordatorio_cita',
                    'display_name' => 'Recordatorio cita',
                    'language' => 'es',
                    'category' => 'UTILITY',
                    'preview' => [
                        'header_type' => 'text',
                        'header_text' => 'Cita',
                        'body_text' => 'Hola {{1}}, tu cita es {{2}}.',
                        'footer_text' => 'MedForge',
                        'buttons' => [
                            ['type' => 'QUICK_REPLY', 'text' => 'Confirmar'],
                        ],
                    ],
                    'components' => [
                        ['type' => 'HEADER', 'format' => 'TEXT', 'text' => 'Cita'],
                        ['type' => 'BODY', 'text' => 'Hola {{1}}, tu cita es {{2}}.'],
                        ['type' => 'FOOTER', 'text' => 'MedForge'],
                        ['type' => 'BUTTONS', 'buttons' => [
                            ['type' => 'QUICK_REPLY', 'text' => 'Confirmar'],
                        ]],
                    ],
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.template.name', 'recordatorio_cita_clon')
            ->assertJsonPath('data.template.editorial_state', 'draft')
            ->assertJsonPath('data.template.is_editable', true);

        $this->assertDatabaseHas('whatsapp_message_templates', [
            'template_code' => 'recordatorio_cita_clon',
            'status' => 'DRAFT',
            'created_by' => 44,
        ]);
    }

    public function test_it_publishes_a_local_template_draft_to_meta(): void
    {
        $templateId = \DB::table('whatsapp_message_templates')->insertGetId([
            'template_code' => 'results_notice',
            'display_name' => 'Results notice',
            'language' => 'es',
            'category' => 'UTILITY',
            'status' => 'DRAFT',
            'wa_business_account' => 'waba-test-1',
            'description' => 'Base',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $revisionId = \DB::table('whatsapp_template_revisions')->insertGetId([
            'template_id' => $templateId,
            'version' => 1,
            'status' => 'draft',
            'header_type' => 'text',
            'header_text' => 'Resultados',
            'body_text' => 'Hola {{1}}, tus resultados estan listos.',
            'footer_text' => 'MedForge',
            'buttons' => json_encode([['type' => 'QUICK_REPLY', 'text' => 'Ver opciones']]),
            'variables' => json_encode(['{{1}}']),
            'quality_rating' => 'unknown',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_message_templates')->where('id', $templateId)->update([
            'current_revision_id' => $revisionId,
        ]);

        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'id' => 'meta-template-id-1',
                'status' => 'PENDING',
            ], 200),
        ]);

        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->postJson('/v2/whatsapp/api/templates/' . $templateId . '/publish');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.template.status', 'PENDING');

        $this->assertDatabaseHas('whatsapp_message_templates', [
            'id' => $templateId,
            'status' => 'PENDING',
        ]);

        $this->assertDatabaseHas('whatsapp_template_revisions', [
            'id' => $revisionId,
            'status' => 'pending',
        ]);
    }

    public function test_it_creates_a_local_template_draft_with_media_header(): void
    {
        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->postJson('/v2/whatsapp/api/templates', [
                'name' => 'results_with_image',
                'language' => 'es',
                'category' => 'UTILITY',
                'components' => [
                    ['type' => 'HEADER', 'format' => 'IMAGE', 'example' => 'media://results-image'],
                    ['type' => 'BODY', 'text' => 'Hola {{1}}, revisa tus resultados.'],
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.template.preview.header_type', 'image')
            ->assertJsonPath('data.template.preview.header_text', 'media://results-image');

        $this->assertDatabaseHas('whatsapp_template_revisions', [
            'header_type' => 'image',
            'header_text' => 'media://results-image',
        ]);
    }

    public function test_it_publishes_media_header_draft_from_laravel(): void
    {
        $templateId = \DB::table('whatsapp_message_templates')->insertGetId([
            'template_code' => 'results_media_blocked',
            'display_name' => 'Results media blocked',
            'language' => 'es',
            'category' => 'UTILITY',
            'status' => 'DRAFT',
            'wa_business_account' => 'waba-test-1',
            'description' => 'Base',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $revisionId = \DB::table('whatsapp_template_revisions')->insertGetId([
            'template_id' => $templateId,
            'version' => 1,
            'status' => 'draft',
            'header_type' => 'image',
            'header_text' => 'media://results-image',
            'body_text' => 'Hola {{1}}, revisa tus resultados.',
            'quality_rating' => 'unknown',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_message_templates')->where('id', $templateId)->update([
            'current_revision_id' => $revisionId,
        ]);

        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'id' => 'meta-template-media-1',
                'status' => 'PENDING',
            ], 200),
        ]);

        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->postJson('/v2/whatsapp/api/templates/' . $templateId . '/publish');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.template.status', 'PENDING');

        $this->assertDatabaseHas('whatsapp_message_templates', [
            'id' => $templateId,
            'status' => 'PENDING',
        ]);
    }

    public function test_it_renders_templates_ui_page(): void
    {
        $templateId = \DB::table('whatsapp_message_templates')->insertGetId([
            'template_code' => 'reminder_base',
            'display_name' => 'Recordatorio base',
            'language' => 'es',
            'category' => 'UTILITY',
            'status' => 'APPROVED',
            'wa_business_account' => 'waba-test-1',
            'description' => 'Base',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $revisionId = \DB::table('whatsapp_template_revisions')->insertGetId([
            'template_id' => $templateId,
            'version' => 1,
            'status' => 'approved',
            'header_type' => 'none',
            'body_text' => 'Mensaje base.',
            'quality_rating' => 'GREEN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_message_templates')
            ->where('id', $templateId)
            ->update(['current_revision_id' => $revisionId]);

        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->get('/v2/whatsapp/templates');

        $response
            ->assertOk()
            ->assertSee('Templates')
            ->assertSee('Sincronizar con Meta')
            ->assertSee('Historial de revisiones');
    }
}

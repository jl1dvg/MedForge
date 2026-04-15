<?php

namespace Tests\Feature;

use App\Http\Middleware\LegacySessionBridge;
use App\Http\Middleware\RequireLegacyPermission;
use App\Http\Middleware\RequireLegacySession;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappCampaignsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('whatsapp_campaign_deliveries');
        Schema::dropIfExists('whatsapp_campaigns');
        Schema::dropIfExists('whatsapp_message_templates');
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

        Schema::create('whatsapp_message_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('template_code', 191);
            $table->string('display_name', 191)->nullable();
            $table->string('language', 16)->nullable();
            $table->string('category', 64)->nullable();
            $table->string('status', 32)->nullable();
            $table->string('wa_business_account', 64)->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('current_revision_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('approval_requested_at')->nullable();
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

        Schema::create('whatsapp_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('status', 32)->default('draft');
            $table->unsignedBigInteger('template_id')->nullable();
            $table->string('template_name', 191)->nullable();
            $table->json('audience_payload')->nullable();
            $table->unsignedInteger('audience_count')->default(0);
            $table->boolean('dry_run')->default(true);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('last_executed_at')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_campaign_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->string('wa_number', 32);
            $table->string('contact_name', 191)->nullable();
            $table->string('status', 32)->default('pending');
            $table->string('template_name', 191)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->text('error_detail')->nullable();
            $table->timestamps();
        });

        \DB::table('users')->insert([
            'id' => 1,
            'username' => 'admin.campaigns',
            'password' => bcrypt('secret'),
            'email' => 'admin-campaigns@example.com',
            'nombre' => 'Admin Campaigns',
            'cedula' => '1',
            'registro' => 'R1',
            'sede' => 'Matriz',
            'especialidad' => 'NA',
            'permisos' => json_encode(['administrativo', 'whatsapp.manage', 'whatsapp.chat.view', 'whatsapp.templates.manage']),
        ]);

        \DB::table('whatsapp_message_templates')->insert([
            'id' => 55,
            'template_code' => 'appointment_reminder',
            'display_name' => 'Appointment Reminder',
            'language' => 'es',
            'category' => 'UTILITY',
            'status' => 'APPROVED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_conversations')->insert([
            [
                'id' => 301,
                'wa_number' => '593999111222',
                'display_name' => 'María Pérez',
                'needs_human' => 1,
                'last_message_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 302,
                'wa_number' => '593999111333',
                'display_name' => 'Carlos Gómez',
                'needs_human' => 0,
                'last_message_at' => now()->subMinute(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        config()->set('whatsapp.migration.enabled', true);
        config()->set('whatsapp.migration.ui.enabled', true);
        config()->set('whatsapp.migration.api.read_enabled', true);
        config()->set('whatsapp.migration.api.write_enabled', true);

        $this->actingAs(User::query()->findOrFail(1));
    }

    public function test_it_creates_a_campaign_draft(): void
    {
        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->postJson('/v2/whatsapp/api/campaigns', [
                'name' => 'Recordatorio abril',
                'template_id' => 55,
                'audience_text' => "593999111222|María Pérez\n0999111222|Paciente 2",
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.name', 'Recordatorio abril')
            ->assertJsonPath('data.audience_count', 2)
            ->assertJsonPath('data.template_id', 55);
    }

    public function test_it_executes_a_campaign_dry_run(): void
    {
        \DB::table('whatsapp_campaigns')->insert([
            'id' => 7,
            'name' => 'Campaña Dry Run',
            'status' => 'draft',
            'template_id' => 55,
            'template_name' => 'Appointment Reminder',
            'audience_payload' => json_encode([
                ['wa_number' => '+593999111222', 'contact_name' => 'María Pérez'],
                ['wa_number' => '+593999111333', 'contact_name' => 'Carlos'],
            ]),
            'audience_count' => 2,
            'dry_run' => 1,
            'created_by_user_id' => 1,
            'updated_by_user_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->postJson('/v2/whatsapp/api/campaigns/7/dry-run');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.campaign.status', 'dry_run_ready')
            ->assertJsonPath('data.deliveries.0.status', 'dry_run_ready');

        $this->assertDatabaseCount('whatsapp_campaign_deliveries', 2);
    }

    public function test_it_renders_campaigns_ui_page(): void
    {
        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->get('/v2/whatsapp/campaigns');

        $response
            ->assertOk()
            ->assertSee('Campañas MVP')
            ->assertSee('Nuevo borrador')
            ->assertSee('Sin envío real todavía')
            ->assertSee('María Pérez');
    }

    public function test_it_lists_audience_suggestions_from_conversations(): void
    {
        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->getJson('/v2/whatsapp/api/campaigns/audience-suggestions?segment=needs_human');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.display_name', 'María Pérez')
            ->assertJsonPath('data.0.wa_number', '+593999111222');
    }
}

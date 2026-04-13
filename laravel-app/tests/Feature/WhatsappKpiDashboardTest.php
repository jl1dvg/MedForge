<?php

namespace Tests\Feature;

use App\Http\Middleware\LegacySessionBridge;
use App\Http\Middleware\RequireLegacyPermission;
use App\Http\Middleware\RequireLegacySession;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappKpiDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'whatsapp_handoff_events',
            'whatsapp_handoffs',
            'whatsapp_messages',
            'whatsapp_conversations',
            'users',
            'roles',
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
            $table->text('permisos')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
        });

        Schema::create('whatsapp_conversations', function (Blueprint $table): void {
            $table->id();
            $table->string('wa_number', 32)->unique();
            $table->string('display_name')->nullable();
            $table->string('patient_full_name')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->string('last_message_direction')->nullable();
            $table->string('last_message_type')->nullable();
            $table->string('last_message_preview', 512)->nullable();
            $table->boolean('needs_human')->default(false);
            $table->unsignedBigInteger('handoff_role_id')->nullable();
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamps();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('direction', 16);
            $table->string('message_type', 64)->default('text');
            $table->longText('body')->nullable();
            $table->string('status', 32)->nullable();
            $table->timestamp('message_timestamp')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_handoffs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('wa_number', 32);
            $table->string('status', 24)->default('queued');
            $table->unsignedBigInteger('handoff_role_id')->nullable();
            $table->unsignedBigInteger('assigned_agent_id')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('assigned_until')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_handoff_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('handoff_id');
            $table->string('event_type', 64);
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        \DB::table('roles')->insert([
            ['id' => 1, 'name' => 'Call Center', 'created_at' => now(), 'updated_at' => now()],
        ]);

        \DB::table('users')->insert([
            'id' => 44,
            'username' => 'wa.admin',
            'first_name' => 'Jorge',
            'last_name' => 'Vera',
            'nombre' => 'Jorge Vera',
            'permisos' => json_encode(['whatsapp.manage', 'whatsapp.chat.view']),
            'role_id' => 1,
        ]);

        $conversationId = \DB::table('whatsapp_conversations')->insertGetId([
            'wa_number' => '593999111222',
            'display_name' => 'Paciente Demo',
            'last_message_at' => now()->subMinutes(5),
            'last_message_direction' => 'outbound',
            'last_message_type' => 'text',
            'needs_human' => true,
            'handoff_role_id' => 1,
            'assigned_user_id' => 44,
            'unread_count' => 0,
            'created_at' => now()->subDays(2),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_messages')->insert([
            [
                'conversation_id' => $conversationId,
                'direction' => 'inbound',
                'message_type' => 'text',
                'body' => 'Hola',
                'status' => null,
                'message_timestamp' => now()->subDays(2)->addMinutes(1),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'conversation_id' => $conversationId,
                'direction' => 'outbound',
                'message_type' => 'text',
                'body' => 'Respuesta humana',
                'status' => 'sent',
                'message_timestamp' => now()->subDays(2)->addMinutes(4),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $handoffId = \DB::table('whatsapp_handoffs')->insertGetId([
            'conversation_id' => $conversationId,
            'wa_number' => '593999111222',
            'status' => 'assigned',
            'handoff_role_id' => 1,
            'assigned_agent_id' => 44,
            'assigned_at' => now()->subDays(2)->addMinutes(2),
            'assigned_until' => now()->addHour(),
            'queued_at' => now()->subDays(2)->addMinute(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_handoff_events')->insert([
            'handoff_id' => $handoffId,
            'event_type' => 'transferred',
            'actor_user_id' => 44,
            'notes' => 'Cambio de agente',
            'created_at' => now()->subDays(2)->addMinutes(3),
        ]);

        config()->set('whatsapp.migration.enabled', true);
        config()->set('whatsapp.migration.ui.enabled', true);
        config()->set('whatsapp.migration.api.read_enabled', true);

        $this->actingAs(User::query()->findOrFail(44));
    }

    public function test_it_returns_dashboard_kpis_from_laravel(): void
    {
        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->getJson('/v2/whatsapp/api/kpis');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.summary.people_inbound', 1)
            ->assertJsonPath('data.summary.messages_inbound', 1)
            ->assertJsonPath('data.summary.messages_outbound', 1)
            ->assertJsonPath('data.summary.handoff_transfers', 1);
    }

    public function test_it_returns_drilldown_for_supported_metric(): void
    {
        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->getJson('/v2/whatsapp/api/kpis/drilldown?metric=messages_inbound');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.metric', 'messages_inbound')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.rows.0.wa_number', '593999111222');
    }

    public function test_it_returns_drilldown_for_queue_needs_template(): void
    {
        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->getJson('/v2/whatsapp/api/kpis/drilldown?metric=queue_needs_template');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.metric', 'queue_needs_template')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.rows.0.wa_number', '593999111222');
    }

    public function test_it_exports_dashboard_csv(): void
    {
        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->get('/v2/whatsapp/api/kpis/export');

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertDownload();

        $content = $response->streamedContent();
        $this->assertStringContainsString("section,label,value,detail", $content);
        $this->assertStringContainsString("summary,\"Mensajes inbound\",1", $content);
    }

    public function test_it_renders_dashboard_ui_page(): void
    {
        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->get('/v2/whatsapp/dashboard');

        $response
            ->assertOk()
            ->assertSee('KPI y reportes')
            ->assertSee('Personas que escribieron')
            ->assertSee('Atención humana por agente')
            ->assertSee('Exportar CSV');
    }
}

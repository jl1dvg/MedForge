<?php

namespace Tests\Feature;

use App\Http\Middleware\LegacySessionBridge;
use App\Http\Middleware\RequireLegacyPermission;
use App\Http\Middleware\RequireLegacySession;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappKnowledgeBaseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'whatsapp_ai_agent_runs',
            'whatsapp_knowledge_documents',
            'whatsapp_autoresponder_sessions',
            'whatsapp_autoresponder_step_transitions',
            'whatsapp_autoresponder_step_actions',
            'whatsapp_autoresponder_steps',
            'whatsapp_autoresponder_schedules',
            'whatsapp_autoresponder_version_filters',
            'whatsapp_autoresponder_flow_versions',
            'whatsapp_autoresponder_flows',
            'roles',
            'users',
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
            $table->string('email')->nullable();
            $table->text('permisos')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
        });

        Schema::create('whatsapp_autoresponder_flows', function (Blueprint $table): void {
            $table->id();
            $table->string('flow_key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->string('timezone')->nullable();
            $table->unsignedBigInteger('active_version_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_autoresponder_flow_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('flow_id');
            $table->unsignedInteger('version');
            $table->string('status')->default('draft');
            $table->text('changelog')->nullable();
            $table->json('entry_settings')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('published_by')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_autoresponder_steps', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('flow_version_id');
            $table->string('step_key');
            $table->string('step_type');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('order_index')->default(0);
            $table->boolean('is_entry_point')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_autoresponder_step_actions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('step_id');
            $table->string('action_type');
            $table->unsignedBigInteger('template_revision_id')->nullable();
            $table->text('message_body')->nullable();
            $table->string('media_url')->nullable();
            $table->unsignedInteger('delay_seconds')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('order_index')->default(0);
            $table->timestamps();
        });

        Schema::create('whatsapp_autoresponder_step_transitions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('step_id');
            $table->unsignedBigInteger('target_step_id')->nullable();
            $table->string('condition_label')->nullable();
            $table->string('condition_type')->default('always');
            $table->json('condition_payload')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->timestamps();
        });

        Schema::create('whatsapp_autoresponder_version_filters', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('flow_version_id');
            $table->string('filter_type');
            $table->string('operator');
            $table->json('value')->nullable();
            $table->boolean('is_exclusion')->default(false);
            $table->unsignedInteger('order_index')->default(0);
            $table->timestamps();
        });

        Schema::create('whatsapp_autoresponder_schedules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('flow_version_id');
            $table->unsignedInteger('day_of_week')->nullable();
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->string('timezone')->nullable();
            $table->boolean('allow_holidays')->default(false);
            $table->timestamps();
        });

        Schema::create('whatsapp_autoresponder_sessions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('wa_number');
            $table->string('scenario_id')->nullable();
            $table->string('node_id')->nullable();
            $table->string('awaiting')->nullable();
            $table->json('context')->nullable();
            $table->json('last_payload')->nullable();
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_knowledge_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 191);
            $table->string('slug', 191)->unique();
            $table->text('summary')->nullable();
            $table->longText('content');
            $table->string('status', 32)->default('draft');
            $table->string('source_type', 32)->default('manual');
            $table->string('source_label', 191)->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_ai_agent_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('wa_number', 32)->nullable();
            $table->string('scenario_id', 191)->nullable();
            $table->unsignedInteger('action_index')->default(0);
            $table->longText('input_text')->nullable();
            $table->json('filters')->nullable();
            $table->json('matched_documents')->nullable();
            $table->longText('response_text')->nullable();
            $table->string('classification', 64)->nullable();
            $table->decimal('confidence', 5, 2)->default(0);
            $table->boolean('suggested_handoff')->default(false);
            $table->json('context_before')->nullable();
            $table->json('context_after')->nullable();
            $table->string('source', 32)->default('preview');
            $table->timestamps();
        });

        \DB::table('roles')->insert([
            'id' => 1,
            'name' => 'Call Center',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('users')->insert([
            'id' => 81,
            'username' => 'kb.admin',
            'first_name' => 'Knowledge',
            'last_name' => 'Admin',
            'nombre' => 'Knowledge Admin',
            'email' => 'kb@example.test',
            'permisos' => json_encode(['whatsapp.manage', 'whatsapp.autoresponder.manage']),
            'role_id' => 1,
        ]);

        \DB::table('whatsapp_autoresponder_flows')->insert([
            'id' => 1,
            'flow_key' => 'default',
            'name' => 'Flujo principal',
            'description' => 'Base',
            'status' => 'active',
            'timezone' => 'America/Guayaquil',
            'active_version_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_autoresponder_flow_versions')->insert([
            'id' => 1,
            'flow_id' => 1,
            'version' => 1,
            'status' => 'published',
            'entry_settings' => json_encode(['flow' => ['scenarios' => []]]),
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        config()->set('whatsapp.migration.enabled', true);
        config()->set('whatsapp.migration.ui.enabled', true);
        config()->set('whatsapp.migration.api.read_enabled', true);
        config()->set('whatsapp.migration.api.write_enabled', true);

        $this->actingAs(User::query()->findOrFail(81));
    }

    public function test_it_creates_and_lists_knowledge_documents(): void
    {
        $create = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->postJson('/v2/whatsapp/api/knowledge-base', [
                'title' => 'Consentimiento y uso de datos',
                'content' => 'Documento base para grounding sobre autorización de datos protegidos.',
                'status' => 'published',
                'sede' => 'Matriz',
                'especialidad' => 'Oftalmología',
                'tipo_contenido' => 'consentimiento',
                'audiencia' => 'paciente',
            ]);

        $create
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.title', 'Consentimiento y uso de datos')
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.metadata.sede', 'Matriz');

        $list = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->getJson('/v2/whatsapp/api/knowledge-base?search=consentimiento');

        $list
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.title', 'Consentimiento y uso de datos')
            ->assertJsonPath('stats.total', 1)
            ->assertJsonPath('stats.published', 1);
    }

    public function test_it_renders_knowledge_base_panel_inside_flowmaker(): void
    {
        \DB::table('whatsapp_knowledge_documents')->insert([
            'title' => 'FAQ Seguros',
            'slug' => 'faq-seguros',
            'summary' => 'Coberturas y verificación',
            'content' => 'Contenido de seguros.',
            'status' => 'published',
            'source_type' => 'manual',
            'source_label' => 'Flowmaker KB',
            'metadata' => json_encode([
                'sede' => 'Matriz',
                'especialidad' => 'Oftalmología',
                'tipo_contenido' => 'seguros',
                'audiencia' => 'agente',
                'vigencia' => 'vigente',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->get('/v2/whatsapp/flowmaker');

        $response
            ->assertOk()
            ->assertSee('Knowledge Base IA')
            ->assertSee('Alta rápida de documento')
            ->assertSee('FAQ Seguros');
    }
}

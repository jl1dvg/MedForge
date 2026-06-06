<?php

namespace Tests\Feature;

use App\Http\Middleware\LegacySessionBridge;
use App\Http\Middleware\RequireAppPermission;
use App\Http\Middleware\RequireAppSession;
use App\Http\Middleware\RequireLegacyPermission;
use App\Http\Middleware\RequireLegacySession;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CrmV3CaseControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function refreshDatabase(): void
    {
        $this->ensureCrmCaseTestSchema();

        foreach ([
            'crm_proposal_activity',
            'crm_proposal_items',
            'crm_proposals',
            'crm_opportunities',
            'crm_contacts',
            'crm_customers',
            'crm_leads',
            'crm_tasks',
            'solicitud_crm_notas',
            'solicitud_crm_detalles',
            'solicitud_procedimiento',
            'patient_data',
            'users',
        ] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }
    }

    public function test_v3_crm_routes_are_registered(): void
    {
        $routeNames = [
            'v3.crm.cases.show',
            'v3.crm.cases.notes.store',
            'v3.crm.cases.tasks.store',
            'v3.crm.cases.whatsapp.store',
            'v3.crm.cases.email.store',
            'v3.crm.cases.proposals.store',
            'v3.crm.proposals.pdf',
            'v3.crm.proposals.email',
            'v3.crm.proposals.whatsapp',
        ];

        foreach ($routeNames as $routeName) {
            $this->assertNotNull(Route::getRoutes()->getByName($routeName), "Route [{$routeName}] is not registered.");
        }
    }

    public function test_show_solicitud_case_returns_normalized_crm_payload(): void
    {
        $user = $this->createUser();

        $this->insertRow('patient_data', [
            'hc_number' => '0932000904',
            'fname' => 'DANIELA',
            'mname' => 'VALENTINA',
            'lname' => 'MORALES',
            'lname2' => 'MURILLO',
        ]);

        $this->insertRow('solicitud_procedimiento', [
            'id' => 275872,
            'paciente_id' => 9901,
            'hc_number' => '0932000904',
            'form_id' => 275872,
            'estado' => 'revision-codigos',
            'sede' => 'MATRIZ',
            'sede_departamento' => 'MATRIZ',
            'created_at' => '2026-06-03 08:00:00',
        ]);

        $this->insertRow('solicitud_crm_detalles', [
            'solicitud_id' => 275872,
            'responsable_id' => 7,
            'responsable_nombre' => 'Coordinación',
            'contacto_telefono' => '0987107769',
            'telefono' => '0987107769',
            'contacto_email' => 'paciente@example.com',
            'email' => 'paciente@example.com',
            'fuente' => 'Convenio',
            'insurance_company' => 'Humana',
            'insurance_plan' => 'Plan Azul',
            'insurance_code' => 'HUM-001',
            'created_at' => '2026-06-03 08:01:00',
            'updated_at' => '2026-06-03 08:01:00',
        ]);

        $this->insertRow('solicitud_crm_notas', [
            'id' => 11,
            'solicitud_id' => 275872,
            'autor_id' => 1,
            'nota' => 'Paciente contactada por convenio',
            'created_at' => '2026-06-03 08:05:00',
        ]);

        $this->insertRow('crm_tasks', [
            'id' => 21,
            'entity_type' => 'solicitud',
            'entity_id' => '275872',
            'form_id' => 275872,
            'source_module' => 'solicitud',
            'source_ref_id' => '275872',
            'title' => 'Validar cobertura',
            'description' => 'Confirmar cobertura del convenio',
            'status' => 'completada',
            'priority' => 'normal',
            'assigned_to' => 1,
            'created_by' => 1,
            'completed_at' => '2026-06-03 08:09:00',
            'created_at' => '2026-06-03 08:06:00',
            'updated_at' => '2026-06-03 08:10:00',
        ]);

        $this->insertRow('crm_tasks', [
            'id' => 22,
            'entity_type' => 'solicitud',
            'entity_id' => '275872',
            'form_id' => 275872,
            'source_module' => 'solicitud',
            'source_ref_id' => '275872',
            'title' => 'Pendiente operativo',
            'description' => 'No debe salir como actividad completada',
            'status' => 'pendiente',
            'priority' => 'normal',
            'assigned_to' => 1,
            'created_by' => 1,
            'completed_at' => null,
            'created_at' => '2026-06-03 08:07:00',
            'updated_at' => '2026-06-03 08:11:00',
        ]);

        $response = $this->actingAs($user)
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
                RequireAppSession::class,
                RequireAppPermission::class,
            ])
            ->getJson('/v3/crm/cases/solicitud/275872')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.case.source_type', 'solicitud')
            ->assertJsonPath('data.case.source_id', 275872)
            ->assertJsonPath('data.case.solicitud_id', 275872)
            ->assertJsonPath('data.case.paciente_id', 9901)
            ->assertJsonPath('data.case.hc_number', '0932000904')
            ->assertJsonPath('data.crm.responsible_id', 7)
            ->assertJsonPath('data.crm.insurance_company', 'Humana')
            ->assertJsonPath('data.crm.insurance_plan', 'Plan Azul')
            ->assertJsonPath('data.crm.insurance_code', 'HUM-001')
            ->assertJsonPath('data.contacts.primary_phone', '0987107769')
            ->assertJsonPath('data.contacts.primary_email', 'paciente@example.com')
            ->assertJsonStructure([
                'data' => [
                    'case' => [
                        'case_id',
                        'source_type',
                        'source_id',
                        'solicitud_id',
                        'paciente_id',
                        'form_id',
                        'hc_number',
                        'patient_name',
                        'stage',
                        'site',
                    ],
                    'crm' => [
                        'responsible_id',
                        'responsible_name',
                        'source',
                        'insurance_company',
                        'insurance_plan',
                        'insurance_code',
                    ],
                    'contacts' => [
                        'primary_phone',
                        'alternate_phones',
                        'primary_email',
                        'alternate_emails',
                    ],
                    'notes',
                    'tasks',
                    'activity',
                    'proposals',
                    'documents',
                ],
            ]);

        $activity = $response->json('data.activity');
        $this->assertIsArray($activity);
        $this->assertCount(2, $activity);
        $this->assertSame('task_completed', $activity[0]['type']);
        $this->assertSame('note_created', $activity[1]['type']);
        $this->assertSame(['id', 'type', 'occurred_at', 'author', 'description', 'reference'], array_keys($activity[0]));
        $this->assertSame(['id', 'type', 'occurred_at', 'author', 'description', 'reference'], array_keys($activity[1]));
        $this->assertSame(['task_id' => 21], $activity[0]['reference']);
        $this->assertSame(['note_id' => 11], $activity[1]['reference']);
        $this->assertFalse(collect($activity)->contains(fn (array $event): bool => ($event['reference']['task_id'] ?? null) === 22));
    }

    public function test_show_solicitud_case_returns_real_proposals_with_items_and_links(): void
    {
        $user = $this->createUser();
        $this->seedSolicitudCaseTables();

        $leadId = DB::table('crm_leads')->insertGetId([
            'name' => 'DANIELA VALENTINA MORALES MURILLO',
            'email' => 'paciente@example.com',
            'phone' => '0987107769',
            'hc_number' => '0932000904',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $proposalId = DB::table('crm_proposals')->insertGetId([
            'public_hash' => 'hash-275872',
            'proposal_number' => 'DQX-077',
            'proposal_year' => 2026,
            'sequence' => 77,
            'lead_id' => $leadId,
            'source_type' => 'solicitud',
            'source_id' => 275872,
            'form_id' => 275872,
            'title' => 'Paquete quirúrgico',
            'status' => 'draft',
            'currency' => 'USD',
            'subtotal' => 320,
            'tax_rate' => 15,
            'tax_total' => 48,
            'total' => 368,
            'valid_until' => '2026-06-23',
            'created_at' => '2026-06-03 09:00:00',
            'updated_at' => '2026-06-03 09:00:00',
        ]);

        DB::table('crm_proposal_items')->insert([
            'proposal_id' => $proposalId,
            'code_id' => 101,
            'description' => 'Derecho de quirófano',
            'quantity' => 1,
            'unit_price' => 320,
            'discount_percent' => 0,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
                RequireAppSession::class,
                RequireAppPermission::class,
            ])
            ->getJson('/v3/crm/cases/solicitud/275872')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.proposals.0.id', $proposalId)
            ->assertJsonPath('data.proposals.0.proposal_number', 'DQX-077')
            ->assertJsonPath('data.proposals.0.public_hash', 'hash-275872')
            ->assertJsonPath('data.proposals.0.pdf_url', '/v2/crm/proposals/' . $proposalId . '/pdf')
            ->assertJsonPath('data.proposals.0.source_type', 'solicitud')
            ->assertJsonPath('data.proposals.0.source_id', 275872)
            ->assertJsonPath('data.proposals.0.items.0.description', 'Derecho de quirófano')
            ->assertJsonPath('data.proposals.0.items.0.unit_price', 320);
    }

    public function test_store_note_persists_and_returns_refreshed_case(): void
    {
        $user = $this->createUser();
        $this->seedSolicitudCaseTables();

        $body = 'Paciente confirma disponibilidad';

        $this->actingAs($user)
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
                RequireAppSession::class,
                RequireAppPermission::class,
            ])
            ->postJson('/v3/crm/cases/solicitud/275872/notes', [
                'body' => $body,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment([
                'body' => $body,
            ]);

        $this->assertDatabaseHas('solicitud_crm_notas', [
            'solicitud_id' => 275872,
            'nota' => $body,
        ]);
    }

    public function test_store_task_and_update_task_status_are_persisted(): void
    {
        $user = $this->createUser();
        $this->seedSolicitudCaseTables();

        $storeResponse = $this->actingAs($user)
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
                RequireAppSession::class,
                RequireAppPermission::class,
            ])
            ->postJson('/v3/crm/cases/solicitud/275872/tasks', [
                'title' => 'Validar cobertura',
                'priority' => 'alta',
                'due_at' => '2026-06-04 09:00:00',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment([
                'title' => 'Validar cobertura',
                'priority' => 'alta',
            ]);

        $tasks = $storeResponse->json('data.tasks');
        $this->assertIsArray($tasks);
        $task = collect($tasks)->firstWhere('title', 'Validar cobertura');
        $this->assertIsArray($task);
        $taskId = (int) $task['id'];
        $this->assertGreaterThan(0, $taskId);

        $this->actingAs($user)
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
                RequireAppSession::class,
                RequireAppPermission::class,
            ])
            ->patchJson("/v3/crm/cases/solicitud/275872/tasks/{$taskId}", [
                'status' => 'done',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment([
                'id' => $taskId,
                'status' => 'done',
            ]);

        $this->assertDatabaseHas('crm_tasks', [
            'id' => $taskId,
            'status' => 'done',
        ]);
    }

    public function test_delete_ownerless_note_is_denied_for_non_admin_user(): void
    {
        $user = $this->createUser();
        $this->seedSolicitudCaseTables();

        $this->insertRow('solicitud_crm_notas', [
            'id' => 31,
            'solicitud_id' => 275872,
            'autor_id' => null,
            'nota' => 'Nota sin propietario',
            'created_at' => '2026-06-03 09:00:00',
        ]);

        $this->actingAs($user)
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
                RequireAppSession::class,
                RequireAppPermission::class,
            ])
            ->deleteJson('/v3/crm/cases/solicitud/275872/notes/31')
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('solicitud_crm_notas', [
            'id' => 31,
            'solicitud_id' => 275872,
            'autor_id' => null,
            'deleted_at' => null,
        ]);
    }

    public function test_update_task_uses_strong_source_scope_before_form_id_fallback(): void
    {
        $user = $this->createUser();
        $this->seedSolicitudCaseTables();

        $this->insertRow('crm_tasks', [
            'id' => 41,
            'source_type' => 'consulta',
            'source_id' => 999,
            'entity_type' => 'solicitud',
            'entity_id' => '275872',
            'form_id' => 275872,
            'source_module' => 'solicitud',
            'source_ref_id' => '275872',
            'title' => 'No debe actualizarse',
            'status' => 'pending',
            'priority' => 'normal',
            'created_at' => '2026-06-03 09:00:00',
            'updated_at' => '2026-06-03 09:00:00',
        ]);

        $this->actingAs($user)
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
                RequireAppSession::class,
                RequireAppPermission::class,
            ])
            ->patchJson('/v3/crm/cases/solicitud/275872/tasks/41', [
                'status' => 'done',
            ])
            ->assertNotFound()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('crm_tasks', [
            'id' => 41,
            'status' => 'pending',
        ]);
    }

    public function test_store_note_and_task_for_missing_solicitud_do_not_persist(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
                RequireAppSession::class,
                RequireAppPermission::class,
            ])
            ->postJson('/v3/crm/cases/solicitud/999999/notes', [
                'body' => 'No debe persistir',
            ])
            ->assertNotFound()
            ->assertJsonPath('success', false);

        $this->actingAs($user)
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
                RequireAppSession::class,
                RequireAppPermission::class,
            ])
            ->postJson('/v3/crm/cases/solicitud/999999/tasks', [
                'title' => 'No debe persistir',
            ])
            ->assertNotFound()
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('solicitud_crm_notas', [
            'solicitud_id' => 999999,
            'nota' => 'No debe persistir',
        ]);
        $this->assertDatabaseMissing('crm_tasks', [
            'source_id' => 999999,
            'title' => 'No debe persistir',
        ]);
    }

    public function test_whatsapp_rejects_empty_recipients_and_message(): void
    {
        $user = $this->createUser();
        $this->seedSolicitudCaseTables();

        $this->actingAs($user)
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
                RequireAppSession::class,
                RequireAppPermission::class,
            ])
            ->postJson('/v3/crm/cases/solicitud/275872/whatsapp', [
                'recipients' => [],
                'message' => '',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_proposal_rejects_items_without_description(): void
    {
        $user = $this->createUser();
        $this->seedSolicitudCaseTables();

        $this->actingAs($user)
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
                RequireAppSession::class,
                RequireAppPermission::class,
            ])
            ->postJson('/v3/crm/cases/solicitud/275872/proposals', [
                'title' => 'Propuesta inicial',
                'items' => [
                    [
                        'quantity' => 1,
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_catalog_search_requires_query(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
                RequireAppSession::class,
                RequireAppPermission::class,
            ])
            ->getJson('/v3/crm/catalog/codes?q=')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', []);

        $this->actingAs($user)
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
                RequireAppSession::class,
                RequireAppPermission::class,
            ])
            ->getJson('/v3/crm/catalog/packages?q=')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', []);
    }

    public function test_proposal_send_by_email_requires_valid_recipient_before_draft_success(): void
    {
        $user = $this->createUser();
        $this->seedSolicitudCaseTables([
            'contacto_email' => '',
            'email' => '',
        ]);

        $this->actingAs($user)
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
                RequireAppSession::class,
                RequireAppPermission::class,
            ])
            ->postJson('/v3/crm/cases/solicitud/275872/proposals', [
                'title' => 'Propuesta inicial',
                'send_by' => 'email',
                'email_to' => 'no-es-email',
                'items' => [
                    [
                        'catalog_type' => 'code',
                        'catalog_id' => 101,
                        'description' => 'Consulta catalogada',
                        'quantity' => 1,
                        'unit_price' => 25,
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonFragment([
                'error' => 'Indica un correo de destino válido para enviar la propuesta',
            ]);
    }

    public function test_proposal_send_by_whatsapp_requires_phone_before_draft_success(): void
    {
        $user = $this->createUser();
        $this->seedSolicitudCaseTables([
            'contacto_telefono' => '',
            'telefono' => '',
        ]);

        $this->actingAs($user)
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
                RequireAppSession::class,
                RequireAppPermission::class,
            ])
            ->postJson('/v3/crm/cases/solicitud/275872/proposals', [
                'title' => 'Propuesta inicial',
                'send_by' => 'whatsapp',
                'phone' => '',
                'items' => [
                    [
                        'catalog_type' => 'code',
                        'catalog_id' => 101,
                        'description' => 'Consulta catalogada',
                        'quantity' => 1,
                        'unit_price' => 25,
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonFragment([
                'error' => 'Indica un teléfono para enviar la propuesta por WhatsApp',
            ]);
    }

    public function test_whatsapp_does_not_fake_success_without_real_conversation(): void
    {
        $user = $this->createUser();
        $this->seedSolicitudCaseTables();
        $this->ensureWhatsappConversationTable();

        $this->actingAs($user)
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
                RequireAppSession::class,
                RequireAppPermission::class,
            ])
            ->postJson('/v3/crm/cases/solicitud/275872/whatsapp', [
                'recipients' => ['0987107769'],
                'message' => 'Mensaje real',
            ])
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    private function ensureCrmCaseTestSchema(): void
    {
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->nullable();
                $table->string('username')->nullable();
                $table->string('password')->default('');
                $table->string('email')->default('');
                $table->string('nombre')->default('');
                $table->string('cedula')->default('');
                $table->string('registro')->default('');
                $table->string('sede')->default('');
                $table->string('especialidad')->default('');
            });
        }

        if (!Schema::hasTable('patient_data')) {
            Schema::create('patient_data', function (Blueprint $table): void {
                $table->id();
                $table->string('hc_number', 30)->nullable()->index();
                $table->string('fname')->nullable();
                $table->string('mname')->nullable();
                $table->string('lname')->nullable();
                $table->string('lname2')->nullable();
                $table->string('full_name')->nullable();
            });
        }

        if (!Schema::hasTable('solicitud_procedimiento')) {
            Schema::create('solicitud_procedimiento', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('paciente_id')->nullable();
                $table->string('hc_number', 30)->nullable()->index();
                $table->unsignedBigInteger('form_id')->nullable()->index();
                $table->string('estado')->nullable();
                $table->string('sede')->nullable();
                $table->string('sede_departamento')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('solicitud_crm_detalles')) {
            Schema::create('solicitud_crm_detalles', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('solicitud_id')->nullable()->index();
                $table->unsignedBigInteger('crm_lead_id')->nullable();
                $table->unsignedBigInteger('crm_opportunity_id')->nullable();
                $table->unsignedBigInteger('responsable_id')->nullable();
                $table->string('responsable_nombre')->nullable();
                $table->string('contacto_telefono')->nullable();
                $table->string('telefono')->nullable();
                $table->string('contacto_email')->nullable();
                $table->string('email')->nullable();
                $table->string('fuente')->nullable();
                $table->string('pipeline_stage')->nullable();
                $table->string('insurance_company')->nullable();
                $table->string('insurance_plan')->nullable();
                $table->string('insurance_code')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('solicitud_crm_notas')) {
            Schema::create('solicitud_crm_notas', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('solicitud_id')->index();
                $table->unsignedBigInteger('autor_id')->nullable();
                $table->text('nota');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('deleted_at')->nullable();
            });
        }

        if (!Schema::hasTable('crm_tasks')) {
            Schema::create('crm_tasks', function (Blueprint $table): void {
                $table->id();
                $table->string('source_type')->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('entity_type')->nullable();
                $table->string('entity_id')->nullable();
                $table->unsignedBigInteger('form_id')->nullable();
                $table->string('source_module')->nullable();
                $table->string('source_ref_id')->nullable();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('status')->default('pendiente');
                $table->string('priority')->default('normal');
                $table->unsignedBigInteger('assigned_to')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('due_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('crm_leads')) {
            Schema::create('crm_leads', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('hc_number')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('crm_contacts')) {
            Schema::create('crm_contacts', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('cedula')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('crm_customers')) {
            Schema::create('crm_customers', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('crm_opportunities')) {
            Schema::create('crm_opportunities', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('contact_id')->nullable();
                $table->string('title')->nullable();
                $table->string('stage')->nullable();
                $table->string('source')->nullable();
                $table->string('source_type')->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('crm_proposals')) {
            Schema::create('crm_proposals', function (Blueprint $table): void {
                $table->id();
                $table->string('public_hash', 64)->nullable()->unique();
                $table->string('proposal_number')->nullable();
                $table->integer('proposal_year')->nullable();
                $table->integer('sequence')->nullable();
                $table->unsignedBigInteger('lead_id')->nullable()->index();
                $table->unsignedBigInteger('crm_opportunity_id')->nullable()->index();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->string('source_type')->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->unsignedBigInteger('form_id')->nullable();
                $table->string('title')->nullable();
                $table->string('status')->default('draft');
                $table->string('currency', 3)->default('USD');
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('discount_total', 12, 2)->default(0);
                $table->decimal('tax_rate', 5, 2)->default(0);
                $table->decimal('tax_total', 12, 2)->default(0);
                $table->decimal('total', 12, 2)->default(0);
                $table->date('valid_until')->nullable();
                $table->text('notes')->nullable();
                $table->text('terms')->nullable();
                $table->json('packages_snapshot')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('crm_proposal_items')) {
            Schema::create('crm_proposal_items', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('proposal_id')->index();
                $table->unsignedBigInteger('code_id')->nullable();
                $table->unsignedBigInteger('package_id')->nullable();
                $table->string('description')->nullable();
                $table->decimal('quantity', 10, 2)->default(1);
                $table->decimal('unit_price', 12, 2)->default(0);
                $table->decimal('discount_percent', 5, 2)->default(0);
                $table->integer('sort_order')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('crm_proposal_activity')) {
            Schema::create('crm_proposal_activity', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('proposal_id')->index();
                $table->string('event', 64);
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    private function ensureWhatsappConversationTable(): void
    {
        if (!Schema::hasTable('whatsapp_conversations')) {
            Schema::create('whatsapp_conversations', function (Blueprint $table): void {
                $table->id();
                $table->string('wa_number', 32)->unique();
                $table->string('display_name')->nullable();
                $table->unsignedBigInteger('assigned_user_id')->nullable();
                $table->timestamp('assigned_at')->nullable();
                $table->boolean('needs_human')->default(false);
                $table->timestamps();
            });
        }
    }

    private function createUser(): User
    {
        $this->insertRow('users', [
            'id' => 1,
            'name' => 'CRM User',
            'username' => 'crm-user',
            'email' => 'crm@example.com',
            'password' => '',
            'nombre' => 'CRM User',
            'cedula' => '',
            'registro' => '',
            'sede' => '',
            'especialidad' => '',
        ]);

        return User::query()->findOrFail(1);
    }

    /**
     * @param array<string, mixed> $detailOverrides
     */
    private function seedSolicitudCaseTables(array $detailOverrides = []): void
    {
        $this->insertRow('patient_data', [
            'hc_number' => '0932000904',
            'fname' => 'DANIELA',
            'mname' => 'VALENTINA',
            'lname' => 'MORALES',
            'lname2' => 'MURILLO',
        ]);

        $this->insertRow('solicitud_procedimiento', [
            'id' => 275872,
            'paciente_id' => 9901,
            'hc_number' => '0932000904',
            'form_id' => 275872,
            'estado' => 'revision-codigos',
            'sede' => 'MATRIZ',
            'sede_departamento' => 'MATRIZ',
            'created_at' => '2026-06-03 08:00:00',
        ]);

        $this->insertRow('solicitud_crm_detalles', array_merge([
            'solicitud_id' => 275872,
            'responsable_id' => 1,
            'responsable_nombre' => 'CRM User',
            'contacto_telefono' => '0987107769',
            'telefono' => '0987107769',
            'contacto_email' => 'paciente@example.com',
            'email' => 'paciente@example.com',
            'fuente' => 'Convenio',
            'insurance_company' => 'Humana',
            'insurance_plan' => 'Plan Azul',
            'insurance_code' => 'HUM-001',
            'created_at' => '2026-06-03 08:01:00',
            'updated_at' => '2026-06-03 08:01:00',
        ], $detailOverrides));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function insertRow(string $table, array $payload): void
    {
        $columns = Schema::getColumnListing($table);
        $filtered = array_intersect_key($payload, array_flip($columns));

        DB::table($table)->insert($filtered);
    }
}

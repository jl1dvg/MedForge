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
     * @param array<string, mixed> $payload
     */
    private function insertRow(string $table, array $payload): void
    {
        $columns = Schema::getColumnListing($table);
        $filtered = array_intersect_key($payload, array_flip($columns));

        DB::table($table)->insert($filtered);
    }
}

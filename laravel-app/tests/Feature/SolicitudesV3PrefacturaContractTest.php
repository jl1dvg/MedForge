<?php

declare(strict_types=1);

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
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SolicitudesV3PrefacturaContractTest extends TestCase
{
    use RefreshDatabase;

    protected function refreshDatabase(): void
    {
        $this->ensureSchema();

        foreach ([
            'diagnosticos_asignados',
            'solicitud_crm_adjuntos',
            'solicitud_crm_notas',
            'solicitud_crm_detalles',
            'crm_leads',
            'procedimiento_proyectado',
            'consulta_data',
            'solicitud_procedimiento',
            'patient_data',
            'users',
        ] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }
    }

    public function test_detalle_includes_diagnosticos_asignados_by_form_id(): void
    {
        $userId = DB::table('users')->insertGetId([
            'username' => 'prefactura',
            'nombre' => 'Prefactura Test',
            'profile_photo' => null,
        ]);
        $user = User::query()->findOrFail($userId);

        DB::table('patient_data')->insert([
            'hc_number' => '0932000904',
            'fname' => 'DANIELA',
            'mname' => 'VALENTINA',
            'lname' => 'MORALES',
            'lname2' => 'MURILLO',
            'celular' => '0987107769',
            'afiliacion' => 'SN4 - SALUD S.A. - SALUD (REEMBOLSO) NIVEL 4',
        ]);

        DB::table('solicitud_procedimiento')->insert([
            'id' => 275872,
            'form_id' => 275872,
            'hc_number' => '0932000904',
            'tipo' => 'cirugia',
            'afiliacion' => 'SN4 - SALUD S.A. - SALUD (REEMBOLSO) NIVEL 4',
            'sede' => 'MATRIZ',
            'procedimiento' => 'CYP-OCU-003 - CHALAZION',
            'doctor' => 'INGRID MARIA PATERNINA ESCUDERO',
            'estado' => 'revision-codigos',
            'fecha' => '2026-05-03 19:00:00',
            'duracion' => 30,
            'ojo' => 'DERECHO',
            'prioridad' => 'normal',
            'producto' => null,
            'observacion' => null,
            'turno' => null,
            'crm_opportunity_id' => null,
            'created_at' => '2026-05-03 19:00:00',
            'updated_at' => '2026-05-03 19:00:00',
        ]);

        DB::table('diagnosticos_asignados')->insert([
            'id' => 70030,
            'form_id' => 275872,
            'fuente' => 'consulta',
            'dx_code' => 'H00',
            'descripcion' => 'ORZUELO Y CALACIO',
            'definitivo' => 1,
            'lateralidad' => 'DERECHO',
            'selector' => null,
        ]);

        $this->actingAs($user)
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
                RequireAppSession::class,
                RequireAppPermission::class,
            ])
            ->getJson('/v3/solicitudes/275872/detalle')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.diagnosticos.0.dx_code', 'H00')
            ->assertJsonPath('data.diagnosticos.0.descripcion', 'ORZUELO Y CALACIO')
            ->assertJsonPath('data.diagnosticos.0.lateralidad', 'DERECHO');
    }

    private function ensureSchema(): void
    {
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('username')->nullable();
                $table->string('nombre')->nullable();
                $table->string('profile_photo')->nullable();
                $table->string('email')->default('');
                $table->string('password')->default('');
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
                $table->string('celular')->nullable();
                $table->string('afiliacion')->nullable();
            });
        }

        if (!Schema::hasTable('solicitud_procedimiento')) {
            Schema::create('solicitud_procedimiento', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('form_id')->nullable()->index();
                $table->string('hc_number', 30)->nullable()->index();
                $table->string('tipo')->nullable();
                $table->string('afiliacion')->nullable();
                $table->string('sede')->nullable();
                $table->string('procedimiento')->nullable();
                $table->string('doctor')->nullable();
                $table->string('estado')->nullable();
                $table->dateTime('fecha')->nullable();
                $table->unsignedInteger('duracion')->nullable();
                $table->string('ojo')->nullable();
                $table->string('prioridad')->nullable();
                $table->string('producto')->nullable();
                $table->text('observacion')->nullable();
                $table->unsignedInteger('turno')->nullable();
                $table->unsignedBigInteger('crm_opportunity_id')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('consulta_data')) {
            Schema::create('consulta_data', function (Blueprint $table): void {
                $table->id();
                $table->string('hc_number', 30)->nullable()->index();
                $table->unsignedBigInteger('form_id')->nullable()->index();
                $table->dateTime('fecha')->nullable();
            });
        }

        if (!Schema::hasTable('procedimiento_proyectado')) {
            Schema::create('procedimiento_proyectado', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('form_id')->nullable()->index();
                $table->string('hc_number', 30)->nullable()->index();
                $table->boolean('sigcenter_present')->default(true);
            });
        }

        if (!Schema::hasTable('solicitud_crm_detalles')) {
            Schema::create('solicitud_crm_detalles', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('solicitud_id')->nullable()->index();
                $table->unsignedBigInteger('responsable_id')->nullable();
                $table->unsignedBigInteger('crm_lead_id')->nullable();
                $table->unsignedBigInteger('crm_opportunity_id')->nullable();
                $table->unsignedBigInteger('crm_project_id')->nullable();
                $table->string('pipeline_stage')->nullable();
                $table->string('fuente')->nullable();
                $table->string('contacto_email')->nullable();
                $table->string('contacto_telefono')->nullable();
                $table->json('followers')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('crm_leads')) {
            Schema::create('crm_leads', function (Blueprint $table): void {
                $table->id();
                $table->string('status')->nullable();
                $table->string('source')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('solicitud_crm_notas')) {
            Schema::create('solicitud_crm_notas', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('solicitud_id')->index();
                $table->text('nota')->nullable();
            });
        }

        if (!Schema::hasTable('solicitud_crm_adjuntos')) {
            Schema::create('solicitud_crm_adjuntos', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('solicitud_id')->index();
            });
        }

        if (!Schema::hasTable('diagnosticos_asignados')) {
            Schema::create('diagnosticos_asignados', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('form_id')->index();
                $table->string('fuente')->nullable();
                $table->string('dx_code')->nullable();
                $table->string('descripcion')->nullable();
                $table->unsignedTinyInteger('definitivo')->nullable();
                $table->string('lateralidad')->nullable();
                $table->string('selector')->nullable();
            });
        }
    }
}

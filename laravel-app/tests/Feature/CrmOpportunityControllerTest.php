<?php

namespace Tests\Feature;

use App\Http\Middleware\LegacySessionBridge;
use App\Http\Middleware\RequireAppPermission;
use App\Http\Middleware\RequireAppSession;
use App\Http\Middleware\RequireLegacyPermission;
use App\Http\Middleware\RequireLegacySession;
use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\CrmOpportunity;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CrmOpportunityControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            'patient_data',
            'examen_crm_detalles',
            'solicitud_crm_detalles',
            'consulta_examenes',
            'solicitud_procedimiento',
            'crm_activities',
            'crm_opportunities',
            'crm_contacts',
            'users',
            'roles',
        ] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::create('roles', fn (Blueprint $t) => $t->id());
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username');
            $table->string('password')->default('');
            $table->string('email')->default('');
            $table->string('nombre')->default('');
            $table->string('cedula')->default('');
            $table->string('registro')->default('');
            $table->string('sede')->default('');
            $table->string('especialidad')->default('');
            $table->text('permisos')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
        });
        Schema::create('crm_contacts', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('patient_id')->nullable();
            $table->string('name'); $table->string('phone');
            $table->string('email')->nullable(); $table->string('cedula')->nullable()->unique();
            $table->string('resolution', 20)->default('provisional');
            $table->string('source', 30)->default('manual'); $table->timestamps();
        });
        Schema::create('crm_opportunities', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('contact_id')->index();
            $table->string('title'); $table->string('stage', 30)->default('nuevo');
            $table->string('phase', 20)->default('operational');
            $table->string('source', 30)->default('manual');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_type', 255)->nullable();
            $table->string('afiliacion_tipo', 20)->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('lost_reason', 500)->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('escalation_at')->nullable();
            $table->timestamps();
        });
        Schema::create('crm_activities', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('opportunity_id')->index();
            $table->string('type', 30)->default('nota'); $table->text('description');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_type', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('patient_data', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number', 30)->nullable()->index();
            $table->string('afiliacion', 255)->nullable();
        });
        Schema::create('solicitud_procedimiento', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('crm_opportunity_id')->nullable()->index();
        });
        Schema::create('consulta_examenes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('crm_opportunity_id')->nullable()->index();
        });
        Schema::create('solicitud_crm_detalles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('solicitud_id')->nullable()->index();
            $table->unsignedBigInteger('crm_opportunity_id')->nullable()->index();
        });
        Schema::create('examen_crm_detalles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('examen_id')->nullable()->index();
            $table->unsignedBigInteger('crm_opportunity_id')->nullable()->index();
        });
    }

    private function makeUser(): User
    {
        return User::query()->create(['username' => 'test', 'email' => 'test@test.com']);
    }

    private function makeContact(): CrmContact
    {
        return CrmContact::query()->create(['name' => 'María', 'phone' => '+5931', 'source' => 'whatsapp']);
    }

    public function test_index_returns_paginated_opportunities(): void
    {
        $contact = $this->makeContact();
        CrmOpportunity::query()->create(['contact_id' => $contact->id, 'title' => 'Op 1', 'stage' => 'nuevo', 'source' => 'whatsapp']);
        CrmOpportunity::query()->create(['contact_id' => $contact->id, 'title' => 'Op 2', 'stage' => 'contactado', 'source' => 'solicitud']);

        $this->actingAs($this->makeUser())
            ->withoutMiddleware([LegacySessionBridge::class, RequireLegacySession::class, RequireLegacyPermission::class, RequireAppSession::class, RequireAppPermission::class])
            ->getJson('/v2/crm/opportunities')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_index_filters_by_stage(): void
    {
        $contact = $this->makeContact();
        CrmOpportunity::query()->create(['contact_id' => $contact->id, 'title' => 'Nuevo', 'stage' => 'nuevo', 'source' => 'whatsapp']);
        CrmOpportunity::query()->create(['contact_id' => $contact->id, 'title' => 'Contactado', 'stage' => 'contactado', 'source' => 'solicitud']);

        $this->actingAs($this->makeUser())
            ->withoutMiddleware([LegacySessionBridge::class, RequireLegacySession::class, RequireLegacyPermission::class, RequireAppSession::class, RequireAppPermission::class])
            ->getJson('/v2/crm/opportunities?stage=nuevo')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.stage', 'nuevo');
    }

    public function test_update_changes_stage(): void
    {
        $contact = $this->makeContact();
        $opp = CrmOpportunity::query()->create(['contact_id' => $contact->id, 'title' => 'Test', 'stage' => 'nuevo', 'source' => 'whatsapp']);

        $this->actingAs($this->makeUser())
            ->withoutMiddleware([LegacySessionBridge::class, RequireLegacySession::class, RequireLegacyPermission::class, RequireAppSession::class, RequireAppPermission::class])
            ->patchJson("/v2/crm/opportunities/{$opp->id}", ['stage' => 'contactado'])
            ->assertOk()
            ->assertJsonPath('data.stage', 'contactado');
    }

    public function test_update_rejects_invalid_stage(): void
    {
        $contact = $this->makeContact();
        $opp = CrmOpportunity::query()->create(['contact_id' => $contact->id, 'title' => 'Test', 'stage' => 'nuevo', 'source' => 'whatsapp']);

        $this->actingAs($this->makeUser())
            ->withoutMiddleware([LegacySessionBridge::class, RequireLegacySession::class, RequireLegacyPermission::class, RequireAppSession::class, RequireAppPermission::class])
            ->patchJson("/v2/crm/opportunities/{$opp->id}", ['stage' => 'etapa_falsa'])
            ->assertStatus(422);
    }

    public function test_index_excludes_public_affiliation_from_patient_data_even_when_cached_column_is_private(): void
    {
        $publicContact = CrmContact::query()->create([
            'name' => 'Paciente Público',
            'phone' => '+5932',
            'cedula' => 'PUBLICO-1',
            'source' => 'solicitud',
        ]);
        $privateContact = CrmContact::query()->create([
            'name' => 'Paciente Privado',
            'phone' => '+5933',
            'cedula' => 'PRIVADO-1',
            'source' => 'solicitud',
        ]);
        \Illuminate\Support\Facades\DB::table('patient_data')->insert([
            ['hc_number' => 'PUBLICO-1', 'afiliacion' => 'IESS'],
            ['hc_number' => 'PRIVADO-1', 'afiliacion' => 'Seguro privado'],
        ]);
        CrmOpportunity::query()->create(['contact_id' => $publicContact->id, 'title' => 'Publica', 'stage' => 'nuevo', 'source' => 'solicitud', 'afiliacion_tipo' => 'privado']);
        CrmOpportunity::query()->create(['contact_id' => $privateContact->id, 'title' => 'Privada', 'stage' => 'nuevo', 'source' => 'solicitud', 'afiliacion_tipo' => 'publico']);

        $this->actingAs($this->makeUser())
            ->withoutMiddleware([LegacySessionBridge::class, RequireLegacySession::class, RequireLegacyPermission::class, RequireAppSession::class, RequireAppPermission::class])
            ->getJson('/v2/crm/opportunities?include_publico=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Privada');
    }

    public function test_index_public_affiliation_filter_returns_no_public_opportunities_in_central_crm(): void
    {
        $publicContact = CrmContact::query()->create([
            'name' => 'Paciente Público',
            'phone' => '+5932',
            'cedula' => 'PUBLICO-1',
            'source' => 'solicitud',
        ]);
        \Illuminate\Support\Facades\DB::table('patient_data')->insert([
            ['hc_number' => 'PUBLICO-1', 'afiliacion' => 'IESS'],
        ]);
        CrmOpportunity::query()->create(['contact_id' => $publicContact->id, 'title' => 'Publica', 'stage' => 'nuevo', 'source' => 'solicitud']);

        $this->actingAs($this->makeUser())
            ->withoutMiddleware([LegacySessionBridge::class, RequireLegacySession::class, RequireLegacyPermission::class, RequireAppSession::class, RequireAppPermission::class])
            ->getJson('/v2/crm/opportunities?afiliacion=publico')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_index_combines_source_and_patient_affiliation_filters_when_source_is_direct(): void
    {
        $privateSolicitudContact = CrmContact::query()->create([
            'name' => 'Paciente Solicitud Privado',
            'phone' => '+5934',
            'cedula' => 'PRIV-SOL-1',
            'source' => 'solicitud',
        ]);
        $privateExamenContact = CrmContact::query()->create([
            'name' => 'Paciente Examen Privado',
            'phone' => '+5935',
            'cedula' => 'PRIV-EX-1',
            'source' => 'examen',
        ]);
        $particularSolicitudContact = CrmContact::query()->create([
            'name' => 'Paciente Solicitud Particular',
            'phone' => '+5936',
            'cedula' => 'PART-SOL-1',
            'source' => 'solicitud',
        ]);

        \Illuminate\Support\Facades\DB::table('patient_data')->insert([
            ['hc_number' => 'PRIV-SOL-1', 'afiliacion' => 'Seguro privado'],
            ['hc_number' => 'PRIV-EX-1', 'afiliacion' => 'Seguro privado'],
            ['hc_number' => 'PART-SOL-1', 'afiliacion' => 'Particular'],
        ]);

        CrmOpportunity::query()->create(['contact_id' => $privateSolicitudContact->id, 'title' => 'Solicitud privada', 'stage' => 'nuevo', 'source' => 'solicitud']);
        CrmOpportunity::query()->create(['contact_id' => $privateExamenContact->id, 'title' => 'Examen privado', 'stage' => 'nuevo', 'source' => 'examen']);
        CrmOpportunity::query()->create(['contact_id' => $particularSolicitudContact->id, 'title' => 'Solicitud particular', 'stage' => 'nuevo', 'source' => 'solicitud']);

        $this->actingAs($this->makeUser())
            ->withoutMiddleware([LegacySessionBridge::class, RequireLegacySession::class, RequireLegacyPermission::class, RequireAppSession::class, RequireAppPermission::class])
            ->getJson('/v2/crm/opportunities?source=solicitud&afiliacion=privado')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Solicitud privada')
            ->assertJsonPath('data.0.effective_source', 'solicitud');
    }

    public function test_index_source_filter_uses_clinical_activity_when_opportunity_source_is_manual(): void
    {
        $contact = CrmContact::query()->create([
            'name' => 'Paciente Manual Con Solicitud',
            'phone' => '+5937',
            'cedula' => 'MANUAL-SOL-1',
            'source' => 'manual',
        ]);
        \Illuminate\Support\Facades\DB::table('patient_data')->insert([
            ['hc_number' => 'MANUAL-SOL-1', 'afiliacion' => 'Seguro privado'],
        ]);
        $opp = CrmOpportunity::query()->create([
            'contact_id' => $contact->id,
            'title' => 'Oportunidad manual',
            'stage' => 'nuevo',
            'source' => 'manual',
        ]);
        CrmActivity::query()->create([
            'opportunity_id' => $opp->id,
            'type' => 'solicitud',
            'description' => 'Solicitud creada',
            'source_id' => 99,
            'source_type' => 'solicitud_procedimiento',
        ]);

        $this->actingAs($this->makeUser())
            ->withoutMiddleware([LegacySessionBridge::class, RequireLegacySession::class, RequireLegacyPermission::class, RequireAppSession::class, RequireAppPermission::class])
            ->getJson('/v2/crm/opportunities?source=solicitud&afiliacion=privado')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Oportunidad manual')
            ->assertJsonPath('data.0.source', 'manual')
            ->assertJsonPath('data.0.effective_source', 'solicitud');
    }

    public function test_index_legacy_migrated_lead_is_not_labeled_as_whatsapp(): void
    {
        $contact = CrmContact::query()->create([
            'name' => 'Paciente Lead Migrado',
            'phone' => '+5938',
            'cedula' => 'LEGACY-1',
            'source' => 'whatsapp',
        ]);
        \Illuminate\Support\Facades\DB::table('patient_data')->insert([
            ['hc_number' => 'LEGACY-1', 'afiliacion' => 'Seguro privado'],
        ]);
        CrmOpportunity::query()->create([
            'contact_id' => $contact->id,
            'title' => 'Lead migrado: Paciente Lead Migrado',
            'stage' => 'nuevo',
            'source' => 'whatsapp',
            'source_type' => 'legacy_crm_lead',
        ]);

        $this->actingAs($this->makeUser())
            ->withoutMiddleware([LegacySessionBridge::class, RequireLegacySession::class, RequireLegacyPermission::class, RequireAppSession::class, RequireAppPermission::class])
            ->getJson('/v2/crm/opportunities?afiliacion=privado')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.source', 'whatsapp')
            ->assertJsonPath('data.0.effective_source', 'legacy');

        $this->actingAs($this->makeUser())
            ->withoutMiddleware([LegacySessionBridge::class, RequireLegacySession::class, RequireLegacyPermission::class, RequireAppSession::class, RequireAppPermission::class])
            ->getJson('/v2/crm/opportunities?source=whatsapp&afiliacion=privado')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_index_effective_source_prefers_operational_table_link_over_historical_whatsapp_source(): void
    {
        $contact = CrmContact::query()->create([
            'name' => 'Paciente Lead Con Solicitud',
            'phone' => '+5938',
            'cedula' => 'WA-SOL-1',
            'source' => 'whatsapp',
        ]);
        \Illuminate\Support\Facades\DB::table('patient_data')->insert([
            ['hc_number' => 'WA-SOL-1', 'afiliacion' => 'Seguro privado'],
        ]);
        $opp = CrmOpportunity::query()->create([
            'contact_id' => $contact->id,
            'title' => 'Lead migrado: Paciente Lead Con Solicitud',
            'stage' => 'nuevo',
            'source' => 'whatsapp',
            'source_type' => 'legacy_crm_lead',
        ]);
        \Illuminate\Support\Facades\DB::table('solicitud_procedimiento')->insert([
            'id' => 100,
            'crm_opportunity_id' => $opp->id,
        ]);
        \Illuminate\Support\Facades\DB::table('solicitud_crm_detalles')->insert([
            'solicitud_id' => 100,
            'crm_opportunity_id' => $opp->id,
        ]);

        $this->actingAs($this->makeUser())
            ->withoutMiddleware([LegacySessionBridge::class, RequireLegacySession::class, RequireLegacyPermission::class, RequireAppSession::class, RequireAppPermission::class])
            ->getJson('/v2/crm/opportunities?afiliacion=privado')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.source', 'whatsapp')
            ->assertJsonPath('data.0.effective_source', 'solicitud');

        $this->actingAs($this->makeUser())
            ->withoutMiddleware([LegacySessionBridge::class, RequireLegacySession::class, RequireLegacyPermission::class, RequireAppSession::class, RequireAppPermission::class])
            ->getJson('/v2/crm/opportunities?source=solicitud&afiliacion=privado')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.effective_source', 'solicitud');
    }
}

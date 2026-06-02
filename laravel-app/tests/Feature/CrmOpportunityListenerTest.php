<?php

namespace Tests\Feature;

use App\Events\Crm\ExamenSolicitado;
use App\Events\Crm\ExamenEstadoCambiado;
use App\Events\Crm\SolicitudCreada;
use App\Events\Crm\SolicitudKanbanEstadoCambiado;
use App\Events\Crm\WhatsappLeadQualified;
use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\CrmOpportunity;
use App\Models\CrmStageMapping;
use App\Models\WhatsappLead;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CrmOpportunityListenerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            'solicitud_estado_log',
            'consulta_examenes',
            'solicitud_procedimiento',
            'patient_data',
            'crm_stage_mappings',
            'crm_activities',
            'crm_opportunities',
            'crm_contacts',
            'whatsapp_leads',
        ] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::create('crm_contacts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->string('name', 255);
            $table->string('phone', 30);
            $table->string('email', 255)->nullable();
            $table->string('cedula', 30)->nullable()->unique();
            $table->string('resolution', 20)->default('provisional');
            $table->string('source', 30)->default('manual');
            $table->timestamps();
        });
        Schema::create('crm_opportunities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('contact_id')->index();
            $table->string('title', 255);
            $table->string('stage', 30)->default('nuevo');
            $table->string('phase', 20)->default('operational');
            $table->string('source', 30)->default('manual');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_type', 255)->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('lost_reason', 500)->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('escalation_at')->nullable();
            $table->timestamps();
        });
        Schema::create('crm_activities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('opportunity_id')->index();
            $table->string('type', 30)->default('nota');
            $table->text('description');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_type', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('whatsapp_leads', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->index();
            $table->unsignedBigInteger('crm_lead_id')->nullable();
            $table->string('wa_number', 30);
            $table->string('display_name', 255)->nullable();
            $table->string('hc_number', 100)->nullable();
            $table->string('cedula', 30)->nullable();
            $table->string('patient_full_name', 255)->nullable();
            $table->text('motivo_baja');
            $table->string('status', 30)->default('pendiente');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();
        });
        Schema::create('crm_stage_mappings', function (Blueprint $table): void {
            $table->id();
            $table->string('source_type', 60);
            $table->string('source_state', 80);
            $table->string('crm_stage', 40);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('solicitud_procedimiento', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable();
            $table->string('estado')->nullable();
            $table->string('afiliacion')->nullable();
        });
        Schema::create('consulta_examenes', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable();
            $table->string('estado')->nullable();
        });
        Schema::create('patient_data', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable()->index();
            $table->string('afiliacion')->nullable();
        });
        Schema::create('solicitud_estado_log', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('solicitud_id');
            $table->string('estado_anterior')->nullable();
            $table->string('estado_nuevo')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('nota')->nullable();
            $table->string('origen')->nullable();
            $table->timestamps();
        });

        CrmStageMapping::query()->insert([
            ['source_type' => 'solicitud_procedimiento', 'source_state' => 'llamado', 'crm_stage' => CrmOpportunity::STAGE_CONTACTADO, 'is_active' => true],
            ['source_type' => 'solicitud_procedimiento', 'source_state' => 'programada', 'crm_stage' => CrmOpportunity::STAGE_COMPROMETIDO, 'is_active' => true],
            ['source_type' => 'solicitud_procedimiento', 'source_state' => 'completado', 'crm_stage' => CrmOpportunity::STAGE_GANADO, 'is_active' => true],
            ['source_type' => 'consulta_examenes', 'source_state' => 'llamado', 'crm_stage' => CrmOpportunity::STAGE_CONTACTADO, 'is_active' => true],
            ['source_type' => 'consulta_examenes', 'source_state' => 'listo para agenda', 'crm_stage' => CrmOpportunity::STAGE_COMPROMETIDO, 'is_active' => true],
            ['source_type' => 'consulta_examenes', 'source_state' => 'completado', 'crm_stage' => CrmOpportunity::STAGE_GANADO, 'is_active' => true],
        ]);
    }

    public function test_whatsapp_lead_creates_nuevo_opportunity(): void
    {
        $wlead = WhatsappLead::query()->create([
            'conversation_id' => 1,
            'wa_number'       => '+593991234567',
            'display_name'    => 'María González',
            'cedula'          => null,
            'motivo_baja'     => 'Interesada en cirugía',
        ]);

        event(new WhatsappLeadQualified($wlead));

        $this->assertEquals(1, CrmContact::query()->count());
        $this->assertEquals(1, CrmOpportunity::query()->count());
        $this->assertEquals('nuevo', CrmOpportunity::query()->first()->stage);
        $this->assertEquals('whatsapp', CrmOpportunity::query()->first()->source);
    }

    public function test_solicitud_creada_creates_nuevo_opportunity(): void
    {
        event(new SolicitudCreada(
            solicitudId: 42,
            solicitudData: [
                'paciente_nombre'   => 'Carlos Mendoza',
                'paciente_cedula'   => '0912345678',
                'paciente_telefono' => '+593987654321',
                'servicio'          => 'Consulta cardiología',
            ],
        ));

        $this->assertEquals(1, CrmOpportunity::query()->count());
        $this->assertEquals('nuevo', CrmOpportunity::query()->first()->stage);
        $this->assertEquals(42, CrmOpportunity::query()->first()->source_id);
    }

    public function test_examen_solicitado_creates_nuevo_opportunity(): void
    {
        event(new ExamenSolicitado(
            examenId: 77,
            examenData: [
                'paciente_nombre'    => 'Laura Rivas',
                'paciente_cedula'    => '1712345678',
                'paciente_telefono'  => '+593995551234',
                'descripcion_examen' => 'Radiografía de tórax',
            ],
        ));

        $this->assertEquals(1, CrmOpportunity::query()->count());
        $this->assertEquals('nuevo', CrmOpportunity::query()->first()->stage);
        $this->assertEquals('examen', CrmOpportunity::query()->first()->source);
        $this->assertEquals(77, CrmOpportunity::query()->first()->source_id);
    }

    public function test_duplicate_cedula_reuses_contact(): void
    {
        CrmContact::query()->create([
            'name' => 'Carlos', 'phone' => '+593987654321',
            'cedula' => '0912345678', 'resolution' => 'identified', 'source' => 'whatsapp',
        ]);

        event(new SolicitudCreada(
            solicitudId: 42,
            solicitudData: [
                'paciente_nombre'   => 'Carlos Mendoza',
                'paciente_cedula'   => '0912345678',
                'paciente_telefono' => '+593987654321',
                'servicio'          => 'Consulta cardiología',
            ],
        ));

        $this->assertEquals(1, CrmContact::query()->count());
        $this->assertEquals(1, CrmOpportunity::query()->count());
    }

    public function test_upsert_creates_opportunity_when_contact_has_none(): void
    {
        $contact = \App\Models\CrmContact::query()->create([
            'name' => 'Nuevo', 'phone' => '0999000010', 'resolution' => 'provisional', 'source' => 'examen',
        ]);

        $service = app(\App\Modules\CRM\Services\CrmOpportunityService::class);
        $opp = $service->upsertFromEvent(
            contact: $contact,
            title: 'Examen: OCT Macular',
            source: 'examen',
            sourceId: 99,
            sourceType: 'consulta_examenes',
        );

        $this->assertDatabaseHas('crm_opportunities', [
            'contact_id' => $contact->id,
            'stage'      => 'nuevo',
            'phase'      => 'operational',
        ]);
        $this->assertDatabaseHas('crm_activities', [
            'opportunity_id' => $opp->id,
            'type'           => 'examen',
            'source_id'      => 99,
        ]);
    }

    public function test_upsert_creates_activity_when_contact_already_has_opportunity(): void
    {
        $contact = \App\Models\CrmContact::query()->create([
            'name' => 'Existente', 'phone' => '0999000011', 'resolution' => 'provisional', 'source' => 'examen',
        ]);
        \App\Models\CrmOpportunity::query()->create([
            'contact_id' => $contact->id, 'title' => 'Primera vez', 'stage' => 'contactado', 'source' => 'examen',
        ]);

        $before = \App\Models\CrmOpportunity::query()->where('contact_id', $contact->id)->count();

        $service = app(\App\Modules\CRM\Services\CrmOpportunityService::class);
        $service->upsertFromEvent(
            contact: $contact,
            title: 'Examen: Angiografía',
            source: 'examen',
            sourceId: 100,
            sourceType: 'consulta_examenes',
        );

        // No new opportunity created
        $this->assertEquals($before, \App\Models\CrmOpportunity::query()->where('contact_id', $contact->id)->count());
        // Activity created
        $this->assertDatabaseHas('crm_activities', ['source_id' => 100, 'type' => 'examen']);
    }

    public function test_log_clinical_creates_activity_with_source(): void
    {
        $contact = \App\Models\CrmContact::query()->create([
            'name' => 'Test', 'phone' => '0999000001', 'resolution' => 'provisional', 'source' => 'examen',
        ]);
        $opp = \App\Models\CrmOpportunity::query()->create([
            'contact_id' => $contact->id, 'title' => 'Test', 'stage' => 'nuevo', 'source' => 'examen',
        ]);

        $service = app(\App\Modules\CRM\Services\CrmActivityService::class);
        $activity = $service->logClinical(
            opportunityId: $opp->id,
            type: \App\Models\CrmActivity::TYPE_EXAMEN,
            description: 'OCT Macular realizado',
            sourceId: 42,
            sourceType: 'consulta_examenes',
        );

        $this->assertEquals('examen', $activity->type);
        $this->assertEquals(42, $activity->source_id);
        $this->assertEquals('consulta_examenes', $activity->source_type);
    }

    public function test_solicitud_turno_llamado_records_activity_without_advancing_stage(): void
    {
        $opp = $this->createLinkedOpportunity('solicitud', 'solicitud_procedimiento', 42, CrmOpportunity::STAGE_NUEVO);
        $before = CrmActivity::query()->where('opportunity_id', $opp->id)->count();

        event(new SolicitudKanbanEstadoCambiado(
            solicitudId: 42,
            kanbanSlug: 'llamado',
            estadoAnterior: 'recibida',
        ));

        $this->assertSame(CrmOpportunity::STAGE_NUEVO, $opp->fresh()->stage);
        $this->assertSame($before + 1, CrmActivity::query()->where('opportunity_id', $opp->id)->count());
        $this->assertDatabaseHas('crm_activities', [
            'opportunity_id' => $opp->id,
            'type' => CrmActivity::TYPE_SOLICITUD,
            'description' => 'Turno llamado al counter del coordinador',
            'source_id' => 42,
            'source_type' => 'solicitud_procedimiento',
        ]);
    }

    public function test_examen_turno_llamado_records_activity_without_commercial_contact(): void
    {
        $opp = $this->createLinkedOpportunity('examen', 'consulta_examenes', 77, CrmOpportunity::STAGE_NUEVO);
        $before = CrmActivity::query()->where('opportunity_id', $opp->id)->count();

        event(new ExamenEstadoCambiado(
            examenId: 77,
            nuevoEstado: 'llamado',
            estadoAnterior: 'recibido',
        ));

        $this->assertSame(CrmOpportunity::STAGE_NUEVO, $opp->fresh()->stage);
        $this->assertSame($before + 1, CrmActivity::query()->where('opportunity_id', $opp->id)->count());
        $this->assertDatabaseHas('crm_activities', [
            'opportunity_id' => $opp->id,
            'type' => CrmActivity::TYPE_EXAMEN,
            'description' => 'Turno llamado al counter del coordinador',
            'source_id' => 77,
            'source_type' => 'consulta_examenes',
        ]);
    }

    public function test_operational_programada_advances_crm_to_comprometido(): void
    {
        $opp = $this->createLinkedOpportunity('solicitud', 'solicitud_procedimiento', 42, CrmOpportunity::STAGE_NUEVO);

        event(new SolicitudKanbanEstadoCambiado(
            solicitudId: 42,
            kanbanSlug: 'programada',
            estadoAnterior: 'listo-para-agenda',
        ));

        $this->assertSame(CrmOpportunity::STAGE_COMPROMETIDO, $opp->fresh()->stage);
    }

    public function test_operational_completado_does_not_override_perdido_opportunity(): void
    {
        $opp = $this->createLinkedOpportunity('solicitud', 'solicitud_procedimiento', 42, CrmOpportunity::STAGE_PERDIDO);

        event(new SolicitudKanbanEstadoCambiado(
            solicitudId: 42,
            kanbanSlug: 'completado',
            estadoAnterior: 'programada',
        ));

        $this->assertSame(CrmOpportunity::STAGE_PERDIDO, $opp->fresh()->stage);
    }

    public function test_crm_stage_change_does_not_update_operational_estado(): void
    {
        $opp = $this->createLinkedOpportunity('solicitud', 'solicitud_procedimiento', 42, CrmOpportunity::STAGE_NUEVO);
        \Illuminate\Support\Facades\DB::table('solicitud_procedimiento')->insert([
            'id' => 42,
            'hc_number' => '0912345678',
            'estado' => 'recibida',
        ]);

        app(\App\Modules\CRM\Services\CrmOpportunityService::class)
            ->changeStage($opp, CrmOpportunity::STAGE_COMPROMETIDO);

        $this->assertSame('recibida', \Illuminate\Support\Facades\DB::table('solicitud_procedimiento')->where('id', 42)->value('estado'));
        $this->assertDatabaseMissing('crm_activities', ['type' => 'conflicto_sync']);
    }

    public function test_classify_afiliacion_recognizes_public_and_private_categories(): void
    {
        $this->assertSame('publico', \App\Listeners\CrmOpportunityListener::classifyAfiliacion('IESS SEGURO GENERAL'));
        $this->assertSame('publico', \App\Listeners\CrmOpportunityListener::classifyAfiliacion('Jubilado campesino'));
        $this->assertSame('particular', \App\Listeners\CrmOpportunityListener::classifyAfiliacion('Particular'));
        $this->assertSame('fundacional', \App\Listeners\CrmOpportunityListener::classifyAfiliacion('Fundación'));
        $this->assertSame('privado', \App\Listeners\CrmOpportunityListener::classifyAfiliacion('BMI Salud'));
        $this->assertSame('sin_dato', \App\Listeners\CrmOpportunityListener::classifyAfiliacion(''));
    }

    private function createLinkedOpportunity(string $source, string $sourceType, int $sourceId, string $stage): CrmOpportunity
    {
        $contact = CrmContact::query()->create([
            'name' => 'Paciente',
            'phone' => '0999000099',
            'cedula' => '0912345678',
            'resolution' => 'identified',
            'source' => $source,
        ]);

        $opp = CrmOpportunity::query()->create([
            'contact_id' => $contact->id,
            'title' => 'Oportunidad',
            'stage' => $stage,
            'source' => $source,
            'source_id' => $sourceId,
            'source_type' => $sourceType,
        ]);

        CrmActivity::query()->create([
            'opportunity_id' => $opp->id,
            'type' => $source,
            'description' => 'Actividad inicial',
            'source_id' => $sourceId,
            'source_type' => $sourceType,
        ]);

        return $opp;
    }
}

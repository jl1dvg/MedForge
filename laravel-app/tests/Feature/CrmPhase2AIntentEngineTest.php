<?php

namespace Tests\Feature;

use App\Events\Crm\WhatsappLeadQualified;
use App\Models\CrmContact;
use App\Models\CrmIntentLead;
use App\Models\CrmOpportunity;
use App\Modules\CRM\Services\CrmIntentLeadService;
use App\Modules\CRM\Services\CrmOpportunityService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CrmPhase2AIntentEngineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'crm_activities', 'crm_intent_leads', 'crm_opportunities', 'crm_contacts',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('crm_contacts', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('cedula', 20)->nullable();
            $table->string('resolution', 20)->default('provisional');
            $table->timestamps();
        });

        Schema::create('crm_opportunities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('contact_id');
            $table->unique('contact_id'); // UNIQUE preserved in Phase 2A
            $table->string('title');
            $table->string('stage', 30)->default('nuevo');
            $table->string('phase', 20)->default('operational');
            $table->string('source', 30)->default('manual');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_type')->nullable();
            $table->string('afiliacion_tipo', 20)->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('lost_reason', 500)->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('escalation_at')->nullable();
            $table->string('procedure_group', 100)->nullable();
            $table->enum('lateralidad', ['OD', 'OI', 'AO'])->nullable();
            $table->timestamp('episode_started_at')->nullable();
            $table->unsignedBigInteger('previous_opportunity_id')->nullable();
            $table->enum('opportunity_type', ['recurrente', 'unica', 'diagnostico'])->nullable();
            $table->tinyInteger('continuity_flag')->default(0);
            $table->timestamps();
        });

        Schema::create('crm_activities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('opportunity_id');
            $table->string('type', 30);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_type', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('crm_intent_leads', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('contact_id');
            $table->string('source', 30)->default('whatsapp');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_type', 100)->nullable();
            $table->string('motivo', 500)->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->enum('status', ['nuevo', 'contactado', 'calificado', 'convertido', 'descartado'])->default('nuevo');
            $table->unsignedBigInteger('opportunity_id')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('discarded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_procedure_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('grupo_codigo', 100)->nullable();
            $table->text('nombre');
            $table->string('tipo', 20)->default('unica');
            $table->unsignedSmallInteger('ventana_dias')->nullable();
            $table->tinyInteger('agrupar_por_ojo')->default(1);
            $table->tinyInteger('genera_oportunidad')->default(1);
            $table->tinyInteger('activo')->default(1);
            $table->timestamps();
        });
    }

    // =========================================================================
    // FLAG ROUTING
    // =========================================================================

    public function test_legacy_flag_uses_legacy_algorithm(): void
    {
        Config::set('crm.intent_model_enabled', false);

        $contact = CrmContact::create(['name' => 'Paciente', 'resolution' => 'provisional']);
        $service = app(CrmOpportunityService::class);

        // Legacy: creates opportunity even without procedureCodigo
        $opp = $service->upsertFromEvent(
            contact: $contact,
            title:   'Solicitud sin código',
            source:  'solicitud',
        );

        $this->assertInstanceOf(CrmOpportunity::class, $opp);
        $this->assertSame(1, CrmOpportunity::count());
    }

    public function test_intent_flag_without_procedureCodigo_falls_back_to_legacy(): void
    {
        Config::set('crm.intent_model_enabled', true);

        $contact = CrmContact::create(['name' => 'Paciente', 'resolution' => 'provisional']);
        $service = app(CrmOpportunityService::class);

        // procedureCodigo is null → falls back to legacy (no crash, creates opp)
        $opp = $service->upsertFromEvent(
            contact:         $contact,
            title:           'Solicitud sin código',
            source:          'solicitud',
            procedureCodigo: null,
        );

        $this->assertInstanceOf(CrmOpportunity::class, $opp);
        $this->assertSame(1, CrmOpportunity::count());
    }

    // =========================================================================
    // LEGACY BEHAVIOUR UNCHANGED
    // =========================================================================

    public function test_legacy_does_not_create_second_opportunity_for_same_contact(): void
    {
        Config::set('crm.intent_model_enabled', false);

        $contact = CrmContact::create(['name' => 'Paciente', 'resolution' => 'provisional']);
        $service = app(CrmOpportunityService::class);

        $first  = $service->upsertFromEvent($contact, 'Primera solicitud', 'solicitud');
        $second = $service->upsertFromEvent($contact, 'Segunda solicitud',  'solicitud');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CrmOpportunity::count());
    }

    // =========================================================================
    // CrmIntentLeadService
    // =========================================================================

    public function test_capture_creates_intent_lead(): void
    {
        $contact = CrmContact::create(['name' => 'Paciente', 'resolution' => 'provisional']);
        $service = app(CrmIntentLeadService::class);

        $lead = $service->capture(
            contact:    $contact,
            source:     'whatsapp',
            sourceId:   42,
            sourceType: 'whatsapp_lead',
            motivo:     'Quiere consulta',
        );

        $this->assertSame(CrmIntentLead::STATUS_NUEVO, $lead->status);
        $this->assertSame('whatsapp', $lead->source);
        $this->assertSame(42, $lead->source_id);
        $this->assertNull($lead->opportunity_id);
    }

    public function test_capture_is_idempotent_for_same_source_id(): void
    {
        $contact = CrmContact::create(['name' => 'Paciente', 'resolution' => 'provisional']);
        $service = app(CrmIntentLeadService::class);

        $first  = $service->capture($contact, 'whatsapp', 42);
        $second = $service->capture($contact, 'whatsapp', 42);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CrmIntentLead::count());
    }

    public function test_capture_allows_multiple_leads_for_different_source_ids(): void
    {
        $contact = CrmContact::create(['name' => 'Paciente', 'resolution' => 'provisional']);
        $service = app(CrmIntentLeadService::class);

        $service->capture($contact, 'whatsapp', 1);
        $service->capture($contact, 'whatsapp', 2);

        $this->assertSame(2, CrmIntentLead::count());
    }

    // =========================================================================
    // WhatsApp + intent flag
    // =========================================================================

    public function test_whatsapp_intent_flag_creates_intent_lead_not_opportunity(): void
    {
        Config::set('crm.intent_model_enabled', true);

        $contact = CrmContact::create(['name' => 'Paciente', 'resolution' => 'provisional']);
        $service = app(CrmIntentLeadService::class);

        $service->capture(
            contact:    $contact,
            source:     'whatsapp',
            sourceId:   99,
            sourceType: 'whatsapp_lead',
            motivo:     'Dolor ocular',
        );

        $this->assertSame(1, CrmIntentLead::count());
        $this->assertSame(0, CrmOpportunity::count());
    }

    public function test_whatsapp_legacy_flag_creates_opportunity_not_intent_lead(): void
    {
        Config::set('crm.intent_model_enabled', false);

        $contact = CrmContact::create(['name' => 'Paciente', 'resolution' => 'provisional']);
        $service = app(CrmOpportunityService::class);

        $service->upsertFromEvent(
            contact:    $contact,
            title:      'Lead WhatsApp: dolor ocular',
            source:     'whatsapp',
            sourceId:   99,
            sourceType: 'whatsapp_lead',
        );

        $this->assertSame(0, CrmIntentLead::count());
        $this->assertSame(1, CrmOpportunity::count());
    }

    // =========================================================================
    // Intent algorithm — genera_oportunidad = 0
    // =========================================================================

    public function test_intent_genera_oportunidad_0_returns_null_when_no_active_opp(): void
    {
        Config::set('crm.intent_model_enabled', true);

        $contact = CrmContact::create(['name' => 'Paciente', 'resolution' => 'provisional']);
        $service = app(CrmOpportunityService::class);

        // SER-OFT-005 is a 'diagnostico' code — genera_oportunidad=0 in Phase 0 classifications
        // We mock the rule by using a code that the fallback returns matched=false, genera_oportunidad=1
        // Instead, test with a procedure code whose rule has genera_oportunidad=0 via DB stub
        // For unit isolation we test the behaviour path directly through a known code with no DB

        // CrmProcedureRule::forCodigo('NONEXISTENT') returns fallback: genera_oportunidad=1
        // To test genera_oportunidad=0 path, insert a real rule
        \DB::table('crm_procedure_rules')->insert([
            'codigo'              => 'TEST-DIAG-001',
            'nombre'              => 'Consulta diagnóstica test',
            'tipo'                => 'diagnostico',
            'genera_oportunidad'  => 0,
            'agrupar_por_ojo'     => 0,
            'activo'              => 1,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        $result = $service->upsertFromEvent(
            contact:         $contact,
            title:           'Consulta test',
            source:          'solicitud',
            procedureCodigo: 'TEST-DIAG-001',
        );

        $this->assertNull($result);
        $this->assertSame(0, CrmOpportunity::count());
    }

    public function test_intent_genera_oportunidad_0_attaches_to_existing_active_opp(): void
    {
        Config::set('crm.intent_model_enabled', true);

        $contact = CrmContact::create(['name' => 'Paciente', 'resolution' => 'provisional']);

        $existingOpp = CrmOpportunity::create([
            'contact_id' => $contact->id,
            'title'      => 'Opp existente',
            'stage'      => 'nuevo',
        ]);

        \DB::table('crm_procedure_rules')->insert([
            'codigo'             => 'TEST-DIAG-002',
            'nombre'             => 'Consulta diagnóstica test 2',
            'tipo'               => 'diagnostico',
            'genera_oportunidad' => 0,
            'agrupar_por_ojo'    => 0,
            'activo'             => 1,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $result = app(CrmOpportunityService::class)->upsertFromEvent(
            contact:         $contact,
            title:           'Consulta diagnóstica',
            source:          'solicitud',
            sourceId:        1,
            procedureCodigo: 'TEST-DIAG-002',
        );

        $this->assertInstanceOf(CrmOpportunity::class, $result);
        $this->assertSame($existingOpp->id, $result->id);
        $this->assertSame(1, CrmOpportunity::count()); // no new opp created
    }

    // =========================================================================
    // UNIQUE(contact_id) still enforced in Phase 2A
    // =========================================================================

    public function test_unique_contact_id_constraint_still_active(): void
    {
        $contact = CrmContact::create(['name' => 'Paciente', 'resolution' => 'provisional']);

        CrmOpportunity::create(['contact_id' => $contact->id, 'title' => 'Primera']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        CrmOpportunity::create(['contact_id' => $contact->id, 'title' => 'Segunda']);
    }
}

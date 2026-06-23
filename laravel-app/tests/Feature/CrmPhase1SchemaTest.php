<?php

namespace Tests\Feature;

use App\Models\CrmContact;
use App\Models\CrmIntentLead;
use App\Models\CrmOpportunity;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CrmPhase1SchemaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('crm_intent_leads');
        Schema::dropIfExists('crm_opportunities');
        Schema::dropIfExists('crm_contacts');
        Schema::dropIfExists('whatsapp_leads');

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
            // Phase 1 columns
            $table->string('procedure_group', 100)->nullable();
            $table->enum('lateralidad', ['OD', 'OI', 'AO'])->nullable();
            $table->timestamp('episode_started_at')->nullable();
            $table->unsignedBigInteger('previous_opportunity_id')->nullable();
            $table->enum('opportunity_type', ['recurrente', 'unica', 'diagnostico'])->nullable();
            $table->tinyInteger('continuity_flag')->default(0);
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

        Schema::create('whatsapp_leads', function (Blueprint $table): void {
            $table->id();
            $table->string('wa_number', 30)->nullable();
            $table->unsignedBigInteger('crm_lead_id')->nullable();
            $table->unsignedBigInteger('crm_intent_lead_id')->nullable();
            $table->timestamps();
        });
    }

    // --- crm_opportunities new columns ---

    public function test_opportunity_accepts_procedure_group(): void
    {
        $contact = CrmContact::create(['name' => 'Paciente Test', 'resolution' => 'provisional']);

        $opp = CrmOpportunity::create([
            'contact_id'      => $contact->id,
            'title'           => 'Inyección OD',
            'procedure_group' => 'inyeccion_intravitrea',
            'lateralidad'     => 'OD',
            'opportunity_type'=> 'recurrente',
        ]);

        $this->assertSame('inyeccion_intravitrea', $opp->fresh()->procedure_group);
        $this->assertSame('OD', $opp->fresh()->lateralidad);
        $this->assertSame('recurrente', $opp->fresh()->opportunity_type);
    }

    public function test_opportunity_episode_started_at_is_cast_to_datetime(): void
    {
        $contact = CrmContact::create(['name' => 'Paciente Test', 'resolution' => 'provisional']);
        $ts = now()->subDays(5);

        $opp = CrmOpportunity::create([
            'contact_id'       => $contact->id,
            'title'            => 'YAG Láser',
            'episode_started_at'=> $ts,
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $opp->fresh()->episode_started_at);
    }

    public function test_opportunity_previous_opportunity_id_nullable_by_default(): void
    {
        $contact = CrmContact::create(['name' => 'Paciente Test', 'resolution' => 'provisional']);

        $opp = CrmOpportunity::create([
            'contact_id' => $contact->id,
            'title'      => 'Primera opp',
        ]);

        $this->assertNull($opp->fresh()->previous_opportunity_id);
        $this->assertFalse((bool) $opp->fresh()->continuity_flag);
    }

    public function test_opportunity_chaining_via_previous_opportunity_id(): void
    {
        $contact = CrmContact::create(['name' => 'Paciente Test', 'resolution' => 'provisional']);

        $first = CrmOpportunity::create([
            'contact_id'      => $contact->id,
            'title'           => 'Episodio 1',
            'procedure_group' => 'laser_retina',
        ]);

        $second = CrmOpportunity::create([
            'contact_id'             => $contact->id,
            'title'                  => 'Episodio 2',
            'procedure_group'        => 'laser_retina',
            'previous_opportunity_id'=> $first->id,
            'continuity_flag'        => 1,
        ]);

        $this->assertSame($first->id, $second->fresh()->previous_opportunity_id);
        $this->assertTrue((bool) $second->fresh()->continuity_flag);
        $this->assertSame($first->id, $second->previousOpportunity->id);
    }

    // --- crm_intent_leads ---

    public function test_intent_lead_created_with_defaults(): void
    {
        $contact = CrmContact::create(['name' => 'Paciente Lead', 'resolution' => 'provisional']);

        $lead = CrmIntentLead::create([
            'contact_id' => $contact->id,
            'motivo'     => 'Quiere consulta por visión borrosa',
        ]);

        $this->assertSame('whatsapp', $lead->fresh()->source);
        $this->assertSame('nuevo', $lead->fresh()->status);
        $this->assertNull($lead->fresh()->opportunity_id);
        $this->assertNull($lead->fresh()->converted_at);
    }

    public function test_intent_lead_conversion_links_opportunity(): void
    {
        $contact = CrmContact::create(['name' => 'Paciente Lead', 'resolution' => 'provisional']);

        $lead = CrmIntentLead::create([
            'contact_id' => $contact->id,
            'source'     => 'whatsapp',
            'status'     => 'calificado',
        ]);

        $opp = CrmOpportunity::create([
            'contact_id' => $contact->id,
            'title'      => 'Cataratas OD',
        ]);

        $lead->update([
            'status'         => CrmIntentLead::STATUS_CONVERTIDO,
            'opportunity_id' => $opp->id,
            'converted_at'   => now(),
        ]);

        $this->assertSame('convertido', $lead->fresh()->status);
        $this->assertSame($opp->id, $lead->fresh()->opportunity_id);
        $this->assertNotNull($lead->fresh()->converted_at);
        $this->assertSame($opp->id, $lead->fresh()->opportunity->id);
    }

    public function test_intent_lead_pending_scope_excludes_converted_and_discarded(): void
    {
        $contact = CrmContact::create(['name' => 'Test', 'resolution' => 'provisional']);

        CrmIntentLead::create(['contact_id' => $contact->id, 'status' => 'nuevo']);
        CrmIntentLead::create(['contact_id' => $contact->id, 'status' => 'calificado']);
        CrmIntentLead::create(['contact_id' => $contact->id, 'status' => 'convertido']);
        CrmIntentLead::create(['contact_id' => $contact->id, 'status' => 'descartado']);

        $this->assertSame(2, CrmIntentLead::pending()->count());
    }

    // --- whatsapp_leads column ---

    public function test_whatsapp_leads_has_crm_intent_lead_id_column(): void
    {
        $this->assertTrue(Schema::hasColumn('whatsapp_leads', 'crm_intent_lead_id'));
        $this->assertTrue(Schema::hasColumn('whatsapp_leads', 'crm_lead_id'));
    }

    public function test_legacy_crm_lead_id_column_still_exists(): void
    {
        $this->assertTrue(Schema::hasColumn('whatsapp_leads', 'crm_lead_id'));
    }
}

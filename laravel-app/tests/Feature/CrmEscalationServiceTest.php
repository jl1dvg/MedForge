<?php

namespace Tests\Feature;

use App\Models\CrmContact;
use App\Models\CrmOpportunity;
use App\Modules\CRM\Services\CrmEscalationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CrmEscalationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['crm_activities', 'crm_opportunities', 'crm_contacts'] as $t) {
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
    }

    public function test_escalate_moves_overdue_operational_to_commercial(): void
    {
        $contact = CrmContact::query()->create([
            'name' => 'Test', 'phone' => '0999000001', 'resolution' => 'provisional', 'source' => 'examen',
        ]);
        $opp = CrmOpportunity::query()->create([
            'contact_id'   => $contact->id,
            'title'        => 'Overdue',
            'stage'        => CrmOpportunity::STAGE_CONTACTADO,
            'phase'        => CrmOpportunity::PHASE_OPERATIONAL,
            'source'       => 'examen',
            'escalation_at'=> now()->subDay(),
        ]);

        app(CrmEscalationService::class)->run(dryRun: false);

        $opp->refresh();
        $this->assertEquals(CrmOpportunity::PHASE_COMMERCIAL, $opp->phase);
        $this->assertNull($opp->escalation_at);
        $this->assertDatabaseHas('crm_activities', [
            'opportunity_id' => $opp->id,
            'type'           => 'nota',
        ]);
    }

    public function test_escalate_dry_run_does_not_mutate(): void
    {
        $contact = CrmContact::query()->create([
            'name' => 'DryTest', 'phone' => '0999000002', 'resolution' => 'provisional', 'source' => 'examen',
        ]);
        CrmOpportunity::query()->create([
            'contact_id'   => $contact->id,
            'title'        => 'Dry',
            'stage'        => CrmOpportunity::STAGE_CONTACTADO,
            'phase'        => CrmOpportunity::PHASE_OPERATIONAL,
            'source'       => 'examen',
            'escalation_at'=> now()->subDay(),
        ]);

        app(CrmEscalationService::class)->run(dryRun: true);

        $this->assertDatabaseHas('crm_opportunities', ['phase' => CrmOpportunity::PHASE_OPERATIONAL]);
    }
}

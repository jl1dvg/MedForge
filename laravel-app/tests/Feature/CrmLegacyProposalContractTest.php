<?php

namespace Tests\Feature;

use App\Http\Middleware\LegacySessionBridge;
use App\Http\Middleware\RequireAppPermission;
use App\Http\Middleware\RequireAppSession;
use App\Http\Middleware\RequireLegacyPermission;
use App\Http\Middleware\RequireLegacySession;
use App\Modules\CRM\Services\CrmProposalService;
use App\Modules\Solicitudes\Services\SolicitudesCrmWriteService;
use App\Modules\Solicitudes\Services\SolicitudesReadParityService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class CrmLegacyProposalContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'crm_proposal_activity',
            'crm_proposal_items',
            'crm_proposals',
            'examen_crm_detalles',
            'consulta_examenes',
            'solicitud_crm_detalles',
            'solicitud_procedimiento',
            'crm_opportunities',
            'crm_contacts',
            'crm_leads',
            'crm_customers',
            'patient_data',
            'consulta_data',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('nombre')->nullable();
        });

        Schema::create('crm_contacts', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('cedula')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('crm_opportunities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('contact_id')->nullable()->index();
            $table->string('title');
            $table->string('stage', 30)->default('nuevo');
            $table->string('source', 30)->default('manual');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_type', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('crm_leads', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable()->index();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->nullable();
            $table->string('source')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_customers', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
        });

        Schema::create('solicitud_procedimiento', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable();
            $table->string('form_id')->nullable();
            $table->string('estado')->nullable();
            $table->string('doctor')->nullable();
            $table->string('procedimiento')->nullable();
            $table->string('ojo')->nullable();
            $table->unsignedBigInteger('crm_opportunity_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('solicitud_crm_detalles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('solicitud_id')->index();
            $table->unsignedBigInteger('crm_lead_id')->nullable()->index();
            $table->unsignedBigInteger('crm_opportunity_id')->nullable()->index();
            $table->unsignedBigInteger('responsable_id')->nullable();
            $table->timestamps();
        });

        Schema::create('consulta_examenes', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable();
            $table->string('form_id')->nullable();
            $table->dateTime('consulta_fecha')->nullable();
            $table->string('doctor')->nullable();
            $table->string('solicitante')->nullable();
            $table->string('examen_codigo')->nullable();
            $table->string('examen_nombre')->nullable();
            $table->string('lateralidad')->nullable();
            $table->unsignedBigInteger('crm_opportunity_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('examen_crm_detalles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('examen_id')->index();
            $table->unsignedBigInteger('crm_lead_id')->nullable()->index();
            $table->unsignedBigInteger('crm_opportunity_id')->nullable()->index();
            $table->unsignedBigInteger('responsable_id')->nullable();
        });

        Schema::create('patient_data', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable()->index();
            $table->string('fname')->nullable();
            $table->string('mname')->nullable();
            $table->string('lname')->nullable();
            $table->string('lname2')->nullable();
            $table->string('afiliacion')->nullable();
        });

        Schema::create('consulta_data', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable();
            $table->string('form_id')->nullable();
            $table->dateTime('fecha')->nullable();
        });

        Schema::create('crm_proposals', function (Blueprint $table): void {
            $table->id();
            $table->string('public_hash', 64)->nullable()->unique();
            $table->string('proposal_number');
            $table->integer('proposal_year');
            $table->integer('sequence');
            $table->unsignedBigInteger('lead_id')->nullable()->index();
            $table->unsignedBigInteger('crm_opportunity_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('title');
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
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_proposal_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('proposal_id')->index();
            $table->unsignedBigInteger('code_id')->nullable();
            $table->unsignedBigInteger('package_id')->nullable();
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_proposal_activity', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('proposal_id')->index();
            $table->string('event', 64);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function test_crm_lead_write_endpoint_is_disabled_for_new_central_crm(): void
    {
        $userId = DB::table('users')->insertGetId(['username' => 'crm']);

        $this->actingAs(\App\Models\User::query()->find($userId))
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
                RequireAppSession::class,
                RequireAppPermission::class,
            ])
            ->postJson('/v2/crm/leads', [
                'hc_number' => 'HC-LEGACY',
                'name' => 'Lead Legacy',
            ])
            ->assertStatus(410);

        $this->assertDatabaseCount('crm_leads', 0);
    }

    public function test_crm_lead_read_endpoint_is_disabled_for_new_central_crm(): void
    {
        $userId = DB::table('users')->insertGetId(['username' => 'crm']);
        DB::table('crm_leads')->insert([
            'hc_number' => 'HC-HIST',
            'name' => 'Lead Histórico',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(\App\Models\User::query()->find($userId))
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
                RequireAppSession::class,
                RequireAppPermission::class,
            ])
            ->getJson('/v2/crm/leads')
            ->assertStatus(410);
    }

    public function test_solicitud_proposal_creation_links_to_crm_opportunity_without_requiring_legacy_lead(): void
    {
        $contactId = DB::table('crm_contacts')->insertGetId([
            'name' => 'Paciente',
            'phone' => '0999999999',
            'cedula' => 'HC-100',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $opportunityId = DB::table('crm_opportunities')->insertGetId([
            'contact_id' => $contactId,
            'title' => 'Solicitud cirugía',
            'stage' => 'comprometido',
            'source' => 'solicitud',
            'source_id' => 10,
            'source_type' => 'solicitud_procedimiento',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('solicitud_procedimiento')->insert([
            'id' => 10,
            'hc_number' => 'HC-100',
            'form_id' => 'F-100',
            'procedimiento' => 'Faco',
            'crm_opportunity_id' => $opportunityId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $readService = Mockery::mock(SolicitudesReadParityService::class);
        $readService->shouldReceive('crmResumen')->once()->with(10)->andReturn(['ok' => true]);

        $service = new SolicitudesCrmWriteService(DB::connection()->getPdo(), $readService);
        $summary = $service->crmCrearPropuesta(10, [
            'title' => 'Propuesta cirugía',
            'items' => [
                ['description' => 'Procedimiento', 'quantity' => 1, 'unit_price' => 1000],
            ],
        ], 7);

        $this->assertDatabaseHas('crm_proposals', [
            'title' => 'Propuesta cirugía',
            'lead_id' => null,
            'crm_opportunity_id' => $opportunityId,
        ]);
        $this->assertSame($opportunityId, $summary['ultima_propuesta']['crm_opportunity_id']);
        $this->assertNull($summary['ultima_propuesta']['lead_id']);
    }

    public function test_proposal_service_prefers_opportunity_patient_data_over_legacy_lead(): void
    {
        $contactId = DB::table('crm_contacts')->insertGetId([
            'name' => 'Paciente Oportunidad',
            'phone' => '0999999999',
            'email' => 'paciente@example.com',
            'cedula' => 'HC-200',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $opportunityId = DB::table('crm_opportunities')->insertGetId([
            'contact_id' => $contactId,
            'title' => 'Examen OCT',
            'stage' => 'comprometido',
            'source' => 'examen',
            'source_id' => 55,
            'source_type' => 'consulta_examenes',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('patient_data')->insert([
            'hc_number' => 'HC-200',
            'fname' => 'Paciente',
            'lname' => 'Nuevo',
            'afiliacion' => 'Privado',
        ]);
        DB::table('consulta_examenes')->insert([
            'id' => 55,
            'hc_number' => 'HC-200',
            'form_id' => 'F-200',
            'crm_opportunity_id' => $opportunityId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('examen_crm_detalles')->insert([
            'examen_id' => 55,
            'crm_opportunity_id' => $opportunityId,
        ]);
        $proposalId = DB::table('crm_proposals')->insertGetId([
            'public_hash' => 'hash-200',
            'proposal_number' => 'PROP-2026-0001',
            'proposal_year' => 2026,
            'sequence' => 1,
            'lead_id' => null,
            'crm_opportunity_id' => $opportunityId,
            'title' => 'Propuesta OCT',
            'status' => 'draft',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $proposal = (new CrmProposalService())->find($proposalId);

        $this->assertSame($opportunityId, (int) $proposal['crm_opportunity_id']);
        $this->assertSame('Paciente Oportunidad', $proposal['lead_name']);
        $this->assertSame('paciente@example.com', $proposal['lead_email']);
        $this->assertSame(55, $proposal['examen_id']);
        $this->assertSame('EXAMEN', $proposal['clinical_context']['type_label']);
        $this->assertSame('Paciente Nuevo', $proposal['clinical_context']['paciente']);
    }
}

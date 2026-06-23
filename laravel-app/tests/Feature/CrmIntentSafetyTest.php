<?php

namespace Tests\Feature;

use App\Events\Crm\SolicitudCreada;
use App\Models\CrmContact;
use App\Models\CrmOpportunity;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Safety tests for three pre-activation guards:
 *   1. Zombie cutoff in findActiveCompatible()
 *   2. Legacy opp exclusion from findActiveCompatible()
 *   3. crm:consolidate-opportunities intent guard
 */
class CrmIntentSafetyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'solicitud_crm_detalles',
            'solicitud_procedimiento',
            'crm_procedure_rules',
            'crm_activities',
            'crm_opportunities',
            'crm_contacts',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('crm_contacts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->string('name', 255);
            $table->string('phone', 30)->default('');
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
            $table->string('afiliacion_tipo', 20)->nullable();
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
            $table->unsignedBigInteger('opportunity_id')->index();
            $table->string('type', 30)->default('nota');
            $table->text('description');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_type', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('solicitud_procedimiento', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable();
            $table->string('procedimiento')->nullable();
            $table->unsignedBigInteger('crm_opportunity_id')->nullable();
            $table->timestamps();
        });

        Schema::create('solicitud_crm_detalles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('solicitud_id');
            $table->unsignedBigInteger('crm_opportunity_id')->nullable();
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

        \DB::table('crm_procedure_rules')->insert([
            [
                'codigo' => 'CYP-CCA-001', 'grupo_codigo' => 'facoemulsificacion',
                'nombre' => 'Catarata con Facoemulsificación', 'tipo' => 'unica',
                'ventana_dias' => null, 'agrupar_por_ojo' => 1, 'genera_oportunidad' => 1,
                'activo' => 1, 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'codigo' => '67028', 'grupo_codigo' => 'inyeccion_intravitrea',
                'nombre' => 'Inyección intravítrea', 'tipo' => 'recurrente',
                'ventana_dias' => 90, 'agrupar_por_ojo' => 1, 'genera_oportunidad' => 1,
                'activo' => 1, 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'codigo' => 'IPL-001', 'grupo_codigo' => 'ipl_ojo_seco',
                'nombre' => 'IPL ojo seco', 'tipo' => 'recurrente',
                'ventana_dias' => null,  // intentionally null — fallback 90 days
                'agrupar_por_ojo' => 1, 'genera_oportunidad' => 1,
                'activo' => 1, 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'codigo' => 'SER-OFT-005', 'grupo_codigo' => null,
                'nombre' => 'Consulta diagnóstica', 'tipo' => 'diagnostico',
                'ventana_dias' => null, 'agrupar_por_ojo' => 0, 'genera_oportunidad' => 0,
                'activo' => 1, 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        Config::set('crm.intent_model_enabled', true);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function dispatchSolicitud(string $cedula, string $codigo, ?string $lat, int $solId): void
    {
        event(new SolicitudCreada($solId, [
            'paciente_cedula'      => $cedula,
            'paciente_nombre'      => 'Test',
            'paciente_telefono'    => '',
            'servicio'             => $codigo,
            'procedimiento_codigo' => $codigo,
            'lateralidad'          => $lat,
        ]));
    }

    private function insertSolicitud(string $cedula, string $codigo): int
    {
        return (int) \DB::table('solicitud_procedimiento')->insertGetId([
            'hc_number'    => $cedula,
            'procedimiento' => $codigo,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    private function createLegacyOpp(string $cedula): CrmOpportunity
    {
        $contact = CrmContact::query()->firstOrCreate(
            ['cedula' => $cedula],
            ['name' => 'Paciente Legacy', 'phone' => '', 'resolution' => 'provisional', 'source' => 'manual'],
        );

        return CrmOpportunity::query()->create([
            'contact_id'       => $contact->id,
            'title'            => 'Oportunidad legacy',
            'stage'            => CrmOpportunity::STAGE_NUEVO,
            'phase'            => CrmOpportunity::PHASE_OPERATIONAL,
            'source'           => 'solicitud',
            'last_activity_at' => now(),
            // procedure_group, lateralidad, opportunity_type intentionally null (legacy)
        ]);
    }

    // =========================================================================
    // 1. Zombie cutoff — tipo=unica
    // =========================================================================

    public function test_unica_opp_idle_181_days_is_not_reused(): void
    {
        $s1 = $this->insertSolicitud('2000000001', 'CYP-CCA-001');
        $this->dispatchSolicitud('2000000001', 'CYP-CCA-001', 'OD', $s1);

        // Age the episode to 181 days idle
        CrmOpportunity::query()->update(['last_activity_at' => now()->subDays(181)]);

        $s2 = $this->insertSolicitud('2000000001', 'CYP-CCA-001');
        $this->dispatchSolicitud('2000000001', 'CYP-CCA-001', 'OD', $s2);

        // Must create a second opportunity — the first is a zombie
        $this->assertSame(2, CrmOpportunity::count(), 'Episode idle 181 days must not be reused');
    }

    public function test_unica_opp_idle_179_days_is_reused(): void
    {
        $s1 = $this->insertSolicitud('2000000002', 'CYP-CCA-001');
        $this->dispatchSolicitud('2000000002', 'CYP-CCA-001', 'OD', $s1);

        // Age the episode to 179 days — still within the 180-day window
        CrmOpportunity::query()->update(['last_activity_at' => now()->subDays(179)]);

        $s2 = $this->insertSolicitud('2000000002', 'CYP-CCA-001');
        $this->dispatchSolicitud('2000000002', 'CYP-CCA-001', 'OD', $s2);

        // Must reuse the existing opportunity
        $this->assertSame(1, CrmOpportunity::count(), 'Episode idle 179 days must be reused');
        $this->assertSame(2, \App\Models\CrmActivity::count(), 'Two activities logged on same opp');
    }

    // =========================================================================
    // 2. Zombie cutoff — tipo=recurrente with explicit ventana_dias
    // =========================================================================

    public function test_recurrente_opp_idle_beyond_ventana_dias_is_not_reused(): void
    {
        // 67028 has ventana_dias=90; idle 91 days → new episode
        $s1 = $this->insertSolicitud('2000000003', '67028');
        $this->dispatchSolicitud('2000000003', '67028', 'OD', $s1);

        CrmOpportunity::query()->update(['last_activity_at' => now()->subDays(91)]);

        $s2 = $this->insertSolicitud('2000000003', '67028');
        $this->dispatchSolicitud('2000000003', '67028', 'OD', $s2);

        $this->assertSame(2, CrmOpportunity::count(), 'Episode idle beyond ventana_dias must not be reused');
    }

    public function test_recurrente_opp_idle_within_ventana_dias_is_reused(): void
    {
        // 67028 has ventana_dias=90; idle 89 days → reuse
        $s1 = $this->insertSolicitud('2000000004', '67028');
        $this->dispatchSolicitud('2000000004', '67028', 'OD', $s1);

        CrmOpportunity::query()->update(['last_activity_at' => now()->subDays(89)]);

        $s2 = $this->insertSolicitud('2000000004', '67028');
        $this->dispatchSolicitud('2000000004', '67028', 'OD', $s2);

        $this->assertSame(1, CrmOpportunity::count(), 'Episode idle within ventana_dias must be reused');
    }

    // =========================================================================
    // 3. Zombie cutoff — tipo=recurrente, ventana_dias=null (fallback 90 days)
    // =========================================================================

    public function test_recurrente_null_ventana_uses_90_day_fallback(): void
    {
        // IPL-001 has ventana_dias=null; fallback=90 days; idle 91 days → new episode
        $s1 = $this->insertSolicitud('2000000005', 'IPL-001');
        $this->dispatchSolicitud('2000000005', 'IPL-001', 'OD', $s1);

        CrmOpportunity::query()->update(['last_activity_at' => now()->subDays(91)]);

        $s2 = $this->insertSolicitud('2000000005', 'IPL-001');
        $this->dispatchSolicitud('2000000005', 'IPL-001', 'OD', $s2);

        $this->assertSame(2, CrmOpportunity::count(), 'recurrente null-ventana fallback is 90 days');
    }

    // =========================================================================
    // 4. Legacy opps do not block intent episode creation
    // =========================================================================

    public function test_legacy_opp_does_not_block_new_intent_episode(): void
    {
        // Create a legacy opp (procedure_group=null, opportunity_type=null) for the contact
        $this->createLegacyOpp('3000000001');
        $this->assertSame(1, CrmOpportunity::count(), 'Setup: one legacy opp');

        // Dispatch an intent event for the same patient
        $s1 = $this->insertSolicitud('3000000001', 'CYP-CCA-001');
        $this->dispatchSolicitud('3000000001', 'CYP-CCA-001', 'OD', $s1);

        // Must create a NEW intent opp — the legacy opp must NOT be reused
        $this->assertSame(2, CrmOpportunity::count(), 'Legacy opp must not block intent episode creation');

        $intentOpp = CrmOpportunity::query()->whereNotNull('procedure_group')->first();
        $this->assertNotNull($intentOpp);
        $this->assertSame('facoemulsificacion', $intentOpp->procedure_group);
        $this->assertSame('unica', $intentOpp->opportunity_type);
        $this->assertSame('OD', $intentOpp->lateralidad);
    }

    public function test_legacy_opp_not_reused_even_when_active(): void
    {
        // Legacy opp is recently active — still should not be reused by intent
        $this->createLegacyOpp('3000000002');
        CrmOpportunity::query()->update(['last_activity_at' => now()]);

        $s1 = $this->insertSolicitud('3000000002', '67028');
        $this->dispatchSolicitud('3000000002', '67028', 'OI', $s1);

        $this->assertSame(2, CrmOpportunity::count());

        $intentOpp = CrmOpportunity::query()->whereNotNull('opportunity_type')->first();
        $this->assertSame('inyeccion_intravitrea', $intentOpp->procedure_group);
    }

    // =========================================================================
    // 5. crm:consolidate-opportunities guard
    // =========================================================================

    public function test_consolidate_aborts_when_intent_enabled(): void
    {
        Config::set('crm.intent_model_enabled', true);

        $this->artisan('crm:consolidate-opportunities')
            ->expectsOutputToContain('ABORTADO')
            ->assertExitCode(1);
    }

    public function test_consolidate_aborts_even_with_dry_run_when_intent_enabled(): void
    {
        Config::set('crm.intent_model_enabled', true);

        $this->artisan('crm:consolidate-opportunities', ['--dry-run' => true])
            ->expectsOutputToContain('ABORTADO')
            ->assertExitCode(1);
    }

    public function test_consolidate_proceeds_with_force_intent_flag(): void
    {
        Config::set('crm.intent_model_enabled', true);

        // Create two contacts, each with one opp — no real duplicates to consolidate
        $this->createLegacyOpp('5000000001');
        $this->createLegacyOpp('5000000002');

        $this->artisan('crm:consolidate-opportunities', ['--force-intent' => true, '--dry-run' => true])
            ->doesntExpectOutputToContain('ABORTADO')
            ->assertExitCode(0);
    }

    public function test_consolidate_runs_normally_when_intent_disabled(): void
    {
        Config::set('crm.intent_model_enabled', false);

        $this->artisan('crm:consolidate-opportunities', ['--dry-run' => true])
            ->doesntExpectOutputToContain('ABORTADO')
            ->assertExitCode(0);
    }

    // =========================================================================
    // 6. COALESCE fallback: last_activity_at NULL uses created_at
    // =========================================================================

    public function test_null_last_activity_uses_created_at_as_fallback_and_is_reused(): void
    {
        // Create an opp with last_activity_at=NULL but recently created → should be reused
        $s1 = $this->insertSolicitud('4000000001', 'CYP-CCA-001');
        $this->dispatchSolicitud('4000000001', 'CYP-CCA-001', 'OD', $s1);

        // Force last_activity_at to NULL — the episode was just created, so created_at is recent
        CrmOpportunity::query()->update(['last_activity_at' => null]);

        $s2 = $this->insertSolicitud('4000000001', 'CYP-CCA-001');
        $this->dispatchSolicitud('4000000001', 'CYP-CCA-001', 'OD', $s2);

        // COALESCE(last_activity_at, created_at) = created_at (recent) → episode reused
        $this->assertSame(1, CrmOpportunity::count(), 'NULL last_activity_at with recent created_at must be reused');
    }

    public function test_null_last_activity_with_old_created_at_is_zombie(): void
    {
        // Create an opp then backdate both timestamps to simulate an old episode with null last_activity_at
        $s1 = $this->insertSolicitud('4000000002', 'CYP-CCA-001');
        $this->dispatchSolicitud('4000000002', 'CYP-CCA-001', 'OD', $s1);

        // Age created_at to 181 days and null out last_activity_at
        CrmOpportunity::query()->update([
            'last_activity_at' => null,
            'created_at'       => now()->subDays(181),
        ]);

        $s2 = $this->insertSolicitud('4000000002', 'CYP-CCA-001');
        $this->dispatchSolicitud('4000000002', 'CYP-CCA-001', 'OD', $s2);

        // COALESCE(null, created_at) = created_at (181 days old) → zombie → new episode
        $this->assertSame(2, CrmOpportunity::count(), 'NULL last_activity_at with old created_at must be treated as zombie');
    }

    public function test_null_last_activity_recurrente_old_created_at_is_zombie(): void
    {
        // 67028: ventana_dias=90; last_activity_at=NULL; created_at=91 days ago → zombie
        $s1 = $this->insertSolicitud('4000000003', '67028');
        $this->dispatchSolicitud('4000000003', '67028', 'OD', $s1);

        CrmOpportunity::query()->update([
            'last_activity_at' => null,
            'created_at'       => now()->subDays(91),
        ]);

        $s2 = $this->insertSolicitud('4000000003', '67028');
        $this->dispatchSolicitud('4000000003', '67028', 'OD', $s2);

        $this->assertSame(2, CrmOpportunity::count(), 'recurrente null last_activity_at with old created_at must be zombie');
    }

    public function test_null_last_activity_recurrente_recent_created_at_is_reused(): void
    {
        // 67028: ventana_dias=90; last_activity_at=NULL; created_at=recent → reuse
        $s1 = $this->insertSolicitud('4000000004', '67028');
        $this->dispatchSolicitud('4000000004', '67028', 'OD', $s1);

        CrmOpportunity::query()->update(['last_activity_at' => null]);
        // created_at remains today — within 90-day window

        $s2 = $this->insertSolicitud('4000000004', '67028');
        $this->dispatchSolicitud('4000000004', '67028', 'OD', $s2);

        $this->assertSame(1, CrmOpportunity::count(), 'recurrente null last_activity_at with recent created_at must be reused');
    }
}

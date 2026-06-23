<?php

namespace Tests\Feature;

use App\Events\Crm\SolicitudCreada;
use App\Models\CrmContact;
use App\Models\CrmOpportunity;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tests for the intent algorithm creating, reusing, and deduplicating
 * opportunities per clinical episode.
 *
 * Schema here does NOT have UNIQUE(contact_id) — simulates the post-migration
 * state that the 2026_06_09_000004 migration will produce in production.
 */
class CrmPhase2BIntentMultiOppTest extends TestCase
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
            // ← NO unique() here — mirrors post-migration state
            $table->unsignedBigInteger('contact_id')->index('idx_crm_opp_contact_id');
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

        // Seed the rules used across all tests
        $this->seedRules();

        Config::set('crm.intent_model_enabled', true);
    }

    private function seedRules(): void
    {
        \DB::table('crm_procedure_rules')->insert([
            [   // Facoemulsificación — unica, agrupar_por_ojo=1
                'codigo' => 'CYP-CCA-001', 'grupo_codigo' => 'facoemulsificacion',
                'nombre' => 'Catarata con Facoemulsificación', 'tipo' => 'unica',
                'ventana_dias' => null, 'agrupar_por_ojo' => 1, 'genera_oportunidad' => 1,
                'activo' => 1, 'created_at' => now(), 'updated_at' => now(),
            ],
            [   // Inyección intravítrea — recurrente, ventana 90 días, agrupar_por_ojo=1
                'codigo' => '67028', 'grupo_codigo' => 'inyeccion_intravitrea',
                'nombre' => 'Inyección intravítrea', 'tipo' => 'recurrente',
                'ventana_dias' => 90, 'agrupar_por_ojo' => 1, 'genera_oportunidad' => 1,
                'activo' => 1, 'created_at' => now(), 'updated_at' => now(),
            ],
            [   // Consulta diagnóstica — diagnostico, genera_oportunidad=0
                'codigo' => 'SER-OFT-005', 'grupo_codigo' => null,
                'nombre' => 'Consulta diagnóstica', 'tipo' => 'diagnostico',
                'ventana_dias' => null, 'agrupar_por_ojo' => 0, 'genera_oportunidad' => 0,
                'activo' => 1, 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
    }

    private function dispatchSolicitud(string $cedula, string $codigo, ?string $lateralidad, int $solId): void
    {
        event(new SolicitudCreada($solId, [
            'paciente_cedula'      => $cedula,
            'paciente_nombre'      => 'Paciente Test',
            'paciente_telefono'    => '',
            'servicio'             => $codigo,
            'procedimiento_codigo' => $codigo,
            'lateralidad'          => $lateralidad,
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

    // =========================================================================
    // Case 1: Faco OD activa → reutiliza al llegar segunda solicitud Faco OD
    // =========================================================================

    public function test_intent_same_faco_od_active_reuses_opportunity(): void
    {
        $s1 = $this->insertSolicitud('1000000001', 'CYP-CCA-001');
        $s2 = $this->insertSolicitud('1000000001', 'CYP-CCA-001');

        $this->dispatchSolicitud('1000000001', 'CYP-CCA-001', 'OD', $s1);
        $this->dispatchSolicitud('1000000001', 'CYP-CCA-001', 'OD', $s2);

        $opps = CrmOpportunity::where('contact_id', CrmContact::first()->id)->get();

        $this->assertSame(1, $opps->count(), 'Misma Faco OD activa debe reutilizar la oportunidad');
        $this->assertSame('facoemulsificacion', $opps->first()->procedure_group);
        $this->assertSame('OD', $opps->first()->lateralidad);
    }

    // =========================================================================
    // Case 2: Faco OD + Faco OI → 2 oportunidades distintas
    // =========================================================================

    public function test_intent_faco_od_and_faco_oi_create_two_opportunities(): void
    {
        $s1 = $this->insertSolicitud('1000000002', 'CYP-CCA-001');
        $s2 = $this->insertSolicitud('1000000002', 'CYP-CCA-001');

        $this->dispatchSolicitud('1000000002', 'CYP-CCA-001', 'OD', $s1);
        $this->dispatchSolicitud('1000000002', 'CYP-CCA-001', 'OI', $s2);

        $contact = CrmContact::where('cedula', '1000000002')->first();
        $opps    = CrmOpportunity::where('contact_id', $contact->id)
            ->orderBy('id')->get();

        $this->assertSame(2, $opps->count(), 'Faco OD y Faco OI deben ser episodios distintos');
        $this->assertSame('OD', $opps[0]->lateralidad);
        $this->assertSame('OI', $opps[1]->lateralidad);
        $this->assertSame('facoemulsificacion', $opps[0]->procedure_group);
        $this->assertSame('facoemulsificacion', $opps[1]->procedure_group);
    }

    // =========================================================================
    // Case 3: Faco OD + Inyección 67028 OD → 2 oportunidades (procedure_group diferente)
    // =========================================================================

    public function test_intent_faco_od_and_injection_od_create_two_opportunities(): void
    {
        $s1 = $this->insertSolicitud('1000000003', 'CYP-CCA-001');
        $s2 = $this->insertSolicitud('1000000003', '67028');

        $this->dispatchSolicitud('1000000003', 'CYP-CCA-001', 'OD', $s1);
        $this->dispatchSolicitud('1000000003', '67028',       'OD', $s2);

        $contact = CrmContact::where('cedula', '1000000003')->first();
        $opps    = CrmOpportunity::where('contact_id', $contact->id)
            ->orderBy('id')->get();

        $this->assertSame(2, $opps->count(), 'Faco OD y Inyección OD son procedure_groups distintos');
        $groups = $opps->pluck('procedure_group')->sort()->values()->all();
        $this->assertSame(['facoemulsificacion', 'inyeccion_intravitrea'], $groups);
    }

    // =========================================================================
    // Case 4: 67028 OD repetida (recurrente activa) → reutiliza el episodio abierto
    // =========================================================================

    public function test_intent_injection_od_repeated_reuses_active_recurrent_episode(): void
    {
        $s1 = $this->insertSolicitud('1000000004', '67028');
        $s2 = $this->insertSolicitud('1000000004', '67028');
        $s3 = $this->insertSolicitud('1000000004', '67028');

        $this->dispatchSolicitud('1000000004', '67028', 'OD', $s1);
        $this->dispatchSolicitud('1000000004', '67028', 'OD', $s2);
        $this->dispatchSolicitud('1000000004', '67028', 'OD', $s3);

        $contact = CrmContact::where('cedula', '1000000004')->first();
        $opps    = CrmOpportunity::where('contact_id', $contact->id)->get();

        $this->assertSame(1, $opps->count(), '67028 OD repetida debe reutilizar el episodio recurrente activo');
        $this->assertSame('inyeccion_intravitrea', $opps->first()->procedure_group);
        $this->assertSame('OD', $opps->first()->lateralidad);
    }

    // =========================================================================
    // Case 5: 67028 OD closed + nueva → continuity_flag=1 si dentro de ventana
    // =========================================================================

    public function test_intent_injection_after_closed_episode_within_window_sets_continuity(): void
    {
        $contact = CrmContact::create([
            'name' => 'Paciente Continuity', 'cedula' => '1000000005',
            'phone' => '', 'resolution' => 'provisional',
        ]);

        // Create a closed (ganado) episode that started 30 days ago — within 90-day window
        CrmOpportunity::create([
            'contact_id'         => $contact->id,
            'title'              => 'Episodio anterior ganado',
            'stage'              => 'ganado',
            'phase'              => 'commercial',
            'source'             => 'solicitud',
            'procedure_group'    => 'inyeccion_intravitrea',
            'lateralidad'        => 'OD',
            'opportunity_type'   => 'recurrente',
            'episode_started_at' => now()->subDays(30),
        ]);

        $s1 = $this->insertSolicitud('1000000005', '67028');
        $this->dispatchSolicitud('1000000005', '67028', 'OD', $s1);

        $newOpp = CrmOpportunity::where('contact_id', $contact->id)
            ->where('stage', 'nuevo')
            ->first();

        $this->assertNotNull($newOpp, 'Debe crear nueva oportunidad al no haber episodio activo');
        $this->assertSame(1, (int) $newOpp->continuity_flag, 'continuity_flag debe ser 1 dentro de ventana');
        $this->assertNotNull($newOpp->previous_opportunity_id, 'previous_opportunity_id debe apuntar al episodio anterior');
    }

    // =========================================================================
    // Case 6: 67028 OD closed + nueva → continuity_flag=0 si fuera de ventana
    // =========================================================================

    public function test_intent_injection_after_closed_episode_outside_window_no_continuity(): void
    {
        $contact = CrmContact::create([
            'name' => 'Paciente Old Episode', 'cedula' => '1000000006',
            'phone' => '', 'resolution' => 'provisional',
        ]);

        // Closed episode 120 days ago — beyond the 90-day ventana
        CrmOpportunity::create([
            'contact_id'         => $contact->id,
            'title'              => 'Episodio viejo',
            'stage'              => 'ganado',
            'phase'              => 'commercial',
            'source'             => 'solicitud',
            'procedure_group'    => 'inyeccion_intravitrea',
            'lateralidad'        => 'OD',
            'opportunity_type'   => 'recurrente',
            'episode_started_at' => now()->subDays(120),
        ]);

        $s1 = $this->insertSolicitud('1000000006', '67028');
        $this->dispatchSolicitud('1000000006', '67028', 'OD', $s1);

        $newOpp = CrmOpportunity::where('contact_id', $contact->id)
            ->where('stage', 'nuevo')
            ->first();

        $this->assertNotNull($newOpp);
        $this->assertSame(0, (int) $newOpp->continuity_flag, 'continuity_flag debe ser 0 fuera de ventana');
        $this->assertNotNull($newOpp->previous_opportunity_id, 'Aún encadena el episodio anterior aunque fuera de ventana');
    }

    // =========================================================================
    // Case 7: procedure_group fallback = codigo cuando grupo_codigo es null
    // =========================================================================

    public function test_intent_stub_rule_uses_codigo_as_procedure_group(): void
    {
        // Insert a stub rule with no grupo_codigo (as seeded by crm:seed-procedure-rules)
        \DB::table('crm_procedure_rules')->insert([
            'codigo' => 'CYP-XYZ-099', 'grupo_codigo' => null,
            'nombre' => 'Procedimiento sin clasificar', 'tipo' => 'unica',
            'ventana_dias' => null, 'agrupar_por_ojo' => 1, 'genera_oportunidad' => 1,
            'activo' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $s1 = $this->insertSolicitud('1000000007', 'CYP-XYZ-099');
        $this->dispatchSolicitud('1000000007', 'CYP-XYZ-099', 'OD', $s1);

        $opp = CrmOpportunity::first();
        $this->assertSame('CYP-XYZ-099', $opp->procedure_group,
            'Stub sin grupo_codigo debe usar el codigo como procedure_group');
    }

    // =========================================================================
    // Case 8: diagnóstico (genera_oportunidad=0) no crea oportunidad
    // =========================================================================

    public function test_intent_diagnostico_does_not_create_opportunity_when_no_active_opp(): void
    {
        $s1 = $this->insertSolicitud('1000000008', 'SER-OFT-005');
        $this->dispatchSolicitud('1000000008', 'SER-OFT-005', null, $s1);

        $this->assertSame(0, CrmOpportunity::count(),
            'Diagnóstico sin opp activa no debe crear ninguna oportunidad');
    }

    // =========================================================================
    // Case 9: diagnóstico con opp activa existente → registra actividad, no crea opp nueva
    // =========================================================================

    public function test_intent_diagnostico_attaches_to_existing_active_opp(): void
    {
        // First create a real opportunity via a Faco OD event
        $s1 = $this->insertSolicitud('1000000009', 'CYP-CCA-001');
        $this->dispatchSolicitud('1000000009', 'CYP-CCA-001', 'OD', $s1);

        $this->assertSame(1, CrmOpportunity::count());

        // Now dispatch a diagnóstico for the same patient
        $s2 = $this->insertSolicitud('1000000009', 'SER-OFT-005');
        $this->dispatchSolicitud('1000000009', 'SER-OFT-005', null, $s2);

        // Must not create a second opportunity
        $this->assertSame(1, CrmOpportunity::count(),
            'Diagnóstico con opp activa no debe crear segunda oportunidad');
    }

    // =========================================================================
    // Verify UNIQUE constraint is gone (multiple opps per contact allowed at DB level)
    // =========================================================================

    public function test_multiple_opportunities_per_contact_allowed_at_db_level(): void
    {
        $contact = CrmContact::create([
            'name' => 'Test DB Constraint', 'cedula' => '9999999999',
            'phone' => '', 'resolution' => 'provisional',
        ]);

        CrmOpportunity::create(['contact_id' => $contact->id, 'title' => 'Opp 1', 'source' => 'solicitud']);
        CrmOpportunity::create(['contact_id' => $contact->id, 'title' => 'Opp 2', 'source' => 'solicitud']);

        $this->assertSame(2, CrmOpportunity::where('contact_id', $contact->id)->count(),
            'Sin UNIQUE, deben coexistir múltiples oportunidades por contacto');
    }
}

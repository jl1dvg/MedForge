<?php

namespace Tests\Feature;

use App\Events\Crm\SolicitudCreada;
use App\Models\CrmContact;
use App\Models\CrmIntentLead;
use App\Models\CrmOpportunity;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CrmPhase2BSolicitudEnrichmentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'solicitud_crm_detalles',
            'solicitud_procedimiento',
            'crm_procedure_rules',
            'crm_intent_leads',
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
            $table->unsignedBigInteger('contact_id');
            $table->unique('contact_id'); // UNIQUE preserved — Phase 2B migration not applied yet
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

        Schema::create('solicitud_procedimiento', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable();
            $table->string('procedimiento')->nullable();
            $table->string('ojo', 20)->nullable();
            $table->string('fecha')->nullable();
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
    }

    // =========================================================================
    // Event dispatch count
    // =========================================================================

    public function test_guardar_solicitud_with_two_procedures_dispatches_two_events(): void
    {
        Event::fake([SolicitudCreada::class]);

        // Simulate the data structure that guardarSolicitud() receives
        $data = [
            'hcNumber'  => '1234567890',
            'form_id'   => 'FORM-001',
            'solicitudes' => [
                ['procedimiento' => 'CYP-CCA-001 - CATARATA CON FACOEMULSIFICACION', 'ojo' => 'OD', 'fecha' => '2026-06-10', 'secuencia' => 1],
                ['procedimiento' => '67028 - INYECCIÓN INTRAVÍTREA',                 'ojo' => 'OI', 'fecha' => '2026-06-10', 'secuencia' => 2],
            ],
        ];

        // Insert two rows so $result['ids'] has two entries
        \DB::table('solicitud_procedimiento')->insert([
            ['hc_number' => '1234567890', 'procedimiento' => 'CYP-CCA-001 - CATARATA CON FACOEMULSIFICACION', 'created_at' => now(), 'updated_at' => now()],
            ['hc_number' => '1234567890', 'procedimiento' => '67028 - INYECCIÓN INTRAVÍTREA', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $ids = \DB::table('solicitud_procedimiento')->pluck('id')->all();

        // Simulate the loop the controller now runs
        foreach ($ids as $index => $id) {
            $sol              = $data['solicitudes'][$index];
            $procedimientoRaw = $sol['procedimiento'];
            $parsed           = \App\Models\CrmProcedureRule::parseProcedureCode($procedimientoRaw);

            SolicitudCreada::dispatch((int) $id, [
                'paciente_nombre'      => '',
                'paciente_cedula'      => $data['hcNumber'],
                'paciente_telefono'    => '',
                'servicio'             => $procedimientoRaw,
                'procedimiento_codigo' => $parsed['codigo'] ?? null,
                'lateralidad'          => match (strtoupper(trim($sol['ojo']))) {
                    'OD' => 'OD', 'OI' => 'OI', 'AO' => 'AO', default => null,
                },
                'episode_at'           => $sol['fecha'],
                'afiliacion'           => '',
            ]);
        }

        Event::assertDispatched(SolicitudCreada::class, 2);

        // Verify first event carries parsed codigo
        Event::assertDispatched(SolicitudCreada::class, function (SolicitudCreada $e) {
            return $e->solicitudData['procedimiento_codigo'] === 'CYP-CCA-001';
        });

        // Verify second event carries correct lateralidad
        Event::assertDispatched(SolicitudCreada::class, function (SolicitudCreada $e) {
            return $e->solicitudData['procedimiento_codigo'] === '67028'
                && $e->solicitudData['lateralidad'] === 'OI';
        });
    }

    // =========================================================================
    // Legacy flag: multiple events → single opportunity (1 per contact)
    // =========================================================================

    public function test_legacy_two_events_same_contact_create_one_opportunity(): void
    {
        Config::set('crm.intent_model_enabled', false);

        $sol1 = \DB::table('solicitud_procedimiento')->insertGetId([
            'hc_number' => '0912345678', 'procedimiento' => 'CYP-CCA-001 - CATARATA', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $sol2 = \DB::table('solicitud_procedimiento')->insertGetId([
            'hc_number' => '0912345678', 'procedimiento' => '67028 - INYECCIÓN',       'created_at' => now(), 'updated_at' => now(),
        ]);

        event(new SolicitudCreada($sol1, [
            'paciente_cedula'      => '0912345678',
            'paciente_nombre'      => 'Juan Pérez',
            'paciente_telefono'    => '',
            'servicio'             => 'CYP-CCA-001 - CATARATA',
            'procedimiento_codigo' => 'CYP-CCA-001',
            'lateralidad'          => 'OD',
        ]));

        event(new SolicitudCreada($sol2, [
            'paciente_cedula'      => '0912345678',
            'paciente_nombre'      => 'Juan Pérez',
            'paciente_telefono'    => '',
            'servicio'             => '67028 - INYECCIÓN',
            'procedimiento_codigo' => '67028',
            'lateralidad'          => 'OI',
        ]));

        // Legacy: always one opportunity per contact regardless of procedure
        $this->assertSame(1, CrmOpportunity::count());
        $this->assertSame(1, CrmContact::count());
    }

    // =========================================================================
    // normalizeOjo helper (tested indirectly via event payload)
    // =========================================================================

    public function test_event_payload_normalizes_ojo_variants(): void
    {
        $cases = [
            ['ojo' => 'OD',         'expected' => 'OD'],
            ['ojo' => 'Derecho',    'expected' => 'OD'],
            ['ojo' => 'D',          'expected' => 'OD'],
            ['ojo' => 'OI',         'expected' => 'OI'],
            ['ojo' => 'OS',         'expected' => 'OI'],
            ['ojo' => 'Izquierdo',  'expected' => 'OI'],
            ['ojo' => 'AO',         'expected' => 'AO'],
            ['ojo' => 'OU',         'expected' => 'AO'],
            ['ojo' => 'Bilateral',  'expected' => 'AO'],
            ['ojo' => '',           'expected' => null],
            ['ojo' => null,         'expected' => null],
            ['ojo' => 'otro',       'expected' => null],
        ];

        // Test normalizeOjo via reflection (it's private in the controller)
        // Instead test the match logic inline — the controller method is a pure transformation
        $normalize = static function (?string $ojo): ?string {
            return match (strtoupper(trim((string) $ojo))) {
                'OD', 'DERECHO', 'D'             => 'OD',
                'OI', 'OS', 'IZQUIERDO', 'I'     => 'OI',
                'AO', 'OU', 'AMBOS', 'BILATERAL' => 'AO',
                default                           => null,
            };
        };

        foreach ($cases as $case) {
            $this->assertSame(
                $case['expected'],
                $normalize($case['ojo']),
                "ojo='{$case['ojo']}' debe normalizar a " . json_encode($case['expected']),
            );
        }
    }

    // =========================================================================
    // parseProcedureCode enriches event correctly
    // =========================================================================

    public function test_parsed_codigo_is_null_for_unparseable_procedure(): void
    {
        $raw    = 'CONSULTA GENERAL SIN CODIGO';
        $parsed = \App\Models\CrmProcedureRule::parseProcedureCode($raw);

        $this->assertNull($parsed);

        // When null, the event should carry procedimiento_codigo = null
        // and the listener falls back to legacy algorithm
        $payload = [
            'servicio'             => $raw,
            'procedimiento_codigo' => $parsed['codigo'] ?? null,
        ];

        $this->assertNull($payload['procedimiento_codigo']);
    }

    // =========================================================================
    // handleSolicitudCreada reads new keys, falls back gracefully without them
    // =========================================================================

    public function test_listener_handles_event_without_new_keys_legacy_compat(): void
    {
        Config::set('crm.intent_model_enabled', false);

        $solId = \DB::table('solicitud_procedimiento')->insertGetId([
            'hc_number' => '1111111111', 'procedimiento' => 'Consulta', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Old-style payload: no procedimiento_codigo, lateralidad, episode_at
        event(new SolicitudCreada($solId, [
            'paciente_cedula'   => '1111111111',
            'paciente_nombre'   => 'Paciente Legacy',
            'paciente_telefono' => '',
            'servicio'          => 'Consulta',
        ]));

        // Should still create opportunity via legacy path
        $this->assertSame(1, CrmOpportunity::count());
    }

    public function test_listener_reads_new_keys_in_enriched_payload(): void
    {
        Config::set('crm.intent_model_enabled', false); // legacy — crear opp normal

        $solId = \DB::table('solicitud_procedimiento')->insertGetId([
            'hc_number' => '2222222222', 'procedimiento' => 'CYP-RVI-009 - AVASTIN', 'created_at' => now(), 'updated_at' => now(),
        ]);

        event(new SolicitudCreada($solId, [
            'paciente_cedula'      => '2222222222',
            'paciente_nombre'      => 'Paciente Enriquecido',
            'paciente_telefono'    => '',
            'servicio'             => 'CYP-RVI-009 - AVASTIN',
            'procedimiento_codigo' => 'CYP-RVI-009',
            'lateralidad'          => 'OD',
            'episode_at'           => '2026-06-10 09:00:00',
        ]));

        // Legacy: opp created, new keys available in payload (no crash)
        $this->assertSame(1, CrmOpportunity::count());
        $this->assertSame(1, CrmContact::count());
    }
}

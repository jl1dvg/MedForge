<?php

namespace Tests\Feature;

use App\Modules\Pacientes\Services\Paciente360Service;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Paciente360ServiceTest extends TestCase
{
    public function test_loads_solicitudes_section_with_summary_and_links_without_pdo(): void
    {
        $this->createSolicitudProcedimientoTable();
        $this->createSummaryTables();

        \DB::table('solicitud_procedimiento')->insert([
            'id' => 7,
            'hc_number' => '100',
            'form_id' => 'F001',
            'created_at' => '2026-06-17 09:00:00',
            'estado' => 'ingresada',
            'prioridad' => 'alta',
            'procedimiento' => 'FACO',
            'doctor' => 'DRA UNO',
            'ojo' => 'OD',
        ]);

        $payload = (new Paciente360Service())->getSection('100', 'solicitudes', 10);

        $this->assertSame('solicitudes', $payload['section']);
        $this->assertSame(1, $payload['summary']['solicitudes']);
        $this->assertSame(1, $payload['total_rows']);
        $this->assertSame('FACO', $payload['rows'][0]['procedimiento']);
        $this->assertSame('/v2/solicitudes/derivacion?hc_number=100&form_id=F001', $payload['rows'][0]['links']['derivacion']);
    }

    public function test_loads_agenda_section_with_status_history(): void
    {
        $this->createProcedimientosTable();
        $this->createVisitasTable();
        $this->createAgendaStatusHistoryTable();
        $this->createSummaryTables(['procedimiento_proyectado']);

        \DB::table('procedimiento_proyectado')->insert([
            'hc_number' => '100',
            'form_id' => 'A001',
            'procedimiento_proyectado' => 'CONSULTA',
            'doctor' => 'DRA UNO',
            'fecha' => '2026-06-18',
            'hora' => '09:00',
            'estado_agenda' => 'confirmado',
            'sede_departamento' => '',
            'id_sede' => 'MATRIZ',
            'visita_id' => null,
            'sigcenter_present' => 1,
        ]);

        \DB::table('procedimiento_proyectado_estado')->insert([
            'form_id' => 'A001',
            'estado' => 'confirmado',
            'fecha_hora_cambio' => '2026-06-17 08:00:00',
        ]);

        $payload = (new Paciente360Service())->getSection('100', 'agenda', 10);

        $this->assertSame('agenda', $payload['section']);
        $this->assertSame(1, $payload['summary']['agenda']);
        $this->assertSame('CONSULTA', $payload['rows'][0]['procedimiento']);
        $this->assertSame('confirmado', $payload['rows'][0]['historial_estados'][0]['estado']);
    }

    private function createSolicitudProcedimientoTable(): void
    {
        Schema::create('solicitud_procedimiento', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable();
            $table->string('form_id')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->string('estado')->nullable();
            $table->string('prioridad')->nullable();
            $table->string('procedimiento')->nullable();
            $table->string('doctor')->nullable();
            $table->string('ojo')->nullable();
        });
    }

    private function createProcedimientosTable(): void
    {
        Schema::create('procedimiento_proyectado', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable();
            $table->string('form_id')->nullable();
            $table->string('procedimiento_proyectado')->nullable();
            $table->string('doctor')->nullable();
            $table->date('fecha')->nullable();
            $table->string('hora')->nullable();
            $table->string('estado_agenda')->nullable();
            $table->string('sede_departamento')->nullable();
            $table->string('id_sede')->nullable();
            $table->unsignedBigInteger('visita_id')->nullable();
            $table->integer('sigcenter_present')->default(1);
        });
    }

    private function createVisitasTable(): void
    {
        Schema::create('visitas', function (Blueprint $table): void {
            $table->id();
            $table->date('fecha_visita')->nullable();
            $table->string('hora_llegada')->nullable();
        });
    }

    private function createAgendaStatusHistoryTable(): void
    {
        Schema::create('procedimiento_proyectado_estado', function (Blueprint $table): void {
            $table->id();
            $table->string('form_id')->nullable();
            $table->string('estado')->nullable();
            $table->dateTime('fecha_hora_cambio')->nullable();
        });
    }

    /**
     * @param array<int,string> $existingTables
     */
    private function createSummaryTables(array $existingTables = []): void
    {
        foreach ([
            'consulta_examenes',
            'consulta_data',
            'protocolo_data',
            'prefactura_paciente',
            'derivaciones_forms',
            'derivaciones_form_id',
            'recetas_items',
            'crm_leads',
            'crm_projects',
            'crm_tasks',
        ] as $tableName) {
            if (in_array($tableName, $existingTables, true) || Schema::hasTable($tableName)) {
                continue;
            }

            Schema::create($tableName, function (Blueprint $table) use ($tableName): void {
                $table->id();
                $table->string('hc_number')->nullable();
                if ($tableName === 'recetas_items') {
                    $table->string('form_id')->nullable();
                }
            });
        }
    }
}

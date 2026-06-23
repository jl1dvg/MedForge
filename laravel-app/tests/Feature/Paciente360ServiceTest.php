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

    public function test_loads_only_meaningful_clinical_history_with_complete_fields(): void
    {
        $this->createConsultaDataTable();
        $this->createSummaryTables(['consulta_data']);

        \DB::table('consulta_data')->insert([
            [
                'hc_number' => '100',
                'form_id' => 'C001',
                'fecha' => '2026-06-23',
                'motivo_consulta' => 'Dolor ocular',
                'enfermedad_actual' => 'Dolor en ojo izquierdo desde hace 2 semanas.',
                'examen_fisico' => 'Biomicroscopía sin secreción.',
                'plan' => 'Control en 2 semanas.',
                'diagnosticos' => '[{"idDiagnostico":"H40 - GLAUCOMA","ojo":"IZQUIERDO","evidencia":"1"},{"idDiagnostico":"Z961 - PRESENCIA DE LENTES INTRAOCULARES","ojo":"AMBOS OJOS"}]',
            ],
            [
                'hc_number' => '100',
                'form_id' => 'C002',
                'fecha' => '2026-06-22',
                'motivo_consulta' => '',
                'enfermedad_actual' => '',
                'examen_fisico' => '',
                'plan' => '',
                'diagnosticos' => '',
            ],
        ]);

        $payload = (new Paciente360Service())->getSection('100', 'consultas', 10);

        $this->assertSame('consultas', $payload['section']);
        $this->assertSame(1, $payload['summary']['consultas']);
        $this->assertSame(1, $payload['total_rows']);
        $this->assertSame('C001', $payload['rows'][0]['form_id']);
        $this->assertSame('Dolor en ojo izquierdo desde hace 2 semanas.', $payload['rows'][0]['enfermedad_actual']);
        $this->assertSame('Biomicroscopía sin secreción.', $payload['rows'][0]['examen_fisico']);
        $this->assertSame([
            'H40 - GLAUCOMA · IZQUIERDO',
            'Z961 - PRESENCIA DE LENTES INTRAOCULARES · AMBOS OJOS',
        ], $payload['rows'][0]['diagnosticos']);
    }

    public function test_clinical_history_ignores_rows_without_real_clinical_content(): void
    {
        $this->createConsultaDataTable();
        $this->createSummaryTables(['consulta_data']);

        \DB::table('consulta_data')->insert([
            'hc_number' => '100',
            'form_id' => 'C001',
            'fecha' => '2026-06-23',
            'motivo_consulta' => '',
            'enfermedad_actual' => '',
            'examen_fisico' => '',
            'plan' => '',
            'diagnosticos' => '[{"idDiagnostico":"H40 - GLAUCOMA"}]',
        ]);

        $payload = (new Paciente360Service())->getSection('100', 'consultas', 10);

        $this->assertSame(0, $payload['summary']['consultas']);
        $this->assertSame(0, $payload['total_rows']);
        $this->assertSame([], $payload['rows']);
    }

    public function test_examenes_section_points_to_v2_nas_result_files(): void
    {
        $this->createConsultaExamenesTable();
        $this->createSummaryTables(['consulta_examenes']);

        \DB::table('consulta_examenes')->insert([
            'id' => 9,
            'hc_number' => '100',
            'form_id' => 'E001',
            'consulta_fecha' => '2026-06-23',
            'created_at' => '2026-06-23 08:00:00',
            'estado' => 'ATENDIDO',
            'prioridad' => '',
            'examen_nombre' => 'OCT NERVIO OPTICO',
            'examen_codigo' => 'IMA-DIA-017',
            'doctor' => 'DRA UNO',
            'turno' => '281032',
        ]);

        $payload = (new Paciente360Service())->getSection('100', 'examenes', 10);

        $this->assertSame('/v2/imagenes/examenes-realizados/nas/list?hc_number=100&form_id=E001', $payload['rows'][0]['links']['archivos_list']);
        $this->assertSame('/v2/imagenes/examenes-realizados?hc_number=100', $payload['rows'][0]['links']['imagenes']);
    }

    public function test_protocolos_section_exposes_pdf_and_cirugias_links(): void
    {
        $this->createProtocoloDataTable();
        $this->createSummaryTables(['protocolo_data']);

        \DB::table('protocolo_data')->insert([
            'hc_number' => '100',
            'form_id' => 'P001',
            'fecha_inicio' => '2026-06-17',
            'membrete' => 'Facoemulsificacion',
            'status' => 'firmado',
        ]);

        $payload = (new Paciente360Service())->getSection('100', 'protocolos', 10);

        $this->assertSame('protocolos', $payload['section']);
        $this->assertSame('/v2/cirugias?form_id=P001', $payload['rows'][0]['links']['cirugia']);
        $this->assertSame('/v2/cirugias/wizard?form_id=P001&hc_number=100', $payload['rows'][0]['links']['editar']);
        $this->assertSame('/v2/reports/protocolo/pdf?form_id=P001&hc_number=100', $payload['rows'][0]['links']['pdf']);
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

    private function createConsultaDataTable(): void
    {
        Schema::create('consulta_data', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable();
            $table->string('form_id')->nullable();
            $table->date('fecha')->nullable();
            $table->text('motivo_consulta')->nullable();
            $table->text('enfermedad_actual')->nullable();
            $table->text('examen_fisico')->nullable();
            $table->text('plan')->nullable();
            $table->text('diagnosticos')->nullable();
        });
    }

    private function createConsultaExamenesTable(): void
    {
        Schema::create('consulta_examenes', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable();
            $table->string('form_id')->nullable();
            $table->date('consulta_fecha')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->string('estado')->nullable();
            $table->string('prioridad')->nullable();
            $table->string('examen_nombre')->nullable();
            $table->string('examen_codigo')->nullable();
            $table->string('doctor')->nullable();
            $table->string('turno')->nullable();
        });
    }

    private function createProtocoloDataTable(): void
    {
        Schema::create('protocolo_data', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable();
            $table->string('form_id')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->string('membrete')->nullable();
            $table->string('status')->nullable();
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

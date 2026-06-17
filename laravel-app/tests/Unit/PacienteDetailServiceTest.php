<?php

namespace Tests\Unit;

use App\Models\PatientDatum;
use App\Modules\Pacientes\Services\PacienteDetailService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PacienteDetailServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
    }

    public function test_it_builds_patient_detail_context_with_laravel_queries(): void
    {
        PatientDatum::query()->create([
            'hc_number' => '0701425019',
            'cedula' => null,
            'fname' => 'TOMAS',
            'mname' => 'DAVID',
            'lname' => 'ROMERO',
            'lname2' => 'MONTOYA',
            'afiliacion' => 'ISSFA',
            'fecha_nacimiento' => '1959-02-15',
            'sexo' => 'M',
            'celular' => '0999999999',
            'email' => 'tomas@example.test',
            'direccion' => 'GUAYAQUIL',
        ]);

        DB::table('consulta_data')->insert([
            'hc_number' => '0701425019',
            'form_id' => 'F001',
            'fecha' => '2026-06-01',
            'motivo_consulta' => 'Control',
            'enfermedad_actual' => 'Estable',
            'examen_fisico' => 'AO normal',
            'plan' => 'Seguimiento',
            'diagnosticos' => json_encode([['idDiagnostico' => 'H25.1']]),
            'antecedente_alergico' => 'Alergia a penicilina',
        ]);

        DB::table('procedimiento_proyectado')->insert([
            'id' => 10,
            'form_id' => 'F001',
            'hc_number' => '0701425019',
            'procedimiento_proyectado' => 'CIRUGIAS - CATARATA - FACO',
            'doctor' => 'Jorge Luis de Vera Gutiérrez',
            'fecha' => '2026-06-20',
            'hora' => '09:00:00',
            'sigcenter_present' => true,
        ]);

        DB::table('solicitud_procedimiento')->insert([
            'hc_number' => '0701425019',
            'procedimiento' => 'FACO',
            'tipo' => 'Cirugia',
            'form_id' => 'S001',
            'created_at' => '2026-06-10 08:00:00',
        ]);

        DB::table('prefactura_paciente')->insert([
            'id' => 5,
            'hc_number' => '0701425019',
            'form_id' => 'P001',
            'cod_derivacion' => 'DER-1',
            'fecha_creacion' => '2026-06-11 10:00:00',
            'fecha_vigencia' => now()->addMonth()->toDateString(),
            'procedimientos' => json_encode([['descripcion' => 'FACO', 'lateralidad' => 'OD']]),
        ]);

        DB::table('protocolo_data')->insert([
            'hc_number' => '0701425019',
            'form_id' => 'F001',
            'membrete' => 'Protocolo catarata',
            'fecha_inicio' => '2026-06-20',
        ]);

        $context = app(PacienteDetailService::class)->obtenerContextoPaciente('0701425019');

        $this->assertSame('0701425019', $context['patientData']['cedula']);
        $this->assertSame('TOMAS', $context['patientData']['fname']);
        $this->assertSame(67, $context['patientAge']);
        $this->assertSame('Con Cobertura', $context['coverageStatus']);
        $this->assertContains('ISSFA', $context['afiliacionesDisponibles']);
        $this->assertArrayHasKey('H25.1', $context['diagnosticos']);
        $this->assertArrayHasKey('Jorge Luis de Vera Gutiérrez', $context['medicos']);
        $this->assertCount(2, $context['timelineItems']);
        $this->assertSame('Prefactura', $context['timelineItems'][0]['origen']);
        $this->assertSame('Control', $context['eventos'][0]['motivo_consulta']);
        $this->assertSame('Protocolo catarata', $context['documentos'][0]['membrete']);
        $this->assertArrayHasKey('CIRUGIAS', $context['estadisticas']);
    }

    public function test_it_returns_empty_context_when_patient_does_not_exist(): void
    {
        $this->assertSame([], app(PacienteDetailService::class)->obtenerContextoPaciente('NOPE'));
    }

    private function createTables(): void
    {
        Schema::dropIfExists('protocolo_data');
        Schema::dropIfExists('prefactura_paciente');
        Schema::dropIfExists('solicitud_procedimiento');
        Schema::dropIfExists('procedimiento_proyectado');
        Schema::dropIfExists('consulta_data');
        Schema::dropIfExists('patient_data');

        Schema::create('patient_data', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable()->unique();
            $table->string('cedula', 64)->nullable();
            $table->date('fecha_caducidad')->nullable();
            $table->string('lname', 100);
            $table->string('lname2', 100)->nullable();
            $table->string('fname', 100);
            $table->string('mname', 100)->nullable();
            $table->string('afiliacion')->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('sexo', 10)->nullable();
            $table->string('celular', 15)->nullable();
            $table->string('telefono_alt', 64)->nullable();
            $table->string('ciudad', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('direccion')->nullable();
            $table->unsignedBigInteger('medico_tratante_id')->nullable();
            $table->string('sede_principal', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('consulta_data', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number');
            $table->string('form_id')->nullable();
            $table->date('fecha')->nullable();
            $table->text('motivo_consulta')->nullable();
            $table->text('enfermedad_actual')->nullable();
            $table->text('examen_fisico')->nullable();
            $table->text('plan')->nullable();
            $table->text('diagnosticos')->nullable();
            $table->text('antecedente_alergico')->nullable();
        });

        Schema::create('procedimiento_proyectado', function (Blueprint $table): void {
            $table->id();
            $table->string('form_id')->nullable();
            $table->string('hc_number');
            $table->string('procedimiento_proyectado')->nullable();
            $table->string('doctor')->nullable();
            $table->date('fecha')->nullable();
            $table->time('hora')->nullable();
            $table->boolean('sigcenter_present')->nullable()->default(true);
        });

        Schema::create('solicitud_procedimiento', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number');
            $table->string('procedimiento')->nullable();
            $table->string('tipo')->nullable();
            $table->string('form_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('prefactura_paciente', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number');
            $table->string('form_id')->nullable();
            $table->string('cod_derivacion')->nullable();
            $table->timestamp('fecha_creacion')->nullable();
            $table->date('fecha_vigencia')->nullable();
            $table->text('procedimientos')->nullable();
        });

        Schema::create('protocolo_data', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number');
            $table->string('form_id')->nullable();
            $table->string('membrete')->nullable();
            $table->date('fecha_inicio')->nullable();
        });
    }
}

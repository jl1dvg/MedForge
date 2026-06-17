<?php

namespace Tests\Feature;

use App\Models\PatientDatum;
use App\Modules\Pacientes\Services\PacienteReadService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PacientesV2ReadServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-16 10:00:00');
        $this->createTables();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_patient_list_preserves_react_contract_with_laravel_queries(): void
    {
        \DB::table('patient_data')->insert([
            'hc_number' => '12345',
            'cedula' => '',
            'fname' => 'ADRIANA',
            'mname' => 'SOFIA',
            'lname' => 'PINOS',
            'lname2' => 'AGUILAR',
            'afiliacion' => 'ISSPOL',
            'fecha_nacimiento' => '2012-01-01',
            'sexo' => 'F',
            'celular' => '0999999999',
            'telefono_alt' => '042681140',
            'email' => 'paciente@example.test',
            'direccion' => 'Direccion',
            'ciudad' => 'Guayaquil',
            'created_at' => '2026-06-01 09:00:00',
            'updated_at' => '2026-06-01 09:00:00',
        ]);
        \DB::table('patient_data')->insert([
            'hc_number' => '2024-11-08 09:20:00',
            'fname' => 'IMA-DIA-017',
            'lname' => 'IMAGENES',
            'afiliacion' => '129.1100',
            'created_at' => '2026-06-01 09:00:00',
            'updated_at' => '2026-06-01 09:00:00',
        ]);

        $this->insertUser('OPTOMETRIA OPT', 'Optometria');
        $this->insertUser('DRA UNO', 'Cirujano Oftalmologo');
        $this->insertUser('DR DOS', 'Cirujano Oftalmologo');

        $this->insertProcedimiento('12345', 'OPTOMETRIA OPT', '2026-06-20', '08:00', 'CEIBOS');
        $this->insertProcedimiento('12345', 'DRA UNO', '2026-06-10', '08:00', 'VILLA CLUB');
        $this->insertProcedimiento('12345', 'DR DOS', '2026-06-15', '08:00', 'CEIBOS');
        $this->insertProcedimiento('12345', 'DR DOS', '2026-06-16', '08:00', 'CEIBOS');

        $payload = app(PacienteReadService::class)->obtenerPacientesReact(null, 0);

        $this->assertSame(1, $payload['meta']['total']);
        $this->assertCount(1, $payload['data']);
        $patient = $payload['data'][0];
        $this->assertSame('12345', $patient['hc_number']);
        $this->assertSame('12345', $patient['cedula']);
        $this->assertSame('ADRIANA SOFIA PINOS AGUILAR', $patient['display_name']);
        $this->assertSame('DR DOS', $patient['medico']);
        $this->assertSame('DR DOS', $patient['medico_tratante']['nombre']);
        $this->assertSame(2, $patient['medico_tratante']['procedimientos_count']);
        $this->assertSame('ceibos', $patient['sede']);
        $this->assertSame('CEIBOS', $patient['sede_info']['nombre']);
        $this->assertSame('publico', $patient['tipo_afiliacion']);
    }

    public function test_kpis_and_catalogs_use_laravel_queries(): void
    {
        \DB::table('patient_data')->insert([
            'hc_number' => '10001',
            'fname' => 'BASE',
            'lname' => 'UNO',
            'afiliacion' => 'PARTICULAR',
            'created_at' => '2026-06-01 09:00:00',
            'updated_at' => '2026-06-01 09:00:00',
        ]);
        \DB::table('patient_data')->insert([
            'hc_number' => '10002',
            'fname' => 'BASE',
            'lname' => 'DOS',
            'afiliacion' => '129.1100',
            'created_at' => '2026-05-01 09:00:00',
            'updated_at' => '2026-05-01 09:00:00',
        ]);
        $this->insertUser('OPTOMETRIA OPT', 'Optometria');
        $this->insertUser('DRA TRATANTE', 'Cirujano Oftalmologo');
        $this->insertProcedimiento('10001', 'DRA TRATANTE', '2026-06-16', '08:00', 'MATRIZ');
        $this->insertSolicitud('10001', 'autorizada');

        $service = app(PacienteReadService::class);

        $this->assertSame([
            'total_pacientes' => 2,
            'pacientes_nuevos' => 1,
            'citas_hoy' => 1,
            'solicitudes_activas' => 1,
        ], $service->obtenerKpisReact());

        $catalogos = $service->obtenerCatalogosReact();
        $this->assertSame(['ceibos', 'matriz'], array_column($catalogos['sedes'], 'id'));
        $this->assertSame(['DRA TRATANTE'], array_column($catalogos['medicos'], 'nombre'));
        $this->assertSame(['PARTICULAR'], array_column($catalogos['afiliaciones'], 'nombre'));
        $this->assertSame(['publico', 'privado', 'particular', 'fundacional', 'otros'], array_column($catalogos['tipos_afiliacion'], 'id'));
    }

    private function createTables(): void
    {
        Schema::dropIfExists('solicitud_procedimiento');
        Schema::dropIfExists('consulta_data');
        Schema::dropIfExists('procedimiento_proyectado');
        Schema::dropIfExists('users');
        Schema::dropIfExists('patient_data');

        Schema::create('patient_data', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable()->unique();
            $table->string('cedula', 64)->nullable();
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

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre')->nullable();
            $table->string('full_name')->nullable();
            $table->string('subespecialidad')->nullable();
            $table->string('especialidad')->nullable();
            $table->string('sede')->nullable();
            $table->string('id_trabajador')->nullable();
        });

        Schema::create('procedimiento_proyectado', function (Blueprint $table): void {
            $table->id();
            $table->integer('form_id')->nullable();
            $table->string('procedimiento_proyectado')->nullable();
            $table->string('doctor')->nullable();
            $table->string('hc_number')->nullable();
            $table->string('sede_departamento')->nullable();
            $table->string('id_sede')->nullable();
            $table->date('fecha')->nullable();
            $table->time('hora')->nullable();
            $table->boolean('sigcenter_present')->nullable()->default(true);
        });

        Schema::create('consulta_data', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable();
            $table->date('fecha')->nullable();
            $table->text('antecedente_alergico')->nullable();
        });

        Schema::create('solicitud_procedimiento', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable();
            $table->string('estado')->nullable();
        });
    }

    private function insertUser(string $nombre, string $especialidad): void
    {
        \DB::table('users')->insert([
            'nombre' => $nombre,
            'full_name' => $nombre,
            'especialidad' => $especialidad,
            'subespecialidad' => $especialidad,
        ]);
    }

    private function insertProcedimiento(string $hcNumber, string $doctor, string $fecha, string $hora, string $sede): void
    {
        \DB::table('procedimiento_proyectado')->insert([
            'form_id' => random_int(1000, 9999),
            'procedimiento_proyectado' => 'Consulta',
            'doctor' => $doctor,
            'hc_number' => $hcNumber,
            'sede_departamento' => $sede,
            'id_sede' => $sede,
            'fecha' => $fecha,
            'hora' => $hora,
            'sigcenter_present' => true,
        ]);
    }

    private function insertSolicitud(string $hcNumber, string $estado): void
    {
        \DB::table('solicitud_procedimiento')->insert([
            'hc_number' => $hcNumber,
            'estado' => $estado,
        ]);
    }
}

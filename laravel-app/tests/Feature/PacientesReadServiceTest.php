<?php

namespace Tests\Feature;

use App\Modules\Pacientes\Services\PacienteReadService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PacientesReadServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-17 10:00:00'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_patient_list_uses_hc_number_and_does_not_require_optional_patient_columns(): void
    {
        $this->createMinimalPatientDataTable();
        $this->createProcedimientosTable();
        $this->createConsultaDataTable();
        $this->createSolicitudProcedimientoTable();

        \DB::table('patient_data')->insert([
            [
                'hc_number' => '100',
                'fname' => 'ANA',
                'mname' => 'MARIA',
                'lname' => 'PEREZ',
                'lname2' => 'LOPEZ',
                'afiliacion' => 'ISSFA',
                'fecha_nacimiento' => '1990-01-01',
                'sexo' => 'F',
                'celular' => '0999999999',
                'email' => 'ana@example.test',
                'direccion' => 'Direccion',
                'ciudad' => 'Guayaquil',
                'created_at' => '2026-06-02 09:00:00',
            ],
            [
                'hc_number' => '2024-11-08 09:20:00',
                'fname' => 'IMA-DIA-017',
                'mname' => '',
                'lname' => 'IMAGENES',
                'lname2' => '',
                'afiliacion' => '129.1100',
                'fecha_nacimiento' => null,
                'sexo' => '',
                'celular' => '',
                'email' => '',
                'direccion' => '',
                'ciudad' => '',
                'created_at' => '2026-06-16 09:00:00',
            ],
        ]);

        \DB::table('procedimiento_proyectado')->insert([
            'hc_number' => '100',
            'doctor' => 'DRA UNO',
            'procedimiento_proyectado' => 'CONSULTA',
            'fecha' => '2026-06-18',
            'hora' => '09:00',
            'id_sede' => 'CEIBOS',
            'sede_departamento' => '',
            'sigcenter_present' => 1,
        ]);

        $payload = (new PacienteReadService())->obtenerPacientesReact(null, 0);

        $this->assertSame(1, $payload['meta']['total']);
        $this->assertCount(1, $payload['data']);
        $this->assertSame('100', $payload['data'][0]['hc_number']);
        $this->assertSame('100', $payload['data'][0]['cedula']);
        $this->assertSame('', $payload['data'][0]['telefono_alt']);
        $this->assertSame('publico', $payload['data'][0]['tipo_afiliacion']);
        $this->assertSame('ceibos', $payload['data'][0]['sede']);
    }

    public function test_catalogs_use_affiliation_catalog_and_fixed_sedes(): void
    {
        $this->createMinimalPatientDataTable();
        $this->createSigcenterAfiliacionesTable();
        $this->createUsersTable();

        \DB::table('sigcenter_afiliaciones')->insert([
            ['nombre' => 'ISSFA', 'activo' => 1],
            ['nombre' => 'PARTICULAR', 'activo' => 1],
            ['nombre' => '129.1100', 'activo' => 1],
            ['nombre' => 'INACTIVA', 'activo' => 0],
        ]);

        \DB::table('users')->insert([
            'nombre' => 'DRA UNO',
            'full_name' => 'DRA UNO',
            'subespecialidad' => 'Cirujano Oftalmologo',
            'especialidad' => 'Cirujano Oftalmologo',
            'sede' => 'MATRIZ',
            'id_trabajador' => '1',
        ]);

        $catalogos = (new PacienteReadService())->obtenerCatalogosReact();

        $this->assertSame(['matriz', 'ceibos'], array_column($catalogos['sedes'], 'id'));
        $this->assertSame(['ISSFA', 'PARTICULAR'], array_column($catalogos['afiliaciones'], 'nombre'));
        $this->assertSame('DRA UNO', $catalogos['medicos'][0]['nombre']);
    }

    public function test_patient_list_only_shows_treating_doctors_with_ophthalmologist_surgeon_specialty(): void
    {
        $this->createMinimalPatientDataTable();
        $this->createProcedimientosTable();
        $this->createUsersTable();

        \DB::table('patient_data')->insert([
            [
                'hc_number' => '100',
                'fname' => 'ANA',
                'mname' => '',
                'lname' => 'PEREZ',
                'lname2' => '',
                'afiliacion' => 'PARTICULAR',
                'fecha_nacimiento' => '1990-01-01',
                'sexo' => 'F',
                'celular' => '',
                'email' => '',
                'direccion' => '',
                'ciudad' => '',
                'created_at' => '2026-06-02 09:00:00',
            ],
            [
                'hc_number' => '101',
                'fname' => 'LUIS',
                'mname' => '',
                'lname' => 'MORA',
                'lname2' => '',
                'afiliacion' => 'PARTICULAR',
                'fecha_nacimiento' => '1991-01-01',
                'sexo' => 'M',
                'celular' => '',
                'email' => '',
                'direccion' => '',
                'ciudad' => '',
                'created_at' => '2026-06-02 09:00:00',
            ],
        ]);

        \DB::table('users')->insert([
            [
                'nombre' => 'OPTOMETRIA OPT',
                'full_name' => 'OPTOMETRIA OPT',
                'subespecialidad' => 'Optometria',
                'especialidad' => 'Optometria',
                'sede' => 'MATRIZ',
                'id_trabajador' => 'OPT',
            ],
            [
                'nombre' => 'Andres Fernando Polit Hoyos',
                'full_name' => 'Andres Fernando Polit Hoyos',
                'subespecialidad' => 'Cornea',
                'especialidad' => 'Cirujano Oftalmologo',
                'sede' => 'MATRIZ',
                'id_trabajador' => '32',
            ],
        ]);

        \DB::table('procedimiento_proyectado')->insert([
            [
                'hc_number' => '100',
                'doctor' => 'OPTOMETRIA OPT',
                'procedimiento_proyectado' => 'CONSULTA',
                'fecha' => '2026-06-17',
                'hora' => '09:00',
                'id_sede' => 'MATRIZ',
                'sede_departamento' => '',
                'sigcenter_present' => 1,
            ],
            [
                'hc_number' => '101',
                'doctor' => 'POLIT HOYOS ANDRES FERNANDO',
                'procedimiento_proyectado' => 'CONSULTA',
                'fecha' => '2026-06-17',
                'hora' => '09:00',
                'id_sede' => 'MATRIZ',
                'sede_departamento' => '',
                'sigcenter_present' => 1,
            ],
        ]);

        $payload = (new PacienteReadService())->obtenerPacientesReact(null, 0);
        $byHc = collect($payload['data'])->keyBy('hc_number');

        $this->assertSame('', $byHc['100']['medico']);
        $this->assertNull($byHc['100']['medico_tratante']);
        $this->assertSame('Andres Fernando Polit Hoyos', $byHc['101']['medico']);
        $this->assertSame('Cirujano Oftalmologo', $byHc['101']['medico_tratante']['especialidad']);
    }

    public function test_kpis_count_valid_patients_current_month_appointments_and_active_requests(): void
    {
        $this->createMinimalPatientDataTable();
        $this->createProcedimientosTable();
        $this->createSolicitudProcedimientoTable();

        \DB::table('patient_data')->insert([
            [
                'hc_number' => '100',
                'fname' => 'ANA',
                'mname' => '',
                'lname' => 'PEREZ',
                'lname2' => '',
                'afiliacion' => 'ISSFA',
                'fecha_nacimiento' => '1990-01-01',
                'sexo' => 'F',
                'celular' => '',
                'email' => '',
                'direccion' => '',
                'ciudad' => '',
                'created_at' => '2026-06-02 09:00:00',
            ],
            [
                'hc_number' => '101',
                'fname' => 'LUIS',
                'mname' => '',
                'lname' => 'MORA',
                'lname2' => '',
                'afiliacion' => 'PARTICULAR',
                'fecha_nacimiento' => '1991-01-01',
                'sexo' => 'M',
                'celular' => '',
                'email' => '',
                'direccion' => '',
                'ciudad' => '',
                'created_at' => '2026-05-31 09:00:00',
            ],
            [
                'hc_number' => '281295-RETINOGRAFIA',
                'fname' => 'BAD',
                'mname' => '',
                'lname' => 'ROW',
                'lname2' => '',
                'afiliacion' => '129.1100',
                'fecha_nacimiento' => null,
                'sexo' => '',
                'celular' => '',
                'email' => '',
                'direccion' => '',
                'ciudad' => '',
                'created_at' => '2026-06-10 09:00:00',
            ],
        ]);

        \DB::table('procedimiento_proyectado')->insert([
            'hc_number' => '100',
            'doctor' => 'DRA UNO',
            'procedimiento_proyectado' => 'CONSULTA',
            'fecha' => '2026-06-17',
            'hora' => '09:00',
            'id_sede' => 'MATRIZ',
            'sede_departamento' => '',
            'sigcenter_present' => 1,
        ]);

        \DB::table('solicitud_procedimiento')->insert([
            'hc_number' => '100',
            'procedimiento' => 'CIRUGIA',
            'created_at' => '2026-06-17 09:00:00',
            'tipo' => 'cirugia',
            'form_id' => 'F1',
            'estado' => 'ingresada',
        ]);

        $kpis = (new PacienteReadService())->obtenerKpisReact();

        $this->assertSame(2, $kpis['total_pacientes']);
        $this->assertSame(1, $kpis['pacientes_nuevos']);
        $this->assertSame(1, $kpis['citas_hoy']);
        $this->assertSame(1, $kpis['solicitudes_activas']);
    }

    public function test_datatable_payload_uses_laravel_query_builder(): void
    {
        $this->createMinimalPatientDataTable();
        $this->createConsultaDataTable();

        \DB::table('patient_data')->insert([
            [
                'hc_number' => '100',
                'fname' => 'ANA',
                'mname' => '',
                'lname' => 'PEREZ',
                'lname2' => 'LOPEZ',
                'afiliacion' => 'ISSFA',
                'fecha_nacimiento' => '1990-01-01',
                'sexo' => 'F',
                'celular' => '',
                'email' => '',
                'direccion' => '',
                'ciudad' => '',
                'created_at' => '2026-06-02 09:00:00',
            ],
            [
                'hc_number' => '101',
                'fname' => 'LUIS',
                'mname' => '',
                'lname' => 'MORA',
                'lname2' => '',
                'afiliacion' => 'PARTICULAR',
                'fecha_nacimiento' => '1991-01-01',
                'sexo' => 'M',
                'celular' => '',
                'email' => '',
                'direccion' => '',
                'ciudad' => '',
                'created_at' => '2026-06-02 09:00:00',
            ],
        ]);

        \DB::table('consulta_data')->insert([
            'hc_number' => '100',
            'fecha' => '2026-06-10',
        ]);

        $payload = (new PacienteReadService())->obtenerPacientesPaginados(
            0,
            10,
            'ana',
            'ultima_fecha',
            'DESC'
        );

        $this->assertSame(2, $payload['recordsTotal']);
        $this->assertSame(1, $payload['recordsFiltered']);
        $this->assertSame('100', $payload['data'][0]['hc_number']);
        $this->assertSame('10/06/2026', $payload['data'][0]['ultima_fecha']);
        $this->assertStringContainsString('/v2/pacientes?hc_number=100', $payload['data'][0]['acciones_html']);
    }

    private function createMinimalPatientDataTable(): void
    {
        Schema::create('patient_data', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable();
            $table->string('fname')->nullable();
            $table->string('mname')->nullable();
            $table->string('lname')->nullable();
            $table->string('lname2')->nullable();
            $table->string('afiliacion')->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('sexo')->nullable();
            $table->string('celular')->nullable();
            $table->string('email')->nullable();
            $table->string('direccion')->nullable();
            $table->string('ciudad')->nullable();
            $table->dateTime('created_at')->nullable();
        });
    }

    private function createProcedimientosTable(): void
    {
        Schema::create('procedimiento_proyectado', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable();
            $table->string('doctor')->nullable();
            $table->string('procedimiento_proyectado')->nullable();
            $table->date('fecha')->nullable();
            $table->string('hora')->nullable();
            $table->string('id_sede')->nullable();
            $table->string('sede_departamento')->nullable();
            $table->integer('sigcenter_present')->default(1);
        });
    }

    private function createConsultaDataTable(): void
    {
        Schema::create('consulta_data', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable();
            $table->date('fecha')->nullable();
        });
    }

    private function createSolicitudProcedimientoTable(): void
    {
        Schema::create('solicitud_procedimiento', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable();
            $table->string('procedimiento')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->string('tipo')->nullable();
            $table->string('form_id')->nullable();
            $table->string('estado')->nullable();
        });
    }

    private function createSigcenterAfiliacionesTable(): void
    {
        Schema::create('sigcenter_afiliaciones', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre')->nullable();
            $table->boolean('activo')->nullable();
        });
    }

    private function createUsersTable(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre')->nullable();
            $table->string('full_name')->nullable();
            $table->string('subespecialidad')->nullable();
            $table->string('especialidad')->nullable();
            $table->string('sede')->nullable();
            $table->string('id_trabajador')->nullable();
        });
    }
}

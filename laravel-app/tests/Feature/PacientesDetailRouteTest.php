<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PacientesDetailRouteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->string('nombre')->nullable();
            $table->string('email')->nullable();
            $table->string('profile_photo')->nullable();
            $table->string('especialidad')->nullable();
            $table->string('subespecialidad')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->timestamps();
        });

        DB::table('roles')->insert([
            'id' => 1,
            'name' => 'administrativo',
        ]);

        DB::table('users')->insert([
            'id' => 40,
            'username' => 'doctor',
            'password' => 'secret',
            'nombre' => 'Doctor',
            'email' => 'doctor@example.test',
            'profile_photo' => null,
            'especialidad' => 'Cirujano Oftalmologo',
            'subespecialidad' => 'Retina',
            'role_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_html_detail_route_redirects_to_react_patient_app(): void
    {
        $this->actingAs(\App\Models\User::query()->findOrFail(40));

        $response = $this->withoutMiddleware()
            ->get('/v2/pacientes/detalles?hc_number=0701425019');

        $response
            ->assertRedirect('/v2/pacientes?hc_number=0701425019');
    }

    public function test_patient_flow_view_uses_vite_bundle_without_legacy_script(): void
    {
        $this->actingAs(\App\Models\User::query()->findOrFail(40));

        $response = $this->withoutMiddleware()
            ->get('/v2/pacientes/flujo');

        $response
            ->assertOk()
            ->assertSee('pacientes-flujo')
            ->assertDontSee('/js/pages/pacientes/flujo.js', false);
    }

    public function test_json_detail_rehydrates_manual_doctor_site_and_next_appointment(): void
    {
        $this->actingAs(\App\Models\User::query()->findOrFail(40));
        $this->createPatientDataTable();
        $this->createProcedimientosTable();

        DB::table('patient_data')->insert([
            'hc_number' => '0902143791',
            'fname' => 'ROSA',
            'mname' => 'VICENTA',
            'lname' => 'ZAMBRANO',
            'lname2' => 'SABANDO',
            'afiliacion' => 'ECUASANITAS',
            'fecha_nacimiento' => '1941-08-16',
            'sexo' => 'F',
            'celular' => '0993798738',
            'email' => 'rosa@example.test',
            'direccion' => '',
            'ciudad' => 'GUAYAQUIL',
            'medico_tratante_id' => 40,
            'sede_principal' => 'MATRIZ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('procedimiento_proyectado')->insert([
            'hc_number' => '0902143791',
            'form_id' => 'FUT-001',
            'procedimiento_proyectado' => 'CONSULTA OFTALMOLOGICA',
            'doctor' => 'Doctor',
            'fecha' => '2099-07-07',
            'hora' => '10:00',
            'id_sede' => 'MATRIZ',
            'sede_departamento' => '',
            'sigcenter_present' => 1,
        ]);

        $this->withoutMiddleware()
            ->getJson('/v2/pacientes/detalles?hc_number=0902143791')
            ->assertOk()
            ->assertJsonPath('data.patientData.medico_tratante.nombre', 'Doctor')
            ->assertJsonPath('data.patientData.medico_tratante.especialidad', 'Cirujano Oftalmologo')
            ->assertJsonPath('data.patientData.sede', 'matriz')
            ->assertJsonPath('data.patientData.sede_info.nombre', 'MATRIZ')
            ->assertJsonPath('data.patientData.proxima_cita.fecha', '2099-07-07')
            ->assertJsonPath('data.patientData.proxima_cita.hora', '10:00');
    }

    private function createPatientDataTable(): void
    {
        Schema::dropIfExists('patient_data');
        Schema::create('patient_data', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->unique();
            $table->string('fname');
            $table->string('mname')->nullable();
            $table->string('lname');
            $table->string('lname2')->nullable();
            $table->string('afiliacion')->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('sexo')->nullable();
            $table->string('celular')->nullable();
            $table->string('email')->nullable();
            $table->string('direccion')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('medico_tratante_id')->nullable();
            $table->string('sede_principal')->nullable();
            $table->timestamps();
        });
    }

    private function createProcedimientosTable(): void
    {
        Schema::dropIfExists('procedimiento_proyectado');
        Schema::create('procedimiento_proyectado', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable();
            $table->string('form_id')->nullable();
            $table->string('procedimiento_proyectado')->nullable();
            $table->string('doctor')->nullable();
            $table->date('fecha')->nullable();
            $table->string('hora')->nullable();
            $table->string('id_sede')->nullable();
            $table->string('sede_departamento')->nullable();
            $table->integer('sigcenter_present')->default(1);
        });
    }
}

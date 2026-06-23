<?php

namespace Tests\Unit;

use App\Modules\Pacientes\Services\MedicoTratanteResolver;
use App\Modules\Pacientes\Services\PacientesFlujoService;
use App\Modules\Pacientes\Services\SedePacienteResolver;
use App\Modules\Pacientes\Services\TipoAfiliacionResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

class PacientesV2ResolversTest extends TestCase
{
    public function test_medico_tratante_uses_most_projected_procedures_and_recent_tiebreaker(): void
    {
        $pdo = $this->makePdo();
        $this->createUsersTable($pdo);
        $this->createProcedimientosTable($pdo);

        $this->insertUser($pdo, 'OPTOMETRIA OPT', 'Optometria');
        $this->insertUser($pdo, 'DRA UNO', 'Cirujano Oftalmologo');
        $this->insertUser($pdo, 'DR DOS', 'Cirujano Oftalmologo');

        $this->insertProcedimiento($pdo, 'HC1', 'OPTOMETRIA OPT', '2026-06-16', '09:00', 'CEIBOS');
        $this->insertProcedimiento($pdo, 'HC1', 'DRA UNO', '2026-06-10', '08:00', 'CEIBOS');
        $this->insertProcedimiento($pdo, 'HC1', 'DRA UNO', '2026-06-12', '08:00', 'CEIBOS');
        $this->insertProcedimiento($pdo, 'HC1', 'DR DOS', '2026-06-11', '08:00', 'CEIBOS');
        $this->insertProcedimiento($pdo, 'HC1', 'DR DOS', '2026-06-15', '08:00', 'CEIBOS');

        $medico = (new MedicoTratanteResolver())->resolve('HC1');

        $this->assertSame('DR DOS', $medico['nombre']);
        $this->assertSame('Cirujano Oftalmologo', $medico['especialidad']);
        $this->assertSame(2, $medico['procedimientos_count']);
        $this->assertTrue($medico['confirmado']);
    }

    public function test_medico_tratante_matches_reordered_agenda_names_and_validates_user_specialty(): void
    {
        $pdo = $this->makePdo();
        $this->createUsersTable($pdo);
        $this->createProcedimientosTable($pdo);

        DB::table('users')->insert([
            'nombre' => 'Andres Fernando Polit Hoyos',
            'full_name' => 'Andres Fernando Polit Hoyos',
            'subespecialidad' => 'cornea_refractiva',
            'especialidad' => 'Cirujano Oftalmologo',
            'sede' => 'CEIBOS',
            'id_trabajador' => '32',
        ]);

        $this->insertProcedimiento($pdo, 'HC1', 'POLIT HOYOS ANDRES FERNANDO', '2026-06-16', '09:00', 'CEIBOS');

        $medico = (new MedicoTratanteResolver())->resolve('HC1');

        $this->assertSame('Andres Fernando Polit Hoyos', $medico['nombre']);
        $this->assertSame('Cirujano Oftalmologo', $medico['especialidad']);
        $this->assertSame(1, $medico['procedimientos_count']);
    }

    public function test_medico_tratante_returns_null_when_only_optometry_exists(): void
    {
        $pdo = $this->makePdo();
        $this->createUsersTable($pdo);
        $this->createProcedimientosTable($pdo);

        $this->insertUser($pdo, 'OPTOMETRIA OPT', 'Optometria');
        $this->insertProcedimiento($pdo, 'HC1', 'OPTOMETRIA OPT', '2026-06-16', '09:00', 'CEIBOS');

        $this->assertNull((new MedicoTratanteResolver())->resolve('HC1'));
    }

    public function test_sede_uses_first_valid_projected_procedure(): void
    {
        $pdo = $this->makePdo();
        $this->createProcedimientosTable($pdo);

        $this->insertProcedimiento($pdo, 'HC1', 'DR UNO', '2026-06-10', '08:00', 'VILLA CLUB');
        $this->insertProcedimiento($pdo, 'HC1', 'DR UNO', '2026-06-15', '08:00', 'CEIBOS');

        $sede = (new SedePacienteResolver())->resolve('HC1');

        $this->assertSame('ceibos', $sede['id']);
        $this->assertSame('CEIBOS', $sede['nombre']);
        $this->assertSame('primera_atencion', $sede['origen']);
    }

    public function test_tipo_afiliacion_classifies_real_affiliations(): void
    {
        $resolver = new TipoAfiliacionResolver();

        $this->assertSame('publico', $resolver->classify('ISSPOL'));
        $this->assertSame('publico', $resolver->classify('MSP'));
        $this->assertSame('privado', $resolver->classify('ECUASANITAS'));
        $this->assertSame('particular', $resolver->classify('PARTICULAR'));
        $this->assertSame('fundacional', $resolver->classify('FUNDACIONES'));
        $this->assertSame('otros', $resolver->classify('ALQUILER'));
    }

    public function test_pacientes_flujo_service_does_not_require_pdo_constructor(): void
    {
        $reflection = new ReflectionClass(PacientesFlujoService::class);

        $this->assertSame(0, $reflection->getConstructor()?->getNumberOfParameters() ?? 0);
        $this->assertInstanceOf(PacientesFlujoService::class, new PacientesFlujoService());
    }

    private function makePdo(): null
    {
        return null;
    }

    private function createUsersTable(mixed $pdo): void
    {
        Schema::dropIfExists('users');
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

    private function createProcedimientosTable(mixed $pdo): void
    {
        Schema::dropIfExists('procedimiento_proyectado');
        Schema::create('procedimiento_proyectado', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable();
            $table->string('doctor')->nullable();
            $table->string('procedimiento_proyectado')->nullable();
            $table->date('fecha')->nullable();
            $table->time('hora')->nullable();
            $table->string('id_sede')->nullable();
            $table->string('sede_departamento')->nullable();
            $table->unsignedTinyInteger('sigcenter_present')->default(1);
        });
    }

    private function createPatientDataTable(mixed $pdo): void
    {
        Schema::dropIfExists('patient_data');
        Schema::create('patient_data', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable();
            $table->string('cedula')->nullable();
            $table->string('fname')->nullable();
            $table->string('mname')->nullable();
            $table->string('lname')->nullable();
            $table->string('lname2')->nullable();
            $table->string('afiliacion')->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('sexo')->nullable();
            $table->string('celular')->nullable();
            $table->string('telefono_alt')->nullable();
            $table->string('email')->nullable();
            $table->string('direccion')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('medico_tratante_id')->nullable();
            $table->string('sede_principal')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    private function createConsultaDataTable(mixed $pdo): void
    {
        Schema::dropIfExists('consulta_data');
        Schema::create('consulta_data', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable();
            $table->date('fecha')->nullable();
            $table->text('antecedente_alergico')->nullable();
        });
    }

    private function createSolicitudProcedimientoTable(mixed $pdo): void
    {
        Schema::dropIfExists('solicitud_procedimiento');
        Schema::create('solicitud_procedimiento', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable();
            $table->string('estado')->nullable();
        });
    }

    private function insertUser(mixed $pdo, string $nombre, string $especialidad): void
    {
        DB::table('users')->insert([
            'nombre' => $nombre,
            'full_name' => $nombre,
            'subespecialidad' => $especialidad,
            'especialidad' => $especialidad,
            'sede' => 'CEIBOS',
            'id_trabajador' => $nombre,
        ]);
    }

    private function insertProcedimiento(mixed $pdo, string $hcNumber, string $doctor, string $fecha, string $hora, string $sede): void
    {
        DB::table('procedimiento_proyectado')->insert([
            'hc_number' => $hcNumber,
            'doctor' => $doctor,
            'procedimiento_proyectado' => 'Consulta',
            'fecha' => $fecha,
            'hora' => $hora,
            'id_sede' => $sede,
            'sede_departamento' => null,
            'sigcenter_present' => 1,
        ]);
    }
}

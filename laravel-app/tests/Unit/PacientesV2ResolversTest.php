<?php

namespace Tests\Unit;

use App\Modules\Pacientes\Services\MedicoTratanteResolver;
use App\Modules\Pacientes\Services\PacientesParityService;
use App\Modules\Pacientes\Services\SedePacienteResolver;
use App\Modules\Pacientes\Services\TipoAfiliacionResolver;
use PDO;
use PHPUnit\Framework\TestCase;

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

        $medico = (new MedicoTratanteResolver($pdo))->resolve('HC1');

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

        $stmt = $pdo->prepare('
            INSERT INTO users (nombre, full_name, subespecialidad, especialidad, sede, id_trabajador)
            VALUES (:nombre, :nombre, :subespecialidad, :especialidad, :sede, :id_trabajador)
        ');
        $stmt->execute([
            ':nombre' => 'Andres Fernando Polit Hoyos',
            ':subespecialidad' => 'cornea_refractiva',
            ':especialidad' => 'Cirujano Oftalmologo',
            ':sede' => 'CEIBOS',
            ':id_trabajador' => '32',
        ]);

        $this->insertProcedimiento($pdo, 'HC1', 'POLIT HOYOS ANDRES FERNANDO', '2026-06-16', '09:00', 'CEIBOS');

        $medico = (new MedicoTratanteResolver($pdo))->resolve('HC1');

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

        $this->assertNull((new MedicoTratanteResolver($pdo))->resolve('HC1'));
    }

    public function test_sede_uses_first_valid_projected_procedure(): void
    {
        $pdo = $this->makePdo();
        $this->createProcedimientosTable($pdo);

        $this->insertProcedimiento($pdo, 'HC1', 'DR UNO', '2026-06-10', '08:00', 'VILLA CLUB');
        $this->insertProcedimiento($pdo, 'HC1', 'DR UNO', '2026-06-15', '08:00', 'CEIBOS');

        $sede = (new SedePacienteResolver($pdo))->resolve('HC1');

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

    public function test_patient_list_contract_uses_resolved_medico_sede_and_affiliation_type(): void
    {
        $pdo = $this->makePdo();
        $this->createPatientDataTable($pdo);
        $this->createUsersTable($pdo);
        $this->createProcedimientosTable($pdo);
        $this->createConsultaDataTable($pdo);
        $this->createSolicitudProcedimientoTable($pdo);

        $pdo->exec("
            INSERT INTO patient_data (
                hc_number, fname, mname, lname, lname2, afiliacion, fecha_nacimiento,
                sexo, celular, email, direccion, ciudad, created_at
            ) VALUES (
                'HC1', 'ADRIANA', 'SOFIA', 'PINOS', 'AGUILAR', 'ISSPOL', '2012-01-01',
                'F', '0999999999', 'paciente@example.test', 'Direccion', 'Guayaquil', '2026-06-01 09:00:00'
            )
        ");

        $this->insertUser($pdo, 'OPTOMETRIA OPT', 'Optometria');
        $this->insertUser($pdo, 'DRA UNO', 'Cirujano Oftalmologo');
        $this->insertUser($pdo, 'DR DOS', 'Cirujano Oftalmologo');

        $this->insertProcedimiento($pdo, 'HC1', 'OPTOMETRIA OPT', '2026-06-20', '08:00', 'CEIBOS');
        $this->insertProcedimiento($pdo, 'HC1', 'DRA UNO', '2026-06-10', '08:00', 'VILLA CLUB');
        $this->insertProcedimiento($pdo, 'HC1', 'DR DOS', '2026-06-15', '08:00', 'CEIBOS');
        $this->insertProcedimiento($pdo, 'HC1', 'DR DOS', '2026-06-16', '08:00', 'CEIBOS');

        $patient = (new PacientesParityService($pdo))->obtenerPacientesReact(null, 0)['data'][0];

        $this->assertSame('DR DOS', $patient['medico']);
        $this->assertSame('DR DOS', $patient['medico_tratante']['nombre']);
        $this->assertSame(2, $patient['medico_tratante']['procedimientos_count']);
        $this->assertSame('ceibos', $patient['sede']);
        $this->assertSame('CEIBOS', $patient['sede_info']['nombre']);
        $this->assertSame('publico', $patient['tipo_afiliacion']);
    }

    private function makePdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    private function createUsersTable(PDO $pdo): void
    {
        $pdo->exec('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre TEXT,
                full_name TEXT,
                subespecialidad TEXT,
                especialidad TEXT,
                sede TEXT,
                id_trabajador TEXT
            )
        ');
    }

    private function createProcedimientosTable(PDO $pdo): void
    {
        $pdo->exec('
            CREATE TABLE procedimiento_proyectado (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                hc_number TEXT,
                doctor TEXT,
                procedimiento_proyectado TEXT,
                fecha TEXT,
                hora TEXT,
                id_sede TEXT,
                sede_departamento TEXT,
                sigcenter_present INTEGER DEFAULT 1
            )
        ');
    }

    private function createPatientDataTable(PDO $pdo): void
    {
        $pdo->exec('
            CREATE TABLE patient_data (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                hc_number TEXT,
                fname TEXT,
                mname TEXT,
                lname TEXT,
                lname2 TEXT,
                afiliacion TEXT,
                fecha_nacimiento TEXT,
                sexo TEXT,
                celular TEXT,
                email TEXT,
                direccion TEXT,
                ciudad TEXT,
                created_at TEXT
            )
        ');
    }

    private function createConsultaDataTable(PDO $pdo): void
    {
        $pdo->exec('
            CREATE TABLE consulta_data (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                hc_number TEXT,
                fecha TEXT,
                antecedente_alergico TEXT
            )
        ');
    }

    private function createSolicitudProcedimientoTable(PDO $pdo): void
    {
        $pdo->exec('
            CREATE TABLE solicitud_procedimiento (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                hc_number TEXT,
                estado TEXT
            )
        ');
    }

    private function insertUser(PDO $pdo, string $nombre, string $especialidad): void
    {
        $stmt = $pdo->prepare('
            INSERT INTO users (nombre, full_name, subespecialidad, especialidad, sede, id_trabajador)
            VALUES (:nombre, :nombre, :especialidad, :especialidad, :sede, :id_trabajador)
        ');
        $stmt->execute([
            ':nombre' => $nombre,
            ':especialidad' => $especialidad,
            ':sede' => 'CEIBOS',
            ':id_trabajador' => $nombre,
        ]);
    }

    private function insertProcedimiento(PDO $pdo, string $hcNumber, string $doctor, string $fecha, string $hora, string $sede): void
    {
        $stmt = $pdo->prepare("
            INSERT INTO procedimiento_proyectado (hc_number, doctor, procedimiento_proyectado, fecha, hora, id_sede, sede_departamento, sigcenter_present)
            VALUES (:hc_number, :doctor, 'Consulta', :fecha, :hora, :sede, NULL, 1)
        ");
        $stmt->execute([
            ':hc_number' => $hcNumber,
            ':doctor' => $doctor,
            ':fecha' => $fecha,
            ':hora' => $hora,
            ':sede' => $sede,
        ]);
    }
}

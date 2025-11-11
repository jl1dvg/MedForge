<?php
declare(strict_types=1);

use Modules\Pacientes\Services\PacienteService;

require_once __DIR__ . '/../Services/PacienteService.php';
require_once __DIR__ . '/../../Shared/Services/PatientIdentityService.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$schema = [
    'CREATE TABLE patient_data (
        hc_number TEXT PRIMARY KEY,
        fname TEXT,
        mname TEXT,
        lname TEXT,
        lname2 TEXT,
        afiliacion TEXT,
        fecha_nacimiento TEXT,
        fecha_caducidad TEXT
    )',
    'CREATE TABLE consulta_data (
        hc_number TEXT,
        fecha TEXT,
        diagnosticos TEXT,
        form_id TEXT,
        examen_fisico TEXT
    )',
    'CREATE TABLE procedimiento_proyectado (
        form_id TEXT,
        hc_number TEXT,
        procedimiento_proyectado TEXT,
        doctor TEXT,
        fecha TEXT,
        hora TEXT
    )',
    'CREATE TABLE solicitud_procedimiento (
        form_id TEXT,
        hc_number TEXT,
        procedimiento TEXT,
        created_at TEXT,
        tipo TEXT
    )',
    'CREATE TABLE prefactura_paciente (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        form_id TEXT,
        hc_number TEXT,
        fecha_creacion TEXT,
        fecha_registro TEXT,
        procedimientos TEXT,
        cod_derivacion TEXT,
        fecha_vigencia TEXT
    )',
    'CREATE TABLE prefactura_detalle_procedimientos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        prefactura_id INTEGER NOT NULL,
        posicion INTEGER,
        external_id TEXT,
        proc_interno TEXT,
        codigo TEXT,
        descripcion TEXT,
        lateralidad TEXT,
        observaciones TEXT,
        precio_base REAL,
        precio_tarifado REAL
    )',
    'CREATE TABLE prefactura_detalle_diagnosticos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        prefactura_id INTEGER NOT NULL,
        posicion INTEGER,
        diagnostico_codigo TEXT,
        descripcion TEXT,
        lateralidad TEXT,
        evidencia TEXT,
        observaciones TEXT
    )',
    'CREATE TABLE prefactura_payload_audit (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        prefactura_id INTEGER,
        hc_number TEXT,
        form_id TEXT,
        source TEXT,
        payload_hash TEXT,
        payload_json TEXT,
        received_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE protocolo_data (
        form_id TEXT,
        hc_number TEXT,
        membrete TEXT,
        fecha_inicio TEXT,
        status INTEGER
    )'
];

foreach ($schema as $sql) {
    $pdo->exec($sql);
}

$pdo->exec("INSERT INTO patient_data (hc_number, fname, lname, lname2, afiliacion, fecha_nacimiento, fecha_caducidad) VALUES
    ('HC123', 'Ana', 'Lopez', 'Perez', 'Particular', '1990-05-10', '2030-01-01'),
    ('HC999', 'Juan', 'Numeros', 'Test', '3Asegurado', '1980-01-01', '2030-01-01'),
    ('HC124', 'Maria', 'Soto', 'Diaz', 'Privado', '1995-01-15', '2030-01-01')
");

$diagnosticosPrimarios = json_encode([
    ['idDiagnostico' => 'D1', 'descripcion' => 'Diagnóstico inicial'],
    ['idDiagnostico' => 'D2', 'descripcion' => 'Diagnóstico secundario'],
]);
$diagnosticosSecundarios = json_encode([
    ['idDiagnostico' => 'D1', 'descripcion' => 'Diagnóstico repetido'],
]);

$pdo->exec("INSERT INTO consulta_data (hc_number, fecha, diagnosticos, form_id, examen_fisico) VALUES
    ('HC123', '2024-01-10 09:00:00', '$diagnosticosPrimarios', 'F001', 'Examen inicial'),
    ('HC123', '2024-02-05 10:30:00', '$diagnosticosSecundarios', 'F001', 'Control'),
    ('HC123', '2024-03-01 11:00:00', '$diagnosticosPrimarios', 'F002', 'Control posterior')
");

$pdo->exec("INSERT INTO procedimiento_proyectado (form_id, hc_number, procedimiento_proyectado, doctor, fecha, hora) VALUES
    ('F001', 'HC123', 'CIRUGIAS - Catarata - Facoemulsificacion', 'Dr. Strange', '2024-02-05', '08:00'),
    ('F002', 'HC123', 'IMAGENES - Retina - OCT', 'Dr. Banner', '2024-03-01', '14:00')
");

$pdo->exec("INSERT INTO solicitud_procedimiento (form_id, hc_number, procedimiento, created_at, tipo) VALUES
    ('SOL1', 'HC123', 'Angiografía', '2024-02-20 15:45:00', 'Laboratorio')
");

$procedimientosPrefactura = json_encode([
    ['procedimiento' => 'Cirugía de catarata', 'ojoId' => 'OD']
]);

$pdo->exec("INSERT INTO prefactura_paciente (id, form_id, hc_number, fecha_creacion, fecha_registro, procedimientos, cod_derivacion, fecha_vigencia) VALUES
    (1, 'PF001', 'HC123', '2024-03-15 09:15:00', '2024-03-14 08:00:00', '$procedimientosPrefactura', 'DER-001', '2099-01-01')
");

$pdo->exec("INSERT INTO prefactura_detalle_procedimientos (prefactura_id, posicion, external_id, proc_interno, codigo, descripcion, lateralidad, observaciones, precio_base, precio_tarifado) VALUES
    (1, 0, 'PROC-001', 'PROC-001', 'PROC-001', 'Cirugía de catarata', 'OD', 'Observación de prueba', 150.0, 200.0)
");

$pdo->exec("INSERT INTO prefactura_detalle_diagnosticos (prefactura_id, posicion, diagnostico_codigo, descripcion, lateralidad, evidencia) VALUES
    (1, 0, 'D1', 'Diagnóstico inicial', 'OD', 'Control postoperatorio')
");

$pdo->exec("INSERT INTO protocolo_data (form_id, hc_number, membrete, fecha_inicio, status) VALUES
    ('F001', 'HC123', 'Protocolo catarata', '2024-02-01', 1)
");

$service = new PacienteService($pdo);

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$context = $service->obtenerContextoPaciente('HC123');
assertTrue($context !== [], 'Se esperaba contexto para HC123');
assertTrue(($context['patientData']['hc_number'] ?? null) === 'HC123', 'El paciente base debe coincidir');
assertTrue(count($context['diagnosticos']) === 2, 'Los diagnósticos deben unificarse por ID');
assertTrue(isset($context['medicos']['Dr. Strange']), 'Debe incluir médicos asignados únicos');
assertTrue(($context['coverageStatus'] ?? null) === 'Con Cobertura', 'La cobertura debe detectarse como vigente');

$timelineItems = $context['timelineItems'];
assertTrue(count($timelineItems) === 2, 'El timeline debe mezclar solicitudes y prefacturas');
assertTrue($timelineItems[0]['origen'] === 'Prefactura', 'El timeline debe ordenarse descendente por fecha');
assertTrue($timelineItems[1]['origen'] === 'Solicitud', 'El timeline debe preservar el origen de cada elemento');

$documentos = $context['documentos'];
assertTrue(count($documentos) === 2, 'Deben listarse documentos de protocolo y solicitudes');

$estadisticas = $context['estadisticas'];
assertTrue(isset($estadisticas['CIRUGIAS']) && $estadisticas['CIRUGIAS'] === 50.0, 'Las estadísticas deben calcular porcentajes');
assertTrue(isset($estadisticas['IMAGENES']) && $estadisticas['IMAGENES'] === 50.0, 'Se espera categoría alternativa para procedimientos');

$eventos = $context['eventos'];
assertTrue($eventos !== [], 'Deben existir eventos en la línea de tiempo');

$expectedAge = (new DateTimeImmutable())->diff(new DateTimeImmutable('1990-05-10'))->y;
assertTrue($context['patientAge'] === $expectedAge, 'La edad calculada debe coincidir con la diferencia de años');

$afiliaciones = $service->getAfiliacionesDisponibles();
assertTrue($afiliaciones === ['Particular', 'Privado'], 'Las afiliaciones deben filtrar valores no alfabéticos');

$coverage = $service->verificarCoberturaPaciente('HC123');
assertTrue($coverage === 'Con Cobertura', 'La cobertura debe ser positiva con fecha futura');

$paginado = $service->obtenerPacientesPaginados(0, 10);
assertTrue($paginado['recordsTotal'] === 3, 'El paginado debe reportar el total de pacientes');
assertTrue($paginado['recordsFiltered'] === 3, 'Sin filtro la cantidad filtrada coincide con el total');
assertTrue(count($paginado['data']) === 3, 'La respuesta debe incluir la cantidad solicitada dentro del límite');

$listaUltimasConsultas = $service->obtenerPacientesConUltimaConsulta();
assertTrue(count($listaUltimasConsultas) === 1, 'Solo el paciente con consultas debe aparecer en el reporte');

fwrite(STDOUT, "PacienteServiceTest: OK\n");

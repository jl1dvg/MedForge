<?php

declare(strict_types=1);

putenv('SKIP_DB_CONNECTION=1');
$_ENV['SKIP_DB_CONNECTION'] = '1';

require_once __DIR__ . '/../../../bootstrap.php';

use Modules\Agenda\Models\AgendaModel;
use Modules\Shared\Services\PatientContextService;

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertEquals(mixed $expected, mixed $actual, string $message): void
{
    if ($expected != $actual) {
        throw new RuntimeException($message . ' Esperado: ' . var_export($expected, true) . ' Actual: ' . var_export($actual, true));
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec('CREATE TABLE patient_data (
    hc_number TEXT PRIMARY KEY,
    fname TEXT,
    mname TEXT,
    lname TEXT,
    lname2 TEXT,
    afiliacion TEXT,
    celular TEXT,
    telefono TEXT,
    email TEXT,
    cedula TEXT,
    ciudad TEXT,
    fecha_nacimiento TEXT
)');

$pdo->exec('CREATE TABLE crm_customers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,
    email TEXT,
    phone TEXT,
    document TEXT,
    affiliation TEXT,
    source TEXT,
    external_ref TEXT,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)');

$pdo->exec('CREATE TABLE crm_leads (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,
    email TEXT,
    phone TEXT,
    status TEXT,
    source TEXT,
    customer_id INTEGER,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)');

$pdo->exec('CREATE TABLE whatsapp_conversations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    wa_number TEXT,
    display_name TEXT,
    patient_hc_number TEXT,
    patient_full_name TEXT,
    last_message_at TEXT,
    last_message_direction TEXT,
    last_message_type TEXT,
    last_message_preview TEXT,
    unread_count INTEGER DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)');

$pdo->exec('CREATE TABLE whatsapp_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    conversation_id INTEGER,
    wa_message_id TEXT,
    direction TEXT,
    message_type TEXT,
    body TEXT,
    message_timestamp TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
)');

$pdo->exec('CREATE TABLE visitas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    hc_number TEXT,
    fecha_visita TEXT,
    hora_llegada TEXT,
    usuario_registro TEXT
)');

$pdo->exec('CREATE TABLE procedimiento_proyectado (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    form_id TEXT,
    hc_number TEXT,
    procedimiento_proyectado TEXT,
    doctor TEXT,
    fecha TEXT,
    hora TEXT,
    estado_agenda TEXT,
    sede_departamento TEXT,
    id_sede TEXT,
    afiliacion TEXT,
    visita_id INTEGER
)');

$pdo->exec('CREATE TABLE procedimiento_proyectado_estado (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    form_id TEXT,
    estado TEXT,
    fecha_hora_cambio TEXT
)');

$pdo->prepare('INSERT INTO patient_data (hc_number, fname, mname, lname, lname2, afiliacion, celular, telefono, email, cedula, ciudad, fecha_nacimiento)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([
        'HC-001', 'Ana', 'María', 'Ríos', 'Lopez', 'IESS', '+593999000111', '022345678', 'ana@example.com', '1101122334', 'Quito', '1985-06-15',
    ]);

$pdo->prepare('INSERT INTO crm_customers (name, email, phone, document, affiliation, source, external_ref, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([
        'Ana Ríos', 'ana@example.com', '+593999000111', '1101122334', 'IESS', 'referido', 'patient:HC-001', '2025-01-01 10:00:00',
    ]);

$pdo->prepare('INSERT INTO crm_leads (name, email, phone, status, source, customer_id, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([
        'Ana Ríos', 'ana@example.com', '+593999000111', 'seguimiento', 'web', 1, '2025-01-02 08:00:00',
    ]);

$pdo->prepare('INSERT INTO whatsapp_conversations (wa_number, display_name, patient_hc_number, patient_full_name, last_message_at, unread_count)
    VALUES (?, ?, ?, ?, ?, ?)')->execute([
        '+593999000111', 'Ana Ríos', 'HC-001', 'Ana Ríos Lopez', '2025-01-02 09:00:00', 0,
    ]);
$pdo->prepare('INSERT INTO whatsapp_messages (conversation_id, wa_message_id, direction, message_type, body, message_timestamp)
    VALUES (?, ?, ?, ?, ?, ?)')->execute([
        1, 'msg-1', 'inbound', 'text', 'Hola, confirmo mi cita', '2025-01-02 08:59:00',
    ]);

$pdo->prepare('INSERT INTO visitas (hc_number, fecha_visita, hora_llegada, usuario_registro)
    VALUES (?, ?, ?, ?)')->execute([
        'HC-001', '2025-01-03', '08:30', 'enfermeria',
    ]);
$visitaId = (int) $pdo->lastInsertId();

$pdo->prepare('INSERT INTO procedimiento_proyectado (form_id, hc_number, procedimiento_proyectado, doctor, fecha, hora, estado_agenda, sede_departamento, id_sede, afiliacion, visita_id)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([
        'F-100', 'HC-001', 'Cirugía refractiva', 'Dr. Perez', '2025-01-03', '09:00', 'PROGRAMADO', 'Matriz', '1', 'IESS', $visitaId,
    ]);

$pdo->prepare('INSERT INTO procedimiento_proyectado_estado (form_id, estado, fecha_hora_cambio)
    VALUES (?, ?, ?)')->execute([
        'F-100', 'PROGRAMADO', '2025-01-02 12:00:00',
    ]);

$service = new PatientContextService($pdo);
$context = $service->getContext('HC-001');

assertEquals('HC-001', $context['hc_number'], 'El contexto debe mantener el HC.');
assertTrue(isset($context['clinic']['patient']) && $context['clinic']['patient']['full_name'] === 'Ana María Ríos Lopez', 'El nombre completo del paciente debe generarse.');
assertTrue(!empty($context['crm']['customers']), 'Debe identificar clientes CRM relacionados.');
assertTrue(!empty($context['communications']['conversations']), 'Debe recuperar conversaciones de comunicaciones.');

$model = new AgendaModel($pdo);
$agenda = $model->listarAgenda([
    'fecha_inicio' => '2025-01-01',
    'fecha_fin' => '2025-01-05',
    'doctor' => null,
    'estado' => null,
    'sede' => null,
    'solo_con_visita' => false,
]);

assertEquals(1, count($agenda), 'Debe retornar un procedimiento proyectado.');
$procedimiento = $agenda[0];
assertEquals('HC-001', $procedimiento->getHcNumber(), 'El procedimiento debe mantener el HC.');
assertEquals('Cirugía refractiva', $procedimiento->getProcedimiento(), 'Debe exponer el nombre del procedimiento.');
assertEquals('Ana María Ríos Lopez', $procedimiento->getPacienteNombre(), 'Debe resolver el nombre del paciente mediante el contexto.');

$visita = $model->obtenerVisita($visitaId);
assertTrue($visita !== null, 'La visita debe existir.');
assertEquals('Ana María Ríos Lopez', $visita->getPacienteNombre(), 'La visita debe exponer el nombre del paciente.');
assertEquals('IESS', $visita->getAfiliacion(), 'La afiliación debe provenir del contexto.');
assertEquals(1, count($visita->getProcedimientos()), 'La visita debe enlazar el procedimiento.');

echo "OK\n";

<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function respond(int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);

if (!is_array($data)) {
    respond(400, ['success' => false, 'error' => 'Body debe ser JSON válido']);
}

// Campos mínimos que tu Python espera
$required = [
    'docSolicitud',
    'idtrabajador',
    'fechaInicio',
    'fechaFin',
    'horaIni',
    'horaFin',
    'sede_departamento',
    'AgendaDoctor_ID_SEDE_DEPARTAMENTO',
    'ID_OJO',
    'ID_ANESTESIA',
];

foreach ($required as $k) {
    if (!array_key_exists($k, $data) || $data[$k] === '' || $data[$k] === null) {
        respond(422, ['success' => false, 'error' => "Falta campo: {$k}"]);
    }
}

// Normalizaciones seguras
$data['docSolicitud'] = (int)$data['docSolicitud'];
$data['idtrabajador'] = (int)$data['idtrabajador'];
$data['sede_departamento'] = (int)$data['sede_departamento'];
$data['AgendaDoctor_ID_SEDE_DEPARTAMENTO'] = (int)$data['AgendaDoctor_ID_SEDE_DEPARTAMENTO'];
$data['ID_OJO'] = (int)$data['ID_OJO'];
$data['ID_ANESTESIA'] = (int)$data['ID_ANESTESIA'];

// Ruta al script (ajusta a tu server)
$py = '/usr/bin/python3';
$script = __DIR__ . '/../../scrapping/agenda_sigcenter.py'; // ejemplo: api/sigcenter/../../scrapping

if (!file_exists($script)) {
    respond(500, ['success' => false, 'error' => 'No existe agenda_sigcenter.py en la ruta configurada']);
}

// Ejecutar Python leyendo JSON por STDIN
$cmd = escapeshellcmd($py) . ' ' . escapeshellarg($script);
$descriptors = [
    0 => ['pipe', 'r'], // STDIN
    1 => ['pipe', 'w'], // STDOUT
    2 => ['pipe', 'w'], // STDERR
];

$proc = proc_open($cmd, $descriptors, $pipes);
if (!is_resource($proc)) {
    respond(500, ['success' => false, 'error' => 'No se pudo iniciar el proceso Python']);
}

fwrite($pipes[0], json_encode($data, JSON_UNESCAPED_UNICODE));
fclose($pipes[0]);

$stdout = stream_get_contents($pipes[1]) ?: '';
$stderr = stream_get_contents($pipes[2]) ?: '';
fclose($pipes[1]);
fclose($pipes[2]);

$exitCode = proc_close($proc);

// Python debería devolver JSON
$pyResp = json_decode(trim($stdout), true);

if (!is_array($pyResp)) {
    respond(500, [
        'success' => false,
        'error' => 'Python no devolvió JSON',
        'exit_code' => $exitCode,
        'stdout' => $stdout,
        'stderr' => $stderr,
    ]);
}

// Uniformar respuesta para el frontend
if (!($pyResp['ok'] ?? false)) {
    respond(400, [
        'success' => false,
        'error' => $pyResp['error'] ?? 'No se pudo agendar',
        'details' => $pyResp,
        'stderr' => $stderr,
    ]);
}

respond(200, [
    'success' => true,
    'agenda_id' => $pyResp['agenda_id'] ?? null,
    'data' => $pyResp,
]);
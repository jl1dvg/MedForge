<?php
declare(strict_types=1);

require_once __DIR__ . '/_client.php';

function sigcenterAgendaRespond(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function sigcenterAgendaLog(string $level, array $context): void
{
    $logDir = __DIR__ . '/../../storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    $logFile = $logDir . '/sigcenter_agenda.log';
    $entry = [
        'timestamp' => date('c'),
        'level' => $level,
        'context' => $context,
    ];
    @file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
}

$data = readJsonBody();

if (!is_array($data) || !$data) {
    sigcenterAgendaRespond(400, ['success' => false, 'error' => 'Body debe ser JSON válido']);
}

$required = [
    'docSolicitud',
    'idtrabajador',
    'fechaInicio',
    'fechaFin',
    'sede_departamento',
];


foreach ($required as $k) {
    if (!array_key_exists($k, $data) || $data[$k] === '' || $data[$k] === null) {
        sigcenterAgendaRespond(422, ['success' => false, 'error' => "Falta campo: {$k}"]);
    }
}

// Credenciales Sigcenter: NO deben venir del frontend.
// Preferir variables de entorno (o constantes definidas en _client.php) y opcionalmente permitir override por payload.
$u = trim((string)($data['sigcenter_user'] ?? $data['username'] ?? 'jdevera' ?? ''));
$p = trim((string)($data['sigcenter_pass'] ?? $data['password'] ?? '0925619736' ?? ''));

if ($u === '') {
    $u = trim((string)(getenv('SIGCENTER_USER') ?: ''));
}
if ($p === '') {
    $p = trim((string)(getenv('SIGCENTER_PASS') ?: ''));
}

// Si _client.php define constantes opcionales, usarlas como fallback.
if ($u === '' && defined('SIGCENTER_USER')) {
    $u = trim((string)constant('SIGCENTER_USER'));
}
if ($p === '' && defined('SIGCENTER_PASS')) {
    $p = trim((string)constant('SIGCENTER_PASS'));
}

if ($u === '' || $p === '') {
    sigcenterAgendaLog('error', ['error' => 'SIGCENTER_USER/SIGCENTER_PASS no configuradas']);
    sigcenterAgendaRespond(500, [
        'success' => false,
        'error' => 'Credenciales Sigcenter no configuradas en el servidor',
    ]);
}

// Pasar a Python por stdin.
$data['sigcenter_user'] = $u;
$data['sigcenter_pass'] = $p;

$data['docSolicitud'] = (int)$data['docSolicitud'];
$data['idtrabajador'] = (int)$data['idtrabajador'];
$data['sede_departamento'] = (int)$data['sede_departamento'];
$data['AgendaDoctor_ID_SEDE_DEPARTAMENTO'] = (int)($data['AgendaDoctor_ID_SEDE_DEPARTAMENTO'] ?? $data['sede_departamento']);
$data['ID_OJO'] = (int)($data['ID_OJO'] ?? 1);
$data['ID_ANESTESIA'] = (int)($data['ID_ANESTESIA'] ?? 4);
$data['horaIni'] = (string)($data['horaIni'] ?? '');
$data['horaFin'] = (string)($data['horaFin'] ?? '');

sigcenterAgendaLog('info', ['payload' => $data]);

$py = '/usr/bin/python3';
$script = realpath(__DIR__ . '/../../scrapping/agenda_sigcenter.py');
$cwd = realpath(__DIR__ . '/../../scrapping');

if (!$script || !file_exists($script)) {
    sigcenterAgendaRespond(500, ['success' => false, 'error' => 'No existe agenda_sigcenter.py en la ruta configurada']);
}

$cmd = escapeshellcmd($py) . ' ' . escapeshellarg($script);
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$proc = proc_open($cmd, $descriptors, $pipes, $cwd ?: null);
if (!is_resource($proc)) {
    sigcenterAgendaRespond(500, ['success' => false, 'error' => 'No se pudo iniciar el proceso Python']);
}

fwrite($pipes[0], json_encode($data, JSON_UNESCAPED_UNICODE));
fclose($pipes[0]);

$stdout = '';
$stderr = '';
$timeout = 35;
$start = microtime(true);
stream_set_blocking($pipes[1], false);
stream_set_blocking($pipes[2], false);

while (true) {
    $stdout .= stream_get_contents($pipes[1]) ?: '';
    $stderr .= stream_get_contents($pipes[2]) ?: '';
    $status = proc_get_status($proc);
    if (!$status['running']) {
        break;
    }
    if ((microtime(true) - $start) > $timeout) {
        proc_terminate($proc);
        sigcenterAgendaLog('error', ['error' => 'timeout', 'stdout' => $stdout, 'stderr' => $stderr]);
        sigcenterAgendaRespond(504, ['success' => false, 'error' => 'Timeout ejecutando agenda_sigcenter.py']);
    }
    usleep(100000);
}

fclose($pipes[1]);
fclose($pipes[2]);
$exitCode = proc_close($proc);

$stdout = trim($stdout);
$pyResp = $stdout !== '' ? json_decode($stdout, true) : null;

sigcenterAgendaLog('info', ['stdout' => $stdout, 'stderr' => $stderr, 'exit_code' => $exitCode]);

if (!is_array($pyResp)) {
    sigcenterAgendaRespond(500, [
        'success' => false,
        'error' => 'Python no devolvió JSON',
        'exit_code' => $exitCode,
        'stdout' => $stdout,
        'stderr' => $stderr,
    ]);
}

if (!($pyResp['ok'] ?? false)) {
    sigcenterAgendaRespond(400, [
        'success' => false,
        'error' => $pyResp['error'] ?? 'No se pudo agendar',
        'details' => $pyResp,
    ]);
}

sigcenterAgendaRespond(200, [
    'success' => true,
    'agenda_id' => $pyResp['agenda_id'] ?? null,
    'raw' => $pyResp,
]);

<?php
declare(strict_types=1);

require_once __DIR__ . '/_client.php';

function sigcenterDocRespond(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function sigcenterDocLog(string $level, array $context): void
{
    $logDir = __DIR__ . '/../../storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    $logFile = $logDir . '/sigcenter_doc_solicitud.log';
    $entry = [
        'timestamp' => date('c'),
        'level' => $level,
        'context' => $context,
    ];
    @file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
}

$payload = readJsonBody();
if (!is_array($payload) || !$payload) {
    sigcenterDocRespond(400, ['success' => false, 'error' => 'Body debe ser JSON válido']);
}

$hcNumber = trim((string)($payload['hc_number'] ?? ''));
$origen = trim((string)($payload['origen'] ?? ''));
$procedimiento = trim((string)($payload['procedimiento'] ?? ''));
$lateralidad = strtoupper(trim((string)($payload['lateralidad'] ?? '')));
$solicitudId = isset($payload['solicitud_id']) ? (int)$payload['solicitud_id'] : 0;
$prefactura = trim((string)($payload['prefactura'] ?? ''));

if ($hcNumber === '' || $procedimiento === '' || $lateralidad === '' || ($origen === '' && $prefactura === '')) {
    sigcenterDocRespond(422, [
        'success' => false,
        'error' => 'hc_number, procedimiento y lateralidad son requeridos; además debes enviar origen o prefactura',
    ]);
}

sigcenterDocLog('info', [
    'payload' => [
        'hc_number' => $hcNumber,
        'origen' => $origen,
        'prefactura' => $prefactura,
        'procedimiento' => $procedimiento,
        'lateralidad' => $lateralidad,
    ],
]);

function sigcenterDocRunPython(array $baseArgs, string $cwd, string $logFilterLabel): array
{
    $cmd = implode(' ', $baseArgs);
    sigcenterDocLog('info', [
        'action' => 'run_python',
        'filter' => $logFilterLabel,
        'cmd' => $cmd,
    ]);

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $proc = proc_open($cmd, $descriptors, $pipes, $cwd ?: null);
    if (!is_resource($proc)) {
        sigcenterDocLog('error', [
            'action' => 'run_python',
            'filter' => $logFilterLabel,
            'error' => 'No se pudo iniciar el proceso Python',
        ]);
        return [
            'ok' => false,
            'rows' => [],
            'stdout' => '',
            'stderr' => '',
            'exit_code' => -1,
            'decoded' => null,
        ];
    }

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
            sigcenterDocLog('error', ['error' => 'timeout', 'stdout' => $stdout, 'stderr' => $stderr]);
            break;
        }
        usleep(100000);
    }

    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($proc);

    $stdoutTrim = trim($stdout);
    $decoded = $stdoutTrim !== '' ? json_decode($stdoutTrim, true) : null;

    sigcenterDocLog('info', [
        'action' => 'run_python',
        'filter' => $logFilterLabel,
        'stdout' => $stdoutTrim,
        'stderr' => $stderr,
        'exit_code' => $exitCode,
    ]);

    $rows = [];
    $ok = false;
    if (is_array($decoded)) {
        if (isset($decoded['rows']) && is_array($decoded['rows'])) {
            $rows = $decoded['rows'];
            $ok = true;
        } elseif (function_exists('array_is_list') && array_is_list($decoded)) {
            $rows = $decoded;
            $ok = true;
        } elseif (isset($decoded[0]) && is_array($decoded[0])) {
            $rows = $decoded;
            $ok = true;
        }
    }

    return [
        'ok' => $ok,
        'rows' => $rows,
        'stdout' => $stdoutTrim,
        'stderr' => $stderr,
        'exit_code' => $exitCode,
        'decoded' => $decoded,
    ];
}

$py = '/usr/bin/python3';
$script = realpath(__DIR__ . '/../../scrapping/scrape_index_admisiones_hc.py');
$cwd = realpath(__DIR__ . '/../../scrapping');

if (!$script || !file_exists($script)) {
    sigcenterDocRespond(500, ['success' => false, 'error' => 'No existe scrape_index_admisiones_hc.py en la ruta configurada']);
}

$baseArgs = [
    escapeshellcmd($py),
    escapeshellarg($script),
    escapeshellarg($hcNumber),
    '--quiet',
    '--procedimiento',
    escapeshellarg($procedimiento),
    '--lateralidad',
    escapeshellarg($lateralidad),
];

$rows = [];

if ($origen !== '' && $prefactura !== '') {
    $args1 = $baseArgs;
    $args1[] = '--origen';
    $args1[] = escapeshellarg($origen);
    $r1 = sigcenterDocRunPython($args1, $cwd ?: null, 'origen');

    if ($r1['ok'] && count($r1['rows']) > 0) {
        $rows = $r1['rows'];
        sigcenterDocLog('info', [
            'action' => 'resolved',
            'filter' => 'origen',
            'count' => count($rows),
        ]);
    } else {
        $args2 = $baseArgs;
        $args2[] = '--prefactura';
        $args2[] = escapeshellarg($prefactura);
        $r2 = sigcenterDocRunPython($args2, $cwd ?: null, 'prefactura');
        $rows = $r2['rows'];
        sigcenterDocLog('info', [
            'action' => 'resolved',
            'filter' => 'prefactura',
            'count' => count($rows),
        ]);
        if (!$r1['ok'] && !$r2['ok']) {
            sigcenterDocRespond(500, [
                'success' => false,
                'error' => 'Python no devolvió JSON',
                'exit_code' => $r2['exit_code'],
                'stdout' => $r2['stdout'],
                'stderr' => $r2['stderr'],
            ]);
        }
    }
} else {
    if ($origen !== '') {
        $args = $baseArgs;
        $args[] = '--origen';
        $args[] = escapeshellarg($origen);
        $r = sigcenterDocRunPython($args, $cwd ?: null, 'origen');
        if (!$r['ok']) {
            sigcenterDocRespond(500, [
                'success' => false,
                'error' => 'Python no devolvió JSON',
                'exit_code' => $r['exit_code'],
                'stdout' => $r['stdout'],
                'stderr' => $r['stderr'],
            ]);
        }
        $rows = $r['rows'];
    } else {
        $args = $baseArgs;
        $args[] = '--prefactura';
        $args[] = escapeshellarg($prefactura);
        $r = sigcenterDocRunPython($args, $cwd ?: null, 'prefactura');
        if (!$r['ok']) {
            sigcenterDocRespond(500, [
                'success' => false,
                'error' => 'Python no devolvió JSON',
                'exit_code' => $r['exit_code'],
                'stdout' => $r['stdout'],
                'stderr' => $r['stderr'],
            ]);
        }
        $rows = $r['rows'];
    }
}

$total = count($rows);

if ($total === 0) {
    sigcenterDocRespond(404, ['success' => false, 'error' => 'No se encontró pedido_id']);
}

if ($total > 1) {
    sigcenterDocRespond(409, [
        'success' => false,
        'error' => 'Múltiples resultados',
        'options' => $rows,
    ]);
}

$record = $rows[0];
$pedidoId = $record['pedido_id'] ?? '';
if ($pedidoId === '') {
    sigcenterDocRespond(404, ['success' => false, 'error' => 'No se encontró pedido_id']);
}

if ($solicitudId > 0 && isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->prepare(
            'UPDATE solicitud_procedimiento SET pedido_cirugia_id = :pedido WHERE id = :id'
        );
        $stmt->execute([
            ':pedido' => $pedidoId,
            ':id' => $solicitudId,
        ]);
    } catch (Throwable $error) {
        sigcenterDocLog('error', [
            'error' => $error->getMessage(),
            'solicitud_id' => $solicitudId,
            'pedido_id' => $pedidoId,
        ]);
    }
}

sigcenterDocRespond(200, [
    'success' => true,
    'pedido_id' => (string)$pedidoId,
    'record' => $record,
]);

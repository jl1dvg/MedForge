<?php

declare(strict_types=1);

$allowedOrigins = [
    'http://cive.ddns.net',
    'https://cive.ddns.net',
    'http://cive.ddns.net:8085',
    'https://cive.ddns.net:8085',
    'http://192.168.1.13:8085',
    'http://localhost:8085',
    'http://127.0.0.1:8085',
    'https://asistentecive.consulmed.me',
    'https://cive.consulmed.me',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
} else {
    http_response_code(403);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => false, 'message' => 'Origen no permitido']);
    exit;
}

header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/**
 * Resolve cutover flags from .env (preferred) and then process env vars.
 */
function consultasFlagEnabled(string $flag, bool $default = false): bool
{
    static $dotenvFlags = null;

    if ($dotenvFlags === null) {
        $dotenvFlags = [];
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $envPath = rtrim((string) $basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env';

        if (is_readable($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $line = trim((string) $line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }

                [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
                $key = trim($key);
                if ($key === '') {
                    continue;
                }

                $dotenvFlags[$key] = trim($value, " \t\n\r\0\x0B\"'");
            }
        }
    }

    $rawFlag = $dotenvFlags[$flag] ?? null;
    if ($rawFlag === null || trim((string) $rawFlag) === '') {
        $rawFlag = $_ENV[$flag] ?? getenv($flag) ?? null;
    }

    if ($rawFlag === null || trim((string) $rawFlag) === '') {
        return $default;
    }

    return filter_var((string) $rawFlag, FILTER_VALIDATE_BOOLEAN);
}

$consultasV2ApiEnabled = consultasFlagEnabled('CONSULTAS_V2_API_ENABLED', false);
$consultasV2ReadsEnabled = consultasFlagEnabled('CONSULTAS_V2_READS_ENABLED', $consultasV2ApiEnabled);

if ($consultasV2ReadsEnabled) {
    $target = '/v2/api/consultas/anterior.php';
    $query = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));
    if ($query !== '') {
        $target .= '?' . $query;
    }

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Location: ' . $target, true, 307);
    exit;
}

require_once __DIR__ . '/../../bootstrap.php';

$hcNumber = $_GET['hcNumber'] ?? null;
$formIdActual = $_GET['form_id'] ?? null;
$procedimientoActual = $_GET['procedimiento'] ?? null;

if (!$hcNumber) {
    echo json_encode([
        'success' => false,
        'message' => 'Parámetro hcNumber requerido',
    ]);
    exit;
}

$baseProcedimiento = null;
if ($procedimientoActual) {
    $partes = explode(' - ', (string) $procedimientoActual);
    $baseProcedimiento = trim((string) ($partes[0] ?? ''));
}

try {
    $sql = 'SELECT cd.form_id,
                   cd.hc_number,
                   cd.examen_fisico,
                   cd.plan,
                   cd.fecha
            FROM procedimiento_proyectado AS pp
            LEFT JOIN consulta_data AS cd
                   ON pp.form_id = cd.form_id
            WHERE pp.hc_number = :hcNumber';

    $params = [':hcNumber' => $hcNumber];

    if ($baseProcedimiento) {
        $sql .= ' AND pp.procedimiento_proyectado LIKE :baseProc';
        $params[':baseProc'] = $baseProcedimiento . '%';

        $sql .= ' AND pp.procedimiento_proyectado <> :procActual';
        $params[':procActual'] = $procedimientoActual;
    }

    if ($formIdActual) {
        $sql .= ' AND pp.form_id < :formIdActual';
        $params[':formIdActual'] = $formIdActual;
    }

    $sql .= ' AND cd.examen_fisico IS NOT NULL
              AND cd.examen_fisico <> \'\'';

    $sql .= ' ORDER BY cd.fecha DESC, cd.form_id DESC
              LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        echo json_encode([
            'success' => true,
            'data' => [
                'form_id' => $row['form_id'],
                'hc_number' => $row['hc_number'],
                'examen_fisico' => $row['examen_fisico'],
                'plan' => $row['plan'],
                'fecha' => $row['fecha'],
            ],
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'data' => null,
            'message' => 'No se encontró consulta anterior con examen físico.',
        ]);
    }
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al buscar consulta anterior',
        'error' => $e->getMessage(),
    ]);
}

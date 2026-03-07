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

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function normalizeString(?string $value): string
{
    return trim((string) ($value ?? ''));
}

function readJsonInput(): array
{
    $raw = file_get_contents('php://input');
    $json = json_decode((string) $raw, true);
    return is_array($json) ? $json : [];
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
$consultasV2WritesEnabled = consultasFlagEnabled('CONSULTAS_V2_WRITES_ENABLED', $consultasV2ApiEnabled);

$shouldRedirectToV2 = ($method === 'GET' && $consultasV2ReadsEnabled)
    || ($method === 'POST' && $consultasV2WritesEnabled);

if ($shouldRedirectToV2) {
    $target = '/v2/api/consultas/plan.php';
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

try {
    if ($method === 'GET') {
        $formId = $_GET['form_id'] ?? $_GET['formId'] ?? null;
        $hcNumber = $_GET['hcNumber'] ?? $_GET['hc_number'] ?? null;

        if (!$formId && !$hcNumber) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Parámetros requeridos: form_id o hcNumber']);
            exit;
        }

        $sql = 'SELECT form_id, hc_number, plan, fecha
                FROM consulta_data
                WHERE 1=1';
        $params = [];

        if ($formId) {
            $sql .= ' AND form_id = :form_id';
            $params[':form_id'] = $formId;
        }
        if ($hcNumber) {
            $sql .= ' AND hc_number = :hc_number';
            $params[':hc_number'] = $hcNumber;
        }

        $sql .= ' ORDER BY fecha DESC LIMIT 1';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'No se encontró un plan registrado para esos parámetros']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'form_id' => $row['form_id'],
                'hc_number' => $row['hc_number'],
                'plan' => $row['plan'] ?? '',
                'fecha' => $row['fecha'] ?? null,
            ],
        ]);
        exit;
    }

    if ($method === 'POST') {
        $payload = array_merge($_POST, readJsonInput());
        $formId = $payload['form_id'] ?? $payload['formId'] ?? null;
        $hcNumber = $payload['hcNumber'] ?? $payload['hc_number'] ?? null;
        $plan = normalizeString($payload['plan'] ?? '');

        if (!$formId || !$hcNumber) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'form_id y hcNumber son obligatorios']);
            exit;
        }

        $update = $pdo->prepare('UPDATE consulta_data SET plan = :plan WHERE form_id = :form_id AND hc_number = :hc_number');
        $update->execute([
            ':plan' => $plan,
            ':form_id' => $formId,
            ':hc_number' => $hcNumber,
        ]);

        if ($update->rowCount() === 0) {
            $insert = $pdo->prepare('
                INSERT INTO consulta_data (form_id, hc_number, fecha, motivo_consulta, enfermedad_actual, examen_fisico, plan, diagnosticos, examenes)
                VALUES (:form_id, :hc_number, CURRENT_DATE, NULL, NULL, NULL, :plan, \'[]\', \'[]\')
                ON DUPLICATE KEY UPDATE plan = VALUES(plan)
            ');
            $insert->execute([
                ':form_id' => $formId,
                ':hc_number' => $hcNumber,
                ':plan' => $plan,
            ]);
        }

        echo json_encode([
            'success' => true,
            'plan' => $plan,
            'message' => 'Plan actualizado correctamente en MedForge',
        ]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'No se pudo procesar la solicitud de plan',
        'error' => $e->getMessage(),
    ]);
}

<?php

declare(strict_types=1);

use Controllers\GuardarConsultaController;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
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
$consultasV2WritesEnabled = consultasFlagEnabled('CONSULTAS_V2_WRITES_ENABLED', $consultasV2ApiEnabled);

if ($consultasV2WritesEnabled) {
    $target = '/v2/api/consultas/guardar.php';
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

$data = json_decode((string) file_get_contents('php://input'), true);
if ($data === null) {
    echo json_encode(['success' => false, 'message' => 'JSON mal formado']);
    exit;
}

$controller = new GuardarConsultaController($pdo);
$response = $controller->guardar($data);

echo json_encode($response);

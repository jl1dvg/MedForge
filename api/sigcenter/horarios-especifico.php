<?php
require_once __DIR__ . '/_client.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'POST') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$payload = array_merge($_POST, readJsonBody());
$trabajadorId = trim((string)($payload['trabajador_id'] ?? ''));
$fecha = trim((string)($payload['FECHA'] ?? $payload['fecha'] ?? ''));

if ($trabajadorId === '' || $fecha === '') {
    sigcenterJsonResponse([
        'success' => false,
        'message' => 'trabajador_id y FECHA son requeridos',
    ], 400);
}

$companyId = $payload['company_id'] ?? 113;
$sedeId = $payload['ID_SEDE'] ?? 3;

$requestPayload = [
    'company_id' => (string) $companyId,
    'ID_SEDE' => (string) $sedeId,
    'trabajador_id' => $trabajadorId,
    'FECHA' => $fecha,
];

$endpoint = 'https://cive.ddns.net:8085/restful/api-agenda/horarios-disponibles-especifico';
$result = sigcenterRequest($endpoint, $requestPayload, 'GET');
$ok = $result['http_code'] >= 200 && $result['http_code'] < 300;

if (!$ok) {
    $fallback = sigcenterRequest($endpoint, $requestPayload, 'POST');
    $fallbackOk = $fallback['http_code'] >= 200 && $fallback['http_code'] < 300;
    if ($fallbackOk) {
        $result = $fallback;
        $ok = true;
        $result['attempted_method'] = 'POST';
    } else {
        $result['fallback'] = $fallback;
        $result['attempted_method'] = 'GET';
    }
}

sigcenterJsonResponse([
    'success' => $ok,
    'http_code' => $result['http_code'],
    'data' => $result['data'] ?? null,
    'raw' => $result['data'] ? null : ($result['raw'] ?? null),
    'attempted_method' => $result['attempted_method'] ?? 'GET',
    'fallback' => $result['fallback'] ?? null,
    'error' => $ok ? null : ($result['error'] ?: 'Error consultando horarios'),
], $ok ? 200 : 502);

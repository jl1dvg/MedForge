<?php
require_once __DIR__ . '/_client.php';

use Models\SolicitudModel;

if (($_SERVER['REQUEST_METHOD'] ?? 'POST') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$payload = array_merge($_POST, readJsonBody());

$solicitudId = isset($payload['solicitud_id']) ? (int) $payload['solicitud_id'] : 0;
$hcNumber = trim((string)($payload['hc_number'] ?? $payload['identificacion'] ?? ''));
$trabajadorId = trim((string)($payload['trabajador_id'] ?? ''));
$procedimientoId = $payload['procedimiento_id'] ?? null;
$fechaInicio = trim((string)($payload['fecha_inicio'] ?? ''));

if ($solicitudId <= 0 || $hcNumber === '' || $trabajadorId === '' || $procedimientoId === null || $fechaInicio === '') {
    sigcenterJsonResponse([
        'success' => false,
        'message' => 'solicitud_id, hc_number, trabajador_id, procedimiento_id y fecha_inicio son requeridos',
    ], 400);
}

$companyId = $payload['company_id'] ?? 113;
$sedeId = $payload['ID_SEDE'] ?? 1;

$requestPayload = [
    'company_id' => (int) $companyId,
    'ID_SEDE' => (string) $sedeId,
    'action' => 'CREATE',
    'agenda_id' => '',
    'estado_pago' => '',
    'identificacion' => $hcNumber,
    'trabajador_id' => $trabajadorId,
    'procedimiento_id' => (int) $procedimientoId,
    'fecha_inicio' => $fechaInicio,
];

$endpoint = 'https://cive.ddns.net:8085/restful/api-eva/agendar-facturar';
$result = sigcenterRequest($endpoint, $requestPayload, 'POST');

$ok = $result['http_code'] >= 200 && $result['http_code'] < 300;
if (!$ok) {
    $formPayload = http_build_query($requestPayload);
    $formHeaders = [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
        'Expect:',
    ];
    $formHandle = curl_init($endpoint);
    curl_setopt_array($formHandle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $formPayload,
        CURLOPT_HTTPHEADER => $formHeaders,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $formRaw = curl_exec($formHandle);
    $formError = curl_error($formHandle);
    $formHttp = (int) curl_getinfo($formHandle, CURLINFO_HTTP_CODE);
    curl_close($formHandle);
    $formData = null;
    if (is_string($formRaw) && $formRaw !== '') {
        $decoded = json_decode($formRaw, true);
        if (is_array($decoded)) {
            $formData = $decoded;
        }
    }
    $formResult = [
        'http_code' => $formHttp,
        'raw' => $formRaw,
        'data' => $formData,
        'error' => $formError,
        'attempted_method' => 'POST_FORM',
    ];

    $fallbackOk = $formHttp >= 200 && $formHttp < 300;
    if ($fallbackOk) {
        $result = $formResult;
        $ok = true;
    } else {
        $getFallback = sigcenterRequest($endpoint, $requestPayload, 'GET');
        $getOk = $getFallback['http_code'] >= 200 && $getFallback['http_code'] < 300;
        if ($getOk) {
            $result = $getFallback;
            $ok = true;
            $result['attempted_method'] = 'GET';
        } else {
            $result['fallback'] = [
                'post_form' => $formResult,
                'get' => $getFallback,
            ];
            $result['attempted_method'] = 'POST';
        }
    }
}

$agendaId = null;
$errorMessage = null;
if (is_array($result['data'])) {
    $agendaId = $result['data']['agenda_id']
        ?? $result['data']['agendaId']
        ?? $result['data']['id_agenda']
        ?? $result['data']['id']
        ?? null;
    $errorMessage = $result['data']['error']
        ?? $result['data']['message']
        ?? $result['data']['msj']
        ?? null;
}
if (!$errorMessage && isset($result['fallback']['post_form']['data']) && is_array($result['fallback']['post_form']['data'])) {
    $errorMessage = $result['fallback']['post_form']['data']['error']
        ?? $result['fallback']['post_form']['data']['message']
        ?? $result['fallback']['post_form']['data']['msj']
        ?? null;
}
if (!$errorMessage && isset($result['fallback']['get']['data']) && is_array($result['fallback']['get']['data'])) {
    $errorMessage = $result['fallback']['get']['data']['error']
        ?? $result['fallback']['get']['data']['message']
        ?? $result['fallback']['get']['data']['msj']
        ?? null;
}

$dbSaved = null;
if ($ok && isset($pdo) && $pdo instanceof PDO) {
    $model = new SolicitudModel($pdo);
    $dbSaved = $model->guardarAgendamientoSigcenter($solicitudId, [
        'sigcenter_agenda_id' => $agendaId ? (string) $agendaId : null,
        'sigcenter_fecha_inicio' => $fechaInicio,
        'sigcenter_trabajador_id' => $trabajadorId,
        'sigcenter_procedimiento_id' => (int) $procedimientoId,
        'sigcenter_payload' => $requestPayload,
        'sigcenter_response' => $result['data'] ?? $result['raw'],
    ]);
}

sigcenterJsonResponse([
    'success' => $ok,
    'http_code' => $result['http_code'],
    'agenda_id' => $agendaId,
    'data' => $result['data'] ?? null,
    'raw' => $result['data'] ? null : ($result['raw'] ?? null),
    'payload' => $requestPayload,
    'attempted_method' => $result['attempted_method'] ?? 'POST',
    'fallback' => $result['fallback'] ?? null,
    'db_saved' => $dbSaved,
    'error' => $ok ? null : ($errorMessage ?: ($result['error'] ?: 'Error al agendar en Sigcenter')),
], $ok ? 200 : 502);

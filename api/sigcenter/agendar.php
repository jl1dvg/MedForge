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
$fechaLlegada = trim((string)($payload['fecha_llegada'] ?? ''));
$agendaIdInput = trim((string)($payload['agenda_id'] ?? ''));
$action = strtoupper(trim((string)($payload['action'] ?? 'CREATE')));
$estadoPago = trim((string)($payload['estado_pago'] ?? ''));
if ($fechaLlegada !== '') {
    $fechaLlegada = str_replace('T', ' ', $fechaLlegada);
    if (strlen($fechaLlegada) === 16) {
        $fechaLlegada .= ':00';
    }
}

if ($solicitudId <= 0 || $hcNumber === '' || $trabajadorId === '' || $procedimientoId === null || $fechaInicio === '') {
    sigcenterJsonResponse([
        'success' => false,
        'message' => 'solicitud_id, hc_number, trabajador_id, procedimiento_id y fecha_inicio son requeridos',
    ], 400);
}
if ($action === 'UPDATE' && $agendaIdInput === '') {
    sigcenterJsonResponse([
        'success' => false,
        'message' => 'agenda_id es requerido para UPDATE',
    ], 400);
}

$companyId = $payload['company_id'] ?? 113;
$sedeId = $payload['ID_SEDE'] ?? 1;
$afiliacionId = null;
$afiliacionNombre = '';

if (isset($pdo) && $pdo instanceof PDO && $hcNumber !== '') {
    try {
        $stmtAfiliacion = $pdo->prepare('SELECT afiliacion FROM patient_data WHERE hc_number = :hc LIMIT 1');
        $stmtAfiliacion->execute([':hc' => $hcNumber]);
        $rowAfiliacion = $stmtAfiliacion->fetch(PDO::FETCH_ASSOC);
        $afiliacionNombre = isset($rowAfiliacion['afiliacion']) ? trim((string) $rowAfiliacion['afiliacion']) : '';
        if ($afiliacionNombre !== '') {
            $normalized = strtoupper(preg_replace('/\\s+/', ' ', $afiliacionNombre));
            $normalized = trim($normalized);
            $normalizedNoIess = preg_replace('/^IESS\\s*-\\s*/', '', $normalized);
            $normalizedWithIess = str_starts_with($normalized, 'IESS')
                ? $normalized
                : 'IESS - ' . $normalized;

            $stmtSigcenterAfiliacion = $pdo->prepare(
                "SELECT sigcenter_id, nombre
                FROM sigcenter_afiliaciones
                WHERE activo = 1
                    AND (
                        UPPER(TRIM(nombre)) = :normalized
                        OR UPPER(TRIM(nombre)) = :normalized_with_iess
                        OR UPPER(TRIM(REPLACE(nombre, 'IESS - ', ''))) = :normalized_no_iess
                    )
                LIMIT 1"
            );
            $stmtSigcenterAfiliacion->execute([
                ':normalized' => $normalized,
                ':normalized_with_iess' => $normalizedWithIess,
                ':normalized_no_iess' => $normalizedNoIess,
            ]);
            $sigcenterRow = $stmtSigcenterAfiliacion->fetch(PDO::FETCH_ASSOC);
            if ($sigcenterRow && $sigcenterRow['sigcenter_id'] !== null) {
                $afiliacionId = (string) $sigcenterRow['sigcenter_id'];
            }
        }
    } catch (Throwable $error) {
        $afiliacionId = null;
    }
}

$requestPayload = [
    'company_id' => (int) $companyId,
    'ID_SEDE' => (string) $sedeId,
    'action' => $action !== '' ? $action : 'CREATE',
    'agenda_id' => $agendaIdInput,
    'estado_pago' => $estadoPago,
    'identificacion' => $hcNumber,
    'trabajador_id' => $trabajadorId,
    'procedimiento_id' => (int) $procedimientoId,
    'fecha_inicio' => $fechaInicio,
];
if ($afiliacionId !== null) {
    $requestPayload['afiliacion_id'] = $afiliacionId;
}

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
$pedidoId = null;
$facturaId = null;
$errorMessage = null;
if (is_array($result['data'])) {
    $agendaId = $result['data']['agenda_id']
        ?? $result['data']['agendaId']
        ?? $result['data']['id_agenda']
        ?? $result['data']['id']
        ?? null;
    if (!$agendaId && $agendaIdInput !== '') {
        $agendaId = $agendaIdInput;
    }
    $pedidoId = $result['data']['pedido_id']
        ?? $result['data']['pedidoId']
        ?? null;
    $facturaId = $result['data']['factura_id']
        ?? $result['data']['facturaId']
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
$agendaSaved = null;
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
    $agendaPayload = [
        'solicitud_id' => $solicitudId,
        'sigcenter_agenda_id' => $agendaId,
        'sigcenter_pedido_id' => $pedidoId,
        'sigcenter_factura_id' => $facturaId,
        'fecha_inicio' => $fechaInicio,
        'fecha_llegada' => $fechaLlegada !== '' ? $fechaLlegada : null,
        'payload' => $requestPayload,
        'response' => $result['data'] ?? $result['raw'],
        'created_by' => $_SESSION['user_id'] ?? null,
    ];
    if ($action === 'UPDATE' && $agendaId) {
        $agendaSaved = $model->actualizarAgendaCitaSigcenter($solicitudId, (string) $agendaId, $agendaPayload);
        if (!$agendaSaved['success']) {
            $agendaSaved = $model->guardarAgendaCitaSigcenter($agendaPayload);
        }
    } else {
        $agendaSaved = $model->guardarAgendaCitaSigcenter($agendaPayload);
    }
}

sigcenterJsonResponse([
    'success' => $ok,
    'http_code' => $result['http_code'],
    'agenda_id' => $agendaId,
    'pedido_id' => $pedidoId,
    'factura_id' => $facturaId,
    'data' => $result['data'] ?? null,
    'raw' => $result['data'] ? null : ($result['raw'] ?? null),
    'payload' => $requestPayload,
    'attempted_method' => $result['attempted_method'] ?? 'POST',
    'fallback' => $result['fallback'] ?? null,
    'db_saved' => $dbSaved,
    'agenda_saved' => $agendaSaved,
    'error' => $ok ? null : ($errorMessage ?: ($result['error'] ?: 'Error al agendar en Sigcenter')),
], $ok ? 200 : 502);

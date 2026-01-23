<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\SolicitudController;

// CORS (similar a tu otro API)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    return is_array($json) ? $json : [];
}

function normalizeSlug(?string $value): string
{
    $base = $value ?? '';
    $base = trim($base);
    if ($base === '') {
        return '';
    }
    $base = mb_strtolower($base, 'UTF-8');
    $base = str_replace(['_', '  '], ['-', ' '], $base);
    $base = preg_replace('/[^\p{L}\p{N}\-\s]/u', '', $base) ?? '';
    $base = preg_replace('/\s+/', '-', $base) ?? '';
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT', $base);
    if ($ascii !== false) {
        $base = $ascii;
    }
    return $base;
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $controller = new SolicitudController($pdo);

    if ($method === 'GET') {
        $hcNumber = $_GET['hcNumber'] ?? null;

        if (!$hcNumber) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Parámetro hcNumber requerido',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $response = $controller->obtenerEstadosPorHc($hcNumber);

        if (!is_array($response)) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Respuesta inválida del controlador',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'POST') {
        $payload = array_merge($_POST, readJsonBody());
        $id = isset($payload['id']) ? (int)$payload['id'] : null;
        if (!$id && isset($payload['solicitud_id'])) {
            $id = (int)$payload['solicitud_id'];
        }
        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Parámetro id requerido para actualizar la solicitud',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        error_log('Solicitud update payload: ' . json_encode($payload, JSON_UNESCAPED_UNICODE));

        $campos = [
            'estado' => $payload['estado'] ?? null,
            'doctor' => $payload['doctor'] ?? null,
            'fecha' => $payload['fecha'] ?? null,
            'prioridad' => $payload['prioridad'] ?? null,
            'observacion' => $payload['observacion'] ?? null,
            'procedimiento' => $payload['procedimiento'] ?? null,
            'producto' => $payload['producto'] ?? null,
            'ojo' => $payload['ojo'] ?? null,
            'afiliacion' => $payload['afiliacion'] ?? null,
            'duracion' => $payload['duracion'] ?? null,
            'lente_id' => $payload['lente_id'] ?? null,
            'lente_nombre' => $payload['lente_nombre'] ?? null,
            'lente_poder' => $payload['lente_poder'] ?? null,
            'lente_observacion' => $payload['lente_observacion'] ?? null,
            'incision' => $payload['incision'] ?? null,
        ];

        $resultado = $controller->actualizarSolicitudParcial($id, $campos);
        error_log('Solicitud update resultado: ' . json_encode($resultado, JSON_UNESCAPED_UNICODE));

        if (!is_array($resultado) || ($resultado['success'] ?? false) === false) {
            http_response_code(422);
        } else {
            $estadoPayload = $payload['estado'] ?? $payload['etapa_slug'] ?? $payload['etapa'] ?? '';
            if (normalizeSlug((string) $estadoPayload) === 'apto-oftalmologo') {
                $checklistActualizado = $controller->marcarChecklistAptoOftalmologo($id);
                if (is_array($resultado)) {
                    $resultado['checklist_updated'] = $checklistActualizado;
                }
            }
        }

        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

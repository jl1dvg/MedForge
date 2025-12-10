<?php
require_once __DIR__ . '/../../bootstrap.php';

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
    header("Access-Control-Allow-Credentials: true");
    header("Vary: Origin");
} else {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Origen no permitido']);
    exit;
}

header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        exit;
    }

    $formId = $_GET['form_id'] ?? $_GET['formId'] ?? null;
    $hcNumber = $_GET['hcNumber'] ?? $_GET['hc_number'] ?? null;

    if (!$hcNumber) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Parámetro requerido: hcNumber']);
        exit;
    }

    $sql = "SELECT form_id, hc_number, examen_fisico, plan, fecha
            FROM consulta_data
            WHERE hc_number = :hc_number";

    $params = [':hc_number' => $hcNumber];

    if ($formId !== null && $formId !== '') {
        $sql .= ctype_digit((string)$formId)
            ? " AND form_id < :form_id"
            : " AND form_id <> :form_id";
        $params[':form_id'] = $formId;
    }

    $sql .= " ORDER BY fecha DESC, form_id DESC LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'No hay consultas anteriores registradas.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'form_id' => $row['form_id'],
            'hc_number' => $row['hc_number'],
            'examen_fisico' => $row['examen_fisico'] ?? '',
            'plan' => $row['plan'] ?? '',
            'fecha' => $row['fecha'] ?? null,
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'No se pudo obtener la consulta anterior',
        'error' => $e->getMessage(),
    ]);
}

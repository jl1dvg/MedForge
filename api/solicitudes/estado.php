<?php
require_once __DIR__ . '/../../bootstrap.php';

use PDO;

// CORS con orígenes permitidos
$allowedOrigins = [
    'http://cive.ddns.net',
    'https://cive.ddns.net',
    'http://cive.ddns.net:8085',
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

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$hcNumber = isset($_GET['hcNumber']) ? trim($_GET['hcNumber']) : '';
if ($hcNumber === '') {
    echo json_encode(['success' => false, 'message' => 'hcNumber requerido']);
    exit;
}

try {
    $sql = "
        SELECT 
            sp.form_id,
            sp.hc_number,
            sp.procedimiento,
            sp.tipo,
            sp.doctor,
            sp.fecha,
            sp.prioridad,
            sp.afiliacion,
            sp.estado,
            sp.created_at,
            sp.updated_at,
            sp.observacionInterconsulta AS observacion
        FROM solicitud_procedimiento sp
        WHERE sp.hc_number = :hc
        ORDER BY sp.created_at DESC, sp.form_id DESC
        LIMIT 100
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['hc' => $hcNumber]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $solicitudes = array_map(function ($row) {
        $estado = $row['estado'] ?? '';
        $map = [
            'PENDIENTE' => 'PENDIENTE',
            'APROBADA'  => 'APROBADA',
            'RECHAZADA' => 'RECHAZADA',
        ];
        $estadoFront = $map[strtoupper($estado)] ?? ($estado ?: 'PENDIENTE');

        return [
            'form_id' => $row['form_id'],
            'hcNumber' => $row['hc_number'],
            'procedimiento' => $row['procedimiento'] ?? '',
            'tipo' => $row['tipo'] ?? '',
            'estado' => $estadoFront,
            'estado_bd' => $estado,
            'doctor' => $row['doctor'] ?? '',
            'fecha' => $row['fecha'] ?? $row['created_at'] ?? '',
            'prioridad' => $row['prioridad'] ?? '',
            'afiliacion' => $row['afiliacion'] ?? '',
            'observacion' => $row['observacion'] ?? '',
        ];
    }, $rows);

    echo json_encode([
        'success' => true,
        'hcNumber' => $hcNumber,
        'solicitudes' => $solicitudes,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'No fue posible obtener las solicitudes quirúrgicas',
        'error' => $e->getMessage(),
    ]);
}

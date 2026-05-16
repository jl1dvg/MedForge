<?php

require_once __DIR__ . '/../../bootstrap.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($requestMethod === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $stmt = $pdo->query("
        SELECT DISTINCT TRIM(doctor) AS doctor
        FROM solicitud_procedimiento
        WHERE doctor IS NOT NULL
          AND TRIM(doctor) <> ''
          AND UPPER(TRIM(doctor)) <> 'SELECCIONE'
        ORDER BY doctor ASC
    ");

    $doctores = array_values(array_filter(array_map(
        static fn ($row) => (string) ($row['doctor'] ?? ''),
        $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
    )));

    echo json_encode([
        'success' => true,
        'doctores' => $doctores,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'doctores' => [],
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}

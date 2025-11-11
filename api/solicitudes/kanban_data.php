<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\ExamenesController;

$controller = new ExamenesController($pdo);

$filtros = [
    'afiliacion' => $_POST['afiliacion'] ?? '',
    'doctor' => $_POST['doctor'] ?? '',
    'prioridad' => $_POST['prioridad'] ?? '',
    'fechaTexto' => $_POST['fechaTexto'] ?? ''
];

$solicitudes = $controller->getSolicitudesConDetalles($filtros);

// Opciones únicas para combos
$afiliaciones = array_values(array_unique(array_filter(array_column($solicitudes, 'afiliacion'))));
$doctores = array_values(array_unique(array_filter(array_column($solicitudes, 'doctor'))));

header('Content-Type: application/json');
echo json_encode([
    'data' => $solicitudes,
    'options' => [
        'afiliaciones' => $afiliaciones,
        'doctores' => $doctores
    ]
], JSON_UNESCAPED_UNICODE);
// 🧠 Depuración temporal
file_put_contents(__DIR__ . '/debug_filtros.log', date('Y-m-d H:i:s') . ' ' . json_encode($_POST) . PHP_EOL, FILE_APPEND);
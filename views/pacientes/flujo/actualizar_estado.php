<?php
require_once __DIR__ . '/../../../bootstrap.php';

use Controllers\GuardarProyeccionController;

header('Content-Type: application/json; charset=UTF-8');
ob_start(); // ← Captura cualquier salida inesperada

// Manejo de errores visibles solo en desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Leer el JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['form_id'], $input['estado'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$form_id = (int)$input['form_id'];
$estado = trim($input['estado']);

if ($form_id <= 0 || $estado === '') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'ID o estado inválido']);
    exit;
}

try {
    $controller = new GuardarProyeccionController($pdo);
    $result = $controller->actualizarEstado($form_id, $estado);

    ob_clean(); // limpia cualquier salida previa
    echo json_encode($result);
} catch (Throwable $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Excepción: ' . $e->getMessage()
    ]);
}


$estados = [
    'Agendado' => 'agendado',
    'Pagado' => 'pagado',
    'Admisión' => 'admision',
    'En atención' => 'en-atencion',
    'Esperando resultado' => 'esperando-resultado',
    'Post-procedimiento' => 'post-procedimiento',
    'Alta' => 'alta',
];
foreach ($estados as $estadoLabel => $estadoId) {
    echo "<div class='kanban-column box box-solid box-primary rounded shadow-sm p-1 me-0' style='min-width: 250px; flex-shrink: 0;'>";
    echo "<div class='box-header with-border'>";
    echo "<h5 class='text-center box-title'>$estadoLabel <span class='badge bg-danger' id='badge-$estadoId' style='display:none;'>¡+4!</span></h5>";
    echo "<ul class='box-controls pull-right'><li><a class='box-btn-close' href='#'></a></li><li><a class='box-btn-slide' href='#'></a></li><li><a class='box-btn-fullscreen' href='#'></a></li></ul></div>";
    echo "<div class='box-body p-0'>";
    echo "<div class='kanban-items' id='kanban-$estadoId'></div>";
    echo "</div>"; // Cierre de box-body
    echo "</div>";
}

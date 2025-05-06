<?php
ob_start();
error_reporting(0);

require_once __DIR__ . '/../../bootstrap.php';
ob_clean();

header('Content-Type: application/json; charset=UTF-8');

use Controllers\DashboardController;

$dashboardController = new DashboardController($pdo);
$username = $dashboardController->getAuthenticatedUser();

use Controllers\ProcedimientoController;

// Verificar que el método sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    http_response_code(405); // Método no permitido
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

// Instanciar el controller
$procedimientoController = new ProcedimientoController($pdo);
$dashboardController = new DashboardController($pdo);
$username = $dashboardController->getAuthenticatedUser();

// Actualizar el procedimiento usando los datos del POST
$resultado = $procedimientoController->actualizarProcedimiento($_POST);

// Devolver respuesta como JSON
if ($resultado) {
    ob_clean();
    echo json_encode(['success' => true, 'message' => 'Protocolo actualizado exitosamente']);
} else {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error al actualizar el protocolo']);
}
exit;
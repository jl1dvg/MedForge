<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
//$resultado = $procedimientoController->actualizarProcedimiento($_POST);

// Devolver respuesta como JSON
try {
    if (empty($_POST['id']) && !empty($_POST['cirugia'])) {
        $_POST['id'] = $procedimientoController->generarIdUnicoDesdeCirugia($_POST['cirugia']);
    }
    try {
        $resultado = $procedimientoController->actualizarProcedimiento($_POST);
        ob_clean();
        echo json_encode([
            'success' => $resultado,
            'message' => $resultado ? 'Protocolo actualizado exitosamente' : 'Error al actualizar el protocolo',
            'debug' => $_POST
        ]);
    } catch (Throwable $e) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Excepción capturada al guardar el protocolo',
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile(),
            'trace' => $e->getTraceAsString()
        ]);
    }
} catch (Throwable $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Excepción capturada al guardar el protocolo',
        'error' => $e->getMessage(),
        'line' => $e->getLine(),
        'file' => $e->getFile(),
        'trace' => $e->getTraceAsString()
    ]);
}
exit;
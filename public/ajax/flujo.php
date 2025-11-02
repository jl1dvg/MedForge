<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\DashboardController;
use Controllers\PacienteController;
use Controllers\GuardarProyeccionController;

header('Content-Type: application/json');

$controller = new GuardarProyeccionController($pdo);
$modo = $_GET['modo'] ?? 'trayecto';
$fecha = $_GET['fecha'] ?? date('Y-m-d');
if ($modo === 'visita') {
    $flujo = $controller->obtenerFlujoPacientesPorVisita($fecha);
} else {
    $flujo = $controller->obtenerFlujoPacientes($fecha);
}
echo json_encode($flujo);
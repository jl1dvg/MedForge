<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\GuardarProyeccionController;

header('Content-Type: application/json');

$controller = new GuardarProyeccionController($pdo);
$fecha = $_GET['fecha'] ?? date('Y-m-d'); // Si no llega fecha, usar hoy
echo json_encode($controller->obtenerFlujoPacientes($fecha));
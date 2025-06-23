<?php
require_once __DIR__ . '/../../bootstrap.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json; charset=UTF-8');

use Controllers\GuardarProyeccionController;

$controller = new GuardarProyeccionController($pdo);

// Obtener la fecha desde GET (opcional)
$fecha = $_GET['fecha'] ?? date('Y-m-d');

$pacientesOptometria = $controller->obtenerPacientesPorEstado('OPTOMETRIA', $fecha);

// Solo devolver los form_id en un array simple
$formIds = array_column($pacientesOptometria, 'form_id');

echo json_encode($formIds);
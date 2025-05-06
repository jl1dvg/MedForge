<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../bootstrap.php';

use Controllers\PacienteController;

$pacienteController = new PacienteController($pdo);
$pacienteController->obtenerPacientesDatatable($_GET);

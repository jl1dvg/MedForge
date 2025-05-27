<?php
require_once '../../bootstrap.php';

use Controllers\ReglaController;

$controller = new ReglaController($pdo);

$reglaId = $controller->crearRegla($_POST);

// Supongamos que condiciones[] y acciones[] vienen como JSON strings
$condiciones = json_decode($_POST['condiciones'], true);
$acciones = json_decode($_POST['acciones'], true);

$controller->agregarCondiciones($reglaId, $condiciones);
$controller->agregarAcciones($reglaId, $acciones);

header("Location: /views/reglas/listar.php");
exit;
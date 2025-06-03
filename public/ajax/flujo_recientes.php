<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\GuardarProyeccionController;

// Establece cabecera de respuesta JSON
header('Content-Type: application/json; charset=UTF-8');

// Instancia el controlador y ejecuta el método
$controller = new GuardarProyeccionController($pdo);
$controller->getCambiosRecientes();
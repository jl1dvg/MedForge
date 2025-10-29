<?php
require_once __DIR__ . '/../bootstrap.php';

use Controllers\MoverInsumosController;

$controller = new MoverInsumosController($pdo);
$controller->ejecutar();
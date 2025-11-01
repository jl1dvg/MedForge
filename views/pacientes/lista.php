<?php
require_once __DIR__ . '/../../bootstrap.php';

use Modules\Pacientes\Controllers\PacientesController;

$controller = new PacientesController();
$controller->index($pdo);

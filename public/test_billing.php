<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../controllers/BillingController.php';

use Controllers\BillingController;

$pdo = $GLOBALS['pdo'] ?? null;
$controller = new BillingController($pdo);
$controller->generarExcel('164951,165005', 'IESS');
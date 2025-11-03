<?php
require_once __DIR__ . '/../../bootstrap.php';

use Modules\Billing\Controllers\BillingController;

$controller = new BillingController($pdo);
$controller->detalle();

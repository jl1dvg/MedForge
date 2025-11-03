<?php
require_once __DIR__ . '/../../bootstrap.php';

use Modules\Billing\Controllers\InformesController;

$controller = new InformesController($pdo);
$controller->informeIess();

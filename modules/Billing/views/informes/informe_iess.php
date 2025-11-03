<?php
if (!defined('BASE_PATH')) {
    require_once dirname(__DIR__, 4) . '/bootstrap.php';
}

use Modules\Billing\Controllers\InformesController;

$controller = new InformesController($pdo);
$controller->informeIess();

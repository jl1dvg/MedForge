<?php

require_once __DIR__ . '/../../bootstrap.php';

use Controllers\InsumosController;

header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'JSON inválido.']);
    exit;
}

$controller = new InsumosController($pdo);
$result = $controller->guardar($data);
echo json_encode($result);
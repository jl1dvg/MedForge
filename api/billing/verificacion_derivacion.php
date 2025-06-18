<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\DerivacionController;

$controller = new DerivacionController($pdo);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['form_ids'])) {
    $form_ids = $_POST['form_ids'] ?? [];

    error_log("📥 form_ids recibidos: " . json_encode($form_ids));

    $resultado = $controller->verificarFormIds($form_ids);

    echo json_encode($resultado);
}
?>
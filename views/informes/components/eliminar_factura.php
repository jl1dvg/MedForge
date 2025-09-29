<?php
require_once __DIR__ . '/../../../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_id'])) {
    $formId = $_POST['form_id'];

    try {
        $stmt = $pdo->prepare("DELETE FROM billing_main WHERE form_id = ?");
        $stmt->execute([$formId]);

        echo json_encode(['success' => true, 'message' => 'Factura eliminada correctamente.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar la factura.']);
    }

    exit;
}

echo json_encode(['success' => false, 'message' => 'Solicitud inválida.']);
exit;
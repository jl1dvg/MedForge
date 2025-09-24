<?php
require_once __DIR__ . '/../../../bootstrap.php'; // Asegura que carga $pdo correctamente

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_id'])) {
    $formId = $_POST['form_id'];

    $stmt = $pdo->prepare("DELETE FROM billing_main WHERE form_id = ?");
    $stmt->execute([$formId]);

    header("Location: /views/informes/informe_iess.php?modo=consolidado&deleted=1");
    exit;
}

header("Location: /views/informes/informe_iess.php?modo=consolidado");
exit;
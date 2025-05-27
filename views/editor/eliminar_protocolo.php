<?php

require_once __DIR__ . '/../../bootstrap.php';

use Controllers\ProcedimientoController;

$procedimientoController = new ProcedimientoController($pdo);

$id = $_POST['id'] ?? null;

if ($id && $procedimientoController->eliminarProtocolo($id)) {
    header("Location: lista_protocolos.php?deleted=1");
    exit;
} else {
    header("Location: lista_protocolos.php?error=1");
    exit;
}
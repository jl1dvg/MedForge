<?php
require_once __DIR__ . '/../../bootstrap.php'; // Autoload y setup inicial
require_once __DIR__ . '/../../helpers/format.php'; // si tienes funciones como formatDate()

use Controllers\BillingController;

// Instanciar controller y obtener datos
$db = require __DIR__ . '/../../config/database.php'; // archivo que retorna PDO
$controller = new BillingController($db);
$facturas = $controller->obtenerFacturasDisponibles();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Facturación - MedForge</title>
    <link rel="stylesheet" href="/public/css/vendors_css.css">
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="/public/css/skin_color.css">
</head>
<body>
<div class="container mt-4">
    <h2 class="mb-4">📋 Facturas Generadas</h2>

    <div class="mb-3">
        <a href="/public/views/billing/exportar_mes.php" class="btn btn-primary">⬇️ Exportar Mes</a>
    </div>

    <table class="table table-bordered table-striped">
        <thead class="table-dark">
        <tr>
            <th>Form ID</th>
            <th>HC</th>
            <th>Paciente</th>
            <th>Fecha</th>
            <th>Acciones</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($facturas as $f): ?>
            <tr>
                <td><?= htmlspecialchars($f['form_id']) ?></td>
                <td><?= htmlspecialchars($f['hc_number']) ?></td>
                <td><?= obtenerNombrePaciente($f['hc_number'], $db) ?></td>
                <td><?= $f['fecha_ordenada'] ?? '—' ?></td>
                <td>
                    <a href="detalle.php?form_id=<?= $f['form_id'] ?>"
                       class="btn btn-sm btn-info">👁️ Ver</a>
                    <a href="/public/index.php?action=generar_excel&form_id=<?= $f['form_id'] ?>&grupoAfiliacion=ISSPOL"
                       class="btn btn-sm btn-success">⬇️ Excel</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
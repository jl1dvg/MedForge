<?php
// views/recetas/index.php
require_once '../../bootstrap.php';

use Controllers\RecetasController;

$controller = new RecetasController($pdo);

// Capturar filtros del formulario
$filtros = [
    'fecha_inicio' => $_GET['fecha_inicio'] ?? '',
    'fecha_fin' => $_GET['fecha_fin'] ?? '',
    'doctor' => $_GET['doctor'] ?? '',
    'producto' => $_GET['producto'] ?? '',
];

$reporte = $controller->reporte($filtros);
$topProductos = $controller->topProductos($filtros);
$resumenDoctores = $controller->resumenPorDoctor($filtros);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Recetas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container py-4">
<h2 class="mb-4">📦 Reporte de Recetas</h2>

<form class="row g-3 mb-4" method="get">
    <div class="col-md-3">
        <label>Fecha inicio</label>
        <input type="date" class="form-control" name="fecha_inicio"
               value="<?= htmlspecialchars($filtros['fecha_inicio']) ?>">
    </div>
    <div class="col-md-3">
        <label>Fecha fin</label>
        <input type="date" class="form-control" name="fecha_fin" value="<?= htmlspecialchars($filtros['fecha_fin']) ?>">
    </div>
    <div class="col-md-3">
        <label>Doctor</label>
        <input type="text" class="form-control" name="doctor" value="<?= htmlspecialchars($filtros['doctor']) ?>">
    </div>
    <div class="col-md-3">
        <label>Producto</label>
        <input type="text" class="form-control" name="producto" value="<?= htmlspecialchars($filtros['producto']) ?>">
    </div>
    <div class="col-12 text-end">
        <button class="btn btn-primary">🔍 Filtrar</button>
    </div>
</form>

<h4>📊 Top 10 Productos Más Recetados</h4>
<table class="table table-striped">
    <thead>
    <tr>
        <th>Producto</th>
        <th>Veces Recetado</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($topProductos as $p): ?>
        <tr>
            <td><?= htmlspecialchars($p['producto']) ?></td>
            <td><?= $p['veces_recetado'] ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h4>🩺 Resumen por Doctor</h4>
<table class="table table-bordered">
    <thead>
    <tr>
        <th>Doctor</th>
        <th>Total Recetas</th>
        <th>Total Unidades</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($resumenDoctores as $d): ?>
        <tr>
            <td><?= htmlspecialchars($d['doctor']) ?></td>
            <td><?= $d['total_recetas'] ?></td>
            <td><?= $d['total_unidades'] ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h4>📋 Detalle de Recetas</h4>
<table class="table table-hover">
    <thead>
    <tr>
        <th>Fecha</th>
        <th>Producto</th>
        <th>Dosis</th>
        <th>Cantidad</th>
        <th>Doctor</th>
        <th>Afiliación</th>
        <th>HC #</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($reporte as $r): ?>
        <tr>
            <td><?= htmlspecialchars($r['fecha_receta']) ?></td>
            <td><?= htmlspecialchars($r['producto']) ?></td>
            <td><?= htmlspecialchars($r['dosis']) ?></td>
            <td><?= $r['cantidad'] ?></td>
            <td><?= htmlspecialchars($r['doctor']) ?></td>
            <td><?= htmlspecialchars($r['afiliacion']) ?></td>
            <td><?= htmlspecialchars($r['hc_number']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>

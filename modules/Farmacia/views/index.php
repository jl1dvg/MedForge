<?php
/**
 * @var array $filtros
 * @var array $resumen
 * @var array $porMes
 * @var array $porProducto
 * @var array $porDoctor
 * @var array $porProductoDoctor
 * @var array $detalle
 * @var array $doctores
 */
$totalRecetas = (int)($resumen['total_recetas'] ?? 0);
$totalUnidades = (int)($resumen['total_unidades'] ?? 0);
$totalDoctores = (int)($resumen['total_doctores'] ?? 0);
$totalProductos = (int)($resumen['total_productos'] ?? 0);

$porProductoTop = array_slice($porProducto ?? [], 0, 10);
$porDoctorTop = array_slice($porDoctor ?? [], 0, 10);
$porMesChart = array_reverse($porMes ?? []);

$chartMonthly = array_map(static function (array $row): array {
    return [
        'label' => $row['mes'] ?? '',
        'recetas' => (int)($row['total_recetas'] ?? 0),
        'unidades' => (int)($row['total_unidades'] ?? 0),
    ];
}, $porMesChart);

$chartProducts = array_map(static function (array $row): array {
    return [
        'label' => $row['producto'] ?? '',
        'recetas' => (int)($row['total_recetas'] ?? 0),
        'unidades' => (int)($row['total_unidades'] ?? 0),
    ];
}, $porProductoTop);

$chartDoctors = array_map(static function (array $row): array {
    return [
        'label' => $row['doctor'] ?? 'Sin médico',
        'recetas' => (int)($row['total_recetas'] ?? 0),
        'unidades' => (int)($row['total_unidades'] ?? 0),
    ];
}, $porDoctorTop);
?>

<div id="farmacia-dashboard"
     data-monthly='<?= htmlspecialchars(json_encode($chartMonthly, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'
     data-products='<?= htmlspecialchars(json_encode($chartProducts, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'
     data-doctors='<?= htmlspecialchars(json_encode($chartDoctors, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'>
<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Farmacia</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="/dashboard"><i class="mdi mdi-home-outline"></i></a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Estadísticas de recetas</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="row">
        <div class="col-12">
            <form class="card card-body mb-3" method="get" action="/farmacia">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label mb-0">Desde</label>
                        <input type="date" name="fecha_inicio" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($filtros['fecha_inicio'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-0">Hasta</label>
                        <input type="date" name="fecha_fin" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($filtros['fecha_fin'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-0">Médico</label>
                        <select name="doctor" class="form-select form-select-sm">
                            <option value="">— Todos —</option>
                            <?php foreach ($doctores as $doctor): ?>
                                <?php $selected = ($filtros['doctor'] ?? '') === $doctor ? 'selected' : ''; ?>
                                <option value="<?= htmlspecialchars($doctor, ENT_QUOTES, 'UTF-8') ?>" <?= $selected ?>>
                                    <?= htmlspecialchars($doctor, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-0">Medicamento</label>
                        <input type="text" name="producto" class="form-control form-control-sm"
                               placeholder="Ej: paracetamol"
                               value="<?= htmlspecialchars($filtros['producto'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-primary btn-sm" type="submit">
                            <i class="mdi mdi-filter"></i> Aplicar filtros
                        </button>
                        <a class="btn btn-outline-secondary btn-sm" href="/farmacia">Limpiar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <?php
        $cards = [
            ['label' => 'Recetas', 'value' => number_format($totalRecetas, 0, '', '.')],
            ['label' => 'Unidades', 'value' => number_format($totalUnidades, 0, '', '.')],
            ['label' => 'Médicos', 'value' => number_format($totalDoctores, 0, '', '.')],
            ['label' => 'Medicamentos', 'value' => number_format($totalProductos, 0, '', '.')],
        ];
        ?>
        <?php foreach ($cards as $card): ?>
            <div class="col-md-3 col-sm-6">
                <div class="box text-center">
                    <div class="box-body">
                        <h2 class="mb-0"><?= htmlspecialchars($card['value'], ENT_QUOTES, 'UTF-8') ?></h2>
                        <p class="text-muted mb-0"><?= htmlspecialchars($card['label'], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title">Evolución mensual</h4>
                </div>
                <div class="box-body" style="height: 280px;">
                    <canvas id="chartFarmaciaMes"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title">Top medicamentos</h4>
                </div>
                <div class="box-body" style="height: 280px;">
                    <canvas id="chartFarmaciaProductos"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title">Top médicos</h4>
                </div>
                <div class="box-body" style="height: 280px;">
                    <canvas id="chartFarmaciaDoctores"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title">Recetas por mes</h4>
                </div>
                <div class="box-body table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                        <tr>
                            <th>Mes</th>
                            <th>Recetas</th>
                            <th>Unidades</th>
                            <th>% del total</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($porMes)): ?>
                            <?php foreach ($porMes as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['mes'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <?php
                                    $recetas = (int)($row['total_recetas'] ?? 0);
                                    $participacion = $totalRecetas > 0 ? round(($recetas / $totalRecetas) * 100, 1) : 0;
                                    ?>
                                    <td><?= number_format($recetas, 0, '', '.') ?></td>
                                    <td><?= number_format((int)($row['total_unidades'] ?? 0), 0, '', '.') ?></td>
                                    <td><?= htmlspecialchars((string) $participacion, ENT_QUOTES, 'UTF-8') ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-muted text-center">Sin datos para los filtros actuales.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title">Medicamentos más recetados</h4>
                </div>
                <div class="box-body table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                        <tr>
                            <th>Medicamento</th>
                            <th>Recetas</th>
                            <th>Unidades</th>
                            <th>% del total</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($porProducto)): ?>
                            <?php foreach ($porProducto as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['producto'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <?php
                                    $recetas = (int)($row['total_recetas'] ?? 0);
                                    $participacion = $totalRecetas > 0 ? round(($recetas / $totalRecetas) * 100, 1) : 0;
                                    ?>
                                    <td><?= number_format($recetas, 0, '', '.') ?></td>
                                    <td><?= number_format((int)($row['total_unidades'] ?? 0), 0, '', '.') ?></td>
                                    <td><?= htmlspecialchars((string) $participacion, ENT_QUOTES, 'UTF-8') ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-muted text-center">Sin datos para los filtros actuales.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title">Recetas por médico</h4>
                </div>
                <div class="box-body table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                        <tr>
                            <th>Médico</th>
                            <th>Recetas</th>
                            <th>Unidades</th>
                            <th>% del total</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($porDoctor)): ?>
                            <?php foreach ($porDoctor as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['doctor'] ?? 'Sin médico', ENT_QUOTES, 'UTF-8') ?></td>
                                    <?php
                                    $recetas = (int)($row['total_recetas'] ?? 0);
                                    $participacion = $totalRecetas > 0 ? round(($recetas / $totalRecetas) * 100, 1) : 0;
                                    ?>
                                    <td><?= number_format($recetas, 0, '', '.') ?></td>
                                    <td><?= number_format((int)($row['total_unidades'] ?? 0), 0, '', '.') ?></td>
                                    <td><?= htmlspecialchars((string) $participacion, ENT_QUOTES, 'UTF-8') ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-muted text-center">Sin datos para los filtros actuales.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title">Médico + medicamento</h4>
                </div>
                <div class="box-body table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                        <tr>
                            <th>Médico</th>
                            <th>Medicamento</th>
                            <th>Recetas</th>
                            <th>Unidades</th>
                            <th>% del total</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($porProductoDoctor)): ?>
                            <?php foreach ($porProductoDoctor as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['doctor'] ?? 'Sin médico', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($row['producto'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <?php
                                    $recetas = (int)($row['total_recetas'] ?? 0);
                                    $participacion = $totalRecetas > 0 ? round(($recetas / $totalRecetas) * 100, 1) : 0;
                                    ?>
                                    <td><?= number_format($recetas, 0, '', '.') ?></td>
                                    <td><?= number_format((int)($row['total_unidades'] ?? 0), 0, '', '.') ?></td>
                                    <td><?= htmlspecialchars((string) $participacion, ENT_QUOTES, 'UTF-8') ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-muted text-center">Sin datos para los filtros actuales.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title">Detalle de recetas</h4>
                </div>
                <div class="box-body table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Médico</th>
                            <th>Paciente</th>
                            <th>Medicamento</th>
                            <th>Dosis</th>
                            <th>Cantidad</th>
                            <th>Unidades farmacia</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($detalle)): ?>
                            <?php foreach ($detalle as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['fecha_receta'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($row['doctor'] ?? 'Sin médico', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($row['hc_number'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($row['producto'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($row['dosis'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= number_format((int)($row['cantidad'] ?? 0), 0, '', '.') ?></td>
                                    <td><?= number_format((int)($row['total_farmacia'] ?? 0), 0, '', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-muted text-center">Sin datos para los filtros actuales.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
</div>

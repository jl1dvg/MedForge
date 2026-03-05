<?php
/**
 * @var array<string, string> $filters
 * @var array<string, mixed> $dashboard
 * @var array<int, array<string, mixed>> $rows
 * @var array<int, string> $doctorOptions
 * @var array<int, string> $afiliacionOptions
 * @var array<int, string> $sedeOptions
 * @var array<int, string> $localidadOptions
 * @var array<int, string> $departamentoOptions
 */

if (!isset($filters) || !is_array($filters)) {
    $filters = [
        'fecha_inicio' => '',
        'fecha_fin' => '',
        'doctor' => '',
        'afiliacion' => '',
        'sede' => '',
        'producto' => '',
        'localidad' => '',
        'departamento' => '',
    ];
}

if (!isset($dashboard) || !is_array($dashboard)) {
    $dashboard = ['cards' => [], 'meta' => [], 'charts' => []];
}

$dashboardCards = is_array($dashboard['cards'] ?? null) ? $dashboard['cards'] : [];
$dashboardMeta = is_array($dashboard['meta'] ?? null) ? $dashboard['meta'] : [];
$rows = is_array($rows ?? null) ? $rows : [];
$doctorOptions = is_array($doctorOptions ?? null) ? $doctorOptions : [];
$afiliacionOptions = is_array($afiliacionOptions ?? null) ? $afiliacionOptions : [];
$sedeOptions = is_array($sedeOptions ?? null) ? $sedeOptions : [];
$localidadOptions = is_array($localidadOptions ?? null) ? $localidadOptions : [];
$departamentoOptions = is_array($departamentoOptions ?? null) ? $departamentoOptions : [];

$exportQuery = http_build_query([
    'fecha_inicio' => (string)($filters['fecha_inicio'] ?? ''),
    'fecha_fin' => (string)($filters['fecha_fin'] ?? ''),
    'doctor' => (string)($filters['doctor'] ?? ''),
    'afiliacion' => (string)($filters['afiliacion'] ?? ''),
    'sede' => (string)($filters['sede'] ?? ''),
    'producto' => (string)($filters['producto'] ?? ''),
    'localidad' => (string)($filters['localidad'] ?? ''),
    'departamento' => (string)($filters['departamento'] ?? ''),
]);

$dashboardPayload = json_encode(
    $dashboard,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
) ?: '{"cards":[],"meta":[],"charts":{}}';

$inlineScripts = array_merge($inlineScripts ?? [], [
    "window.farmaciaDashboardData = {$dashboardPayload};",
    "if (window.initFarmaciaDashboard) { window.initFarmaciaDashboard(window.farmaciaDashboardData); }",
]);
?>

<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Dashboard de Recetas</h3>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                <li class="breadcrumb-item"><a href="/farmacia">Farmacia</a></li>
                <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
            </ol>
        </div>
    </div>
</div>

<section class="content">
    <div class="box mb-3">
        <div class="box-header with-border d-flex justify-content-between align-items-center">
            <h4 class="box-title mb-0">Filtros</h4>
            <div class="d-flex flex-wrap gap-2 justify-content-end">
                <a href="/farmacia/dashboard/export/pdf<?= $exportQuery !== '' ? ('?' . htmlspecialchars($exportQuery, ENT_QUOTES, 'UTF-8')) : '' ?>"
                   class="btn btn-outline-danger btn-sm">
                    <i class="mdi mdi-file-pdf-box me-1"></i> Descargar PDF
                </a>
                <a href="/farmacia/dashboard/export/excel<?= $exportQuery !== '' ? ('?' . htmlspecialchars($exportQuery, ENT_QUOTES, 'UTF-8')) : '' ?>"
                   class="btn btn-outline-success btn-sm">
                    <i class="mdi mdi-file-excel-box me-1"></i> Descargar Excel
                </a>
            </div>
        </div>
        <div class="box-body">
            <form class="row g-2 align-items-end" method="get">
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Desde</label>
                    <input type="date" class="form-control" name="fecha_inicio"
                           value="<?= htmlspecialchars($filters['fecha_inicio'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Hasta</label>
                    <input type="date" class="form-control" name="fecha_fin"
                           value="<?= htmlspecialchars($filters['fecha_fin'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Médico</label>
                    <select class="form-select" name="doctor">
                        <option value="">Todos</option>
                        <?php foreach ($doctorOptions as $doctor): ?>
                            <option value="<?= htmlspecialchars($doctor, ENT_QUOTES, 'UTF-8') ?>" <?= (string)($filters['doctor'] ?? '') === (string)$doctor ? 'selected' : '' ?>>
                                <?= htmlspecialchars($doctor, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Localidad</label>
                    <select class="form-select" name="localidad">
                        <option value="">Todas</option>
                        <?php foreach ($localidadOptions as $localidad): ?>
                            <option value="<?= htmlspecialchars($localidad, ENT_QUOTES, 'UTF-8') ?>" <?= (string)($filters['localidad'] ?? '') === (string)$localidad ? 'selected' : '' ?>>
                                <?= htmlspecialchars($localidad, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Sede</label>
                    <select class="form-select" name="sede">
                        <option value="">Todas</option>
                        <?php foreach ($sedeOptions as $sede): ?>
                            <option value="<?= htmlspecialchars($sede, ENT_QUOTES, 'UTF-8') ?>" <?= (string)($filters['sede'] ?? '') === (string)$sede ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sede, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Departamento</label>
                    <select class="form-select" name="departamento">
                        <option value="">Todos</option>
                        <?php foreach ($departamentoOptions as $departamento): ?>
                            <option value="<?= htmlspecialchars($departamento, ENT_QUOTES, 'UTF-8') ?>" <?= (string)($filters['departamento'] ?? '') === (string)$departamento ? 'selected' : '' ?>>
                                <?= htmlspecialchars($departamento, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Afiliación</label>
                    <select class="form-select" name="afiliacion">
                        <option value="">Todas</option>
                        <?php foreach ($afiliacionOptions as $afiliacion): ?>
                            <option value="<?= htmlspecialchars($afiliacion, ENT_QUOTES, 'UTF-8') ?>" <?= (string)($filters['afiliacion'] ?? '') === (string)$afiliacion ? 'selected' : '' ?>>
                                <?= htmlspecialchars($afiliacion, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-12 col-md-4">
                    <label class="form-label">Producto</label>
                    <input type="text" class="form-control" name="producto"
                           value="<?= htmlspecialchars($filters['producto'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="Ej: Paracetamol">
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="mdi mdi-filter-variant"></i> Aplicar filtros
                    </button>
                    <a href="/farmacia" class="btn btn-outline-secondary btn-sm">
                        <i class="mdi mdi-close-circle-outline"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="farmacia-kpi-grid mb-3">
        <?php foreach ($dashboardCards as $card): ?>
            <article class="farmacia-kpi-card">
                <p class="farmacia-kpi-label mb-1"><?= htmlspecialchars((string)($card['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                <h4 class="farmacia-kpi-value mb-1"><?= htmlspecialchars((string)($card['value'] ?? '0'), ENT_QUOTES, 'UTF-8') ?></h4>
                <p class="farmacia-kpi-hint mb-0"><?= htmlspecialchars((string)($card['hint'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="d-flex flex-wrap gap-3 align-items-center text-muted small mb-3">
        <span><strong>TAT promedio:</strong> <?= htmlspecialchars(($dashboardMeta['tat_promedio_horas'] ?? null) !== null ? number_format((float)$dashboardMeta['tat_promedio_horas'], 2) . ' h' : '—', ENT_QUOTES, 'UTF-8') ?></span>
        <span><strong>TAT mediana:</strong> <?= htmlspecialchars(($dashboardMeta['tat_mediana_horas'] ?? null) !== null ? number_format((float)$dashboardMeta['tat_mediana_horas'], 2) . ' h' : '—', ENT_QUOTES, 'UTF-8') ?></span>
        <span><strong>TAT P90:</strong> <?= htmlspecialchars(($dashboardMeta['tat_p90_horas'] ?? null) !== null ? number_format((float)$dashboardMeta['tat_p90_horas'], 2) . ' h' : '—', ENT_QUOTES, 'UTF-8') ?></span>
        <span><strong>SLA <= 24h:</strong> <?= htmlspecialchars(($dashboardMeta['sla_24h_pct'] ?? null) !== null ? number_format((float)$dashboardMeta['sla_24h_pct'], 1) . '%' : '—', ENT_QUOTES, 'UTF-8') ?></span>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-6">
            <div class="farmacia-chart-card">
                <h6 class="farmacia-chart-title">Serie diaria (ítems y unidades)</h6>
                <div class="farmacia-chart-wrap">
                    <canvas id="chartFarmaciaSerieDiaria"></canvas>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="farmacia-chart-card">
                <h6 class="farmacia-chart-title">Top productos</h6>
                <div class="farmacia-chart-wrap">
                    <canvas id="chartFarmaciaTopProductos"></canvas>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="farmacia-chart-card">
                <h6 class="farmacia-chart-title">Top médicos</h6>
                <div class="farmacia-chart-wrap">
                    <canvas id="chartFarmaciaTopDoctores"></canvas>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="farmacia-chart-card">
                <h6 class="farmacia-chart-title">Distribución por vía</h6>
                <div class="farmacia-chart-wrap">
                    <canvas id="chartFarmaciaVias"></canvas>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="farmacia-chart-card">
                <h6 class="farmacia-chart-title">Distribución por afiliación</h6>
                <div class="farmacia-chart-wrap">
                    <canvas id="chartFarmaciaAfiliacion"></canvas>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="farmacia-chart-card">
                <h6 class="farmacia-chart-title">Distribución por localidad</h6>
                <div class="farmacia-chart-wrap">
                    <canvas id="chartFarmaciaLocalidad"></canvas>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="farmacia-chart-card">
                <h6 class="farmacia-chart-title">Distribución por departamento</h6>
                <div class="farmacia-chart-wrap">
                    <canvas id="chartFarmaciaDepartamento"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="box">
        <div class="box-header with-border">
            <h4 class="box-title">Detalle de recetas</h4>
        </div>
        <div class="box-body table-responsive">
            <table class="table table-striped table-sm">
                <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Sede</th>
                    <th>Localidad</th>
                    <th>Departamento</th>
                    <th>Médico</th>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Unid. farmacia</th>
                    <th>Diagnóstico</th>
                    <th>Paciente</th>
                    <th>Cédula paciente</th>
                    <th>Edad</th>
                    <th>Form ID</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!empty($rows)): ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)($row['fecha_receta'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($row['sede'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($row['localidad'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($row['departamento'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($row['doctor'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($row['producto'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($row['cantidad'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($row['total_farmacia'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($row['diagnostico'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($row['paciente_nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($row['cedula_paciente'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($row['edad_paciente'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($row['form_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="13" class="text-muted text-center">Sin datos para los filtros actuales.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<style>
    .farmacia-kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: 0.75rem;
    }

    .farmacia-kpi-card {
        border: 1px solid #e7ebf0;
        border-radius: 0.75rem;
        padding: 0.75rem 0.9rem;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }

    .farmacia-kpi-label {
        font-size: 0.78rem;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .farmacia-kpi-value {
        font-size: 1.35rem;
        font-weight: 700;
        color: #0b4f9c;
    }

    .farmacia-kpi-hint {
        font-size: 0.8rem;
        color: #6c757d;
    }

    .farmacia-chart-card {
        border: 1px solid #e7ebf0;
        border-radius: 0.75rem;
        padding: 0.75rem 0.9rem;
        background: #fff;
    }

    .farmacia-chart-title {
        font-size: 0.9rem;
        margin-bottom: 0.5rem;
        color: #34495e;
    }

    .farmacia-chart-wrap {
        position: relative;
        height: 290px;
        max-height: 290px;
    }

    .farmacia-chart-wrap canvas {
        width: 100% !important;
        height: 100% !important;
        display: block;
    }

    @media (max-width: 991.98px) {
        .farmacia-chart-wrap {
            height: 240px;
            max-height: 240px;
        }
    }
</style>

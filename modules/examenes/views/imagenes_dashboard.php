<?php
/** @var array<string, string> $filters */
/** @var array<string, mixed> $dashboard */
/** @var array<int, array<string, mixed>> $rows */

if (!isset($scripts) || !is_array($scripts)) {
    $scripts = [];
}
$scripts[] = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';

if (!isset($filters) || !is_array($filters)) {
    $filters = [
        'fecha_inicio' => '',
        'fecha_fin' => '',
        'afiliacion' => '',
        'tipo_examen' => '',
        'paciente' => '',
        'estado_agenda' => '',
    ];
}

if (!isset($dashboard) || !is_array($dashboard)) {
    $dashboard = ['cards' => [], 'meta' => [], 'charts' => []];
}

$dashboardCards = is_array($dashboard['cards'] ?? null) ? $dashboard['cards'] : [];
$dashboardMeta = is_array($dashboard['meta'] ?? null) ? $dashboard['meta'] : [];
$exportQuery = http_build_query([
    'fecha_inicio' => (string)($filters['fecha_inicio'] ?? ''),
    'fecha_fin' => (string)($filters['fecha_fin'] ?? ''),
    'afiliacion' => (string)($filters['afiliacion'] ?? ''),
    'tipo_examen' => (string)($filters['tipo_examen'] ?? ''),
    'paciente' => (string)($filters['paciente'] ?? ''),
    'estado_agenda' => (string)($filters['estado_agenda'] ?? ''),
]);

$estadoOpciones = [];
foreach (($rows ?? []) as $row) {
    $estado = trim((string)($row['estado_agenda'] ?? ''));
    if ($estado !== '' && !in_array($estado, $estadoOpciones, true)) {
        $estadoOpciones[] = $estado;
    }
}
sort($estadoOpciones);
?>

<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Dashboard de Imágenes</h3>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                <li class="breadcrumb-item"><a href="/imagenes/examenes-realizados">Imágenes</a></li>
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
                <a href="/imagenes/dashboard/export/pdf<?= $exportQuery !== '' ? ('?' . htmlspecialchars($exportQuery, ENT_QUOTES, 'UTF-8')) : '' ?>"
                   class="btn btn-outline-danger btn-sm">
                    <i class="mdi mdi-file-pdf-box me-1"></i> Descargar PDF
                </a>
                <a href="/imagenes/dashboard/export/excel<?= $exportQuery !== '' ? ('?' . htmlspecialchars($exportQuery, ENT_QUOTES, 'UTF-8')) : '' ?>"
                   class="btn btn-outline-success btn-sm">
                    <i class="mdi mdi-file-excel-box me-1"></i> Descargar Excel
                </a>
                <a href="/imagenes/examenes-realizados" class="btn btn-outline-primary btn-sm">
                    <i class="mdi mdi-format-list-bulleted me-1"></i> Ir a imágenes realizadas
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
                    <label class="form-label">Afiliación</label>
                    <input type="text" class="form-control" name="afiliacion"
                           value="<?= htmlspecialchars($filters['afiliacion'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="IESS, ISSFA, ...">
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Tipo examen</label>
                    <input type="text" class="form-control" name="tipo_examen"
                           value="<?= htmlspecialchars($filters['tipo_examen'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="281032 / OCT / ...">
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Paciente/Cédula</label>
                    <input type="text" class="form-control" name="paciente"
                           value="<?= htmlspecialchars($filters['paciente'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="Nombre o ID">
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Estado agenda</label>
                    <select class="form-select" name="estado_agenda">
                        <option value="">Todos</option>
                        <?php foreach ($estadoOpciones as $estado): ?>
                            <option value="<?= htmlspecialchars($estado, ENT_QUOTES, 'UTF-8') ?>" <?= ($filters['estado_agenda'] ?? '') === $estado ? 'selected' : '' ?>>
                                <?= htmlspecialchars($estado, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="mdi mdi-filter-variant"></i> Aplicar filtros
                    </button>
                    <a href="/imagenes/dashboard" class="btn btn-outline-secondary btn-sm">
                        <i class="mdi mdi-close-circle-outline"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="imagenes-kpi-grid mb-3">
        <?php foreach ($dashboardCards as $card): ?>
            <article class="imagenes-kpi-card">
                <p class="imagenes-kpi-label mb-1"><?= htmlspecialchars((string)($card['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                <h4 class="imagenes-kpi-value mb-1"><?= htmlspecialchars((string)($card['value'] ?? '0'), ENT_QUOTES, 'UTF-8') ?></h4>
                <p class="imagenes-kpi-hint mb-0"><?= htmlspecialchars((string)($card['hint'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="d-flex flex-wrap gap-3 align-items-center text-muted small mb-3">
        <span><strong>TAT promedio:</strong> <?= htmlspecialchars(($dashboardMeta['tat_promedio_horas'] ?? null) !== null ? number_format((float)$dashboardMeta['tat_promedio_horas'], 2) . ' h' : '—', ENT_QUOTES, 'UTF-8') ?></span>
        <span><strong>TAT mediana:</strong> <?= htmlspecialchars(($dashboardMeta['tat_mediana_horas'] ?? null) !== null ? number_format((float)$dashboardMeta['tat_mediana_horas'], 2) . ' h' : '—', ENT_QUOTES, 'UTF-8') ?></span>
        <span><strong>TAT P90:</strong> <?= htmlspecialchars(($dashboardMeta['tat_p90_horas'] ?? null) !== null ? number_format((float)$dashboardMeta['tat_p90_horas'], 2) . ' h' : '—', ENT_QUOTES, 'UTF-8') ?></span>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-6">
            <div class="imagenes-chart-card">
                <h6 class="imagenes-chart-title">Serie diaria (realizados vs informados)</h6>
                <div class="imagenes-chart-wrap">
                    <canvas id="chartImagenesSerieDiaria"></canvas>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="imagenes-chart-card">
                <h6 class="imagenes-chart-title">Trazabilidad facturación</h6>
                <div class="imagenes-chart-wrap">
                    <canvas id="chartImagenesTrazabilidad"></canvas>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="imagenes-chart-card">
                <h6 class="imagenes-chart-title">Citas generadas vs exámenes realizados</h6>
                <div class="imagenes-chart-wrap">
                    <canvas id="chartImagenesCitasRealizados"></canvas>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="imagenes-chart-card">
                <h6 class="imagenes-chart-title">Top códigos de imágenes</h6>
                <div class="imagenes-chart-wrap">
                    <canvas id="chartImagenesMixCodigos"></canvas>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="imagenes-chart-card">
                <h6 class="imagenes-chart-title">Aging de no informados</h6>
                <div class="imagenes-chart-wrap">
                    <canvas id="chartImagenesAging"></canvas>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
    .imagenes-kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 0.75rem;
    }
    .imagenes-kpi-card {
        border: 1px solid #e7ebf0;
        border-radius: 0.75rem;
        padding: 0.75rem 0.85rem;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }
    .imagenes-kpi-label {
        font-size: 0.78rem;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: .04em;
    }
    .imagenes-kpi-value {
        font-size: 1.4rem;
        font-weight: 700;
        color: #0b4f9c;
    }
    .imagenes-kpi-hint {
        font-size: 0.8rem;
        color: #6c757d;
    }
    .imagenes-chart-card {
        border: 1px solid #e7ebf0;
        border-radius: 0.75rem;
        padding: 0.75rem 0.9rem;
        background: #fff;
    }
    .imagenes-chart-title {
        font-size: 0.9rem;
        margin-bottom: 0.5rem;
        color: #34495e;
    }
    .imagenes-chart-wrap {
        position: relative;
        height: 290px;
        max-height: 290px;
    }
    .imagenes-chart-wrap canvas {
        width: 100% !important;
        height: 100% !important;
        display: block;
    }
    @media (max-width: 991.98px) {
        .imagenes-chart-wrap {
            height: 240px;
            max-height: 240px;
        }
    }
</style>

<script>
    (function () {
        const dashboardData = <?= json_encode($dashboard, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"cards":[],"meta":[],"charts":[]}' ?>;

        function drawChart(targetId, configBuilder, hasData) {
            const canvas = document.getElementById(targetId);
            if (!canvas) return;
            if (!hasData) {
                const wrapper = canvas.parentNode;
                if (wrapper) {
                    wrapper.innerHTML = '<p class="text-muted small mb-0">Sin datos para el rango seleccionado.</p>';
                }
                return;
            }
            if (typeof Chart === 'undefined') return;
            new Chart(canvas.getContext('2d'), configBuilder());
        }

        document.addEventListener('DOMContentLoaded', function () {
            const charts = (dashboardData && dashboardData.charts) ? dashboardData.charts : {};
            const serie = charts.serie_diaria || {};
            const trazabilidad = charts.trazabilidad || {};
            const citasRealizados = charts.citas_vs_realizados || {};
            const mix = charts.mix_codigos || {};
            const aging = charts.aging_backlog || {};

            drawChart(
                'chartImagenesSerieDiaria',
                function () {
                    return {
                        type: 'line',
                        data: {
                            labels: Array.isArray(serie.labels) ? serie.labels : [],
                            datasets: [
                                {
                                    label: 'Realizados',
                                    data: Array.isArray(serie.realizados) ? serie.realizados : [],
                                    borderColor: '#0d6efd',
                                    backgroundColor: 'rgba(13,110,253,.15)',
                                    borderWidth: 2,
                                    tension: 0.25
                                },
                                {
                                    label: 'Informados',
                                    data: Array.isArray(serie.informados) ? serie.informados : [],
                                    borderColor: '#198754',
                                    backgroundColor: 'rgba(25,135,84,.15)',
                                    borderWidth: 2,
                                    tension: 0.25
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {legend: {position: 'top'}},
                            scales: {y: {beginAtZero: true, ticks: {precision: 0}}}
                        }
                    };
                },
                Array.isArray(serie.labels) && serie.labels.length > 0
            );

            drawChart(
                'chartImagenesTrazabilidad',
                function () {
                    return {
                        type: 'doughnut',
                        data: {
                            labels: Array.isArray(trazabilidad.labels) ? trazabilidad.labels : [],
                            datasets: [
                                {
                                    data: Array.isArray(trazabilidad.values) ? trazabilidad.values : [],
                                    backgroundColor: ['#198754', '#dc3545', '#fd7e14', '#6c757d']
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {legend: {position: 'bottom'}}
                        }
                    };
                },
                Array.isArray(trazabilidad.values) && trazabilidad.values.some(function (value) { return Number(value || 0) > 0; })
            );

            drawChart(
                'chartImagenesMixCodigos',
                function () {
                    return {
                        type: 'bar',
                        data: {
                            labels: Array.isArray(mix.labels) ? mix.labels : [],
                            datasets: [
                                {
                                    label: 'Estudios',
                                    data: Array.isArray(mix.values) ? mix.values : [],
                                    backgroundColor: '#0dcaf0'
                                }
                            ]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {legend: {display: false}},
                            scales: {x: {beginAtZero: true, ticks: {precision: 0}}}
                        }
                    };
                },
                Array.isArray(mix.labels) && mix.labels.length > 0
            );

            drawChart(
                'chartImagenesAging',
                function () {
                    return {
                        type: 'bar',
                        data: {
                            labels: Array.isArray(aging.labels) ? aging.labels : [],
                            datasets: [
                                {
                                    label: 'No informados',
                                    data: Array.isArray(aging.values) ? aging.values : [],
                                    backgroundColor: ['#20c997', '#ffc107', '#fd7e14', '#dc3545']
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {legend: {display: false}},
                            scales: {y: {beginAtZero: true, ticks: {precision: 0}}}
                        }
                    };
                },
                Array.isArray(aging.values) && aging.values.some(function (value) { return Number(value || 0) > 0; })
            );

            drawChart(
                'chartImagenesCitasRealizados',
                function () {
                    return {
                        type: 'bar',
                        data: {
                            labels: Array.isArray(citasRealizados.labels) ? citasRealizados.labels : [],
                            datasets: [
                                {
                                    label: 'Casos',
                                    data: Array.isArray(citasRealizados.values) ? citasRealizados.values : [],
                                    backgroundColor: ['#0d6efd', '#198754', '#fd7e14']
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {legend: {display: false}},
                            scales: {y: {beginAtZero: true, ticks: {precision: 0}}}
                        }
                    };
                },
                Array.isArray(citasRealizados.values) && citasRealizados.values.some(function (value) { return Number(value || 0) > 0; })
            );
        });
    })();
</script>

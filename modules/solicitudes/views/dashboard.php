<?php
/** @var string $pageTitle */

if (!isset($styles) || !is_array($styles)) {
    $styles = [];
}

$styles[] = 'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css';

if (!isset($scripts) || !is_array($scripts)) {
    $scripts = [];
}

array_push(
    $scripts,
    'https://cdn.jsdelivr.net/momentjs/latest/moment.min.js',
    'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js',
    'assets/vendor_components/apexcharts-bundle/dist/apexcharts.js',
    'js/pages/solicitudes/dashboard.js'
);

$solicitudesV2ReadsEnabled = filter_var(
    $_ENV['SOLICITUDES_V2_READS_ENABLED'] ?? getenv('SOLICITUDES_V2_READS_ENABLED') ?? '0',
    FILTER_VALIDATE_BOOLEAN
);
$solicitudesReadPrefix = $solicitudesV2ReadsEnabled ? '/v2' : '';

$basePath = '/solicitudes';
if (defined('BASE_URL')) {
    $baseUrlPath = parse_url((string) BASE_URL, PHP_URL_PATH) ?: '';
    $baseUrlPath = rtrim($baseUrlPath, '/');
    if ($baseUrlPath !== '') {
        $basePath = $baseUrlPath . '/solicitudes';
    }
}

$dashboardDataEndpoint = $basePath . '/dashboard-data';
if ($solicitudesReadPrefix !== '') {
    $marker = '/solicitudes/dashboard-data';
    $markerPos = strpos($dashboardDataEndpoint, $marker);
    if ($markerPos !== false) {
        $rootPrefix = substr($dashboardDataEndpoint, 0, $markerPos);
        $dashboardDataEndpoint = $rootPrefix . $solicitudesReadPrefix . $marker;
    } else {
        $dashboardDataEndpoint = $solicitudesReadPrefix . '/' . ltrim($dashboardDataEndpoint, '/');
    }
}
?>

<script>
    window.__SOLICITUDES_DASHBOARD__ = {
        endpoint: <?= json_encode($dashboardDataEndpoint, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        readPrefix: <?= json_encode($solicitudesReadPrefix, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        v2ReadsEnabled: <?= json_encode($solicitudesV2ReadsEnabled) ?>,
    };
</script>

<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Dashboard Solicitudes</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item"><a href="/solicitudes">Solicitudes</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <style>
        .dashboard-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .dashboard-header .date-range {
            min-width: 260px;
        }

        .dashboard-header .range-label {
            font-size: 0.9rem;
            color: #64748b;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .metric-card {
            border-radius: 14px;
            border: 1px solid rgba(148, 163, 184, 0.2);
            background: #ffffff;
            padding: 1rem 1.1rem;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
        }

        .metric-card h6 {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #6366f1;
            margin-bottom: 0.5rem;
        }

        .metric-value {
            font-size: 1.7rem;
            font-weight: 700;
            color: #0f172a;
        }

        .metric-subtext {
            font-size: 0.85rem;
            color: #64748b;
        }

        .chart-card {
            height: 100%;
        }

        .chart-container {
            min-height: 280px;
        }

        .chart-empty {
            height: 240px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            text-align: center;
            font-size: 0.95rem;
        }

        .dashboard-section-title {
            font-weight: 600;
            color: #0f172a;
        }
    </style>

    <div class="dashboard-header">
        <div>
            <h4 class="mb-1">Resumen ejecutivo</h4>
            <div class="range-label">Rango actual: <span id="dashboard-range">—</span></div>
        </div>
        <div class="ms-auto d-flex flex-wrap gap-2">
            <input type="text" class="form-control date-range" id="dashboard-range-input" placeholder="Selecciona rango" autocomplete="off">
            <button type="button" class="btn btn-primary" id="dashboard-refresh">
                <i class="mdi mdi-refresh"></i> Actualizar
            </button>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="metric-card">
            <h6>Total de solicitudes</h6>
            <div class="metric-value" id="metric-total">—</div>
            <div class="metric-subtext">Solicitudes registradas en el periodo</div>
        </div>
        <div class="metric-card">
            <h6>Solicitudes completadas</h6>
            <div class="metric-value" id="metric-completed">—</div>
            <div class="metric-subtext">Finalizadas en el flujo kanban</div>
        </div>
        <div class="metric-card">
            <h6>Avance promedio</h6>
            <div class="metric-value" id="metric-progress">—</div>
            <div class="metric-subtext">Promedio de progreso del checklist</div>
        </div>
        <div class="metric-card">
            <h6>Correos enviados</h6>
            <div class="metric-value" id="metric-mails-sent">—</div>
            <div class="metric-subtext">Mensajes de cobertura exitosos</div>
        </div>
        <div class="metric-card">
            <h6>Correos fallidos</h6>
            <div class="metric-value" id="metric-mails-failed">—</div>
            <div class="metric-subtext">Errores durante el envío</div>
        </div>
        <div class="metric-card">
            <h6>Adjuntos promedio</h6>
            <div class="metric-value" id="metric-attachments">—</div>
            <div class="metric-subtext">Promedio de tamaño de adjuntos</div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-8 col-12">
            <div class="box chart-card">
                <div class="box-header with-border">
                    <h4 class="box-title dashboard-section-title">Solicitudes creadas por mes</h4>
                </div>
                <div class="box-body">
                    <div id="chart-solicitudes-mes" class="chart-container"></div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-12">
            <div class="box chart-card">
                <div class="box-header with-border">
                    <h4 class="box-title dashboard-section-title">Solicitudes por afiliación</h4>
                </div>
                <div class="box-body">
                    <div id="chart-afiliacion" class="chart-container"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-6 col-12">
            <div class="box chart-card">
                <div class="box-header with-border">
                    <h4 class="box-title dashboard-section-title">Top procedimientos</h4>
                </div>
                <div class="box-body">
                    <div id="chart-procedimientos" class="chart-container"></div>
                </div>
            </div>
        </div>
        <div class="col-xl-6 col-12">
            <div class="box chart-card">
                <div class="box-header with-border">
                    <h4 class="box-title dashboard-section-title">Solicitudes por doctor</h4>
                </div>
                <div class="box-body">
                    <div id="chart-doctor" class="chart-container"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-4 col-12">
            <div class="box chart-card">
                <div class="box-header with-border">
                    <h4 class="box-title dashboard-section-title">Solicitudes por prioridad</h4>
                </div>
                <div class="box-body">
                    <div id="chart-prioridad" class="chart-container"></div>
                </div>
            </div>
        </div>
        <div class="col-xl-8 col-12">
            <div class="box chart-card">
                <div class="box-header with-border">
                    <h4 class="box-title dashboard-section-title">WIP por columna kanban</h4>
                </div>
                <div class="box-body">
                    <div id="chart-wip" class="chart-container"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-4 col-12">
            <div class="box chart-card">
                <div class="box-header with-border">
                    <h4 class="box-title dashboard-section-title">Progreso promedio</h4>
                </div>
                <div class="box-body">
                    <div id="chart-progress" class="chart-container"></div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-12">
            <div class="box chart-card">
                <div class="box-header with-border">
                    <h4 class="box-title dashboard-section-title">Distribución de avance</h4>
                </div>
                <div class="box-body">
                    <div id="chart-progress-buckets" class="chart-container"></div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-12">
            <div class="box chart-card">
                <div class="box-header with-border">
                    <h4 class="box-title dashboard-section-title">Próxima etapa más frecuente</h4>
                </div>
                <div class="box-body">
                    <div id="chart-next-stages" class="chart-container"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-4 col-12">
            <div class="box chart-card">
                <div class="box-header with-border">
                    <h4 class="box-title dashboard-section-title">Estado de correos</h4>
                </div>
                <div class="box-body">
                    <div id="chart-mail-status" class="chart-container"></div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-12">
            <div class="box chart-card">
                <div class="box-header with-border">
                    <h4 class="box-title dashboard-section-title">Plantillas más usadas</h4>
                </div>
                <div class="box-body">
                    <div id="chart-mail-templates" class="chart-container"></div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-12">
            <div class="box chart-card">
                <div class="box-header with-border">
                    <h4 class="box-title dashboard-section-title">Usuarios que envían correos</h4>
                </div>
                <div class="box-body">
                    <div id="chart-mail-users" class="chart-container"></div>
                </div>
            </div>
        </div>
    </div>
</section>

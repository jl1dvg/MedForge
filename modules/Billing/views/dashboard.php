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
    'js/pages/billing/dashboard.js'
);
?>

<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Dashboard Billing</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item"><a href="/billing">Billing</a></li>
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
            color: #0ea5e9;
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

        .table-sticky th {
            position: sticky;
            top: 0;
            background: #f8fafc;
        }

        .dashboard-loader-overlay {
            position: fixed;
            inset: 0;
            z-index: 2050;
            background: rgba(15, 23, 42, 0.2);
            display: none;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(1px);
        }

        .dashboard-loader-overlay.is-visible {
            display: flex;
        }

        .dashboard-loader-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.16);
            padding: 14px 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #0f172a;
            font-weight: 600;
        }
    </style>

    <div id="billing-dashboard-loader" class="dashboard-loader-overlay" aria-live="polite" aria-busy="true">
        <div class="dashboard-loader-card">
            <div class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></div>
            <span>Cargando datos del dashboard...</span>
        </div>
    </div>

    <div class="dashboard-header">
        <div>
            <h4 class="mb-1">Resumen ejecutivo</h4>
            <div class="range-label">Rango actual: <span id="billing-range">—</span></div>
        </div>
        <div class="ms-auto d-flex flex-wrap gap-2">
            <input type="text" class="form-control date-range" id="billing-range-input" placeholder="Selecciona rango" autocomplete="off">
            <button type="button" class="btn btn-primary" id="billing-refresh">
                <i class="mdi mdi-refresh"></i> Actualizar
            </button>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="metric-card">
            <h6>Total facturas</h6>
            <div class="metric-value" id="metric-facturas">—</div>
            <div class="metric-subtext">Facturas generadas en el periodo</div>
        </div>
        <div class="metric-card">
            <h6>Monto facturado</h6>
            <div class="metric-value" id="metric-monto">—</div>
            <div class="metric-subtext">Total facturado (incluye todos los ítems)</div>
        </div>
        <div class="metric-card">
            <h6>Ticket promedio</h6>
            <div class="metric-value" id="metric-ticket">—</div>
            <div class="metric-subtext">Promedio de facturación por factura</div>
        </div>
        <div class="metric-card">
            <h6>Ítems por factura</h6>
            <div class="metric-value" id="metric-items">—</div>
            <div class="metric-subtext">Promedio de ítems cobrados por factura</div>
        </div>
        <div class="metric-card">
            <h6>Fuga detectada</h6>
            <div class="metric-value" id="metric-leakage">—</div>
            <div class="metric-subtext">Procedimientos sin factura</div>
        </div>
        <div class="metric-card">
            <h6>Aging promedio</h6>
            <div class="metric-value" id="metric-aging">—</div>
            <div class="metric-subtext">Días promedio sin facturar</div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-8 col-12">
            <div class="box chart-card">
                <div class="box-header with-border">
                    <h4 class="box-title dashboard-section-title">Monto facturado por día</h4>
                </div>
                <div class="box-body">
                    <div id="chart-billing-dia" class="chart-container"></div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-12">
            <div class="box chart-card">
                <div class="box-header with-border">
                    <h4 class="box-title dashboard-section-title">Fuga de facturación</h4>
                </div>
                <div class="box-body">
                    <div id="chart-leakage" class="chart-container"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-6 col-12">
            <div class="box chart-card">
                <div class="box-header with-border">
                    <h4 class="box-title dashboard-section-title">Monto por afiliación</h4>
                </div>
                <div class="box-body">
                    <div id="chart-afiliacion" class="chart-container"></div>
                </div>
            </div>
        </div>
        <div class="col-xl-6 col-12">
            <div class="box chart-card">
                <div class="box-header with-border">
                    <h4 class="box-title dashboard-section-title">Top procedimientos por ingresos</h4>
                </div>
                <div class="box-body">
                    <div id="chart-procedimientos" class="chart-container"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-6 col-12">
            <div class="box chart-card">
                <div class="box-header with-border">
                    <h4 class="box-title dashboard-section-title">No facturados por afiliación</h4>
                </div>
                <div class="box-body">
                    <div id="chart-leakage-afiliacion" class="chart-container"></div>
                </div>
            </div>
        </div>
        <div class="col-xl-6 col-12">
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title dashboard-section-title">Top pendientes más antiguos</h4>
                </div>
                <div class="box-body table-responsive" style="max-height: 320px;">
                    <table class="table table-hover table-sm table-sticky mb-0">
                        <thead>
                        <tr>
                            <th>Form ID</th>
                            <th>Paciente</th>
                            <th>Afiliación</th>
                            <th>Días pendiente</th>
                        </tr>
                        </thead>
                        <tbody id="table-oldest">
                        <tr>
                            <td colspan="4" class="text-center text-muted">Sin datos disponibles</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <hr class="my-4">

    <div class="dashboard-header">
        <div>
            <h4 class="mb-1">Ingresos por Procedimientos</h4>
            <div class="range-label">Filtros aplicados: <span id="procedimientos-filters-label">—</span></div>
        </div>
        <div class="ms-auto d-flex flex-wrap gap-2">
            <select class="form-select" id="procedimientos-year" style="min-width: 140px;">
                <?php $currentYear = (int) date('Y'); ?>
                <?php for ($year = $currentYear; $year >= $currentYear - 4; $year--): ?>
                    <option value="<?= $year ?>" <?= $year === $currentYear ? 'selected' : '' ?>>
                        <?= $year ?>
                    </option>
                <?php endfor; ?>
            </select>
            <input type="text" class="form-control" id="procedimientos-sede" placeholder="Villa Club" style="min-width: 180px;">
            <select class="form-select" id="procedimientos-cliente" style="min-width: 180px;">
                <option value="todos">Todos los clientes</option>
                <option value="privado">Privado</option>
                <option value="particular">Particular</option>
                <option value="publico">Público</option>
                <option value="fundacional">Fundacional</option>
            </select>
            <button type="button" class="btn btn-primary" id="procedimientos-refresh">
                <i class="mdi mdi-refresh"></i> Actualizar
            </button>
            <button type="button" class="btn btn-outline-primary" id="procedimientos-export">
                <i class="mdi mdi-file-export"></i> Exportar detalle CSV
            </button>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="metric-card">
            <h6>Total anual</h6>
            <div class="metric-value" id="proc-total-anual">—</div>
            <div class="metric-subtext">Ingresos totales del año</div>
        </div>
        <div class="metric-card">
            <h6>Acumulado YTD</h6>
            <div class="metric-value" id="proc-ytd">—</div>
            <div class="metric-subtext">Acumulado hasta el mes actual</div>
        </div>
        <div class="metric-card">
            <h6>Run rate</h6>
            <div class="metric-value" id="proc-run-rate">—</div>
            <div class="metric-subtext">Promedio mensual</div>
        </div>
        <div class="metric-card">
            <h6>Mejor mes</h6>
            <div class="metric-value" id="proc-best-month">—</div>
            <div class="metric-subtext">Mes pico de ingresos</div>
        </div>
        <div class="metric-card">
            <h6>Peor mes</h6>
            <div class="metric-value" id="proc-worst-month">—</div>
            <div class="metric-subtext">Mes valle de ingresos</div>
        </div>
        <div class="metric-card">
            <h6>Crecimiento MoM</h6>
            <div class="metric-value" id="proc-mom">—</div>
            <div class="metric-subtext">Último mes vs anterior</div>
        </div>
        <div class="metric-card">
            <h6>Top categoría</h6>
            <div class="metric-value" id="proc-top-category">—</div>
            <div class="metric-subtext" id="proc-top-category-subtext">Participación anual</div>
        </div>
        <div class="metric-card">
            <h6>% Cirugía del total</h6>
            <div class="metric-value" id="proc-cirugia-share">—</div>
            <div class="metric-subtext">Mix de ingresos por cirugía</div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-7 col-12">
            <div class="box chart-card">
                <div class="box-header with-border">
                    <h4 class="box-title dashboard-section-title">Serie mensual total</h4>
                </div>
                <div class="box-body">
                    <div id="chart-proc-line" class="chart-container"></div>
                </div>
            </div>
        </div>
        <div class="col-xl-5 col-12">
            <div class="box chart-card">
                <div class="box-header with-border">
                    <h4 class="box-title dashboard-section-title">Distribución anual por categoría</h4>
                </div>
                <div class="box-body">
                    <div id="chart-proc-donut" class="chart-container"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-8 col-12">
            <div class="box chart-card">
                <div class="box-header with-border">
                    <h4 class="box-title dashboard-section-title">Ingresos por categoría (mensual)</h4>
                </div>
                <div class="box-body">
                    <div id="chart-proc-stacked" class="chart-container"></div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-12">
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title dashboard-section-title">Resumen por categoría</h4>
                </div>
                <div class="box-body table-responsive" style="max-height: 320px;">
                    <table class="table table-hover table-sm table-sticky mb-0">
                        <thead>
                        <tr>
                            <th>Categoría</th>
                            <th>Cantidad</th>
                            <th>Total anual</th>
                            <th>%</th>
                            <th>Mes pico</th>
                            <th>Promedio</th>
                        </tr>
                        </thead>
                        <tbody id="table-proc-summary">
                        <tr>
                            <td colspan="6" class="text-center text-muted">Sin datos para este periodo</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="box">
                <div class="box-header with-border d-flex align-items-center gap-2 flex-wrap">
                    <h4 class="box-title dashboard-section-title mb-0">Detalle de procedimientos</h4>
                    <div class="ms-auto d-flex gap-2">
                        <select class="form-select form-select-sm" id="proc-detail-category" style="min-width: 180px;">
                            <option value="">Todas las categorías</option>
                            <option value="Cirugía">Cirugía</option>
                            <option value="Consulta Externa">Consulta Externa</option>
                            <option value="Exámenes">Exámenes</option>
                            <option value="PNI">PNI</option>
                            <option value="Otros">Otros</option>
                        </select>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="proc-detail-refresh">Ver detalle</button>
                    </div>
                </div>
                <div class="box-body table-responsive" style="max-height: 420px;">
                    <table class="table table-hover table-sm table-sticky mb-0">
                        <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Form ID</th>
                            <th>Paciente</th>
                            <th>Afiliación</th>
                            <th>Tipo cliente</th>
                            <th>Categoría</th>
                            <th>Código</th>
                            <th>Detalle</th>
                            <th>Valor</th>
                        </tr>
                        </thead>
                        <tbody id="table-proc-detail">
                        <tr>
                            <td colspan="9" class="text-center text-muted">Sin datos para este periodo</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

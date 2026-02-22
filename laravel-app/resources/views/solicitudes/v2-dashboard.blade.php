@extends('layouts.medforge')

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
    <style>
        .sol-dashboard-header {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }

        .sol-dashboard-title {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
        }

        .sol-dashboard-subtitle {
            margin: 2px 0 0;
            color: #64748b;
            font-size: 13px;
        }

        .sol-dashboard-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .sol-dashboard-actions .date-range {
            min-width: 260px;
        }

        .sol-metrics-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            margin-bottom: 18px;
        }

        .sol-metric-card {
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            background: #fff;
            padding: 14px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
        }

        .sol-metric-card h6 {
            margin: 0 0 6px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #475569;
        }

        .sol-metric-value {
            margin: 0;
            font-size: 30px;
            line-height: 1;
            font-weight: 700;
            color: #0f172a;
        }

        .chart-card .box-body {
            min-height: 300px;
        }

        .chart-empty {
            min-height: 260px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            text-align: center;
        }
    </style>
@endpush

@section('content')
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title">Dashboard de Solicitudes v2</h3>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                            <li class="breadcrumb-item"><a href="/v2/solicitudes">Solicitudes</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="sol-dashboard-header">
            <div>
                <h4 class="sol-dashboard-title">Resumen operativo</h4>
                <p class="sol-dashboard-subtitle">Rango actual: <span id="dashboard-range">—</span></p>
            </div>
            <div class="sol-dashboard-actions">
                <input type="text" class="form-control date-range" id="dashboard-range-input" placeholder="Selecciona rango" autocomplete="off">
                <button type="button" class="btn btn-primary" id="dashboard-refresh">
                    <i class="mdi mdi-refresh"></i> Actualizar
                </button>
            </div>
        </div>

        <div class="sol-metrics-grid">
            <article class="sol-metric-card">
                <h6>Total solicitudes</h6>
                <p class="sol-metric-value" id="metric-total">—</p>
            </article>
            <article class="sol-metric-card">
                <h6>Completadas</h6>
                <p class="sol-metric-value" id="metric-completed">—</p>
            </article>
            <article class="sol-metric-card">
                <h6>Avance promedio</h6>
                <p class="sol-metric-value" id="metric-progress">—</p>
            </article>
            <article class="sol-metric-card">
                <h6>Correos enviados</h6>
                <p class="sol-metric-value" id="metric-mails-sent">—</p>
            </article>
            <article class="sol-metric-card">
                <h6>Correos fallidos</h6>
                <p class="sol-metric-value" id="metric-mails-failed">—</p>
            </article>
            <article class="sol-metric-card">
                <h6>Adjuntos promedio</h6>
                <p class="sol-metric-value" id="metric-attachments">—</p>
            </article>
        </div>

        <div class="row">
            <div class="col-xl-8 col-12">
                <div class="box chart-card">
                    <div class="box-header with-border"><h4 class="box-title">Solicitudes por mes</h4></div>
                    <div class="box-body"><div id="chart-solicitudes-mes"></div></div>
                </div>
            </div>
            <div class="col-xl-4 col-12">
                <div class="box chart-card">
                    <div class="box-header with-border"><h4 class="box-title">Solicitudes por afiliación</h4></div>
                    <div class="box-body"><div id="chart-afiliacion"></div></div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-6 col-12">
                <div class="box chart-card">
                    <div class="box-header with-border"><h4 class="box-title">Top procedimientos</h4></div>
                    <div class="box-body"><div id="chart-procedimientos"></div></div>
                </div>
            </div>
            <div class="col-xl-6 col-12">
                <div class="box chart-card">
                    <div class="box-header with-border"><h4 class="box-title">Solicitudes por doctor</h4></div>
                    <div class="box-body"><div id="chart-doctor"></div></div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-4 col-12">
                <div class="box chart-card">
                    <div class="box-header with-border"><h4 class="box-title">Prioridad</h4></div>
                    <div class="box-body"><div id="chart-prioridad"></div></div>
                </div>
            </div>
            <div class="col-xl-8 col-12">
                <div class="box chart-card">
                    <div class="box-header with-border"><h4 class="box-title">WIP por columna</h4></div>
                    <div class="box-body"><div id="chart-wip"></div></div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-4 col-12">
                <div class="box chart-card">
                    <div class="box-header with-border"><h4 class="box-title">Progreso promedio</h4></div>
                    <div class="box-body"><div id="chart-progress"></div></div>
                </div>
            </div>
            <div class="col-xl-4 col-12">
                <div class="box chart-card">
                    <div class="box-header with-border"><h4 class="box-title">Distribución avance</h4></div>
                    <div class="box-body"><div id="chart-progress-buckets"></div></div>
                </div>
            </div>
            <div class="col-xl-4 col-12">
                <div class="box chart-card">
                    <div class="box-header with-border"><h4 class="box-title">Próxima etapa</h4></div>
                    <div class="box-body"><div id="chart-next-stages"></div></div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-4 col-12">
                <div class="box chart-card">
                    <div class="box-header with-border"><h4 class="box-title">Estado correos</h4></div>
                    <div class="box-body"><div id="chart-mail-status"></div></div>
                </div>
            </div>
            <div class="col-xl-4 col-12">
                <div class="box chart-card">
                    <div class="box-header with-border"><h4 class="box-title">Plantillas correo</h4></div>
                    <div class="box-body"><div id="chart-mail-templates"></div></div>
                </div>
            </div>
            <div class="col-xl-4 col-12">
                <div class="box chart-card">
                    <div class="box-header with-border"><h4 class="box-title">Usuarios correo</h4></div>
                    <div class="box-body"><div id="chart-mail-users"></div></div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script src="/assets/vendor_components/apexcharts-bundle/dist/apexcharts.js"></script>
    <script>
        window.__SOLICITUDES_DASHBOARD__ = {
            endpoint: @json($dashboardEndpoint),
            readPrefix: '/v2',
            v2ReadsEnabled: true,
        };
    </script>
    <script src="/js/pages/solicitudes/dashboard.js"></script>
@endpush


@extends('layouts.medforge')

@php
    $currentYear = (int) date('Y');
    $afiliacionCategoriaOptions = is_array($afiliacionCategoriaOptions ?? null) ? $afiliacionCategoriaOptions : [];
    $empresaSeguroOptions = is_array($empresaSeguroOptions ?? null) ? $empresaSeguroOptions : [];
    $seguroOptions = is_array($seguroOptions ?? null) ? $seguroOptions : [];
@endphp

@push('styles')
    <link rel="stylesheet" href="/assets/vendor_components/bootstrap-daterangepicker/daterangepicker.css">
    <style>
        .billing-dashboard-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .billing-dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .metric-card {
            border-radius: 14px;
            border: 1px solid rgba(148, 163, 184, 0.2);
            background: #fff;
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

        .chart-container {
            min-height: 280px;
        }

        .chart-empty {
            min-height: 240px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: #94a3b8;
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
            background: #fff;
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
@endpush

@section('content')
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title">Dashboard Billing v2</h3>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                            <li class="breadcrumb-item"><a href="/v2/billing">Billing</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="ms-auto">
                <span class="badge bg-light text-primary">Fuente: LARAVEL V2</span>
            </div>
        </div>
    </div>

    <section class="content">
        <div id="billing-dashboard-loader" class="dashboard-loader-overlay" aria-live="polite" aria-busy="true">
            <div class="dashboard-loader-card">
                <div class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></div>
                <span>Cargando datos del dashboard...</span>
            </div>
        </div>

        <div class="billing-dashboard-header">
            <div>
                <h4 class="mb-1">Resumen ejecutivo</h4>
                <div class="text-muted">Rango actual: <span id="billing-range">—</span></div>
            </div>
            <div class="ms-auto d-flex flex-wrap gap-2">
                <input type="text" class="form-control" id="billing-range-input" placeholder="Selecciona rango" autocomplete="off" style="min-width: 260px;">
                <select class="form-select" id="billing-categoria" style="min-width: 190px;">
                    @foreach($afiliacionCategoriaOptions as $option)
                        <option value="{{ (string) ($option['value'] ?? '') }}">{{ (string) ($option['label'] ?? '') }}</option>
                    @endforeach
                </select>
                <select class="form-select" id="billing-empresa-seguro" style="min-width: 220px;">
                    @foreach($empresaSeguroOptions as $option)
                        <option value="{{ (string) ($option['value'] ?? '') }}">{{ (string) ($option['label'] ?? '') }}</option>
                    @endforeach
                </select>
                <select class="form-select" id="billing-seguro" style="min-width: 240px;">
                    @foreach($seguroOptions as $option)
                        <option value="{{ (string) ($option['value'] ?? '') }}">{{ (string) ($option['label'] ?? '') }}</option>
                    @endforeach
                </select>
                <select class="form-select" id="billing-sede" style="min-width: 170px;">
                    <option value="">Todas las sedes</option>
                    <option value="MATRIZ">MATRIZ</option>
                    <option value="CEIBOS">CEIBOS</option>
                </select>
                <button type="button" class="btn btn-primary" id="billing-refresh">
                    <i class="mdi mdi-refresh"></i> Actualizar
                </button>
            </div>
        </div>

        <div class="billing-dashboard-grid">
            <div class="metric-card"><h6>Total facturas</h6><div class="metric-value" id="metric-facturas">—</div><div class="metric-subtext">Facturas generadas</div></div>
            <div class="metric-card"><h6>Monto facturado</h6><div class="metric-value" id="metric-monto">—</div><div class="metric-subtext">Total del periodo</div></div>
            <div class="metric-card"><h6>Ticket promedio</h6><div class="metric-value" id="metric-ticket">—</div><div class="metric-subtext">Promedio por factura</div></div>
            <div class="metric-card"><h6>Ítems por factura</h6><div class="metric-value" id="metric-items">—</div><div class="metric-subtext">Promedio de ítems</div></div>
            <div class="metric-card"><h6>Fuga detectada</h6><div class="metric-value" id="metric-leakage">—</div><div class="metric-subtext">Pendientes</div></div>
            <div class="metric-card"><h6>Aging promedio</h6><div class="metric-value" id="metric-aging">—</div><div class="metric-subtext">Días sin facturar</div></div>
        </div>

        <div class="row">
            <div class="col-xl-8 col-12">
                <div class="box"><div class="box-header with-border"><h4 class="box-title">Monto facturado por día</h4></div><div class="box-body"><div id="chart-billing-dia" class="chart-container"></div></div></div>
            </div>
            <div class="col-xl-4 col-12">
                <div class="box"><div class="box-header with-border"><h4 class="box-title">Fuga de facturación</h4></div><div class="box-body"><div id="chart-leakage" class="chart-container"></div></div></div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-6 col-12">
                <div class="box"><div class="box-header with-border"><h4 class="box-title">Monto por afiliación</h4></div><div class="box-body"><div id="chart-afiliacion" class="chart-container"></div></div></div>
            </div>
            <div class="col-xl-6 col-12">
                <div class="box"><div class="box-header with-border"><h4 class="box-title">Top procedimientos por ingresos</h4></div><div class="box-body"><div id="chart-procedimientos" class="chart-container"></div></div></div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-6 col-12">
                <div class="box"><div class="box-header with-border"><h4 class="box-title">No facturados por afiliación</h4></div><div class="box-body"><div id="chart-leakage-afiliacion" class="chart-container"></div></div></div>
            </div>
            <div class="col-xl-6 col-12">
                <div class="box">
                    <div class="box-header with-border"><h4 class="box-title">Top pendientes más antiguos</h4></div>
                    <div class="box-body table-responsive" style="max-height: 320px;">
                        <table class="table table-hover table-sm table-sticky mb-0">
                            <thead><tr><th>Form ID</th><th>Paciente</th><th>Afiliación</th><th>Días pendiente</th></tr></thead>
                            <tbody id="table-oldest"><tr><td colspan="4" class="text-center text-muted">Sin datos disponibles</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <hr class="my-4">

        <div class="billing-dashboard-header">
            <div>
                <h4 class="mb-1">Ingresos por Procedimientos</h4>
                <div class="text-muted">Filtros aplicados: <span id="procedimientos-filters-label">—</span></div>
            </div>
            <div class="ms-auto d-flex flex-wrap gap-2">
                <select class="form-select" id="procedimientos-year" style="min-width: 140px;">
                    @for($year = $currentYear; $year >= $currentYear - 4; $year--)
                        <option value="{{ $year }}" @selected($year === $currentYear)>{{ $year }}</option>
                    @endfor
                </select>
                <select class="form-select" id="procedimientos-sede" style="min-width: 180px;">
                    <option value="">Todas las sedes</option>
                    <option value="MATRIZ">MATRIZ</option>
                    <option value="CEIBOS">CEIBOS</option>
                </select>
                <select class="form-select" id="procedimientos-cliente" style="min-width: 180px;">
                    <option value="">Todas las categorías</option>
                    <option value="privado">Privado</option>
                    <option value="particular">Particular</option>
                    <option value="publico">Público</option>
                    <option value="fundacional">Fundacional</option>
                    <option value="otros">Otros</option>
                </select>
                <select class="form-select" id="procedimientos-empresa-seguro" style="min-width: 220px;">
                    @foreach($empresaSeguroOptions as $option)
                        <option value="{{ (string) ($option['value'] ?? '') }}">{{ (string) ($option['label'] ?? '') }}</option>
                    @endforeach
                </select>
                <select class="form-select" id="procedimientos-seguro" style="min-width: 240px;">
                    @foreach($seguroOptions as $option)
                        <option value="{{ (string) ($option['value'] ?? '') }}">{{ (string) ($option['label'] ?? '') }}</option>
                    @endforeach
                </select>
                <button type="button" class="btn btn-primary" id="procedimientos-refresh"><i class="mdi mdi-refresh"></i> Actualizar</button>
                <button type="button" class="btn btn-outline-primary" id="procedimientos-export"><i class="mdi mdi-file-export"></i> Exportar detalle CSV</button>
            </div>
        </div>

        <div class="billing-dashboard-grid">
            <div class="metric-card"><h6>Total anual</h6><div class="metric-value" id="proc-total-anual">—</div><div class="metric-subtext">Ingresos del año</div></div>
            <div class="metric-card"><h6>Acumulado YTD</h6><div class="metric-value" id="proc-ytd">—</div><div class="metric-subtext">Hasta mes actual</div></div>
            <div class="metric-card"><h6>Run rate</h6><div class="metric-value" id="proc-run-rate">—</div><div class="metric-subtext">Promedio mensual</div></div>
            <div class="metric-card"><h6>Mejor mes</h6><div class="metric-value" id="proc-best-month">—</div><div class="metric-subtext">Mes pico</div></div>
            <div class="metric-card"><h6>Peor mes</h6><div class="metric-value" id="proc-worst-month">—</div><div class="metric-subtext">Mes valle</div></div>
            <div class="metric-card"><h6>Crecimiento MoM</h6><div class="metric-value" id="proc-mom">—</div><div class="metric-subtext">Último mes vs anterior</div></div>
            <div class="metric-card"><h6>Top categoría</h6><div class="metric-value" id="proc-top-category">—</div><div class="metric-subtext" id="proc-top-category-subtext">Participación anual</div></div>
            <div class="metric-card"><h6>% cirugía</h6><div class="metric-value" id="proc-cirugia-share">—</div><div class="metric-subtext">Participación cirugía</div></div>
        </div>

        <div class="row">
            <div class="col-xl-6 col-12"><div class="box"><div class="box-header with-border"><h4 class="box-title">Tendencia mensual</h4></div><div class="box-body"><div id="chart-proc-line" class="chart-container"></div></div></div></div>
            <div class="col-xl-6 col-12"><div class="box"><div class="box-header with-border"><h4 class="box-title">Mix por categoría</h4></div><div class="box-body"><div id="chart-proc-stacked" class="chart-container"></div></div></div></div>
        </div>

        <div class="row">
            <div class="col-xl-6 col-12"><div class="box"><div class="box-header with-border"><h4 class="box-title">Participación anual</h4></div><div class="box-body"><div id="chart-proc-donut" class="chart-container"></div></div></div></div>
            <div class="col-xl-6 col-12">
                <div class="box">
                    <div class="box-header with-border"><h4 class="box-title">Resumen por categoría</h4></div>
                    <div class="box-body table-responsive" style="max-height: 320px;">
                        <table class="table table-sm table-striped table-sticky mb-0">
                            <thead><tr><th>Categoría</th><th>Cantidad</th><th>Total</th><th>%</th><th>Pico</th><th>Promedio</th></tr></thead>
                            <tbody id="table-proc-summary"><tr><td colspan="6" class="text-center text-muted">Sin datos para este periodo</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="box">
            <div class="box-header with-border d-flex flex-wrap align-items-center gap-2">
                <h4 class="box-title mb-0">Detalle de procedimientos</h4>
                <div class="ms-auto d-flex gap-2">
                    <select class="form-select" id="proc-detail-category" style="min-width: 220px;">
                        <option value="">Todas las categorías</option>
                        <option value="cirugia">Cirugía</option>
                        <option value="consulta">Consulta</option>
                        <option value="imagenes">Imágenes</option>
                        <option value="insumos">Insumos</option>
                    </select>
                    <button type="button" class="btn btn-outline-primary" id="proc-detail-refresh"><i class="mdi mdi-refresh"></i> Refrescar detalle</button>
                </div>
            </div>
            <div class="box-body table-responsive" style="max-height: 420px;">
                <table class="table table-sm table-striped table-sticky mb-0">
                    <thead>
                    <tr>
                        <th>Fecha</th><th>Form ID</th><th>Paciente</th><th>Afiliación</th><th>Tipo cliente</th><th>Categoría</th><th>Código</th><th>Detalle</th><th>Valor</th>
                    </tr>
                    </thead>
                    <tbody id="table-proc-detail"><tr><td colspan="9" class="text-center text-muted">Sin datos para este periodo</td></tr></tbody>
                </table>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    @if (\App\Modules\Shared\Support\MedforgeAssets::hasViteBuild())
        @vite('resources/js/v2/billing-dashboard.js')
    @else
        <script src="/assets/vendor_components/moment/moment.js"></script>
        <script src="/assets/vendor_components/bootstrap-daterangepicker/daterangepicker.js"></script>
        <script src="/assets/vendor_components/apexcharts-bundle/dist/apexcharts.js"></script>
        <script src="/js/pages/billing/v2-dashboard.js"></script>
    @endif
@endpush

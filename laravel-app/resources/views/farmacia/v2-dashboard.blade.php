@extends('layouts.medforge')

@push('scripts')
    <script src="/assets/vendor_components/chart.js/chart.umd.js"></script>
    <script src="/assets/vendor_components/datatable/datatables.min.js"></script>
    <script src="/js/pages/shared/datatables-language-es.js"></script>
    <script src="/js/pages/farmacia-dashboard.js"></script>
    <script>
        window.farmaciaDashboardData = @json($dashboard ?? ['cards' => [], 'meta' => [], 'charts' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        document.addEventListener('DOMContentLoaded', function () {
            if (window.initFarmaciaDashboard) {
                window.initFarmaciaDashboard(window.farmaciaDashboardData);
            }
        });
    </script>
@endpush

@section('content')
@php
    $filters = is_array($filters ?? null) ? $filters : [];
    $dashboard = is_array($dashboard ?? null) ? $dashboard : ['cards' => [], 'meta' => [], 'charts' => []];
    $rows = is_array($rows ?? null) ? $rows : [];
    $conciliationRows = is_array($conciliationRows ?? null) ? $conciliationRows : [];
    $doctorOptions = is_array($doctorOptions ?? null) ? $doctorOptions : [];
    $afiliacionOptions = is_array($afiliacionOptions ?? null) ? $afiliacionOptions : [];
    $afiliacionCategoriaOptions = is_array($afiliacionCategoriaOptions ?? null) ? $afiliacionCategoriaOptions : [];
    $empresaAfiliacionOptions = is_array($empresaAfiliacionOptions ?? null) ? $empresaAfiliacionOptions : [];
    $sedeOptions = is_array($sedeOptions ?? null) ? $sedeOptions : [];
    $departamentoOptions = is_array($departamentoOptions ?? null) ? $departamentoOptions : [];
    $topMedicosOptions = is_array($topMedicosOptions ?? null) ? $topMedicosOptions : [10, 20, 30, 50];
    $dashboardCards = is_array($dashboard['cards'] ?? null) ? $dashboard['cards'] : [];
    $dashboardMeta = is_array($dashboard['meta'] ?? null) ? $dashboard['meta'] : [];
    $exportQuery = http_build_query([
        'fecha_inicio' => (string)($filters['fecha_inicio'] ?? ''),
        'fecha_fin' => (string)($filters['fecha_fin'] ?? ''),
        'doctor' => (string)($filters['doctor'] ?? ''),
        'tipo_afiliacion' => (string)($filters['tipo_afiliacion'] ?? ''),
        'empresa_afiliacion' => (string)($filters['empresa_afiliacion'] ?? ''),
        'afiliacion' => (string)($filters['afiliacion'] ?? ''),
        'sede' => (string)($filters['sede'] ?? ''),
        'producto' => (string)($filters['producto'] ?? ''),
        'departamento' => (string)($filters['departamento'] ?? ''),
        'top_n_medicos' => (string)($filters['top_n_medicos'] ?? '10'),
    ]);
@endphp

<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Dashboard de Recetas</h3>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                <li class="breadcrumb-item"><a href="/v2/farmacia">Farmacia</a></li>
                <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
            </ol>
        </div>
        <div class="ms-auto">
            <span class="badge bg-light text-primary">Fuente: LARAVEL V2</span>
        </div>
    </div>
</div>

<section class="content">
    <div class="box mb-3">
        <div class="box-header with-border d-flex justify-content-between align-items-center">
            <h4 class="box-title mb-0">Filtros</h4>
            <div class="d-flex flex-wrap gap-2 justify-content-end">
                <a href="/v2/farmacia/export/pdf{{ $exportQuery !== '' ? ('?' . $exportQuery) : '' }}" class="btn btn-outline-danger btn-sm">
                    <i class="mdi mdi-file-pdf-box me-1"></i> Descargar PDF
                </a>
                <a href="/v2/farmacia/export/excel{{ $exportQuery !== '' ? ('?' . $exportQuery) : '' }}" class="btn btn-outline-success btn-sm">
                    <i class="mdi mdi-file-excel-box me-1"></i> Descargar Excel
                </a>
            </div>
        </div>
        <div class="box-body">
            <form class="row g-2 align-items-end" method="get">
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Desde</label>
                    <input type="date" class="form-control" name="fecha_inicio" value="{{ (string) ($filters['fecha_inicio'] ?? '') }}">
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Hasta</label>
                    <input type="date" class="form-control" name="fecha_fin" value="{{ (string) ($filters['fecha_fin'] ?? '') }}">
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Médico</label>
                    <select class="form-select" name="doctor">
                        <option value="">Todos</option>
                        @foreach($doctorOptions as $doctor)
                            <option value="{{ (string) $doctor }}" @selected((string)($filters['doctor'] ?? '') === (string)$doctor)>{{ (string) $doctor }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Sede</label>
                    <select class="form-select" name="sede">
                        <option value="">Todas</option>
                        @foreach($sedeOptions as $sede)
                            <option value="{{ (string) $sede }}" @selected((string)($filters['sede'] ?? '') === (string)$sede)>{{ (string) $sede }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Departamento</label>
                    <select class="form-select" name="departamento">
                        <option value="">Todos</option>
                        @foreach($departamentoOptions as $departamento)
                            <option value="{{ (string) $departamento }}" @selected((string)($filters['departamento'] ?? '') === (string)$departamento)>{{ (string) $departamento }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Tipo afiliación</label>
                    <select class="form-select" name="tipo_afiliacion">
                        @foreach($afiliacionCategoriaOptions as $option)
                            <option value="{{ (string)($option['value'] ?? '') }}" @selected((string)($filters['tipo_afiliacion'] ?? '') === (string)($option['value'] ?? ''))>{{ (string)($option['label'] ?? '') }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-md-3">
                    <label class="form-label">Empresa afiliación</label>
                    <select class="form-select" name="empresa_afiliacion">
                        @foreach($empresaAfiliacionOptions as $option)
                            <option value="{{ (string)($option['value'] ?? '') }}" @selected((string)($filters['empresa_afiliacion'] ?? '') === (string)($option['value'] ?? ''))>{{ (string)($option['label'] ?? '') }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-md-3">
                    <label class="form-label">Seguro / plan</label>
                    <select class="form-select" name="afiliacion">
                        @foreach($afiliacionOptions as $option)
                            <option value="{{ (string)($option['value'] ?? '') }}" @selected((string)($filters['afiliacion'] ?? '') === (string)($option['value'] ?? ''))>{{ (string)($option['label'] ?? '') }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-12 col-md-4">
                    <label class="form-label">Producto</label>
                    <input type="text" class="form-control" name="producto" value="{{ (string) ($filters['producto'] ?? '') }}" placeholder="Ej: Paracetamol">
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Top médicos</label>
                    <select class="form-select" name="top_n_medicos">
                        @foreach($topMedicosOptions as $topOption)
                            <option value="{{ (string)$topOption }}" @selected((string)($filters['top_n_medicos'] ?? '10') === (string)$topOption)>{{ (string)$topOption }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="mdi mdi-filter-variant"></i> Aplicar filtros</button>
                    <a href="/v2/farmacia" class="btn btn-outline-secondary btn-sm"><i class="mdi mdi-close-circle-outline"></i> Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="farmacia-kpi-grid mb-3">
        @foreach($dashboardCards as $card)
            <article class="farmacia-kpi-card">
                <p class="farmacia-kpi-label mb-1">{{ (string)($card['label'] ?? '') }}</p>
                <h4 class="farmacia-kpi-value mb-1">{{ (string)($card['value'] ?? '0') }}</h4>
                <p class="farmacia-kpi-hint mb-0">{{ (string)($card['hint'] ?? '') }}</p>
            </article>
        @endforeach
    </div>

    <div class="d-flex flex-wrap gap-3 align-items-center text-muted small mb-3">
        <span><strong>TAT promedio:</strong> {{ ($dashboardMeta['tat_promedio_horas'] ?? null) !== null ? number_format((float)$dashboardMeta['tat_promedio_horas'], 2) . ' h' : '—' }}</span>
        <span><strong>TAT mediana:</strong> {{ ($dashboardMeta['tat_mediana_horas'] ?? null) !== null ? number_format((float)$dashboardMeta['tat_mediana_horas'], 2) . ' h' : '—' }}</span>
        <span><strong>TAT P90:</strong> {{ ($dashboardMeta['tat_p90_horas'] ?? null) !== null ? number_format((float)$dashboardMeta['tat_p90_horas'], 2) . ' h' : '—' }}</span>
        <span><strong>SLA <= 24h:</strong> {{ ($dashboardMeta['sla_24h_pct'] ?? null) !== null ? number_format((float)$dashboardMeta['sla_24h_pct'], 1) . '%' : '—' }}</span>
        <span><strong>Ingreso neto:</strong> {{ ($dashboardMeta['economia_neto_total'] ?? null) !== null ? ('$' . number_format((float)$dashboardMeta['economia_neto_total'], 2)) : '—' }}</span>
        <span><strong>Descuentos:</strong> {{ ($dashboardMeta['economia_descuentos_total'] ?? null) !== null ? ('$' . number_format((float)$dashboardMeta['economia_descuentos_total'], 2)) : '—' }}</span>
        <span><strong>Tasa exacta:</strong> {{ ($dashboardMeta['conciliacion_exacta_pct'] ?? null) !== null ? number_format((float)$dashboardMeta['conciliacion_exacta_pct'], 1) . '%' : '—' }}</span>
        <span><strong>Dif. promedio facturación:</strong> {{ ($dashboardMeta['conciliacion_diff_promedio_dias'] ?? null) !== null ? number_format((float)$dashboardMeta['conciliacion_diff_promedio_dias'], 2) . ' d' : '—' }}</span>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-6"><div class="farmacia-chart-card"><h6 class="farmacia-chart-title">Serie diaria (ítems y unidades)</h6><div class="farmacia-chart-wrap"><canvas id="chartFarmaciaSerieDiaria"></canvas></div></div></div>
        <div class="col-12 col-xl-6"><div class="farmacia-chart-card"><h6 class="farmacia-chart-title">Top productos</h6><div class="farmacia-chart-wrap"><canvas id="chartFarmaciaTopProductos"></canvas></div></div></div>
        <div class="col-12 col-xl-6"><div class="farmacia-chart-card"><h6 class="farmacia-chart-title">Top médicos ({{ (string)($filters['top_n_medicos'] ?? '10') }})</h6><div class="farmacia-chart-wrap"><canvas id="chartFarmaciaTopDoctores"></canvas></div></div></div>
        <div class="col-12 col-xl-6"><div class="farmacia-chart-card"><h6 class="farmacia-chart-title">Distribución por vía</h6><div class="farmacia-chart-wrap"><canvas id="chartFarmaciaVias"></canvas></div></div></div>
        <div class="col-12 col-xl-6"><div class="farmacia-chart-card"><h6 class="farmacia-chart-title">Distribución por afiliación</h6><div class="farmacia-chart-wrap"><canvas id="chartFarmaciaAfiliacion"></canvas></div></div></div>
        <div class="col-12 col-xl-6"><div class="farmacia-chart-card"><h6 class="farmacia-chart-title">Tipos de match de conciliación</h6><div class="farmacia-chart-wrap"><canvas id="chartFarmaciaTiposMatch"></canvas></div></div></div>
        <div class="col-12 col-xl-6"><div class="farmacia-chart-card"><h6 class="farmacia-chart-title">Distribución por departamento</h6><div class="farmacia-chart-wrap"><canvas id="chartFarmaciaDepartamento"></canvas></div></div></div>
        <div class="col-12 col-xl-6"><div class="farmacia-chart-card"><h6 class="farmacia-chart-title">Serie económica facturada</h6><div class="farmacia-chart-wrap"><canvas id="chartFarmaciaSerieEconomica"></canvas></div></div></div>
        <div class="col-12 col-xl-6"><div class="farmacia-chart-card"><h6 class="farmacia-chart-title">Neto por seguro</h6><div class="farmacia-chart-wrap"><canvas id="chartFarmaciaNetoAfiliacion"></canvas></div></div></div>
        <div class="col-12 col-xl-6"><div class="farmacia-chart-card"><h6 class="farmacia-chart-title">Neto por sede</h6><div class="farmacia-chart-wrap"><canvas id="chartFarmaciaNetoSede"></canvas></div></div></div>
        <div class="col-12 col-xl-6"><div class="farmacia-chart-card"><h6 class="farmacia-chart-title">Neto por médico ({{ (string)($filters['top_n_medicos'] ?? '10') }})</h6><div class="farmacia-chart-wrap"><canvas id="chartFarmaciaNetoDoctores"></canvas></div></div></div>
        <div class="col-12 col-xl-6"><div class="farmacia-chart-card"><h6 class="farmacia-chart-title">Neto por departamento facturado</h6><div class="farmacia-chart-wrap"><canvas id="chartFarmaciaDepartamentoFactura"></canvas></div></div></div>
    </div>

    <div class="box">
        <div class="box-header with-border"><h4 class="box-title">Detalle de recetas</h4></div>
        <div class="box-body table-responsive">
            <table id="tablaFarmaciaDetalle" class="table table-striped table-hover table-sm farmacia-data-table align-middle">
                <thead><tr><th>Fecha</th><th>Sede</th><th>Departamento</th><th>Médico</th><th>Producto</th><th>Cantidad</th><th>Unid. farmacia</th><th>Diagnóstico</th><th>Paciente</th><th>Cédula paciente</th><th>Edad</th><th>Form ID</th></tr></thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ (string)($row['fecha_receta'] ?? '—') }}</td>
                        <td>{{ (string)($row['sede'] ?? '') }}</td>
                        <td>{{ (string)($row['departamento'] ?? '') }}</td>
                        <td>{{ (string)($row['doctor'] ?? '') }}</td>
                        <td>{{ (string)($row['producto'] ?? '') }}</td>
                        <td>{{ (string)($row['cantidad'] ?? 0) }}</td>
                        <td>{{ (string)($row['total_farmacia'] ?? 0) }}</td>
                        <td>{{ (string)($row['diagnostico'] ?? '') }}</td>
                        <td>{{ (string)($row['paciente_nombre'] ?? '') }}</td>
                        <td>{{ (string)($row['cedula_paciente'] ?? '') }}</td>
                        <td>{{ (string)($row['edad_paciente'] ?? '—') }}</td>
                        <td>{{ (string)($row['form_id'] ?? '') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="text-muted text-center">Sin datos para los filtros actuales.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="box mt-3">
        <div class="box-header with-border"><h4 class="box-title">Incidencias de conciliación</h4></div>
        <div class="box-body table-responsive">
            <table id="tablaFarmaciaConciliacion" class="table table-striped table-hover table-sm farmacia-data-table align-middle">
                <thead><tr><th>Fecha receta</th><th>Fecha factura</th><th>Tipo match</th><th>Sede</th><th>Seguro</th><th>Médico</th><th>Paciente</th><th>Producto receta</th><th>Producto factura</th><th>Neto</th><th>Descuentos</th><th>Departamento factura</th></tr></thead>
                <tbody>
                @forelse($conciliationRows as $row)
                    <tr>
                        <td>{{ (string)($row['fecha_receta'] ?? '—') }}</td>
                        <td>{{ (string)($row['fecha_facturacion'] ?? '—') }}</td>
                        <td>
                            @php $tipoMatch = (string)($row['tipo_match'] ?? ''); @endphp
                            <span class="badge {{ $tipoMatch === 'sin_match' ? 'bg-danger' : ($tipoMatch === 'solo_paciente' ? 'bg-warning text-dark' : 'bg-info text-dark') }}">
                                {{ $tipoMatch }}
                            </span>
                        </td>
                        <td>{{ (string)($row['sede'] ?? '') }}</td>
                        <td>{{ (string)($row['afiliacion'] ?? '') }}</td>
                        <td>{{ (string)($row['doctor'] ?? '') }}</td>
                        <td>{{ (string)($row['paciente'] ?? '') }}</td>
                        <td>{{ (string)($row['producto_receta'] ?? '') }}</td>
                        <td>{{ (string)($row['producto_factura'] ?? '—') }}</td>
                        <td>{{ (string)($row['monto_linea_neto'] ?? '—') }}</td>
                        <td>{{ (string)($row['descuentos'] ?? '—') }}</td>
                        <td>{{ (string)($row['departamento_factura'] ?? '—') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="text-muted text-center">Sin incidencias de conciliación para los filtros actuales.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>

<style>
    .farmacia-kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: .75rem; }
    .farmacia-kpi-card { border: 1px solid #e7ebf0; border-radius: .75rem; padding: .75rem .9rem; background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%); }
    .farmacia-kpi-label { font-size: .78rem; color: #6c757d; text-transform: uppercase; letter-spacing: .04em; }
    .farmacia-kpi-value { font-size: 1.35rem; font-weight: 700; color: #0b4f9c; }
    .farmacia-kpi-hint { font-size: .8rem; color: #6c757d; }
    .farmacia-chart-card { border: 1px solid #e7ebf0; border-radius: .75rem; padding: .75rem .9rem; background: #fff; }
    .farmacia-chart-title { font-size: .9rem; margin-bottom: .5rem; color: #34495e; }
    .farmacia-chart-wrap { position: relative; height: 290px; max-height: 290px; }
    .farmacia-chart-wrap canvas { width: 100% !important; }
    .farmacia-data-table thead th { background: #eef5ff; color: #244a73; border-bottom: 1px solid #d3e1f1; white-space: nowrap; }
    .farmacia-data-table tbody td { vertical-align: middle; }
    .farmacia-data-table tbody tr:hover { background: #f8fbff; }
    div.dataTables_wrapper div.dataTables_filter input { border-radius: .5rem; border: 1px solid #d6dde6; padding: .25rem .5rem; }
    div.dataTables_wrapper div.dataTables_length select { border-radius: .5rem; border: 1px solid #d6dde6; padding: .2rem 1.8rem .2rem .45rem; }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current,
    .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover { background: #0b4f9c !important; color: #fff !important; border: 1px solid #0b4f9c !important; }
</style>
@endsection

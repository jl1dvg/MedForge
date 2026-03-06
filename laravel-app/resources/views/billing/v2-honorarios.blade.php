@extends('layouts.medforge')

@php
    $cirujanosList = is_array($cirujanos ?? null) ? $cirujanos : [];
@endphp

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
    <style>
        .honorarios-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .honorarios-grid {
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

        .chart-container { min-height: 280px; }
    </style>
@endpush

@section('content')
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title">Honorarios médicos v2</h3>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                            <li class="breadcrumb-item"><a href="/v2/billing">Billing</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Honorarios</li>
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
        <div class="honorarios-header">
            <div>
                <h4 class="mb-1">Producción quirúrgica por cirujano</h4>
                <div class="text-muted">Consulta sobre datos migrados vía endpoint v2.</div>
            </div>
            <div class="ms-auto d-flex flex-wrap gap-2">
                <input type="text" class="form-control" id="honorarios-range-input" placeholder="Selecciona rango" autocomplete="off" style="min-width: 260px;">
                <select class="form-select" id="honorarios-cirujano" style="min-width: 210px;">
                    <option value="">Todos los cirujanos</option>
                    @foreach($cirujanosList as $cirujano)
                        <option value="{{ $cirujano }}">{{ $cirujano }}</option>
                    @endforeach
                </select>
                <select class="form-select" id="honorarios-afiliacion" style="min-width: 180px;">
                    <option value="">Todas las afiliaciones</option>
                    <option value="IESS">IESS</option>
                    <option value="ISSFA">ISSFA</option>
                    <option value="ISSPOL">ISSPOL</option>
                    <option value="Particular">Particular</option>
                    <option value="Sin afiliación">Sin afiliación</option>
                </select>
                <button type="button" class="btn btn-primary" id="honorarios-refresh"><i class="mdi mdi-refresh"></i> Actualizar</button>
            </div>
        </div>

        <div class="box mb-15">
            <div class="box-body">
                <div class="row g-2">
                    <div class="col-md-3"><label class="form-label">IESS (%)</label><input type="number" class="form-control" value="30" min="0" max="100" step="0.5" data-rule-key="IESS"></div>
                    <div class="col-md-3"><label class="form-label">ISSFA (%)</label><input type="number" class="form-control" value="35" min="0" max="100" step="0.5" data-rule-key="ISSFA"></div>
                    <div class="col-md-3"><label class="form-label">ISSPOL (%)</label><input type="number" class="form-control" value="35" min="0" max="100" step="0.5" data-rule-key="ISSPOL"></div>
                    <div class="col-md-3"><label class="form-label">Default (%)</label><input type="number" class="form-control" value="30" min="0" max="100" step="0.5" data-rule-key="DEFAULT"></div>
                </div>
            </div>
        </div>

        <div class="honorarios-grid">
            <div class="metric-card"><h6>Total casos</h6><div class="metric-value" id="metric-casos">—</div><div class="metric-subtext">Cirugías con procedimientos</div></div>
            <div class="metric-card"><h6>Procedimientos</h6><div class="metric-value" id="metric-procedimientos">—</div><div class="metric-subtext">Cantidad total</div></div>
            <div class="metric-card"><h6>Producción quirúrgica</h6><div class="metric-value" id="metric-produccion">—</div><div class="metric-subtext">Facturado procedimientos</div></div>
            <div class="metric-card"><h6>Honorarios estimados</h6><div class="metric-value" id="metric-honorarios">—</div><div class="metric-subtext">Aplicando reglas</div></div>
            <div class="metric-card"><h6>Ticket promedio</h6><div class="metric-value" id="metric-ticket">—</div><div class="metric-subtext">Producción por caso</div></div>
            <div class="metric-card"><h6>Honorario promedio</h6><div class="metric-value" id="metric-honorario-promedio">—</div><div class="metric-subtext">Honorario por caso</div></div>
        </div>

        <div class="row">
            <div class="col-xl-6 col-12"><div class="box"><div class="box-header with-border"><h4 class="box-title">Producción por afiliación</h4></div><div class="box-body"><div id="chart-honorarios-afiliacion" class="chart-container"></div></div></div></div>
            <div class="col-xl-6 col-12"><div class="box"><div class="box-header with-border"><h4 class="box-title">Producción por cirujano</h4></div><div class="box-body"><div id="chart-honorarios-cirujano" class="chart-container"></div></div></div></div>
        </div>

        <div class="row">
            <div class="col-xl-6 col-12"><div class="box"><div class="box-header with-border"><h4 class="box-title">Top procedimientos</h4></div><div class="box-body"><div id="chart-honorarios-procedimientos" class="chart-container"></div></div></div></div>
            <div class="col-xl-6 col-12">
                <div class="box">
                    <div class="box-header with-border"><h4 class="box-title">Detalle por cirujano</h4></div>
                    <div class="box-body table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="bg-primary-light"><tr><th>Cirujano</th><th class="text-end">Casos</th><th class="text-end">Procedimientos</th><th class="text-end">Producción</th><th class="text-end">Honorarios</th></tr></thead>
                            <tbody id="table-honorarios"><tr><td colspan="5" class="text-center text-muted">Sin datos</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script src="/assets/vendor_components/apexcharts-bundle/dist/apexcharts.js"></script>
    <script src="/js/pages/billing/v2-honorarios.js"></script>
@endpush

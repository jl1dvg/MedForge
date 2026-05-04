@extends('layouts.medforge')

@php
    $doctoresList = is_array($doctores ?? null) ? $doctores : [];
    $afiliacionCategoriaOptions = is_array($afiliacionCategoriaOptions ?? null) ? $afiliacionCategoriaOptions : [];
    $empresaSeguroOptions = is_array($empresaSeguroOptions ?? null) ? $empresaSeguroOptions : [];
    $seguroOptions = is_array($seguroOptions ?? null) ? $seguroOptions : [];
@endphp

@push('styles')
    @unless (\App\Modules\Shared\Support\MedforgeAssets::hasViteBuild())
        <link rel="stylesheet" href="/assets/vendor_components/bootstrap-daterangepicker/daterangepicker.css">
        <link rel="stylesheet" href="/assets/vendor_components/datatable/datatables.min.css">
    @endunless
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

        .chart-container {
            min-height: 280px;
        }

        .honorarios-filter {
            min-width: 190px;
        }

        .honorarios-filter .form-label {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            margin-bottom: 0.25rem;
            font-size: 0.78rem;
            font-weight: 600;
            color: #475569;
        }

        .honorarios-filter .form-select,
        .honorarios-filter .form-control {
            min-width: 100%;
        }

        .honorarios-filter select[multiple] {
            min-height: 72px;
        }

        .honorarios-table-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }

        .honorarios-quick-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
        }

        .honorarios-quick-filter {
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #334155;
            border-radius: 999px;
            padding: 0.32rem 0.7rem;
            font-size: 0.78rem;
            font-weight: 600;
            line-height: 1;
        }

        .honorarios-quick-filter.active {
            border-color: #0ea5e9;
            background: #e0f2fe;
            color: #0369a1;
        }

        .honorarios-visible-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            font-size: 0.78rem;
            color: #475569;
        }

        .honorarios-visible-summary span {
            border: 1px solid #e2e8f0;
            border-radius: 999px;
            padding: 0.3rem 0.65rem;
            background: #f8fafc;
        }

        .honorarios-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            border-radius: 999px;
            padding: 0.24rem 0.55rem;
            font-size: 0.74rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .honorarios-badge-success { background: #dcfce7; color: #166534; }
        .honorarios-badge-warning { background: #fef3c7; color: #92400e; }
        .honorarios-badge-muted { background: #f1f5f9; color: #475569; }
        .honorarios-badge-danger { background: #fee2e2; color: #991b1b; }

        #honorarios-table.dataTable tbody tr.honorarios-row-alert {
            --bs-table-accent-bg: #fff7ed;
        }

        #honorarios-table {
            font-size: 0.78rem;
        }

        #honorarios-table thead th {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            white-space: nowrap;
        }

        #honorarios-table tbody td {
            padding-top: 0.45rem;
            padding-bottom: 0.45rem;
            vertical-align: top;
        }

        #honorarios-table small {
            font-size: 0.68rem;
        }

        #honorarios-table .honorarios-badge {
            font-size: 0.68rem;
        }
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
                            <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a>
                            </li>
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
                <h4 class="mb-1">Producción por doctor</h4>
            </div>
            <div class="ms-auto" style="width:100%;">
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:10px;">
                    <div class="honorarios-filter">
                        <label for="honorarios-range-input" class="form-label"><i class="mdi mdi-calendar-range"></i>
                            Rango</label>
                        <input type="text" class="form-control" id="honorarios-range-input"
                               placeholder="Selecciona rango" autocomplete="off">
                    </div>

                    <div class="honorarios-filter">
                        <label for="honorarios-doctor" class="form-label"><i class="mdi mdi-doctor"></i> Doctor</label>
                        <select class="form-select" id="honorarios-doctor"
                                data-server-options-count="{{ count($doctoresList) }}">
                            <option value="">Todos los doctores</option>
                            @foreach($doctoresList as $doctor)
                                <option
                                    value="{{ (string) ($doctor['value'] ?? '') }}">{{ (string) ($doctor['label'] ?? '') }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="honorarios-filter">
                        <label for="honorarios-sede" class="form-label"><i class="mdi mdi-hospital-building"></i> Sede</label>
                        <select class="form-select" id="honorarios-sede">
                            <option value="">Todas las sedes</option>
                            <option value="MATRIZ">MATRIZ</option>
                            <option value="CEIBOS">CEIBOS</option>
                        </select>
                    </div>

                    <div class="honorarios-filter">
                        <label for="honorarios-tipo" class="form-label"><i
                                class="mdi mdi-format-list-bulleted-type"></i> Tipo</label>
                        <select class="form-select" id="honorarios-tipo" multiple size="3">
                            <option value="cirugias">Cirugías</option>
                            <option value="imagenes">Imágenes</option>
                            <option value="pni">PNI</option>
                            <option value="servicios_oftalmologicos">Servicios</option>
                        </select>
                    </div>

                    <div class="honorarios-filter">
                        <label for="honorarios-categoria" class="form-label"><i
                                class="mdi mdi-account-group-outline"></i> Categoría</label>
                        <select class="form-select" id="honorarios-categoria" multiple size="3">
                            @foreach($afiliacionCategoriaOptions as $option)
                                @php $value = (string) ($option['value'] ?? ''); @endphp
                                @if($value !== '')
                                    <option value="{{ $value }}">{{ (string) ($option['label'] ?? '') }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    <div class="honorarios-filter">
                        <label for="honorarios-empresa-seguro" class="form-label"><i class="mdi mdi-domain"></i> Empresa</label>
                        <select class="form-select" id="honorarios-empresa-seguro" multiple size="3">
                            @foreach($empresaSeguroOptions as $option)
                                @php
                                    $value = (string) ($option['value'] ?? '');
                                    $categorias = is_array($option['categorias'] ?? null) ? $option['categorias'] : [];
                                @endphp
                                @if($value !== '')
                                    <option value="{{ $value }}"
                                            data-categorias="{{ implode(',', array_map('strval', $categorias)) }}">{{ (string) ($option['label'] ?? '') }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    <div class="honorarios-filter">
                        <label for="honorarios-seguro" class="form-label"><i class="mdi mdi-shield-check-outline"></i>
                            Seguro</label>
                        <select class="form-select" id="honorarios-seguro" multiple size="3">
                            @foreach($seguroOptions as $option)
                                @php $value = (string) ($option['value'] ?? ''); @endphp
                                @if($value !== '')
                                    <option value="{{ $value }}"
                                            data-categoria="{{ (string) ($option['categoria'] ?? '') }}"
                                            data-empresa="{{ (string) ($option['empresa_seguro'] ?? '') }}">{{ (string) ($option['label'] ?? '') }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    <div class="d-flex align-items-end gap-2">
                        <a class="btn btn-outline-primary" href="/v2/billing/honorarios/settings" title="Configurar reglas de honorarios">
                            <i class="mdi mdi-cog-outline"></i>
                        </a>
                        <button type="button" class="btn btn-primary w-100" id="honorarios-refresh">
                            <i class="mdi mdi-refresh"></i> Actualizar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="honorarios-grid">
            <div class="metric-card"><h6>Total casos</h6>
                <div class="metric-value" id="metric-casos">—</div>
                <div class="metric-subtext">Cirugías con procedimientos</div>
            </div>
            <div class="metric-card"><h6>Procedimientos</h6>
                <div class="metric-value" id="metric-procedimientos">—</div>
                <div class="metric-subtext">Cantidad total</div>
            </div>
            <div class="metric-card"><h6>Producción quirúrgica</h6>
                <div class="metric-value" id="metric-produccion">—</div>
                <div class="metric-subtext">Facturado procedimientos</div>
            </div>
            <div class="metric-card"><h6>Honorarios estimados</h6>
                <div class="metric-value" id="metric-honorarios">—</div>
                <div class="metric-subtext">Aplicando reglas</div>
            </div>
            <div class="metric-card"><h6>Ticket promedio</h6>
                <div class="metric-value" id="metric-ticket">—</div>
                <div class="metric-subtext">Producción por caso</div>
            </div>
            <div class="metric-card"><h6>Honorario promedio</h6>
                <div class="metric-value" id="metric-honorario-promedio">—</div>
                <div class="metric-subtext">Honorario por caso</div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-4 col-12">
                <div class="box">
                    <div class="box-header with-border"><h4 class="box-title">Producción por afiliación</h4></div>
                    <div class="box-body">
                        <div id="chart-honorarios-afiliacion" class="chart-container"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-12">
                <div class="box">
                    <div class="box-header with-border"><h4 class="box-title">Producción por doctor</h4></div>
                    <div class="box-body">
                        <div id="chart-honorarios-cirujano" class="chart-container"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-12">
                <div class="box">
                    <div class="box-header with-border"><h4 class="box-title">Top procedimientos</h4></div>
                    <div class="box-body">
                        <div id="chart-honorarios-procedimientos" class="chart-container"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-12 col-12">
                <div class="box">
                    <div class="box-header with-border"><h4 class="box-title">Detalle por doctor</h4></div>
                    <div class="box-body table-responsive">
                        <div class="honorarios-table-toolbar">
                            <div class="honorarios-quick-filters" id="honorarios-table-filters">
                                <button type="button" class="honorarios-quick-filter active" data-filter="all">Todas</button>
                                <button type="button" class="honorarios-quick-filter" data-filter="facturadas">Facturadas</button>
                                <button type="button" class="honorarios-quick-filter" data-filter="pendientes">Pendientes</button>
                                <button type="button" class="honorarios-quick-filter" data-filter="con_honorario">Con honorario</button>
                                <button type="button" class="honorarios-quick-filter" data-filter="honorario_cero">Honorario $0</button>
                            </div>
                            <div class="honorarios-visible-summary" id="honorarios-visible-summary">
                                <span>Filas: 0</span>
                                <span>Recolectado: $0,00</span>
                                <span>Honorarios: $0,00</span>
                                <span>Pendientes: 0</span>
                            </div>
                        </div>
                        <table class="table table-striped table-hover mb-0" id="honorarios-table">
                            <thead class="bg-primary-light">
                            <tr>
                                <th>Médico</th>
                                <th>Tipo</th>
                                <th class="text-end">Casos</th>
                                <th class="text-end">Procedimientos</th>
                                <th class="text-end">Producción</th>
                                <th class="text-end">Honorarios</th>
                            </tr>
                            </thead>
                            <tbody id="table-honorarios">
                            <tr>
                                <td colspan="6" class="text-center text-muted">Sin datos</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        window.medforgeHonorariosDoctorDebug = {
            serverOptionsCount: {{ count($doctoresList) }},
            serverOptionsSample: @json(array_slice($doctoresList, 0, 5)),
        };
        console.info('[Honorarios] doctores enviados por Blade', window.medforgeHonorariosDoctorDebug);
    </script>
    @if (\App\Modules\Shared\Support\MedforgeAssets::hasViteBuild())
        @vite('resources/js/v2/billing-honorarios.js')
    @else
        <script src="/assets/vendor_components/moment/moment.js"></script>
        <script src="/assets/vendor_components/bootstrap-daterangepicker/daterangepicker.js"></script>
        <script src="/assets/vendor_components/apexcharts-bundle/dist/apexcharts.js"></script>
        <script src="/assets/vendor_components/datatable/datatables.min.js"></script>
        <script src="/js/pages/shared/datatables-language-es.js"></script>
        <script src="/js/pages/billing/v2-honorarios.js?v=20260503-honorarios-datatable"></script>
    @endif
@endpush

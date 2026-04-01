@extends('layouts.medforge')

@php
    $filters = is_array($filters ?? null) ? $filters : [];
    $catalogos = is_array($catalogos ?? null) ? $catalogos : ['categorias_madre_referido' => []];
    $selectedCategory = strtoupper(trim((string) ($selectedCategory ?? 'MARKETING')));
    if ($selectedCategory === '') {
        $selectedCategory = 'MARKETING';
    }

    $dashboard = is_array($dashboard ?? null) ? $dashboard : [];
    $selected = is_array($dashboard['selected'] ?? null) ? $dashboard['selected'] : [];
    $overall = is_array($dashboard['overall'] ?? null) ? $dashboard['overall'] : [];
    $comparison = is_array($dashboard['comparison'] ?? null) ? $dashboard['comparison'] : [];
    $comparisonChart = is_array($dashboard['comparison_chart'] ?? null) ? $dashboard['comparison_chart'] : ['labels' => [], 'atenciones' => [], 'usd' => [], 'ticket' => [], 'cero' => []];
    $trend = is_array($dashboard['trend'] ?? null) ? $dashboard['trend'] : ['labels' => [], 'counts' => [], 'usd' => []];
    $classification = is_array($dashboard['classification'] ?? null) ? $dashboard['classification'] : ['label' => 'DESEMPEÑO MIXTO', 'tone' => 'secondary', 'reason' => 'Sin suficientes datos'];
    $hallazgos = is_array($dashboard['hallazgos'] ?? null) ? $dashboard['hallazgos'] : [];
    $opportunities = is_array($dashboard['opportunities'] ?? null) ? $dashboard['opportunities'] : [];
    $automaticInsights = is_array($dashboard['automatic_insights'] ?? null) ? $dashboard['automatic_insights'] : [];
    $budget = is_array($dashboard['budget'] ?? null) ? $dashboard['budget'] : null;

    $dateFromSeleccionado = trim((string) ($filters['date_from'] ?? ''));
    $dateToSeleccionado = trim((string) ($filters['date_to'] ?? ''));
    $empresaSeguroSeleccionada = trim((string) ($filters['empresa_seguro'] ?? ''));
    $afiliacionSeleccionada = strtolower(trim((string) ($filters['afiliacion'] ?? '')));
    $sedeSeleccionada = strtoupper(trim((string) ($filters['sede'] ?? '')));
    $categoriaClienteSeleccionada = strtolower(trim((string) ($filters['categoria_cliente'] ?? '')));
    $tipoSeleccionado = strtoupper(trim((string) ($filters['tipo'] ?? '')));
    $procedimientoSeleccionado = trim((string) ($filters['procedimiento'] ?? ''));

    $baseQuery = array_filter([
        'date_from' => $dateFromSeleccionado,
        'date_to' => $dateToSeleccionado,
        'empresa_seguro' => $empresaSeguroSeleccionada,
        'categoria_cliente' => $categoriaClienteSeleccionada,
        'tipo' => $tipoSeleccionado,
        'sede' => $sedeSeleccionada,
        'afiliacion' => $afiliacionSeleccionada,
        'procedimiento' => $procedimientoSeleccionado,
    ], static fn($value): bool => trim((string) $value) !== '');

    $backQuery = $baseQuery;
    $backQuery['categoria_madre_referido'] = $selectedCategory;
    $backUrl = '/v2/informes/particulares?' . http_build_query($backQuery);

    $categories = [];
    foreach ((array) ($catalogos['categorias_madre_referido'] ?? []) as $category) {
        $normalized = strtoupper(trim((string) $category));
        if ($normalized === '' || isset($categories[$normalized])) {
            continue;
        }
        $categories[$normalized] = $normalized;
    }
    $categories[$selectedCategory] = $selectedCategory;
    ksort($categories);

    $categoryUrl = static function (string $category) use ($baseQuery): string {
        $query = $baseQuery;
        $query['categoria_referido'] = $category;
        return '/v2/informes/particulares/referidos?' . http_build_query($query);
    };

    $toneClasses = [
        'success' => 'bg-success-light text-success',
        'warning' => 'bg-warning-light text-warning',
        'danger' => 'bg-danger-light text-danger',
        'info' => 'bg-info-light text-info',
        'secondary' => 'bg-secondary-light text-secondary',
    ];
    $classificationToneClass = $toneClasses[(string) ($classification['tone'] ?? 'secondary')] ?? $toneClasses['secondary'];
@endphp

@section('content')
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title"><i class="mdi mdi-chart-bar"></i> Informe Estratégico de {{ $selectedCategory }}</h3>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/v2/billing"><i class="mdi mdi-home-outline"></i></a></li>
                            <li class="breadcrumb-item"><a href="{{ $backUrl }}">Informe particulares</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Referidos: {{ $selectedCategory }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ $backUrl }}" class="btn btn-outline-primary btn-sm">Volver al informe</a>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="row mb-10">
            <div class="col-12">
                <div class="d-flex flex-wrap gap-2">
                    @foreach($categories as $category)
                        <a href="{{ $categoryUrl($category) }}"
                           class="btn btn-sm {{ $category === $selectedCategory ? 'btn-primary' : 'btn-outline-primary' }}">
                            {{ $category }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-8 col-12">
                <div class="box">
                    <div class="box-header with-border d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="box-title mb-0">Resumen Ejecutivo</h5>
                        <span class="badge {{ $classificationToneClass }}">{{ $classification['label'] ?? 'DESEMPEÑO MIXTO' }}</span>
                    </div>
                    <div class="box-body">
                        <p class="mb-15">
                            {{ $selectedCategory }} aporta <strong>{{ number_format((float) ($selected['pacientes_unicos'] ?? 0)) }}</strong>
                            pacientes únicos, <strong>${{ number_format((float) ($selected['usd_total'] ?? 0), 2) }}</strong>
                            de producción real y un ticket promedio de
                            <strong>${{ number_format((float) ($selected['ticket_promedio'] ?? 0), 2) }}</strong>.
                        </p>
                        <p class="text-muted mb-20">{{ $classification['reason'] ?? '' }}</p>
                        <div class="row">
                            <div class="col-md-6 col-12">
                                <h6 class="fw-600">Hallazgos clave</h6>
                                <ul class="mb-0 ps-20">
                                    @forelse($hallazgos as $item)
                                        <li class="mb-5">{{ $item }}</li>
                                    @empty
                                        <li>Sin hallazgos relevantes para el período.</li>
                                    @endforelse
                                </ul>
                            </div>
                            <div class="col-md-6 col-12">
                                <h6 class="fw-600">Oportunidades</h6>
                                <ul class="mb-0 ps-20">
                                    @forelse($opportunities as $item)
                                        <li class="mb-5">{{ $item }}</li>
                                    @empty
                                        <li>Sin oportunidades detectadas con los datos actuales.</li>
                                    @endforelse
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-12">
                <div class="box">
                    <div class="box-header with-border">
                        <h5 class="box-title mb-0">Contexto de análisis</h5>
                    </div>
                    <div class="box-body">
                        <table class="table table-sm mb-0">
                            <tbody>
                            <tr>
                                <th class="ps-0">Período</th>
                                <td class="text-end pe-0">{{ $dateFromSeleccionado !== '' ? $dateFromSeleccionado : 'N/D' }} a {{ $dateToSeleccionado !== '' ? $dateToSeleccionado : 'N/D' }}</td>
                            </tr>
                            <tr>
                                <th class="ps-0">Sede</th>
                                <td class="text-end pe-0">{{ $sedeSeleccionada !== '' ? $sedeSeleccionada : 'TODAS' }}</td>
                            </tr>
                            <tr>
                                <th class="ps-0">Empresa</th>
                                <td class="text-end pe-0">{{ $empresaSeguroSeleccionada !== '' ? strtoupper($empresaSeguroSeleccionada) : 'TODAS' }}</td>
                            </tr>
                            <tr>
                                <th class="ps-0">Categoría cliente</th>
                                <td class="text-end pe-0">{{ $categoriaClienteSeleccionada !== '' ? strtoupper($categoriaClienteSeleccionada) : 'TODAS' }}</td>
                            </tr>
                            <tr>
                                <th class="ps-0">Tipo atención</th>
                                <td class="text-end pe-0">{{ $tipoSeleccionado !== '' ? $tipoSeleccionado : 'TODOS' }}</td>
                            </tr>
                            </tbody>
                        </table>
                        @if(is_array($budget))
                            <div class="alert alert-info mt-15 mb-0">
                                <strong>Presupuesto marketing</strong><br>
                                Anual {{ $budget['scope'] ?? 'N/D' }}:
                                ${{ number_format((float) ($budget['annual_budget'] ?? 0), 2) }}<br>
                                Prorrateado {{ (int) ($budget['days'] ?? 0) }} días:
                                ${{ number_format((float) ($budget['period_budget'] ?? 0), 2) }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-2 col-md-4 col-12">
                <div class="box">
                    <div class="box-body text-center">
                        <h6 class="mb-5">Pacientes</h6>
                        <div class="fs-28 fw-700 text-primary">{{ number_format((float) ($selected['pacientes_unicos'] ?? 0)) }}</div>
                        <small class="text-muted">{{ number_format((float) ($selected['pacientes_share'] ?? 0), 2) }}% del total</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-12">
                <div class="box">
                    <div class="box-body text-center">
                        <h6 class="mb-5">Atenciones</h6>
                        <div class="fs-28 fw-700 text-primary">{{ number_format((float) ($selected['atenciones'] ?? 0)) }}</div>
                        <small class="text-muted">{{ number_format((float) ($selected['atenciones_share'] ?? 0), 2) }}% del total</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-12">
                <div class="box">
                    <div class="box-body text-center">
                        <h6 class="mb-5">USD real</h6>
                        <div class="fs-28 fw-700 text-success">${{ number_format((float) ($selected['usd_total'] ?? 0), 2) }}</div>
                        <small class="text-muted">{{ number_format((float) ($selected['usd_share'] ?? 0), 2) }}% del total</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-12">
                <div class="box">
                    <div class="box-body text-center">
                        <h6 class="mb-5">Ticket prom.</h6>
                        <div class="fs-28 fw-700 text-dark">${{ number_format((float) ($selected['ticket_promedio'] ?? 0), 2) }}</div>
                        <small class="text-muted">Clínica: ${{ number_format((float) ($selected['overall_ticket_promedio'] ?? 0), 2) }}</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-12">
                <div class="box">
                    <div class="box-body text-center">
                        <h6 class="mb-5">% atenciones 0</h6>
                        <div class="fs-28 fw-700 text-danger">{{ number_format((float) ($selected['tasa_cero'] ?? 0), 2) }}%</div>
                        <small class="text-muted">{{ number_format((float) ($selected['atenciones_cero'] ?? 0)) }} sin valor</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-12">
                <div class="box">
                    <div class="box-body text-center">
                        <h6 class="mb-5">{{ is_array($budget) ? 'ROI' : 'Retorno' }}</h6>
                        <div class="fs-28 fw-700 {{ is_array($budget) && (float) ($budget['roi_pct'] ?? 0) < 0 ? 'text-danger' : 'text-info' }}">
                            @if(is_array($budget))
                                {{ number_format((float) ($budget['roi_pct'] ?? 0), 2) }}%
                            @else
                                {{ number_format((float) ($selected['tasa_retorno'] ?? 0), 2) }}%
                            @endif
                        </div>
                        <small class="text-muted">
                            @if(is_array($budget))
                                retorno sobre gasto prorrateado
                            @else
                                pacientes recurrentes
                            @endif
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-7 col-12">
                <div class="box">
                    <div class="box-header with-border">
                        <h5 class="box-title mb-0">Métricas clave</h5>
                    </div>
                    <div class="box-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0">
                                <tbody>
                                <tr><th>Volumen</th><td class="text-end">{{ number_format((float) ($selected['atenciones'] ?? 0)) }} atenciones</td></tr>
                                <tr><th>Producción económica</th><td class="text-end">${{ number_format((float) ($selected['usd_total'] ?? 0), 2) }}</td></tr>
                                <tr><th>Ticket promedio</th><td class="text-end">${{ number_format((float) ($selected['ticket_promedio'] ?? 0), 2) }}</td></tr>
                                <tr><th>Tasa de atenciones sin valor</th><td class="text-end">{{ number_format((float) ($selected['tasa_cero'] ?? 0), 2) }}%</td></tr>
                                <tr><th>Facturación real</th><td class="text-end">{{ number_format((float) ($selected['facturacion_rate'] ?? 0), 2) }}%</td></tr>
                                <tr><th>Conversión a procedimiento</th><td class="text-end">{{ number_format((float) ($selected['conversion_procedimiento'] ?? 0), 2) }}%</td></tr>
                                <tr><th>Conversión a cirugía</th><td class="text-end">{{ number_format((float) ($selected['conversion_cirugia'] ?? 0), 2) }}%</td></tr>
                                <tr><th>Nuevos vs recurrentes</th><td class="text-end">{{ number_format((float) ($selected['nuevos'] ?? 0)) }} / {{ number_format((float) ($selected['recurrentes'] ?? 0)) }}</td></tr>
                                <tr><th>Tasa de retorno</th><td class="text-end">{{ number_format((float) ($selected['tasa_retorno'] ?? 0), 2) }}%</td></tr>
                                <tr><th>LTV observado</th><td class="text-end">${{ number_format((float) ($selected['ltv'] ?? 0), 2) }}</td></tr>
                                <tr><th>Pacientes sin valor</th><td class="text-end">{{ number_format((float) ($selected['pacientes_sin_valor_rate'] ?? 0), 2) }}%</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-5 col-12">
                <div class="box">
                    <div class="box-header with-border">
                        <h5 class="box-title mb-0">Insights automáticos</h5>
                    </div>
                    <div class="box-body">
                        <ul class="mb-0 ps-20">
                            @forelse($automaticInsights as $item)
                                <li class="mb-10">{{ $item }}</li>
                            @empty
                                <li>No se generaron insights automáticos para el período actual.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-7 col-12">
                <div class="box">
                    <div class="box-header with-border">
                        <h5 class="box-title mb-0">Comparativa estratégica</h5>
                    </div>
                    <div class="box-body">
                        <div id="referralStrategicComparisonChart" style="min-height: 340px;"></div>
                        <div class="table-responsive mt-20">
                            <table class="table table-sm table-striped mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>Fuente</th>
                                    <th class="text-end">Pacientes</th>
                                    <th class="text-end">Atenciones</th>
                                    <th class="text-end">USD total</th>
                                    <th class="text-end">Ticket</th>
                                    <th class="text-end">Facturación</th>
                                    <th class="text-end">% 0</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($comparison as $item)
                                    <tr>
                                        <td>{{ $item['label'] ?? 'N/D' }}</td>
                                        <td class="text-end">{{ number_format((float) ($item['pacientes_unicos'] ?? 0)) }}</td>
                                        <td class="text-end">{{ number_format((float) ($item['atenciones'] ?? 0)) }}</td>
                                        <td class="text-end">${{ number_format((float) ($item['usd_total'] ?? 0), 2) }}</td>
                                        <td class="text-end">${{ number_format((float) ($item['ticket_promedio'] ?? 0), 2) }}</td>
                                        <td class="text-end">{{ number_format((float) ($item['facturacion_rate'] ?? 0), 2) }}%</td>
                                        <td class="text-end">{{ number_format((float) ($item['tasa_cero'] ?? 0), 2) }}%</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">Sin datos comparativos para el rango seleccionado.</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-5 col-12">
                <div class="box">
                    <div class="box-header with-border">
                        <h5 class="box-title mb-0">Riesgo operativo</h5>
                    </div>
                    <div class="box-body">
                        <div id="referralStrategicRiskChart" style="min-height: 340px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="box">
                    <div class="box-header with-border">
                        <h5 class="box-title mb-0">Evolución mensual</h5>
                    </div>
                    <div class="box-body">
                        <div id="referralStrategicTrendChart" style="min-height: 360px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        (function () {
            function renderReferralStrategicCharts() {
                if (typeof ApexCharts === 'undefined') {
                    return;
                }

                var comparisonData = @json($comparisonChart);
                var trendData = @json($trend);

                var comparisonTarget = document.querySelector('#referralStrategicComparisonChart');
                if (comparisonTarget) {
                    comparisonTarget.innerHTML = '';
                    new ApexCharts(comparisonTarget, {
                        chart: {
                            type: 'line',
                            height: 340,
                            toolbar: {show: false}
                        },
                        series: [
                            {
                                name: 'Atenciones',
                                type: 'column',
                                data: comparisonData.atenciones || []
                            },
                            {
                                name: 'Ticket prom.',
                                type: 'line',
                                data: comparisonData.ticket || []
                            }
                        ],
                        colors: ['#2563eb', '#0f766e'],
                        stroke: {
                            width: [0, 3],
                            curve: 'smooth'
                        },
                        dataLabels: {
                            enabled: true,
                            enabledOnSeries: [0]
                        },
                        xaxis: {
                            categories: comparisonData.labels || []
                        },
                        yaxis: [
                            {
                                title: {text: 'Atenciones'}
                            },
                            {
                                opposite: true,
                                title: {text: 'Ticket prom.'},
                                labels: {
                                    formatter: function (value) {
                                        return '$' + Number(value || 0).toFixed(0);
                                    }
                                }
                            }
                        ],
                        tooltip: {
                            shared: true,
                            intersect: false,
                            y: {
                                formatter: function (value, context) {
                                    if (context.seriesIndex === 1) {
                                        return '$' + Number(value || 0).toFixed(2);
                                    }
                                    return Number(value || 0).toFixed(0) + ' atenciones';
                                }
                            }
                        },
                        legend: {
                            position: 'top'
                        }
                    }).render();
                }

                var riskTarget = document.querySelector('#referralStrategicRiskChart');
                if (riskTarget) {
                    riskTarget.innerHTML = '';
                    new ApexCharts(riskTarget, {
                        chart: {
                            type: 'bar',
                            height: 340,
                            toolbar: {show: false}
                        },
                        series: [
                            {
                                name: '% 0',
                                data: comparisonData.cero || []
                            }
                        ],
                        plotOptions: {
                            bar: {
                                horizontal: true,
                                borderRadius: 4
                            }
                        },
                        colors: ['#dc2626'],
                        dataLabels: {
                            enabled: true,
                            formatter: function (value) {
                                return Number(value || 0).toFixed(1) + '%';
                            }
                        },
                        xaxis: {
                            categories: comparisonData.labels || [],
                            labels: {
                                formatter: function (value) {
                                    return Number(value || 0).toFixed(0) + '%';
                                }
                            }
                        },
                        tooltip: {
                            y: {
                                formatter: function (value) {
                                    return Number(value || 0).toFixed(2) + '%';
                                }
                            }
                        }
                    }).render();
                }

                var trendTarget = document.querySelector('#referralStrategicTrendChart');
                if (trendTarget) {
                    trendTarget.innerHTML = '';
                    new ApexCharts(trendTarget, {
                        chart: {
                            type: 'line',
                            height: 360,
                            toolbar: {show: false}
                        },
                        series: [
                            {
                                name: 'Atenciones',
                                type: 'column',
                                data: trendData.counts || []
                            },
                            {
                                name: 'USD',
                                type: 'line',
                                data: trendData.usd || []
                            }
                        ],
                        colors: ['#1d4ed8', '#059669'],
                        stroke: {
                            width: [0, 3],
                            curve: 'smooth'
                        },
                        xaxis: {
                            categories: trendData.labels || []
                        },
                        yaxis: [
                            {
                                title: {text: 'Atenciones'}
                            },
                            {
                                opposite: true,
                                title: {text: 'USD'},
                                labels: {
                                    formatter: function (value) {
                                        return '$' + Number(value || 0).toFixed(0);
                                    }
                                }
                            }
                        ],
                        tooltip: {
                            shared: true,
                            intersect: false,
                            y: {
                                formatter: function (value, context) {
                                    if (context.seriesIndex === 1) {
                                        return '$' + Number(value || 0).toFixed(2);
                                    }
                                    return Number(value || 0).toFixed(0) + ' atenciones';
                                }
                            }
                        },
                        legend: {
                            position: 'top'
                        }
                    }).render();
                }
            }

            function bootReferralStrategicCharts() {
                if (typeof ApexCharts !== 'undefined') {
                    renderReferralStrategicCharts();
                    return;
                }

                var script = document.createElement('script');
                script.src = '/assets/vendor_components/apexcharts-bundle/dist/apexcharts.js';
                script.onload = renderReferralStrategicCharts;
                document.head.appendChild(script);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bootReferralStrategicCharts);
            } else {
                bootReferralStrategicCharts();
            }
        })();
    </script>
@endpush

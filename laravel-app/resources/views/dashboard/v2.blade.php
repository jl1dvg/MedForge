@php
    $summaryData = is_array($summary['data'] ?? null) ? $summary['data'] : [];
    $meta = is_array($summary['meta'] ?? null) ? $summary['meta'] : [];
    $dateRange = is_array($date_range ?? null) ? $date_range : (is_array($meta['date_range'] ?? null) ? $meta['date_range'] : []);

    $patientsTotal = (int) ($summaryData['patients_total'] ?? 0);
    $usersTotal = (int) ($summaryData['users_total'] ?? 0);
    $protocolsTotal = (int) ($summaryData['protocols_total'] ?? 0);
    $cirugiasPeriodo = (int) ($summaryData['total_cirugias_periodo'] ?? 0);

    $procedimientosDia = is_array($summaryData['procedimientos_dia'] ?? null) ? $summaryData['procedimientos_dia'] : ['fechas' => [], 'totales' => []];
    $topProcedimientos = is_array($summaryData['top_procedimientos'] ?? null) ? $summaryData['top_procedimientos'] : ['membretes' => [], 'totales' => []];
    $revisionEstados = is_array($summaryData['revision_estados'] ?? null) ? $summaryData['revision_estados'] : ['incompletos' => 0, 'revisados' => 0, 'no_revisados' => 0];
    $solicitudesFunnel = is_array($summaryData['solicitudes_funnel'] ?? null) ? $summaryData['solicitudes_funnel'] : ['etapas' => [], 'totales' => [], 'prioridades' => []];
    $crmBacklog = is_array($summaryData['crm_backlog'] ?? null) ? $summaryData['crm_backlog'] : ['pendientes' => 0, 'completadas' => 0, 'vencidas' => 0, 'vencen_hoy' => 0, 'avance' => 0];

    $startInput = $startDate !== '' ? $startDate : (string) ($dateRange['start'] ?? '');
    $endInput = $endDate !== '' ? $endDate : (string) ($dateRange['end'] ?? '');
    $rangeLabel = (string) ($dateRange['label'] ?? 'Sin rango');

    $chartPayload = [
        'procedimientos_dia' => [
            'fechas' => array_values((array) ($procedimientosDia['fechas'] ?? [])),
            'totales' => array_values((array) ($procedimientosDia['totales'] ?? [])),
        ],
        'top_procedimientos' => [
            'membretes' => array_values((array) ($topProcedimientos['membretes'] ?? [])),
            'totales' => array_values((array) ($topProcedimientos['totales'] ?? [])),
        ],
        'revision_estados' => [
            'incompletos' => (int) ($revisionEstados['incompletos'] ?? 0),
            'revisados' => (int) ($revisionEstados['revisados'] ?? 0),
            'no_revisados' => (int) ($revisionEstados['no_revisados'] ?? 0),
        ],
        'solicitudes_funnel' => [
            'etapas' => (array) ($solicitudesFunnel['etapas'] ?? []),
            'totales' => (array) ($solicitudesFunnel['totales'] ?? []),
            'prioridades' => (array) ($solicitudesFunnel['prioridades'] ?? []),
        ],
        'crm_backlog' => [
            'pendientes' => (int) ($crmBacklog['pendientes'] ?? 0),
            'completadas' => (int) ($crmBacklog['completadas'] ?? 0),
            'vencidas' => (int) ($crmBacklog['vencidas'] ?? 0),
            'vencen_hoy' => (int) ($crmBacklog['vencen_hoy'] ?? 0),
            'avance' => (float) ($crmBacklog['avance'] ?? 0),
        ],
    ];

    $estadisticasAfiliacion = is_array($estadisticas_afiliacion ?? null) ? $estadisticas_afiliacion : ['afiliaciones' => ['No data'], 'totales' => [0]];
    $kpiCards = is_array($kpi_cards ?? null) ? $kpi_cards : [];
    $cirugiasRecientes = is_array($cirugias_recientes ?? null) ? $cirugias_recientes : [];
    $plantillasRecientes = is_array($plantillas ?? null) ? $plantillas : [];
    $diagnosticosFrecuentes = is_array($diagnosticos_frecuentes ?? null) ? $diagnosticos_frecuentes : [];
    $solicitudesQuirurgicas = is_array($solicitudes_quirurgicas ?? null) ? $solicitudes_quirurgicas : ['solicitudes' => [], 'total' => 0];
    $doctoresTop = is_array($doctores_top ?? null) ? $doctores_top : [];
    $aiSummary = is_array($ai_summary ?? null) ? $ai_summary : ['provider' => '', 'provider_configured' => false, 'features' => ['consultas_enfermedad' => false, 'consultas_plan' => false]];

    $fechasJson = json_encode($chartPayload['procedimientos_dia']['fechas'], JSON_UNESCAPED_UNICODE);
    $procedimientosDiaJson = json_encode($chartPayload['procedimientos_dia']['totales'], JSON_UNESCAPED_UNICODE);
    $membretesJson = json_encode($chartPayload['top_procedimientos']['membretes'], JSON_UNESCAPED_UNICODE);
    $procedimientosMembreteJson = json_encode($chartPayload['top_procedimientos']['totales'], JSON_UNESCAPED_UNICODE);
    $afiliacionesJson = json_encode(array_values((array) ($estadisticasAfiliacion['afiliaciones'] ?? [])), JSON_UNESCAPED_UNICODE);
    $procedimientosAfiliacionJson = json_encode(array_values((array) ($estadisticasAfiliacion['totales'] ?? []),), JSON_UNESCAPED_UNICODE);
    $solicitudesFunnelJson = json_encode($solicitudesFunnel, JSON_UNESCAPED_UNICODE);
    $crmBacklogJson = json_encode($crmBacklog, JSON_UNESCAPED_UNICODE);
    $revisionEstadosJson = json_encode($revisionEstados, JSON_UNESCAPED_UNICODE);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="icon" href="/images/favicon.ico">
    <title>MedForge - Dashboard</title>

    <link rel="stylesheet" href="/css/vendors_css.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="/css/horizontal-menu.css">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/skin_color.css">
    <style>
        .v2-shell .main-sidebar {
            width: 78px;
        }
        .v2-shell .content-wrapper {
            margin-left: 78px;
        }
        .v2-shell .logo-box {
            width: 78px;
        }
        .v2-shell .main-header .navbar {
            margin-left: 78px;
        }
        .v2-shell .mini-nav-label {
            font-size: 11px;
            color: #8696ad;
            text-align: center;
            margin-top: 6px;
        }
        .v2-shell .mini-nav-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 10px 4px;
            color: #334155;
            text-decoration: none;
            border-radius: 10px;
        }
        .v2-shell .mini-nav-link:hover {
            background: #f1f5f9;
            color: #0f172a;
        }
        .v2-shell .kpi-value {
            font-size: 32px;
            font-weight: 700;
            line-height: 1.1;
        }
        .v2-shell .stat-chip {
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 12px;
            font-weight: 600;
            background: #edf2ff;
            color: #273c75;
        }
    </style>
</head>
<body class="hold-transition light-skin sidebar-mini theme-primary fixed v2-shell">
<div class="wrapper">
    <header class="main-header">
        <div class="d-flex align-items-center logo-box justify-content-center">
            <a href="/dashboard" class="logo">
                <div class="logo-mini w-50">
                    <span class="light-logo"><img src="/images/logo-light-text.png" alt="logo"></span>
                    <span class="dark-logo"><img src="/images/logo-light-text.png" alt="logo"></span>
                </div>
            </a>
        </div>
        <nav class="navbar navbar-static-top">
            <div class="navbar-custom-menu r-side">
                <ul class="nav navbar-nav">
                    <li class="me-15 d-none d-md-block">
                        <span class="badge bg-light text-primary mt-15">Dashboard Laravel v2</span>
                    </li>
                    <li class="me-15 d-none d-md-block">
                        <span class="badge bg-light text-dark mt-15">Rango: {{ $rangeLabel }}</span>
                    </li>
                    <li class="btn-group nav-item">
                        <a href="/v2/auth/logout" class="waves-effect waves-light nav-link btn-danger-light mt-10">
                            <i class="fa fa-sign-out-alt me-5"></i> Cerrar sesión
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
    </header>

    <aside class="main-sidebar">
        <section class="sidebar position-relative">
            <div class="multinav">
                <div class="multinav-scroll" style="height: 100%;">
                    <ul class="sidebar-menu" data-widget="tree">
                        <li>
                            <a href="/dashboard" class="mini-nav-link">
                                <i class="mdi mdi-view-dashboard fs-24"></i>
                                <span class="mini-nav-label">Dashboard</span>
                            </a>
                        </li>
                        <li>
                            <a href="/pacientes" class="mini-nav-link">
                                <i class="mdi mdi-account-multiple-outline fs-24"></i>
                                <span class="mini-nav-label">Pacientes</span>
                            </a>
                        </li>
                        <li>
                            <a href="/billing/no-facturados" class="mini-nav-link">
                                <i class="mdi mdi-chart-areaspline fs-24"></i>
                                <span class="mini-nav-label">Billing</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </section>
    </aside>

    <div class="content-wrapper">
        <div class="container-full">
            <section class="content">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="box mb-15">
                            <div class="box-body">
                                <form method="get" action="/v2/dashboard" class="row g-3 align-items-end">
                                    <div class="col-md-4 col-lg-3">
                                        <label for="start_date" class="form-label">Desde</label>
                                        <input type="date" id="start_date" name="start_date" class="form-control" value="{{ $startInput }}">
                                    </div>
                                    <div class="col-md-4 col-lg-3">
                                        <label for="end_date" class="form-label">Hasta</label>
                                        <input type="date" id="end_date" name="end_date" class="form-control" value="{{ $endInput }}">
                                    </div>
                                    <div class="col-md-4 col-lg-3">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fa fa-filter me-5"></i>Aplicar filtros
                                        </button>
                                    </div>
                                    <div class="col-md-4 col-lg-3">
                                        <a href="/v2/dashboard" class="btn btn-light w-100">
                                            <i class="fa fa-undo me-5"></i>Limpiar
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-xxxl-9 col-xl-8 col-12">
                        <div class="row g-3">
                            @foreach($kpiCards as $card)
                                <div class="col-xxxl-4 col-xl-4 col-lg-6 col-md-6 col-12">
                                    <div class="box mb-20">
                                        <div class="box-body">
                                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-15">
                                                <div class="d-flex align-items-center gap-15">
                                                    @if(!empty($card['icon']))
                                                        <img src="/images/{{ ltrim((string) $card['icon'], '/') }}" alt="" class="w-120"/>
                                                    @endif
                                                    <div>
                                                        <h4 class="mb-5 text-muted text-uppercase fs-12 letter-spacing-1">{{ (string) ($card['title'] ?? '') }}</h4>
                                                        <h2 class="mb-0 fw-600">{{ (string) ($card['value'] ?? '') }}</h2>
                                                        @if(!empty($card['description']))
                                                            <p class="mb-0 text-muted fs-12">{{ (string) $card['description'] }}</p>
                                                        @endif
                                                    </div>
                                                </div>
                                                @if(!empty($card['tag']))
                                                    <span class="badge bg-light text-primary fw-500 px-3 py-2">{{ (string) $card['tag'] }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach

                            <div class="col-xl-6 col-12">
                                <div class="box">
                                    <div class="box-header">
                                        <h4 class="box-title">Embudo de solicitudes quirúrgicas</h4>
                                        <span class="stat-chip">
                                            Conversión {{ number_format((float) ($solicitudesFunnel['totales']['conversion_agendada'] ?? 0), 1) }}%
                                        </span>
                                    </div>
                                    <div class="box-body">
                                        <div id="solicitudes_funnel_chart"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-6 col-12">
                                <div class="box">
                                    <div class="box-header">
                                        <h4 class="box-title">Backlog CRM</h4>
                                        <span class="stat-chip">Avance {{ number_format((float) ($crmBacklog['avance'] ?? 0), 1) }}%</span>
                                    </div>
                                    <div class="box-body">
                                        <div id="crm_backlog_chart"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-6 col-12">
                                <div class="box">
                                    <div class="box-header">
                                        <h4 class="box-title">Procedimientos más realizados</h4>
                                    </div>
                                    <div class="box-body">
                                        <div id="patient_statistics"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-6 col-12">
                                <div class="box">
                                    <div class="box-header">
                                        <h4 class="box-title">Estado de protocolos</h4>
                                    </div>
                                    <div class="box-body">
                                        <div id="revision_estado_chart"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="box">
                                    <div class="box-header with-border">
                                        <h4 class="box-title">Cirugías recientes</h4>
                                    </div>
                                    <div class="box-body no-padding">
                                        <div class="table-responsive">
                                            <table class="table mb-0">
                                                <tbody>
                                                <tr class="bg-info-light">
                                                    <th>No</th>
                                                    <th>Fecha</th>
                                                    <th>ID</th>
                                                    <th>Nombre</th>
                                                    <th>Edad</th>
                                                    <th>Procedimiento</th>
                                                    <th>Afiliación</th>
                                                </tr>
                                                @if(!empty($cirugiasRecientes))
                                                    @foreach($cirugiasRecientes as $index => $patient)
                                                        @php
                                                            $fecha = isset($patient['fecha_inicio']) ? date('d/m/Y', strtotime((string) $patient['fecha_inicio'])) : '-';
                                                            $fname = trim(((string) ($patient['fname'] ?? '')) . ' ' . ((string) ($patient['lname'] ?? '')) . ' ' . ((string) ($patient['lname2'] ?? '')));
                                                            $edad = '-';
                                                            if (!empty($patient['fecha_nacimiento']) && !empty($patient['fecha_inicio'])) {
                                                                try {
                                                                    $birthDate = new DateTime((string) $patient['fecha_nacimiento']);
                                                                    $opDate = new DateTime((string) $patient['fecha_inicio']);
                                                                    $edad = (string) $opDate->diff($birthDate)->y;
                                                                } catch (Throwable) {
                                                                    $edad = '-';
                                                                }
                                                            }
                                                        @endphp
                                                        <tr>
                                                            <td>{{ $index + 1 }}</td>
                                                            <td>{{ $fecha }}</td>
                                                            <td>{{ (string) ($patient['hc_number'] ?? '') }}</td>
                                                            <td><strong>{{ $fname }}</strong></td>
                                                            <td>{{ $edad }}</td>
                                                            <td>{{ (string) ($patient['membrete'] ?? '') }}</td>
                                                            <td>{{ (string) ($patient['afiliacion'] ?? '') }}</td>
                                                        </tr>
                                                    @endforeach
                                                @else
                                                    <tr>
                                                        <td colspan="7" class="text-center">No hay cirugías registradas en el periodo.</td>
                                                    </tr>
                                                @endif
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="box-footer bg-light py-10 with-border">
                                        <p class="mb-0">Total {{ number_format($cirugiasPeriodo) }} cirugías ({{ $rangeLabel }})</p>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-6 col-12">
                                <div class="box">
                                    <div class="box-body px-0 pb-0">
                                        <div class="px-20 bb-1 pb-15 d-flex align-items-center justify-content-between flex-wrap gap-10">
                                            <h4 class="mb-0">Plantillas recientes</h4>
                                        </div>
                                        <div class="box-body">
                                            <div class="inner-user-div4" id="plantilla-container">
                                                @forelse($plantillasRecientes as $row)
                                                    <div class="d-flex justify-content-between align-items-center pb-20 mb-10 bb-dashed border-bottom plantilla-card">
                                                        <div class="pe-20">
                                                            <p class="fs-12 text-fade mb-1">
                                                                {{ !empty($row['fecha']) ? date('d M Y', strtotime((string) $row['fecha'])) : '' }}
                                                                <span class="mx-10">/</span> {{ (string) ($row['tipo'] ?? '') }}
                                                            </p>
                                                            <h4 class="mb-5">{{ (string) ($row['membrete'] ?? '') }}</h4>
                                                            <p class="text-fade mb-10">{{ (string) ($row['cirugia'] ?? '') }}</p>
                                                        </div>
                                                    </div>
                                                @empty
                                                    <p class="text-muted mb-0">No hay protocolos recientes.</p>
                                                @endforelse
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-6 col-12">
                                <div class="box">
                                    <div class="box-header">
                                        <h4 class="box-title">Diagnósticos más frecuentes</h4>
                                    </div>
                                    <div class="box-body">
                                        <div class="news-slider owl-carousel owl-sl">
                                            @if(!empty($diagnosticosFrecuentes))
                                                @php $totalPacientesCount = array_sum($diagnosticosFrecuentes); @endphp
                                                @foreach($diagnosticosFrecuentes as $key => $cantidad)
                                                    @php $porcentaje = $totalPacientesCount > 0 ? round(($cantidad / $totalPacientesCount) * 100, 1) : 0; @endphp
                                                    <div>
                                                        <div class="d-flex align-items-center mb-10">
                                                            <div class="d-flex flex-column flex-grow-1 fw-500">
                                                                <p class="hover-primary text-fade mb-1 fs-14"><i class="fa fa-stethoscope"></i> Diagnóstico</p>
                                                                <span class="text-dark fs-16">{{ (string) $key }}</span>
                                                                <p class="mb-0 fs-14">{{ $porcentaje }}% de pacientes</p>
                                                            </div>
                                                        </div>
                                                        <div class="progress progress-xs mt-5">
                                                            <div class="progress-bar bg-success" role="progressbar" style="width: {{ $porcentaje }}%"></div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            @else
                                                <p class="text-muted">No hay diagnósticos registrados.</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xxxl-3 col-xl-4 col-12">
                        <div class="box">
                            <div class="box-header">
                                <h4 class="box-title">Cirugías por día</h4>
                            </div>
                            <div class="box-body">
                                <div id="total_patient"></div>
                            </div>
                        </div>
                        <div class="box">
                            <div class="box-header with-border">
                                <h4 class="box-title">Últimas solicitudes quirúrgicas</h4>
                            </div>
                            <div class="box-body">
                                @forelse(($solicitudesQuirurgicas['solicitudes'] ?? []) as $row)
                                    <div class="pb-10 mb-10 border-bottom">
                                        <strong>{{ trim(((string) ($row['fname'] ?? '')) . ' ' . ((string) ($row['lname'] ?? ''))) }}</strong><br>
                                        @php $fechaSolicitud = !empty($row['fecha']) ? date('d/m/Y', strtotime((string) $row['fecha'])) : 'Sin fecha'; @endphp
                                        <span class="text-fade fs-12 d-block mb-5">{{ (string) ($row['procedimiento'] ?? '') }} · {{ $fechaSolicitud }}</span>
                                        <div class="d-flex flex-wrap gap-2 fs-12">
                                            <span class="badge bg-primary-light text-primary">Estado: {{ (string) ($row['estado'] ?? 'Sin estado') }}</span>
                                            <span class="badge bg-info-light text-info">Prioridad: {{ (string) ($row['prioridad'] ?? 'Normal') }}</span>
                                            @if(!empty($row['turno']))
                                                <span class="badge bg-success-light text-success">Turno {{ (string) $row['turno'] }}</span>
                                            @else
                                                <span class="badge bg-warning-light text-warning">Sin turno</span>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-muted">No hay solicitudes registradas en el periodo.</p>
                                @endforelse

                                <hr>
                                <p class="mb-0 text-end"><strong>Total:</strong> {{ number_format((int) ($solicitudesQuirurgicas['total'] ?? 0)) }} solicitud(es)</p>
                            </div>
                        </div>

                        <div class="box">
                            <div class="box-header with-border">
                                <h4 class="box-title">Equipo quirúrgico destacado</h4>
                            </div>
                            <div class="box-body">
                                <div class="inner-user-div3">
                                    @forelse($doctoresTop as $doctor)
                                        <div class="d-flex align-items-center mb-30">
                                            <div class="me-15">
                                                @if(!empty($doctor['avatar']))
                                                    <img src="{{ (string) $doctor['avatar'] }}" class="avatar avatar-lg rounded10" style="object-fit: cover;" alt="{{ (string) ($doctor['cirujano_1'] ?? '') }}">
                                                @else
                                                    <span class="avatar avatar-lg rounded10 bg-primary-light d-inline-flex align-items-center justify-content-center text-primary fw-bold">DR</span>
                                                @endif
                                            </div>
                                            <div class="d-flex flex-column flex-grow-1 fw-500">
                                                <span class="text-dark mb-1 fs-16">{{ (string) ($doctor['cirujano_1'] ?? '') }}</span>
                                                <span class="text-fade">Cirugías: {{ number_format((float) ($doctor['total'] ?? 0)) }}</span>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-muted">No hay datos disponibles.</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        <div class="box">
                            <div class="box-header">
                                <h4 class="box-title">Asistente IA</h4>
                            </div>
                            <div class="box-body">
                                <p class="mb-5">Estado: <strong>{{ !empty($aiSummary['provider_configured']) ? 'Activo' : 'En migración' }}</strong></p>
                                <p class="text-muted mb-10">Proveedor: {{ !empty($aiSummary['provider']) ? strtoupper((string) $aiSummary['provider']) : 'Por definir' }}</p>
                                <ul class="list-unstyled mb-0 fs-12 text-muted">
                                    <li><i class="fa fa-check-circle me-5 text-success"></i> Consultas por enfermedad: {{ !empty($aiSummary['features']['consultas_enfermedad']) ? 'Habilitado' : 'Pendiente' }}</li>
                                    <li><i class="fa fa-check-circle me-5 text-success"></i> Consultas de plan: {{ !empty($aiSummary['features']['consultas_plan']) ? 'Habilitado' : 'Pendiente' }}</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<script src="/js/vendors.min.js"></script>
<script src="/assets/vendor_components/apexcharts-bundle/dist/apexcharts.js"></script>
<script src="/assets/vendor_components/OwlCarousel2/dist/owl.carousel.js"></script>
<script src="/js/template.js"></script>
<script>
    var fechas = {!! $fechasJson ?: '[]' !!};
    var procedimientos_dia = {!! $procedimientosDiaJson ?: '[]' !!};
    var membretes = {!! $membretesJson ?: '[]' !!};
    var procedimientos_membrete = {!! $procedimientosMembreteJson ?: '[]' !!};
    var afiliaciones = {!! $afiliacionesJson ?: '[]' !!};
    var procedimientos_por_afiliacion = {!! $procedimientosAfiliacionJson ?: '[]' !!};
    var solicitudesFunnel = {!! $solicitudesFunnelJson ?: '{}' !!};
    var crmBacklog = {!! $crmBacklogJson ?: '{}' !!};
    var revisionEstados = {!! $revisionEstadosJson ?: '{}' !!};
</script>
<script src="/js/pages/dashboard3.js"></script>
<script>
    (function () {
        if (window.jQuery && typeof window.jQuery.fn.slimScroll === 'function') {
            window.jQuery('.inner-user-div3').slimScroll({height: '310px'});
            window.jQuery('.inner-user-div4').slimScroll({height: '200px'});
        }
    })();
</script>
</body>
</html>

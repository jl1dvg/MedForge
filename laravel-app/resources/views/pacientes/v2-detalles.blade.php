@extends('layouts.medforge')

@php
    $patient = is_array($patientData ?? null) ? $patientData : [];
    $afiliaciones = is_array($afiliacionesDisponibles ?? null) ? $afiliacionesDisponibles : [];
    $diagnosticosRows = is_array($diagnosticos ?? null) ? $diagnosticos : [];
    $eventosRows = is_array($eventos ?? null) ? $eventos : [];
    $documentRows = is_array($documentos ?? null) ? $documentos : [];
    $statsRows = is_array($estadisticas ?? null) ? $estadisticas : [];
    $edadPaciente = isset($patientAge) ? $patientAge : null;

    $fullName = trim(implode(' ', array_filter([
        (string) ($patient['fname'] ?? ''),
        (string) ($patient['mname'] ?? ''),
        (string) ($patient['lname'] ?? ''),
        (string) ($patient['lname2'] ?? ''),
    ])));

    $formatDate = static function ($value, string $format = 'd/m/Y'): string {
        if ($value === null || trim((string) $value) === '') {
            return '—';
        }

        try {
            return \Illuminate\Support\Carbon::parse((string) $value)->format($format);
        } catch (\Throwable) {
            return (string) $value;
        }
    };

    $insurance = strtolower((string) ($patient['afiliacion'] ?? ''));
    $backgroundImage = asset('assets/logos_seguros/5.png');
    $generalInsurances = [
        'contribuyente voluntario',
        'conyuge',
        'conyuge pensionista',
        'seguro campesino',
        'seguro campesino jubilado',
        'seguro general',
        'seguro general jubilado',
        'seguro general por montepío',
        'seguro general tiempo parcial',
    ];

    foreach ($generalInsurances as $generalInsurance) {
        if (str_contains($insurance, $generalInsurance)) {
            $backgroundImage = asset('assets/logos_seguros/1.png');
            break;
        }
    }

    if (str_contains($insurance, 'issfa')) {
        $backgroundImage = asset('assets/logos_seguros/2.png');
    } elseif (str_contains($insurance, 'isspol')) {
        $backgroundImage = asset('assets/logos_seguros/3.png');
    } elseif (str_contains($insurance, 'msp')) {
        $backgroundImage = asset('assets/logos_seguros/4.png');
    }

    $gender = strtolower((string) ($patient['sexo'] ?? ''));
    $avatarImage = str_contains($gender, 'masculino')
        ? asset('images/avatar/male.png')
        : asset('images/avatar/female.png');

    $solicitudPdfBaseUrl = '/views/reports/solicitud_quirurgica/solicitud_qx_pdf.php';
@endphp

@push('styles')
    <style>
        .patient-box-header {
            border-bottom: 0 !important;
            display: flex;
            align-items: center;
            min-height: 56px;
            padding: 12px 20px !important;
        }

        .patient-box-header .box-title {
            color: #fff;
            margin: 0;
            line-height: 1.25;
            white-space: normal;
        }

        .patient-box-header-agenda {
            background: linear-gradient(90deg, #2563eb 0%, #1d4ed8 100%);
        }

        .patient-box-header-antecedentes {
            background: linear-gradient(90deg, #7c3aed 0%, #6d28d9 100%);
        }

        .patient-box-header-solicitudes {
            background: linear-gradient(90deg, #d97706 0%, #b45309 100%);
        }

        .patient-box-header-derivaciones {
            background: linear-gradient(90deg, #059669 0%, #047857 100%);
        }

        .patient-box-header-recetas {
            background: linear-gradient(90deg, #dc2626 0%, #b91c1c 100%);
        }

        .patient-box-header-cirugias {
            background: linear-gradient(90deg, #0f766e 0%, #115e59 100%);
        }

        .patient-box-header-estadisticas {
            background: linear-gradient(90deg, #7e22ce 0%, #6b21a8 100%);
        }

        .patient-details-page .patient-scroll-box .patient-scroll-body,
        .patient-details-page .patient-scroll-self {
            max-height: 560px;
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-gutter: stable;
        }

        .patient-scroll-inner {
            padding-right: 6px;
        }
    </style>
@endpush

@section('content')
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title">Detalles del paciente</h3>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                            <li class="breadcrumb-item"><a href="/v2/pacientes">Pacientes</a></li>
                            <li class="breadcrumb-item active" aria-current="page">HC {{ $hc_number }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <section class="content patient-details-page">
        <div class="row">
            <div class="col-xl-4 col-12">
                <div class="box patient-scroll-box">
                    <div class="box-header with-border patient-box-header patient-box-header-agenda">
                        <h4 class="box-title">Agenda</h4>
                    </div>
                    <div class="box-body patient-scroll-body">
                        <div id="paciente360-panel-agenda" class="table-responsive">
                            <p class="text-muted mb-0">Cargando...</p>
                        </div>
                    </div>
                </div>

                <div class="box patient-scroll-box">
                    <div class="box-header with-border patient-box-header patient-box-header-antecedentes">
                        <h4 class="box-title">Antecedentes Patológicos</h4>
                    </div>
                    <div class="box-body patient-scroll-body">
                        <div class="widget-timeline-icon">
                            <ul>
                                @forelse($diagnosticosRows as $diagnosis)
                                    <li>
                                        <div class="icon bg-primary fa fa-heart-o"></div>
                                        <a class="timeline-panel text-muted" href="#">
                                            <h4 class="mb-2 mt-1">{{ $diagnosis['idDiagnostico'] ?? '' }}</h4>
                                            <p class="fs-15 mb-0">{{ $diagnosis['fecha'] ?? '' }}</p>
                                        </a>
                                    </li>
                                @empty
                                    <li class="text-muted">Sin diagnósticos registrados.</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="box patient-scroll-box">
                    <div class="box-header with-border patient-box-header patient-box-header-solicitudes">
                        <h4 class="box-title">Solicitudes</h4>
                    </div>
                    <div class="box-body patient-scroll-body">
                        <div id="patient-solicitudes-panel" class="table-responsive">
                            <p class="text-muted mb-0">Cargando solicitudes...</p>
                        </div>
                    </div>
                </div>

                <div class="box patient-scroll-box">
                    <div class="box-header with-border patient-box-header patient-box-header-derivaciones">
                        <h4 class="box-title">Derivaciones</h4>
                    </div>
                    <div class="box-body patient-scroll-body">
                        <div id="paciente360-panel-derivaciones">
                            <p class="text-muted mb-0">Cargando...</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-8 col-12">
                <div class="box patient-scroll-box">
                    <div class="box-body patient-scroll-body text-end min-h-150"
                         style="background-image:url('{{ $backgroundImage }}'); background-repeat:no-repeat; background-position:center; background-size:cover;">
                    </div>
                    <div class="box-body patient-scroll-body wed-up position-relative">
                        <button class="btn btn-warning mb-3" data-bs-toggle="modal" data-bs-target="#modalEditarPaciente">Editar datos</button>
                        <div class="d-md-flex align-items-center">
                            <div class="me-20 text-center text-md-start">
                                <img src="{{ $avatarImage }}" style="height: 150px" class="bg-success-light rounded10" alt="Paciente">
                                <div class="text-center my-10">
                                    <p class="mb-0">Afiliación</p>
                                    <h4>{{ $patient['afiliacion'] ?? '—' }}</h4>
                                </div>
                            </div>
                            <div class="mt-40">
                                <h4 class="fw-600 mb-5">{{ $fullName !== '' ? $fullName : 'Paciente sin nombre' }}</h4>
                                <h5 class="fw-500 mb-5">HC: {{ $patient['hc_number'] ?? $hc_number }}</h5>
                                <p><i class="fa fa-clock-o"></i> Edad: {{ $edadPaciente !== null ? $edadPaciente . ' años' : '—' }}</p>
                                <p><i class="fa fa-calendar"></i> Fecha de nacimiento: {{ $formatDate($patient['fecha_nacimiento'] ?? null) }}</p>
                                <p><i class="fa fa-phone"></i> Celular: {{ trim((string) ($patient['celular'] ?? '')) !== '' ? $patient['celular'] : '—' }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="box-body patient-scroll-body pt-0">
                        @if(!empty($eventosRows))
                            <section class="cd-horizontal-timeline">
                                <div class="timeline">
                                    <div class="events-wrapper">
                                        <div class="events">
                                            <ol style="white-space: nowrap; overflow-x: auto; display: flex; gap: 1rem;">
                                                @foreach($eventosRows as $index => $row)
                                                    @php
                                                        $fechaRaw = (string) ($row['fecha'] ?? '');
                                                        $timestamp = strtotime($fechaRaw);
                                                        $syntheticTimestamp = $timestamp ? ($timestamp + ($index * 86400)) : (946684800 + ($index * 86400));
                                                        $dataDate = date('d/m/Y', $syntheticTimestamp);
                                                        $fechaCorta = $timestamp ? date('d M', $timestamp) : '01 Jan';
                                                    @endphp
                                                    <li style="min-width: 80px; text-align: center;">
                                                        <a href="#0"
                                                           style="display: inline-block; padding: 6px 10px;"
                                                           data-date="{{ $dataDate }}"
                                                           class="{{ $index === 0 ? 'selected' : '' }}">
                                                            {{ $fechaCorta }}
                                                        </a>
                                                    </li>
                                                @endforeach
                                            </ol>
                                            <span class="filling-line" aria-hidden="true"></span>
                                        </div>
                                    </div>
                                    <ul class="cd-timeline-navigation">
                                        <li><a href="#0" class="prev inactive">Prev</a></li>
                                        <li><a href="#0" class="next">Next</a></li>
                                    </ul>
                                </div>
                                <div class="events-content">
                                    <ol>
                                        @foreach($eventosRows as $index => $row)
                                            @php
                                                $fechaRaw = (string) ($row['fecha'] ?? '');
                                                $timestamp = strtotime($fechaRaw);
                                                $syntheticTimestamp = $timestamp ? ($timestamp + ($index * 86400)) : (946684800 + ($index * 86400));
                                                $dataDate = date('d/m/Y', $syntheticTimestamp);
                                                $fechaLarga = $timestamp ? date('F jS, Y', $timestamp) : 'Fecha no disponible';
                                                $procedimiento = (string) ($row['procedimiento_proyectado'] ?? '');
                                                $parts = explode(' - ', $procedimiento);
                                                $nombreProcedimiento = trim(implode(' - ', array_slice($parts, 2)));
                                                if ($nombreProcedimiento === '') {
                                                    $nombreProcedimiento = trim($procedimiento) !== '' ? $procedimiento : 'Encuentro';
                                                }
                                                $motivoConsulta = trim((string) ($row['motivo_consulta'] ?? ''));
                                                $enfermedadActual = trim((string) ($row['enfermedad_actual'] ?? ''));
                                                $examenFisico = trim((string) ($row['examen_fisico'] ?? ''));
                                                $planConsulta = trim((string) ($row['plan'] ?? ''));
                                                $contenido = trim((string) ($row['contenido'] ?? ''));
                                                $hasStructuredContent = $motivoConsulta !== '' || $enfermedadActual !== '' || $examenFisico !== '' || $planConsulta !== '';
                                            @endphp
                                            <li data-date="{{ $dataDate }}" class="{{ $index === 0 ? 'selected' : '' }}">
                                                <h2>{{ $nombreProcedimiento }}</h2>
                                                <small>{{ $fechaLarga }}</small>
                                                <hr class="my-30">
                                                @if($hasStructuredContent)
                                                    <div class="pb-30">
                                                        <p class="mb-10"><strong>Motivo:</strong><br>{!! nl2br(e($motivoConsulta !== '' ? $motivoConsulta : '—')) !!}</p>
                                                        <p class="mb-10"><strong>Enfermedad Actual:</strong><br>{!! nl2br(e($enfermedadActual !== '' ? $enfermedadActual : '—')) !!}</p>
                                                        <p class="mb-10"><strong>Examen Físico:</strong><br>{!! nl2br(e($examenFisico !== '' ? $examenFisico : '—')) !!}</p>
                                                        <p class="mb-0"><strong>Plan:</strong><br>{!! nl2br(e($planConsulta !== '' ? $planConsulta : '—')) !!}</p>
                                                    </div>
                                                @else
                                                    <p class="pb-30">{!! nl2br(e($contenido !== '' ? $contenido : 'Sin contenido clínico registrado.')) !!}</p>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ol>
                                </div>
                            </section>
                        @else
                            <p class="text-muted mb-0">No hay datos disponibles para mostrar en el timeline.</p>
                        @endif
                    </div>
                </div>

                <div id="patient-sections" data-hc="{{ $hc_number }}" data-sections="examenes,agenda,derivaciones,recetas">
                    <div class="row">
                        <div class="col-xl-6 col-12">
                            <div class="box box-body px-35 bg-lightgray patient-scroll-self">
                                <div class="d-flex justify-content-between align-items-center mb-15">
                                    <h4 class="m-0">Imágenes</h4>
                                    <span class="float-end"><a class="btn btn-rounded btn-light fw-500 w-90" href="/imagenes/examenes-realizados" target="_blank" rel="noopener noreferrer">Todas</a></span>
                                </div>
                                <div id="paciente360-panel-examenes"><p class="text-muted mb-0">Cargando...</p></div>
                            </div>
                        </div>

                        <div class="col-xl-6 col-12">
                            <div class="box patient-scroll-box">
                                <div class="box-header with-border patient-box-header patient-box-header-recetas">
                                    <h4 class="box-title">Recetas</h4>
                                </div>
                                <div class="box-body patient-scroll-body">
                                    <div id="paciente360-panel-recetas"><p class="text-muted mb-0">Cargando...</p></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-6 col-12">
                        <div class="box patient-scroll-box">
                            <div class="box-header with-border patient-box-header patient-box-header-cirugias">
                                <h4 class="box-title">Cirugías y PNI</h4>
                            </div>
                            <div class="box-body patient-scroll-body">
                                <div class="media-list media-list-divided">
                                    @forelse($documentRows as $documento)
                                        @php
                                            $isProtocolo = isset($documento['membrete']);
                                            $fechaDoc = $documento['fecha_inicio'] ?? ($documento['created_at'] ?? '');
                                        @endphp
                                        <div class="media media-single px-0">
                                            <div class="ms-0 me-15 bg-{{ $isProtocolo ? 'success' : 'primary' }}-light h-50 w-50 l-h-50 rounded text-center d-flex align-items-center justify-content-center">
                                                <span class="fs-24 text-{{ $isProtocolo ? 'success' : 'primary' }}"><i class="fa fa-file-{{ $isProtocolo ? 'pdf' : 'text' }}-o"></i></span>
                                            </div>
                                            <div class="d-flex flex-column flex-grow-1">
                                                <span class="title fw-500 fs-16 text-truncate" style="max-width: 220px;">
                                                    {{ $documento['membrete'] ?? ($documento['procedimiento'] ?? 'Documento') }}
                                                </span>
                                                <span class="text-fade fw-500 fs-12">{{ $formatDate($fechaDoc) }}</span>
                                            </div>
                                            @if($isProtocolo)
                                                <a class="fs-18 text-gray hover-info" href="#"
                                                   onclick="window.descargarPDFsSeparados('{{ (string) ($documento['form_id'] ?? '') }}', '{{ (string) ($documento['hc_number'] ?? '') }}'); return false;">
                                                    <i class="fa fa-download"></i>
                                                </a>
                                            @else
                                                <a class="fs-18 text-gray hover-info"
                                                   href="{{ $solicitudPdfBaseUrl }}?hc_number={{ urlencode((string) ($documento['hc_number'] ?? '')) }}&form_id={{ urlencode((string) ($documento['form_id'] ?? '')) }}"
                                                   target="_blank" rel="noopener noreferrer">
                                                    <i class="fa fa-download"></i>
                                                </a>
                                            @endif
                                        </div>
                                    @empty
                                        <p class="text-muted mb-0">No hay documentos disponibles.</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-6 col-12">
                        <div class="box patient-scroll-box">
                            <div class="box-header no-border patient-box-header patient-box-header-estadisticas">
                                <h4 class="box-title">Estadísticas de citas</h4>
                            </div>
                            <div class="box-body patient-scroll-body">
                                <div id="chart123"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="modal fade" id="modalSolicitud" tabindex="-1" aria-labelledby="modalSolicitudLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalSolicitudLabel">Detalle de la Solicitud</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3 p-3 rounded" id="solicitudContainer" style="background-color: #e9f5ff;">
                        <p class="mb-1"><strong>Fecha:</strong> <span id="modalFecha" class="float-end badge bg-light text-dark"></span></p>
                        <p class="mb-1"><strong>Procedimiento:</strong> <span id="modalProcedimiento"></span></p>
                        <p class="mb-1"><strong>Ojo:</strong> <span id="modalOjo"></span></p>
                        <p class="mb-1"><strong>Diagnóstico:</strong> <span id="modalDiagnostico"></span></p>
                        <p class="mb-1"><strong>Doctor:</strong> <span id="modalDoctor"></span></p>
                        <p class="mb-1">
                            <strong>Estado:</strong>
                            <span id="modalEstado" class="float-end badge bg-secondary"></span>
                            <span id="modalSemaforo" class="float-end me-2 badge" style="width: 16px; height: 16px; border-radius: 50%;"></span>
                        </p>
                    </div>
                    <p><strong>Motivo:</strong> <span id="modalMotivo"></span></p>
                    <p><strong>Enfermedad Actual:</strong> <span id="modalEnfermedad"></span></p>
                    <p><strong>Plan:</strong> <span id="modalDescripcion"></span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalEditarPaciente" tabindex="-1" aria-labelledby="modalEditarPacienteLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="/v2/pacientes/detalles?hc_number={{ urlencode($hc_number) }}">
                    <input type="hidden" name="actualizar_paciente" value="1">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalEditarPacienteLabel">Editar datos del paciente</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Primer nombre</label>
                            <input type="text" name="fname" class="form-control" value="{{ $patient['fname'] ?? '' }}">
                        </div>
                        <div class="mb-3">
                            <label>Segundo nombre</label>
                            <input type="text" name="mname" class="form-control" value="{{ $patient['mname'] ?? '' }}">
                        </div>
                        <div class="mb-3">
                            <label>Primer apellido</label>
                            <input type="text" name="lname" class="form-control" value="{{ $patient['lname'] ?? '' }}">
                        </div>
                        <div class="mb-3">
                            <label>Segundo apellido</label>
                            <input type="text" name="lname2" class="form-control" value="{{ $patient['lname2'] ?? '' }}">
                        </div>
                        <div class="mb-3">
                            <label>Afiliación</label>
                            <select name="afiliacion" class="form-control">
                                @foreach($afiliaciones as $afiliacion)
                                    <option value="{{ $afiliacion }}" @selected(strtolower((string) $afiliacion) === strtolower((string) ($patient['afiliacion'] ?? '')))>{{ $afiliacion }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Fecha de nacimiento</label>
                            <input type="date" name="fecha_nacimiento" class="form-control" value="{{ $patient['fecha_nacimiento'] ?? '' }}">
                        </div>
                        <div class="mb-3">
                            <label>Sexo</label>
                            <select name="sexo" class="form-control">
                                <option value="Masculino" @selected(strtolower((string) ($patient['sexo'] ?? '')) === 'masculino')>Masculino</option>
                                <option value="Femenino" @selected(strtolower((string) ($patient['sexo'] ?? '')) === 'femenino')>Femenino</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Celular</label>
                            <input type="text" name="celular" class="form-control" value="{{ $patient['celular'] ?? '' }}">
                        </div>
                        <div class="mb-3">
                            <label>Número HC</label>
                            <input type="text" name="hc_number" class="form-control" value="{{ $patient['hc_number'] ?? $hc_number }}" readonly>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalReceta" tabindex="-1" aria-labelledby="modalRecetaLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalRecetaLabel">Detalle de receta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body" id="recetaModalContent">
                    <p class="text-muted mb-0">Cargando detalle de receta...</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary" id="btnPrintReceta">
                        <i class="fa fa-print me-5"></i>Imprimir receta
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="/assets/vendor_components/apexcharts-bundle/dist/apexcharts.js"></script>
    <script src="/assets/vendor_components/horizontal-timeline/js/horizontal-timeline.js"></script>
    <script src="/js/pages/patient-detail.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const chartContainer = document.querySelector('#chart123');
            if (!chartContainer) {
                return;
            }

            const series = @json(array_values($statsRows));
            const labels = @json(array_keys($statsRows));

            if (typeof ApexCharts === 'undefined' || !Array.isArray(series) || series.length === 0) {
                chartContainer.innerHTML = '<p class="text-muted mb-0">Sin datos suficientes para mostrar estadísticas.</p>';
                return;
            }

            const options = {
                series: series,
                chart: { type: 'donut' },
                colors: ['#3246D3', '#00D0FF', '#ee3158', '#ffa800', '#05825f'],
                legend: { position: 'bottom' },
                plotOptions: { pie: { donut: { size: '45%' } } },
                labels: labels,
                responsive: [
                    { breakpoint: 1600, options: { chart: { width: 330 } } },
                    { breakpoint: 500, options: { chart: { width: 280 } } }
                ]
            };

            const chart = new ApexCharts(chartContainer, options);
            chart.render();
        });
    </script>
@endpush

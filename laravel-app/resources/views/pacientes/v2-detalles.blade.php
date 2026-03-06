@extends('layouts.medforge')

@php
    $patient = is_array($patientData ?? null) ? $patientData : [];
    $afiliaciones = is_array($afiliacionesDisponibles ?? null) ? $afiliacionesDisponibles : [];
    $diagnosticosRows = is_array($diagnosticos ?? null) ? $diagnosticos : [];
    $timelineRows = is_array($timelineItems ?? null) ? $timelineItems : [];
    $documentRows = is_array($documentos ?? null) ? $documentos : [];
    $statsRows = is_array($estadisticas ?? null) ? $estadisticas : [];
    $edadPaciente = isset($patientAge) ? $patientAge : null;

    $fullName = trim(implode(' ', array_filter([
        (string) ($patient['fname'] ?? ''),
        (string) ($patient['mname'] ?? ''),
        (string) ($patient['lname'] ?? ''),
        (string) ($patient['lname2'] ?? ''),
    ])));

    $timelineColorMap = [
        'solicitud' => 'bg-primary',
        'prefactura' => 'bg-info',
        'cirugia' => 'bg-danger',
        'interconsulta' => 'bg-warning',
    ];

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

    <section class="content">
        <div class="row">
            <div class="col-xl-4 col-12">
                <div class="box">
                    <div class="box-body box-profile">
                        <p>Nombre completo: <span class="text-gray ps-10">{{ $fullName !== '' ? $fullName : '—' }}</span></p>
                        <p>Fecha de nacimiento: <span class="text-gray ps-10">{{ $formatDate($patient['fecha_nacimiento'] ?? null) }}</span></p>
                        <p>Edad: <span class="text-gray ps-10">{{ $edadPaciente !== null ? $edadPaciente . ' años' : '—' }}</span></p>
                        <p>Celular: <span class="text-gray ps-10">{{ trim((string) ($patient['celular'] ?? '')) !== '' ? $patient['celular'] : '—' }}</span></p>
                        <p>Afiliación: <span class="text-gray ps-10">{{ trim((string) ($patient['afiliacion'] ?? '')) !== '' ? $patient['afiliacion'] : '—' }}</span></p>
                    </div>
                </div>

                <div class="box">
                    <div class="box-header border-0 pb-0">
                        <h4 class="box-title">Antecedentes Patológicos</h4>
                    </div>
                    <div class="box-body">
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

                <div class="box">
                    <div class="box-header with-border">
                        <h4 class="box-title">Solicitudes</h4>
                    </div>
                    <div class="box-body">
                        @forelse($timelineRows as $procedimientoData)
                            @php
                                $origen = strtolower((string) ($procedimientoData['origen'] ?? ''));
                                $tipo = strtolower((string) ($procedimientoData['tipo'] ?? ''));
                                $bulletColor = $timelineColorMap[$tipo]
                                    ?? $timelineColorMap[$origen]
                                    ?? 'bg-secondary';
                            @endphp
                            <div class="d-flex align-items-center mb-25">
                                <span class="bullet bullet-bar {{ $bulletColor }} align-self-stretch"></span>
                                <div class="h-20 mx-20 flex-shrink-0">
                                    <input type="checkbox" id="timeline_{{ md5((string) (($procedimientoData['form_id'] ?? '') . ($procedimientoData['fecha'] ?? '') . ($procedimientoData['nombre'] ?? ''))) }}"
                                           class="filled-in">
                                    <label for="timeline_{{ md5((string) (($procedimientoData['form_id'] ?? '') . ($procedimientoData['fecha'] ?? '') . ($procedimientoData['nombre'] ?? ''))) }}"
                                           class="h-20 p-10 mb-0"></label>
                                </div>
                                <div class="d-flex flex-column flex-grow-1">
                                    <a href="#" class="text-dark fw-500 fs-16">{{ $procedimientoData['nombre'] ?? '' }}</a>
                                    <span class="text-fade fw-500">
                                        {{ ucfirst(strtolower((string) ($procedimientoData['origen'] ?? 'registro'))) }} creado el {{ $formatDate($procedimientoData['fecha'] ?? null) }}
                                    </span>
                                </div>
                                @if(($procedimientoData['origen'] ?? '') === 'Solicitud')
                                    <div class="dropdown">
                                        <a class="px-10 pt-5" href="#" data-bs-toggle="dropdown"><i class="ti-more-alt"></i></a>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a class="dropdown-item flexbox"
                                               href="#"
                                               data-bs-toggle="modal"
                                               data-bs-target="#modalSolicitud"
                                               data-form-id="{{ (string) ($procedimientoData['form_id'] ?? '') }}"
                                               data-hc="{{ $hc_number }}">
                                                <span>Ver detalles</span>
                                            </a>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="text-muted mb-0">Sin solicitudes registradas.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-xl-8 col-12">
                <div class="box">
                    <div class="box-body text-end min-h-150"
                         style="background-image:url('{{ $backgroundImage }}'); background-repeat:no-repeat; background-position:center; background-size:cover;">
                    </div>
                    <div class="box-body wed-up position-relative">
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
                            </div>
                        </div>
                    </div>
                </div>

                <div class="box" id="paciente360" data-hc="{{ $hc_number }}" data-sections="solicitudes,examenes,agenda,consultas,protocolos,prefacturas,derivaciones,recetas,crm">
                    <div class="box-header with-border">
                        <h4 class="box-title">Paciente 360</h4>
                    </div>
                    <div class="box-body">
                        <div class="mb-20 d-flex flex-wrap gap-10" id="paciente360Summary">
                            <span class="badge bg-light text-dark">Cargando resumen...</span>
                        </div>
                        <ul class="nav nav-tabs mb-15" role="tablist">
                            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#paciente360-tab-solicitudes" type="button">Solicitudes</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#paciente360-tab-examenes" type="button">Exámenes</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#paciente360-tab-agenda" type="button">Agenda</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#paciente360-tab-consultas" type="button">Consultas</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#paciente360-tab-protocolos" type="button">Protocolos</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#paciente360-tab-prefacturas" type="button">Prefacturas</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#paciente360-tab-derivaciones" type="button">Derivaciones</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#paciente360-tab-recetas" type="button">Recetas</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#paciente360-tab-crm" type="button">CRM</button></li>
                        </ul>

                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="paciente360-tab-solicitudes"><div id="paciente360-panel-solicitudes" class="table-responsive"></div></div>
                            <div class="tab-pane fade" id="paciente360-tab-examenes"><div id="paciente360-panel-examenes" class="table-responsive"></div></div>
                            <div class="tab-pane fade" id="paciente360-tab-agenda"><div id="paciente360-panel-agenda" class="table-responsive"></div></div>
                            <div class="tab-pane fade" id="paciente360-tab-consultas"><div id="paciente360-panel-consultas" class="table-responsive"></div></div>
                            <div class="tab-pane fade" id="paciente360-tab-protocolos"><div id="paciente360-panel-protocolos" class="table-responsive"></div></div>
                            <div class="tab-pane fade" id="paciente360-tab-prefacturas"><div id="paciente360-panel-prefacturas" class="table-responsive"></div></div>
                            <div class="tab-pane fade" id="paciente360-tab-derivaciones"><div id="paciente360-panel-derivaciones" class="table-responsive"></div></div>
                            <div class="tab-pane fade" id="paciente360-tab-recetas"><div id="paciente360-panel-recetas" class="table-responsive"></div></div>
                            <div class="tab-pane fade" id="paciente360-tab-crm"><div id="paciente360-panel-crm" class="table-responsive"></div></div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-6 col-12">
                        <div class="box">
                            <div class="box-header with-border">
                                <h4 class="box-title">Descargar archivos</h4>
                            </div>
                            <div class="box-body">
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
                        <div class="box">
                            <div class="box-header no-border">
                                <h4 class="box-title">Estadísticas de citas</h4>
                            </div>
                            <div class="box-body">
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
@endsection

@push('scripts')
    <script src="/assets/vendor_components/apexcharts-bundle/dist/apexcharts.js"></script>
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

@extends('layouts.medforge')

@push('scripts')
    <script src="{{ asset('assets/vendor_components/apexcharts-bundle/dist/apexcharts.js') }}"></script>
    <script src="{{ asset('assets/vendor_components/OwlCarousel2/dist/owl.carousel.js') }}"></script>
    <script src="{{ asset('assets/vendor_components/date-paginator/moment.min.js') }}"></script>
    <script src="{{ asset('assets/vendor_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
    <script src="{{ asset('assets/vendor_components/date-paginator/bootstrap-datepaginator.min.js') }}"></script>
    <script src="{{ asset('js/pages/doctor-details.js') }}"></script>
@endpush

@section('content')
@php
    $displayName = $doctor['display_name'] ?? ($doctor['name'] ?? 'Doctor');

    $profilePhoto = $doctor['profile_photo'] ?? null;
    $coverUrl = \App\Modules\Doctores\Services\DoctoresService::resolveProfilePhotoUrl($profilePhoto);
    if (!$coverUrl) {
        $coverUrl = asset('images/avatar/375x200/1.jpg');
    }
    $avatarUrl = \App\Modules\Doctores\Services\DoctoresService::resolveProfilePhotoUrl($profilePhoto);
    if (!$avatarUrl) {
        $avatarUrl = asset('images/avatar/1.jpg');
    }

    $preferredSignature = $doctor['signature_path'] ?? $doctor['firma'] ?? null;
    $firmaUrl = null;
    if (!empty($preferredSignature)) {
        $firmaUrl = \App\Modules\Doctores\Services\DoctoresService::resolveProfilePhotoUrl($preferredSignature);
        if (!$firmaUrl) {
            $firmaUrl = asset(ltrim($preferredSignature, '/'));
        }
    }

    $statusVariant = $doctor['status_variant'] ?? null;
    $statusLabel = $doctor['status'] ?? null;
    $statusBadgeMap = ['primary' => 'primary', 'success' => 'success', 'danger' => 'danger', 'info' => 'info'];
    $statusBadgeClass = $statusBadgeMap[$statusVariant] ?? 'secondary';

    $todayPatients = $todayPatients ?? [];
    $activityStats = $activityStats ?? [];
    $careProgress = $careProgress ?? [];
    $milestones = $milestones ?? [];
    $biographyParagraphs = $biographyParagraphs ?? [];
    $availabilitySummary = $availabilitySummary ?? [];
    $focusAreas = $focusAreas ?? [];
    $supportChannels = $supportChannels ?? [];
    $researchHighlights = $researchHighlights ?? [];
    $performanceSummary = is_array($performanceSummary ?? null) ? $performanceSummary : [];
    $operationalNotes = $operationalNotes ?? [];
    $appointmentsDays = $appointmentsDays ?? [];
    $appointments = $appointments ?? [];
    $appointmentsSelectedDate = $appointmentsSelectedDate ?? null;
    $appointmentsSelectedLabel = $appointmentsSelectedLabel ?? null;

    $doctorId = isset($doctor['id']) ? (int) $doctor['id'] : null;
    $doctorDetailUrl = $doctorId !== null ? '/doctores/' . $doctorId : null;

    $selectedDayIndex = null;
    foreach ($appointmentsDays as $idx => $day) {
        if (!empty($day['is_selected'])) {
            $selectedDayIndex = $idx;
            break;
        }
    }
    $prevDay = $selectedDayIndex !== null && $selectedDayIndex > 0 ? $appointmentsDays[$selectedDayIndex - 1] : null;
    $nextDay = $selectedDayIndex !== null && $selectedDayIndex < count($appointmentsDays) - 1 ? $appointmentsDays[$selectedDayIndex + 1] : null;

    $buildDayUrl = function (?array $day) use ($doctorDetailUrl): string {
        if ($doctorDetailUrl === null || $day === null || empty($day['date'])) {
            return 'javascript:void(0);';
        }
        return $doctorDetailUrl . '?fecha=' . urlencode((string) $day['date']);
    };
@endphp

<div class="container-full">
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h4 class="page-title">Perfil del doctor</h4>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/"><i class="mdi mdi-home-outline"></i></a></li>
                            <li class="breadcrumb-item"><a href="/doctores">Doctores</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Perfil</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="row">
            <div class="col-xl-4 col-12">
                {{-- Doctor profile card --}}
                <div class="box">
                    <div class="box-body p-0">
                        <div class="position-relative">
                            <img src="{{ $coverUrl }}"
                                 class="img-fluid w-100"
                                 alt="{{ $displayName }}">
                            @if ($statusLabel)
                                <span class="badge badge-{{ $statusBadgeClass }} px-15 py-5 shadow"
                                      style="position: absolute; top: 15px; right: 15px;">
                                    {{ $statusLabel }}
                                </span>
                            @endif
                        </div>
                        <div class="p-20 text-center">
                            <img src="{{ $avatarUrl }}"
                                 class="avatar avatar-xxl rounded-circle border border-3 border-white shadow mb-15"
                                 alt="{{ $displayName }}">
                            <h3 class="mb-5">{{ $displayName }}</h3>
                            @if (!empty($performanceSummary))
                                <div class="mb-10">
                                    <div class="d-flex align-items-center justify-content-center gap-10 flex-wrap">
                                        <div class="fs-18">
                                            @php $stars = (float) ($performanceSummary['stars'] ?? 0); @endphp
                                            @for ($i = 1; $i <= 5; $i++)
                                                @if ($stars >= $i)
                                                    <i class="fa fa-star text-warning"></i>
                                                @elseif ($stars >= ($i - 0.5))
                                                    <i class="fa fa-star-half-o text-warning"></i>
                                                @else
                                                    <i class="fa fa-star-o text-muted"></i>
                                                @endif
                                            @endfor
                                        </div>
                                        <span class="badge badge-warning-light text-warning px-10 py-5">
                                            {{ $performanceSummary['label'] ?? 'Sin score' }}
                                        </span>
                                    </div>
                                    <p class="mb-0 text-fade fs-13 mt-5">
                                        {{ $performanceSummary['summary'] ?? '' }}
                                    </p>
                                </div>
                            @endif
                            @if (!empty($doctor['especialidad']))
                                <p class="text-fade mb-5">{{ $doctor['especialidad'] }}</p>
                            @endif
                            @if (!empty($doctor['subespecialidad']))
                                <p class="text-fade mb-5">{{ $doctor['subespecialidad'] }}</p>
                            @endif
                            @if (!empty($doctor['email']))
                                <p class="mb-5">
                                    <a href="mailto:{{ $doctor['email'] }}" class="text-primary">
                                        <i class="fa fa-envelope me-5"></i>{{ $doctor['email'] }}
                                    </a>
                                </p>
                            @endif
                            @if (!empty($doctor['sede']))
                                <p class="mb-0 text-fade">
                                    <i class="mdi mdi-hospital-building me-5"></i>{{ $doctor['sede'] }}
                                </p>
                            @endif
                        </div>
                    </div>
                    <div class="box-footer text-center">
                        <div class="d-flex justify-content-center gap-10 flex-wrap">
                            <a href="/doctores" class="btn btn-outline-primary btn-sm">
                                <i class="fa fa-arrow-left me-5"></i> Volver al listado
                            </a>
                            @if (!empty($doctor['email']))
                                <a href="mailto:{{ $doctor['email'] }}" class="btn btn-primary btn-sm">
                                    <i class="fa fa-paper-plane me-5"></i> Contactar
                                </a>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Account status --}}
                <div class="box">
                    <div class="box-header">
                        <h4 class="box-title">Estado de la cuenta</h4>
                    </div>
                    <div class="box-body">
                        <div class="d-flex flex-wrap gap-10">
                            <span class="badge badge-{{ $doctor['is_approved'] ? 'success' : 'warning' }} px-15 py-5">
                                {{ $doctor['is_approved'] ? 'Aprobado' : 'Pendiente de aprobación' }}
                            </span>
                            <span class="badge badge-{{ $doctor['is_subscribed'] ? 'info' : 'secondary' }} px-15 py-5">
                                {{ $doctor['is_subscribed'] ? 'Suscripción activa' : 'Sin suscripción activa' }}
                            </span>
                            @if ($statusLabel)
                                <span class="badge badge-{{ $statusBadgeClass }} px-15 py-5">
                                    {{ $statusLabel }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Appointments schedule --}}
                <div class="box">
                    <div class="box-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="box-title mb-0">Agenda</h4>
                            @if ($appointmentsSelectedLabel)
                                <span class="text-fade fs-12">{{ $appointmentsSelectedLabel }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="box-body">
                        @if (!empty($appointmentsDays))
                            <div id="paginator1" class="datepaginator">
                                <ul class="pagination">
                                    <li>
                                        @if ($prevDay)
                                            <a href="{{ $buildDayUrl($prevDay) }}"
                                               class="dp-nav dp-nav-left"
                                               title="{{ $prevDay['title'] ?? '' }}"
                                               style="width: 24px;">
                                                <i class="glyphicon glyphicon-chevron-left dp-nav-left"></i>
                                            </a>
                                        @else
                                            <span class="dp-nav dp-nav-left disabled" style="width: 24px;">
                                                <i class="glyphicon glyphicon-chevron-left dp-nav-left"></i>
                                            </span>
                                        @endif
                                    </li>
                                    @foreach ($appointmentsDays as $day)
                                        @php
                                            $dayClasses = ['dp-item'];
                                            if (!empty($day['is_selected'])) $dayClasses[] = 'dp-selected';
                                            if (!empty($day['is_today'])) $dayClasses[] = 'dp-today';
                                            if (empty($day['is_selected']) && empty($day['is_today'])) $dayClasses[] = 'dp-off';
                                            $width = !empty($day['is_selected']) ? 144 : 48;
                                        @endphp
                                        <li>
                                            <a href="{{ $buildDayUrl($day) }}"
                                               class="{{ implode(' ', $dayClasses) }}"
                                               data-moment="{{ $day['date'] ?? '' }}"
                                               title="{{ $day['title'] ?? '' }}"
                                               style="width: {{ $width }}px;">
                                                {!! $day['label'] ?? '' !!}
                                            </a>
                                        </li>
                                    @endforeach
                                    <li>
                                        @if ($nextDay)
                                            <a href="{{ $buildDayUrl($nextDay) }}"
                                               class="dp-nav dp-nav-right"
                                               title="{{ $nextDay['title'] ?? '' }}"
                                               style="width: 24px;">
                                                <i class="glyphicon glyphicon-chevron-right dp-nav-right"></i>
                                            </a>
                                        @else
                                            <span class="dp-nav dp-nav-right disabled" style="width: 24px;">
                                                <i class="glyphicon glyphicon-chevron-right dp-nav-right"></i>
                                            </span>
                                        @endif
                                    </li>
                                </ul>
                            </div>
                        @else
                            <div class="text-center text-fade py-20">No hay fechas disponibles en la agenda.</div>
                        @endif
                    </div>
                    <div class="box-body">
                        <div class="inner-user-div4">
                            @if (!empty($appointments))
                                @php $appointmentsCount = count($appointments); @endphp
                                @foreach ($appointments as $apptIndex => $appointment)
                                    @php
                                        $hasDivider = $apptIndex < $appointmentsCount - 1;
                                        $footerClasses = 'd-flex justify-content-between align-items-end py-10';
                                        if ($hasDivider) $footerClasses .= ' mb-15 bb-dashed border-bottom';
                                        $callClasses = 'waves-effect waves-circle btn btn-circle btn-primary-light btn-sm';
                                        if (!empty($appointment['call_disabled'])) $callClasses .= ' disabled opacity-50';
                                        $apptStatusVariant = !empty($appointment['status_variant']) ? (string) $appointment['status_variant'] : 'secondary';
                                    @endphp
                                    <div class="{{ $hasDivider ? 'mb-15' : '' }}">
                                        <div class="d-flex align-items-center mb-10">
                                            <div class="me-15">
                                                <img src="{{ asset($appointment['avatar'] ?? 'images/avatar/1.jpg') }}"
                                                     class="avatar avatar-lg rounded10 bg-primary-light" alt=""/>
                                            </div>
                                            <div class="d-flex flex-column flex-grow-1 fw-500">
                                                <p class="hover-primary text-fade mb-1 fs-14">{{ $appointment['patient'] ?? 'Paciente' }}</p>
                                                @php
                                                    $procRaw = $appointment['procedure'] ?? 'Consulta';
                                                    $procParts = array_map('trim', explode(' - ', (string) $procRaw));
                                                    $procedureDisplay = $procParts ? end($procParts) : $procRaw;
                                                @endphp
                                                <span class="text-dark fs-16">{{ $procedureDisplay }}</span>
                                                @if (!empty($appointment['afiliacion_label']))
                                                    <span class="text-fade fs-12">{{ $appointment['afiliacion_label'] }}</span>
                                                @endif
                                            </div>
                                            <div class="text-end">
                                                @if (!empty($appointment['status_label']))
                                                    <span class="badge badge-{{ $apptStatusVariant }} mb-10">
                                                        {{ $appointment['status_label'] }}
                                                    </span>
                                                @endif
                                                <a href="{{ $appointment['call_href'] ?? 'javascript:void(0);' }}"
                                                   class="{{ $callClasses }}"
                                                   @if (!empty($appointment['call_disabled'])) aria-disabled="true" @endif>
                                                    <i class="fa fa-phone"></i>
                                                </a>
                                            </div>
                                        </div>
                                        <div class="{{ $footerClasses }}">
                                            <div>
                                                <p class="mb-0 text-muted">
                                                    <i class="fa fa-clock-o me-5"></i> {{ $appointment['time'] ?? '--:--' }}
                                                    @if (!empty($appointment['hc_label']))
                                                        <span class="mx-20">{{ $appointment['hc_label'] }}</span>
                                                    @endif
                                                </p>
                                            </div>
                                            <div>
                                                <div class="dropdown">
                                                    <a data-bs-toggle="dropdown" href="#" class="base-font mx-10"><i class="ti-more-alt text-muted"></i></a>
                                                    <div class="dropdown-menu dropdown-menu-end">
                                                        <a class="dropdown-item" href="javascript:void(0);"><i class="ti-import"></i> Detalles</a>
                                                        <a class="dropdown-item" href="javascript:void(0);"><i class="ti-export"></i> Reportes</a>
                                                        <a class="dropdown-item" href="javascript:void(0);"><i class="ti-printer"></i> Imprimir</a>
                                                        <div class="dropdown-divider"></div>
                                                        <a class="dropdown-item" href="javascript:void(0);"><i class="ti-settings"></i> Gestionar</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="text-center py-30 text-fade">No hay citas registradas para la fecha seleccionada.</div>
                            @endif
                        </div>
                    </div>
                </div>

                @if (!empty($focusAreas))
                    <div class="box">
                        <div class="box-header">
                            <h4 class="box-title">Áreas de enfoque</h4>
                        </div>
                        <div class="box-body">
                            <div class="d-flex flex-wrap gap-10">
                                @foreach ($focusAreas as $area)
                                    <span class="badge badge-primary-light px-10 py-5">
                                        <i class="fa fa-check-circle me-5 text-primary"></i>
                                        {{ $area }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                @if (!empty($supportChannels))
                    <div class="box">
                        <div class="box-header">
                            <h4 class="box-title">Distribución operativa</h4>
                        </div>
                        <div class="box-body">
                            <ul class="list-unstyled mb-0">
                                @foreach ($supportChannels as $channel)
                                    <li class="mb-10 d-flex align-items-start">
                                        <i class="fa fa-headset text-primary me-10 mt-1"></i>
                                        <div>
                                            <span class="d-block fw-600">{{ $channel['label'] }}</span>
                                            <span class="text-fade">{{ $channel['value'] }}</span>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-xl-8 col-12">
                {{-- General info --}}
                <div class="box">
                    <div class="box-header">
                        <h4 class="box-title">Información general</h4>
                    </div>
                    <div class="box-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4 text-fade">Nombre completo</dt>
                            <dd class="col-sm-8">{{ $doctor['name'] ?? '' ?: '<span class="text-fade">No registrado</span>' }}</dd>

                            <dt class="col-sm-4 text-fade">Usuario</dt>
                            <dd class="col-sm-8">
                                @if (!empty($doctor['username']))
                                    {{ $doctor['username'] }}
                                @else
                                    <span class="text-fade">No registrado</span>
                                @endif
                            </dd>

                            <dt class="col-sm-4 text-fade">Correo electrónico</dt>
                            <dd class="col-sm-8">
                                @if (!empty($doctor['email']))
                                    <a href="mailto:{{ $doctor['email'] }}">{{ $doctor['email'] }}</a>
                                @else
                                    <span class="text-fade">No registrado</span>
                                @endif
                            </dd>

                            <dt class="col-sm-4 text-fade">Rol asignado</dt>
                            <dd class="col-sm-8">
                                @if (!empty($doctor['role_name']))
                                    {{ $doctor['role_name'] }}
                                @else
                                    <span class="text-fade">No registrado</span>
                                @endif
                            </dd>

                            <dt class="col-sm-4 text-fade">Especialidad</dt>
                            <dd class="col-sm-8">
                                @if (!empty($doctor['especialidad']))
                                    {{ $doctor['especialidad'] }}
                                @else
                                    <span class="text-fade">No registrado</span>
                                @endif
                            </dd>

                            <dt class="col-sm-4 text-fade">Subespecialidad</dt>
                            <dd class="col-sm-8">
                                @if (!empty($doctor['subespecialidad']))
                                    {{ $doctor['subespecialidad'] }}
                                @else
                                    <span class="text-fade">No registrado</span>
                                @endif
                            </dd>

                            <dt class="col-sm-4 text-fade">Sede</dt>
                            <dd class="col-sm-8">
                                @if (!empty($doctor['sede']))
                                    {{ $doctor['sede'] }}
                                @else
                                    <span class="text-fade">No registrado</span>
                                @endif
                            </dd>

                            <dt class="col-sm-4 text-fade">Cédula</dt>
                            <dd class="col-sm-8">
                                @if (!empty($doctor['cedula']))
                                    {{ $doctor['cedula'] }}
                                @else
                                    <span class="text-fade">No registrado</span>
                                @endif
                            </dd>

                            <dt class="col-sm-4 text-fade">Registro profesional</dt>
                            <dd class="col-sm-8">
                                @if (!empty($doctor['registro']))
                                    {{ $doctor['registro'] }}
                                @else
                                    <span class="text-fade">No registrado</span>
                                @endif
                            </dd>
                        </dl>

                        @if (!empty($availabilitySummary))
                            <div class="row g-3 mt-20 pt-15 border-top">
                                <div class="col-sm-6 col-lg-3">
                                    <p class="text-fade mb-0">Agenda habitual</p>
                                    <h5 class="mb-0">{{ $availabilitySummary['schedule_label'] ?? 'Sin agenda futura' }}</h5>
                                </div>
                                <div class="col-sm-6 col-lg-3">
                                    <p class="text-fade mb-0">Próximos 7 días</p>
                                    <h5 class="mb-0">{{ $availabilitySummary['next_7d_appointments'] ?? 0 }} citas</h5>
                                </div>
                                <div class="col-sm-6 col-lg-3">
                                    <p class="text-fade mb-0">Pacientes hoy</p>
                                    <h5 class="mb-0">{{ $availabilitySummary['today_patients'] ?? 0 }} casos</h5>
                                </div>
                                <div class="col-sm-6 col-lg-3">
                                    <p class="text-fade mb-0">Última cirugía</p>
                                    <h5 class="mb-0">{{ $availabilitySummary['latest_surgery_label'] ?? 'Sin registros recientes' }}</h5>
                                </div>
                            </div>
                            <div class="row g-3 mt-5">
                                <div class="col-sm-6 col-lg-3">
                                    <p class="text-fade mb-0">Último examen</p>
                                    <h5 class="mb-0">{{ $availabilitySummary['latest_exam_label'] ?? 'Sin registros recientes' }}</h5>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                @if (!empty($biographyParagraphs))
                    <div class="box">
                        <div class="box-header">
                            <h4 class="box-title">Resumen operativo</h4>
                        </div>
                        <div class="box-body">
                            @foreach ($biographyParagraphs as $paragraph)
                                <p>{{ $paragraph }}</p>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (!empty($careProgress))
                    <div class="box">
                        <div class="box-header">
                            <h4 class="box-title">Indicadores de atención</h4>
                        </div>
                        <div class="box-body">
                            @foreach ($careProgress as $metric)
                                <div class="mb-20">
                                    <div class="d-flex align-items-center justify-content-between mb-5">
                                        <h5 class="mb-0">{{ $metric['value'] }}%</h5>
                                        <h5 class="mb-0 text-fade">{{ $metric['label'] }}</h5>
                                    </div>
                                    <div class="progress progress-xs">
                                        <div class="progress-bar progress-bar-{{ $metric['variant'] }}"
                                             role="progressbar"
                                             aria-valuenow="{{ $metric['value'] }}"
                                             aria-valuemin="0" aria-valuemax="100"
                                             style="width: {{ $metric['value'] }}%">
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="box">
                    <div class="box-header">
                        <h4 class="box-title">Resumen de actividad</h4>
                    </div>
                    <div class="box-body">
                        @if (!empty($activityStats))
                            <div class="row g-3">
                                @foreach ($activityStats as $stat)
                                    <div class="col-md-3">
                                        <div class="bg-lightest rounded10 p-20 h-100">
                                            <p class="text-fade mb-10">{{ $stat['label'] }}</p>
                                            <h3 class="mb-5 fw-600">
                                                {{ $stat['value'] }}
                                                @if (!empty($stat['suffix']))
                                                    <span class="fs-16 text-fade">{{ $stat['suffix'] }}</span>
                                                @endif
                                            </h3>
                                            @if (!empty($stat['trend']))
                                                @php
                                                    $trendDirection = $stat['trend']['direction'] ?? 'up';
                                                    $trendClass = $trendDirection === 'up' ? 'success' : 'danger';
                                                @endphp
                                                <span class="badge badge-{{ $trendClass }}-light text-{{ $trendClass }}">
                                                    <i class="fa fa-caret-{{ $trendDirection === 'up' ? 'up' : 'down' }} me-5"></i>
                                                    {{ $stat['trend']['value'] ?? '' }} vs. mes anterior
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <p class="text-fade mt-15 mb-0">Los indicadores combinan la actividad registrada en agenda, cirugía, solicitudes y órdenes diagnósticas del doctor.</p>
                        @else
                            <div class="alert alert-info mb-0 d-flex align-items-start">
                                <i class="fa fa-info-circle me-10 mt-1"></i>
                                <div>
                                    <strong>Sin estadísticas registradas.</strong>
                                    <p class="mb-0">Conecta este módulo con la agenda y los reportes de procedimientos para visualizar citas, pacientes y otros indicadores relacionados con este doctor.</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="box">
                    <div class="box-header">
                        <h4 class="box-title">Actividad reciente</h4>
                    </div>
                    <div class="box-body">
                        @if (!empty($milestones))
                            @foreach ($milestones as $milestone)
                                <div class="d-flex mb-15">
                                    <div class="me-15">
                                        <span class="badge badge-primary-light px-10 py-5 fw-600">{{ $milestone['year'] }}</span>
                                    </div>
                                    <div>
                                        <h5 class="mb-5">{{ $milestone['title'] }}</h5>
                                        <p class="mb-0 text-fade">{{ $milestone['description'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <p class="text-fade mb-0">No se encontraron movimientos recientes para este doctor.</p>
                        @endif
                    </div>
                </div>

                @if (!empty($researchHighlights))
                    <div class="box">
                        <div class="box-header">
                            <h4 class="box-title">Top procedimientos</h4>
                        </div>
                        <div class="box-body">
                            @foreach ($researchHighlights as $highlight)
                                <div class="mb-15">
                                    <h5 class="mb-5">
                                        {{ $highlight['year'] }} · {{ $highlight['title'] }}
                                    </h5>
                                    <p class="text-fade mb-0">{{ $highlight['description'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="box">
                    <div class="box-header">
                        <h4 class="box-title">Notas operativas</h4>
                    </div>
                    <div class="box-body">
                        @if (!empty($operationalNotes))
                            <ul class="list-unstyled mb-0">
                                @foreach ($operationalNotes as $note)
                                    <li class="mb-10"><i class="fa fa-line-chart text-primary me-10"></i>{{ $note }}</li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-fade mb-0">Sin observaciones operativas relevantes en la ventana analizada.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

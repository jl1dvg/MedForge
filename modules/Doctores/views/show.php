<?php
/** @var array<string, mixed> $doctor */

$doctor = $doctor ?? [];
$displayName = $doctor['display_name'] ?? ($doctor['name'] ?? 'Doctor');
$coverUrl = format_profile_photo_url($doctor['profile_photo'] ?? null);
if (!$coverUrl) {
    $coverUrl = asset('images/avatar/375x200/1.jpg');
}

$avatarUrl = format_profile_photo_url($doctor['profile_photo'] ?? null);
if (!$avatarUrl) {
    $avatarUrl = asset('images/avatar/1.jpg');
}

$firmaUrl = null;
if (!empty($doctor['firma'])) {
    $firmaUrl = format_profile_photo_url($doctor['firma']);
    if (!$firmaUrl) {
        $firmaUrl = asset(ltrim($doctor['firma'], '/'));
    }
}

$statusVariant = $doctor['status_variant'] ?? null;
$statusLabel = $doctor['status'] ?? null;
$statusBadgeMap = [
    'primary' => 'primary',
    'success' => 'success',
    'danger' => 'danger',
    'info' => 'info',
];
$statusBadgeClass = $statusBadgeMap[$statusVariant] ?? 'secondary';

$printValue = static function (?string $value): string {
    return $value !== null
        ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
        : '<span class="text-fade">No registrado</span>';
};

/** @var array<int, array<string, mixed>> $todayPatients */
$todayPatients = $todayPatients ?? [];
/** @var array<int, array<string, mixed>> $activityStats */
$activityStats = $activityStats ?? [];
/** @var array<int, array<string, mixed>> $careProgress */
$careProgress = $careProgress ?? [];
/** @var array<int, array<string, string>> $milestones */
$milestones = $milestones ?? [];
/** @var array<int, string> $biographyParagraphs */
$biographyParagraphs = $biographyParagraphs ?? [];
/** @var array<string, mixed> $availabilitySummary */
$availabilitySummary = $availabilitySummary ?? [];
/** @var array<int, string> $focusAreas */
$focusAreas = $focusAreas ?? [];
/** @var array<int, array<string, string>> $supportChannels */
$supportChannels = $supportChannels ?? [];
/** @var array<int, array<string, string>> $researchHighlights */
$researchHighlights = $researchHighlights ?? [];
/** @var array<int, array<string, mixed>> $appointmentsDays */
$appointmentsDays = $appointmentsDays ?? [];
/** @var array<int, array<string, mixed>> $appointments */
$appointments = $appointments ?? [];
$appointmentsSelectedDate = $appointmentsSelectedDate ?? null;
$appointmentsSelectedLabel = $appointmentsSelectedLabel ?? null;

$primarySupportLabel = $supportChannels[0]['label'] ?? null;
$primarySupportValue = $supportChannels[0]['value'] ?? null;

$doctorId = isset($doctor['id']) ? (int)$doctor['id'] : null;
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

$buildDayUrl = static function (?array $day) use ($doctorDetailUrl): string {
    if ($doctorDetailUrl === null || $day === null || empty($day['date'])) {
        return 'javascript:void(0);';
    }

    return $doctorDetailUrl . '?fecha=' . urlencode((string)$day['date']);
};
$scripts = array_merge($scripts ?? [], [
    'assets/vendor_components/apexcharts-bundle/dist/apexcharts.js',
    'assets/vendor_components/OwlCarousel2/dist/owl.carousel.js',
    'assets/vendor_components/date-paginator/moment.min.js',
    'assets/vendor_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js',
    'assets/vendor_components/date-paginator/bootstrap-datepaginator.min.js',
    'js/pages/doctor-details.js',
]);
?>

<div class="container-full">
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h4 class="page-title">Perfil del doctor</h4>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/"><i class="mdi mdi-home-outline"></i></a></li>
                            <li class="breadcrumb-item"><a href="/doctores">Doctors</a></li>
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
                <div class="box">
                    <div class="box-body p-0">
                        <div class="position-relative">
                            <img src="<?= htmlspecialchars($coverUrl, ENT_QUOTES, 'UTF-8') ?>"
                                 class="img-fluid w-100"
                                 alt="<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>">
                            <?php if ($statusLabel): ?>
                                <span class="badge badge-<?= htmlspecialchars($statusBadgeClass, ENT_QUOTES, 'UTF-8') ?> px-15 py-5 shadow"
                                      style="position: absolute; top: 15px; right: 15px;">
                                    <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="p-20 text-center">
                            <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>"
                                 class="avatar avatar-xxl rounded-circle border border-3 border-white shadow mb-15"
                                 alt="<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>">
                            <h3 class="mb-5"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></h3>
                            <?php if (!empty($doctor['especialidad'])): ?>
                                <p class="text-fade mb-5"><?= htmlspecialchars($doctor['especialidad'], ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                            <?php if (!empty($doctor['subespecialidad'])): ?>
                                <p class="text-fade mb-5"><?= htmlspecialchars($doctor['subespecialidad'], ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                            <?php if (!empty($doctor['email'])): ?>
                                <p class="mb-5">
                                    <a href="mailto:<?= htmlspecialchars($doctor['email'], ENT_QUOTES, 'UTF-8') ?>"
                                       class="text-primary">
                                        <i class="fa fa-envelope me-5"></i><?= htmlspecialchars($doctor['email'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                </p>
                            <?php endif; ?>
                            <?php if (!empty($doctor['sede'])): ?>
                                <p class="mb-0 text-fade">
                                    <i class="mdi mdi-hospital-building me-5"></i><?= htmlspecialchars($doctor['sede'], ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="box-footer text-center">
                        <div class="d-flex justify-content-center gap-10 flex-wrap">
                            <a href="/doctores" class="btn btn-outline-primary btn-sm">
                                <i class="fa fa-arrow-left me-5"></i> Volver al listado
                            </a>
                            <?php if (!empty($doctor['email'])): ?>
                                <a href="mailto:<?= htmlspecialchars($doctor['email'], ENT_QUOTES, 'UTF-8') ?>"
                                   class="btn btn-primary btn-sm">
                                    <i class="fa fa-paper-plane me-5"></i> Contactar
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="box">
                    <div class="box-header">
                        <h4 class="box-title">Estado de la cuenta</h4>
                    </div>
                    <div class="box-body">
                        <div class="d-flex flex-wrap gap-10">
                            <span class="badge badge-<?= $doctor['is_approved'] ? 'success' : 'warning' ?> px-15 py-5">
                                <?= $doctor['is_approved'] ? 'Aprobado' : 'Pendiente de aprobación' ?>
                            </span>
                            <span class="badge badge-<?= $doctor['is_subscribed'] ? 'info' : 'secondary' ?> px-15 py-5">
                                <?= $doctor['is_subscribed'] ? 'Suscripción activa' : 'Sin suscripción activa' ?>
                            </span>
                            <?php if ($statusLabel): ?>
                                <span class="badge badge-<?= htmlspecialchars($statusBadgeClass, ENT_QUOTES, 'UTF-8') ?> px-15 py-5">
                                    <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="box">
                    <div class="box-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="box-title mb-0">Appointments</h4>
                            <?php if ($appointmentsSelectedLabel): ?>
                                <span class="text-fade fs-12"><?= htmlspecialchars($appointmentsSelectedLabel, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="box-body">
                        <?php if (!empty($appointmentsDays)): ?>
                            <div id="paginator1" class="datepaginator">
                                <ul class="pagination">
                                    <li>
                                        <?php if ($prevDay): ?>
                                            <a href="<?= htmlspecialchars($buildDayUrl($prevDay), ENT_QUOTES, 'UTF-8') ?>"
                                               class="dp-nav dp-nav-left"
                                               title="<?= htmlspecialchars($prevDay['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                               style="width: 24px;">
                                                <i class="glyphicon glyphicon-chevron-left dp-nav-left"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="dp-nav dp-nav-left disabled" style="width: 24px;">
                                                <i class="glyphicon glyphicon-chevron-left dp-nav-left"></i>
                                            </span>
                                        <?php endif; ?>
                                    </li>
                                    <?php foreach ($appointmentsDays as $day): ?>
                                        <?php
                                        $dayClasses = ['dp-item'];
                                        if (!empty($day['is_selected'])) {
                                            $dayClasses[] = 'dp-selected';
                                        }
                                        if (!empty($day['is_today'])) {
                                            $dayClasses[] = 'dp-today';
                                        }
                                        if (empty($day['is_selected']) && empty($day['is_today'])) {
                                            $dayClasses[] = 'dp-off';
                                        }
                                        $width = !empty($day['is_selected']) ? 144 : 48;
                                        ?>
                                        <li>
                                            <a href="<?= htmlspecialchars($buildDayUrl($day), ENT_QUOTES, 'UTF-8') ?>"
                                               class="<?= htmlspecialchars(implode(' ', $dayClasses), ENT_QUOTES, 'UTF-8') ?>"
                                               data-moment="<?= htmlspecialchars((string)($day['date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                               title="<?= htmlspecialchars($day['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                               style="width: <?= $width ?>px;">
                                                <?= $day['label'] ?? '' ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                    <li>
                                        <?php if ($nextDay): ?>
                                            <a href="<?= htmlspecialchars($buildDayUrl($nextDay), ENT_QUOTES, 'UTF-8') ?>"
                                               class="dp-nav dp-nav-right"
                                               title="<?= htmlspecialchars($nextDay['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                               style="width: 24px;">
                                                <i class="glyphicon glyphicon-chevron-right dp-nav-right"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="dp-nav dp-nav-right disabled" style="width: 24px;">
                                                <i class="glyphicon glyphicon-chevron-right dp-nav-right"></i>
                                            </span>
                                        <?php endif; ?>
                                    </li>
                                </ul>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-fade py-20">No hay fechas disponibles en la agenda.</div>
                        <?php endif; ?>
                    </div>
                    <div class="box-body">
                        <div class="inner-user-div4">
                            <?php if (!empty($appointments)): ?>
                                <?php $appointmentsCount = count($appointments); ?>
                                <?php foreach ($appointments as $index => $appointment): ?>
                                    <?php
                                    $hasDivider = $index < $appointmentsCount - 1;
                                    $footerClasses = 'd-flex justify-content-between align-items-end py-10';
                                    if ($hasDivider) {
                                        $footerClasses .= ' mb-15 bb-dashed border-bottom';
                                    }
                                    $callClasses = 'waves-effect waves-circle btn btn-circle btn-primary-light btn-sm';
                                    if (!empty($appointment['call_disabled'])) {
                                        $callClasses .= ' disabled opacity-50';
                                    }
                                    $statusVariant = !empty($appointment['status_variant']) ? (string)$appointment['status_variant'] : 'secondary';
                                    ?>
                                    <div class="<?= $hasDivider ? 'mb-15' : '' ?>">
                                        <div class="d-flex align-items-center mb-10">
                                            <div class="me-15">
                                                <img src="<?= htmlspecialchars(asset($appointment['avatar'] ?? 'images/avatar/1.jpg'), ENT_QUOTES, 'UTF-8') ?>"
                                                     class="avatar avatar-lg rounded10 bg-primary-light" alt=""/>
                                            </div>
                                            <div class="d-flex flex-column flex-grow-1 fw-500">
                                                <p class="hover-primary text-fade mb-1 fs-14"><?= htmlspecialchars($appointment['patient'] ?? 'Paciente', ENT_QUOTES, 'UTF-8') ?></p>
                                                <?php
                                                    $procRaw = $appointment['procedure'] ?? 'Consulta';
                                                    $procParts = array_map('trim', explode(' - ', (string)$procRaw));
                                                    $procedureDisplay = $procParts ? end($procParts) : $procRaw;
                                                ?>
                                                <span class="text-dark fs-16"><?= htmlspecialchars($procedureDisplay, ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php if (!empty($appointment['afiliacion_label'])): ?>
                                                    <span class="text-fade fs-12"><?= htmlspecialchars($appointment['afiliacion_label'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-end">
                                                <?php if (!empty($appointment['status_label'])): ?>
                                                    <span class="badge badge-<?= htmlspecialchars($statusVariant, ENT_QUOTES, 'UTF-8') ?> mb-10">
                                                        <?= htmlspecialchars($appointment['status_label'], ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                <?php endif; ?>
                                                <a href="<?= htmlspecialchars($appointment['call_href'] ?? 'javascript:void(0);', ENT_QUOTES, 'UTF-8') ?>"
                                                   class="<?= htmlspecialchars($callClasses, ENT_QUOTES, 'UTF-8') ?>"
                                                   <?php if (!empty($appointment['call_disabled'])): ?>aria-disabled="true"<?php endif; ?>>
                                                    <i class="fa fa-phone"></i>
                                                </a>
                                            </div>
                                        </div>
                                        <div class="<?= htmlspecialchars($footerClasses, ENT_QUOTES, 'UTF-8') ?>">
                                            <div>
                                                <p class="mb-0 text-muted">
                                                    <i class="fa fa-clock-o me-5"></i> <?= htmlspecialchars($appointment['time'] ?? '--:--', ENT_QUOTES, 'UTF-8') ?>
                                                    <?php if (!empty($appointment['hc_label'])): ?>
                                                        <span class="mx-20"><?= htmlspecialchars($appointment['hc_label'], ENT_QUOTES, 'UTF-8') ?></span>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                            <div>
                                                <div class="dropdown">
                                                    <a data-bs-toggle="dropdown" href="#" class="base-font mx-10"><i
                                                                class="ti-more-alt text-muted"></i></a>
                                                    <div class="dropdown-menu dropdown-menu-end">
                                                        <a class="dropdown-item" href="javascript:void(0);"><i
                                                                    class="ti-import"></i> Detalles</a>
                                                        <a class="dropdown-item" href="javascript:void(0);"><i
                                                                    class="ti-export"></i> Reportes</a>
                                                        <a class="dropdown-item" href="javascript:void(0);"><i
                                                                    class="ti-printer"></i> Imprimir</a>
                                                        <div class="dropdown-divider"></div>
                                                        <a class="dropdown-item" href="javascript:void(0);"><i
                                                                    class="ti-settings"></i> Gestionar</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-30 text-fade">No hay citas registradas para la fecha
                                    seleccionada.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (!empty($focusAreas)): ?>
                    <div class="box">
                        <div class="box-header">
                            <h4 class="box-title">Áreas de enfoque</h4>
                        </div>
                        <div class="box-body">
                            <div class="d-flex flex-wrap gap-10">
                                <?php foreach ($focusAreas as $area): ?>
                                    <span class="badge badge-primary-light px-10 py-5">
                                        <i class="fa fa-check-circle me-5 text-primary"></i>
                                        <?= htmlspecialchars($area, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($supportChannels)): ?>
                    <div class="box">
                        <div class="box-header">
                            <h4 class="box-title">Canales de coordinación</h4>
                        </div>
                        <div class="box-body">
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($supportChannels as $channel): ?>
                                    <li class="mb-10 d-flex align-items-start">
                                        <i class="fa fa-headset text-primary me-10 mt-1"></i>
                                        <div>
                                            <span class="d-block fw-600"><?= htmlspecialchars($channel['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="text-fade"><?= htmlspecialchars($channel['value'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-xl-8 col-12">
                <div class="box">
                    <div class="box-header">
                        <h4 class="box-title">Información general</h4>
                    </div>
                    <div class="box-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4 text-fade">Nombre completo</dt>
                            <dd class="col-sm-8"><?= $printValue($doctor['name'] ?? null) ?></dd>

                            <dt class="col-sm-4 text-fade">Usuario</dt>
                            <dd class="col-sm-8"><?= $printValue($doctor['username'] ?? null) ?></dd>

                            <dt class="col-sm-4 text-fade">Correo electrónico</dt>
                            <dd class="col-sm-8">
                                <?php if (!empty($doctor['email'])): ?>
                                    <a href="mailto:<?= htmlspecialchars($doctor['email'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($doctor['email'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-fade">No registrado</span>
                                <?php endif; ?>
                            </dd>

                            <dt class="col-sm-4 text-fade">Rol asignado</dt>
                            <dd class="col-sm-8"><?= $printValue($doctor['role_name'] ?? null) ?></dd>

                            <dt class="col-sm-4 text-fade">Especialidad</dt>
                            <dd class="col-sm-8"><?= $printValue($doctor['especialidad'] ?? null) ?></dd>

                            <dt class="col-sm-4 text-fade">Subespecialidad</dt>
                            <dd class="col-sm-8"><?= $printValue($doctor['subespecialidad'] ?? null) ?></dd>

                            <dt class="col-sm-4 text-fade">Sede</dt>
                            <dd class="col-sm-8"><?= $printValue($doctor['sede'] ?? null) ?></dd>

                            <dt class="col-sm-4 text-fade">Cédula</dt>
                            <dd class="col-sm-8"><?= $printValue($doctor['cedula'] ?? null) ?></dd>

                            <dt class="col-sm-4 text-fade">Registro profesional</dt>
                            <dd class="col-sm-8"><?= $printValue($doctor['registro'] ?? null) ?></dd>
                        </dl>

                        <?php if (!empty($availabilitySummary)): ?>
                            <div class="row g-3 mt-20 pt-15 border-top">
                                <div class="col-sm-6 col-lg-3">
                                    <p class="text-fade mb-0">Jornada presencial</p>
                                    <h5 class="mb-0"><?= htmlspecialchars((string)($availabilitySummary['working_hours_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h5>
                                </div>
                                <div class="col-sm-6 col-lg-3">
                                    <p class="text-fade mb-0">Consultas presenciales</p>
                                    <h5 class="mb-0"><?= htmlspecialchars((string)$availabilitySummary['in_person_slots'], ENT_QUOTES, 'UTF-8') ?>
                                        turnos</h5>
                                </div>
                                <div class="col-sm-6 col-lg-3">
                                    <p class="text-fade mb-0">Telemedicina</p>
                                    <h5 class="mb-0"><?= htmlspecialchars((string)$availabilitySummary['virtual_slots'], ENT_QUOTES, 'UTF-8') ?>
                                        cupos</h5>
                                </div>
                                <div class="col-sm-6 col-lg-3">
                                    <p class="text-fade mb-0">Tiempo de respuesta</p>
                                    <h5 class="mb-0"><?= htmlspecialchars((string)$availabilitySummary['response_time_hours'], ENT_QUOTES, 'UTF-8') ?>
                                        h</h5>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($biographyParagraphs)): ?>
                    <div class="box">
                        <div class="box-header">
                            <h4 class="box-title">Biografía</h4>
                        </div>
                        <div class="box-body">
                            <?php foreach ($biographyParagraphs as $paragraph): ?>
                                <p><?= htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($careProgress)): ?>
                    <div class="box">
                        <div class="box-header">
                            <h4 class="box-title">Indicadores de atención</h4>
                        </div>
                        <div class="box-body">
                            <?php foreach ($careProgress as $metric): ?>
                                <div class="mb-20">
                                    <div class="d-flex align-items-center justify-content-between mb-5">
                                        <h5 class="mb-0"><?= htmlspecialchars((string)$metric['value'], ENT_QUOTES, 'UTF-8') ?>
                                            %</h5>
                                        <h5 class="mb-0 text-fade"><?= htmlspecialchars($metric['label'], ENT_QUOTES, 'UTF-8') ?></h5>
                                    </div>
                                    <div class="progress progress-xs">
                                        <div class="progress-bar progress-bar-<?= htmlspecialchars($metric['variant'], ENT_QUOTES, 'UTF-8') ?>"
                                             role="progressbar"
                                             aria-valuenow="<?= htmlspecialchars((string)$metric['value'], ENT_QUOTES, 'UTF-8') ?>"
                                             aria-valuemin="0" aria-valuemax="100"
                                             style="width: <?= htmlspecialchars((string)$metric['value'], ENT_QUOTES, 'UTF-8') ?>%">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="box">
                    <div class="box-header">
                        <h4 class="box-title">Resumen de actividad</h4>
                    </div>
                    <div class="box-body">
                        <?php if (!empty($activityStats)): ?>
                            <div class="row g-3">
                                <?php foreach ($activityStats as $stat): ?>
                                    <div class="col-md-4">
                                        <div class="bg-lightest rounded10 p-20 h-100">
                                            <p class="text-fade mb-10"><?= htmlspecialchars($stat['label'], ENT_QUOTES, 'UTF-8') ?></p>
                                            <h3 class="mb-5 fw-600">
                                                <?= htmlspecialchars((string)$stat['value'], ENT_QUOTES, 'UTF-8') ?>
                                                <?php if (!empty($stat['suffix'])): ?>
                                                    <span class="fs-16 text-fade"><?= htmlspecialchars($stat['suffix'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php endif; ?>
                                            </h3>
                                            <?php if (!empty($stat['trend'])): ?>
                                                <?php
                                                $trendDirection = $stat['trend']['direction'] ?? 'up';
                                                $trendClass = $trendDirection === 'up' ? 'success' : 'danger';
                                                ?>
                                                <span class="badge badge-<?= $trendClass ?>-light text-<?= $trendClass ?>">
                                                    <i class="fa fa-caret-<?= $trendDirection === 'up' ? 'up' : 'down' ?> me-5"></i>
                                                    <?= htmlspecialchars($stat['trend']['value'] ?? '', ENT_QUOTES, 'UTF-8') ?> vs. mes anterior
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <p class="text-fade mt-15 mb-0">Los indicadores combinan la actividad registrada en agenda,
                                reportes clínicos y sesiones de telemedicina coordinadas por el equipo de soporte.</p>
                        <?php else: ?>
                            <div class="alert alert-info mb-0 d-flex align-items-start">
                                <i class="fa fa-info-circle me-10 mt-1"></i>
                                <div>
                                    <strong>Sin estadísticas registradas.</strong>
                                    <p class="mb-0">Conecta este módulo con la agenda y los reportes de procedimientos
                                        para visualizar citas, pacientes y otros indicadores relacionados con este
                                        doctor.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="box">
                    <div class="box-header">
                        <h4 class="box-title">Hitos profesionales</h4>
                    </div>
                    <div class="box-body">
                        <?php if (!empty($milestones)): ?>
                            <?php foreach ($milestones as $milestone): ?>
                                <div class="d-flex mb-15">
                                    <div class="me-15">
                                        <span class="badge badge-primary-light px-10 py-5 fw-600"><?= htmlspecialchars($milestone['year'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <div>
                                        <h5 class="mb-5"><?= htmlspecialchars($milestone['title'], ENT_QUOTES, 'UTF-8') ?></h5>
                                        <p class="mb-0 text-fade"><?= htmlspecialchars($milestone['description'], ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-fade mb-0">Registra hitos importantes de la trayectoria del profesional para
                                compartirlos con el equipo médico.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($researchHighlights)): ?>
                    <div class="box">
                        <div class="box-header">
                            <h4 class="box-title">Investigación y publicaciones</h4>
                        </div>
                        <div class="box-body">
                            <?php foreach ($researchHighlights as $highlight): ?>
                                <div class="mb-15">
                                    <h5 class="mb-5">
                                        <?= htmlspecialchars($highlight['year'], ENT_QUOTES, 'UTF-8') ?>
                                        · <?= htmlspecialchars($highlight['title'], ENT_QUOTES, 'UTF-8') ?>
                                    </h5>
                                    <p class="text-fade mb-0"><?= htmlspecialchars($highlight['description'], ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="box">
                    <div class="box-header">
                        <h4 class="box-title">Notas operativas</h4>
                    </div>
                    <div class="box-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-10"><i class="fa fa-calendar-check text-primary me-10"></i> Confirmar
                                disponibilidad
                                de <?= htmlspecialchars((string)($availabilitySummary['virtual_slots'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>
                                teleconsultas
                                y <?= htmlspecialchars((string)($availabilitySummary['in_person_slots'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>
                                turnos presenciales antes de las 12h00.
                            </li>
                            <?php if ($primarySupportLabel && $primarySupportValue): ?>
                                <li class="mb-10"><i class="fa fa-users text-success me-10"></i> Coordinar
                                    interconsultas
                                    con <?= htmlspecialchars($primarySupportLabel, ENT_QUOTES, 'UTF-8') ?>
                                    (<?= htmlspecialchars($primarySupportValue, ENT_QUOTES, 'UTF-8') ?>) para pacientes
                                    complejos.
                                </li>
                            <?php endif; ?>
                            <?php if (!empty($activityStats)): ?>
                                <?php $satisfactionStat = end($activityStats); ?>
                                <?php if ($satisfactionStat && isset($satisfactionStat['value'])): ?>
                                    <li class="mb-0"><i class="fa fa-line-chart text-warning me-10"></i> Actualizar
                                        tablero de satisfacción cuando cierre la jornada
                                        (meta <?= htmlspecialchars((string)$satisfactionStat['value'], ENT_QUOTES, 'UTF-8') ?><?= !empty($satisfactionStat['suffix']) ? htmlspecialchars($satisfactionStat['suffix'], ENT_QUOTES, 'UTF-8') : '' ?>
                                        ).
                                    </li>
                                <?php endif; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

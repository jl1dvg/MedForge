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
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">Your Patients today</h4>
                            <a href="#" class="">All patients <i class="ms-10 fa fa-angle-right"></i></a>
                        </div>
                    </div>
                    <div class="box-body p-15">
                        <div class="mb-10 d-flex justify-content-between align-items-center">
                            <div class="fw-600 min-w-120">
                                10:30am
                            </div>
                            <div class="w-p100 p-10 rounded10 justify-content-between align-items-center d-flex bg-lightest">
                                <div class="d-flex justify-content-between align-items-center">
                                    <img src="../images/avatar/1.jpg" class="me-10 avatar rounded-circle" alt="">
                                    <div>
                                        <h6 class="mb-0">Sarah Hostemn</h6>
                                        <p class="mb-0 fs-12 text-mute">Diagnosis: Bronchitis</p>
                                    </div>
                                </div>
                                <div class="dropdown">
                                    <a data-bs-toggle="dropdown" href="#"><i class="ti-more-alt rotate-90"></i></a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="#"><i class="ti-import"></i> Details</a>
                                        <a class="dropdown-item" href="#"><i class="ti-export"></i> Lab Reports</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mb-10 d-flex justify-content-between align-items-center">
                            <div class="fw-600 min-w-120">
                                11:00am
                            </div>
                            <div class="w-p100 p-10 rounded10 justify-content-between align-items-center d-flex bg-lightest">
                                <div class="d-flex justify-content-between align-items-center">
                                    <img src="../images/avatar/2.jpg" class="me-10 avatar rounded-circle" alt="">
                                    <div>
                                        <h6 class="mb-0">Dakota Smith</h6>
                                        <p class="mb-0 fs-12 text-mute">Diagnosis: Stroke</p>
                                    </div>
                                </div>
                                <div class="dropdown">
                                    <a data-bs-toggle="dropdown" href="#"><i class="ti-more-alt rotate-90"></i></a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="#"><i class="ti-import"></i> Details</a>
                                        <a class="dropdown-item" href="#"><i class="ti-export"></i> Lab Reports</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="fw-600 min-w-120">
                                11:30am
                            </div>
                            <div class="w-p100 p-10 rounded10 justify-content-between align-items-center d-flex bg-lightest">
                                <div class="d-flex justify-content-between align-items-center">
                                    <img src="../images/avatar/3.jpg" class="me-10 avatar rounded-circle" alt="">
                                    <div>
                                        <h6 class="mb-0">John Lane</h6>
                                        <p class="mb-0 fs-12 text-mute">Diagnosis: Liver cimhosis</p>
                                    </div>
                                </div>
                                <div class="dropdown">
                                    <a data-bs-toggle="dropdown" href="#"><i class="ti-more-alt rotate-90"></i></a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="#"><i class="ti-import"></i> Details</a>
                                        <a class="dropdown-item" href="#"><i class="ti-export"></i> Lab Reports</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if ($firmaUrl): ?>
                    <div class="box">
                        <div class="box-header">
                            <h4 class="box-title">Firma digital</h4>
                        </div>
                        <div class="box-body text-center">
                            <img src="<?= htmlspecialchars($firmaUrl, ENT_QUOTES, 'UTF-8') ?>"
                                 alt="Firma del doctor"
                                 class="img-fluid">
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
                    </div>
                </div>

                <div class="box">
                    <div class="box-header">
                        <h4 class="box-title">Resumen de actividad</h4>
                    </div>
                    <div class="box-body">
                        <div class="alert alert-info mb-0 d-flex align-items-start">
                            <i class="fa fa-info-circle me-10 mt-1"></i>
                            <div>
                                <strong>Sin estadísticas registradas.</strong>
                                <p class="mb-0">Conecta este módulo con la agenda y los reportes de procedimientos para visualizar citas, pacientes y otros indicadores relacionados con este doctor.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="box">
                    <div class="box-header">
                        <h4 class="box-title">Notas internas</h4>
                    </div>
                    <div class="box-body">
                        <p class="text-fade mb-0">Aún no se han agregado notas para este doctor. Utiliza esta sección para documentar acuerdos, seguimientos o recordatorios relevantes.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
<script src="../assets/vendor_components/apexcharts-bundle/dist/apexcharts.js"></script>
	<script src="../assets/vendor_components/OwlCarousel2/dist/owl.carousel.js"></script>
	<script src="../assets/vendor_components/date-paginator/moment.min.js"></script>
	<script src="../assets/vendor_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js"></script>
	<script src="../assets/vendor_components/date-paginator/bootstrap-datepaginator.min.js"></script>	
	
	<!-- Doclinic App -->
	<script src="js/template.js"></script>
	<script src="js/pages/doctor-details.js"></script>
<?php
/** @var array<int, array<string, mixed>> $doctors */
/** @var int $totalDoctors */

$doctors = $doctors ?? [];
$totalDoctors = isset($totalDoctors) ? (int) $totalDoctors : count($doctors);
?>

<div class="container-full">
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h4 class="page-title">Doctors</h4>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/"><i class="mdi mdi-home-outline"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Doctors</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="row mb-20 align-items-center">
            <div class="col-12 col-xl-8">
                <h4 class="mb-5 fw-600">Directorio de doctores</h4>
                <p class="text-fade mb-0">Se muestran los usuarios con especialidad o rol asignado como doctor.</p>
            </div>
            <div class="col-12 col-xl-4 text-xl-end text-start mt-10 mt-xl-0">
                <span class="badge badge-primary-light px-20 py-10 fs-16">
                    <?= htmlspecialchars((string) $totalDoctors, ENT_QUOTES, 'UTF-8') ?>
                    <?= $totalDoctors === 1 ? 'doctor registrado' : 'doctores registrados' ?>
                </span>
            </div>
        </div>

        <div class="row">
            <?php if (empty($doctors)): ?>
                <div class="col-12">
                    <div class="box">
                        <div class="box-body text-center py-60">
                            <div class="avatar avatar-xxl bg-primary-light d-inline-flex align-items-center justify-content-center mb-20">
                                <i class="mdi mdi-stethoscope fs-40 text-primary"></i>
                            </div>
                            <h4 class="mb-10">Aún no hay doctores para mostrar</h4>
                            <p class="text-fade mb-0">Los usuarios con una especialidad o rol de doctor aparecerán automáticamente en esta lista.</p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($doctors as $index => $doctor): ?>
                    <?php
                    $coverUrl = format_profile_photo_url($doctor['profile_photo'] ?? null);
                    if (!$coverUrl) {
                        $coverUrl = asset(sprintf('images/avatar/375x200/%d.jpg', ($index % 8) + 1));
                    }

                    $avatarUrl = format_profile_photo_url($doctor['profile_photo'] ?? null);
                    if (!$avatarUrl) {
                        $avatarUrl = asset(sprintf('images/avatar/%d.jpg', ($index % 9) + 1));
                    }

                    $statusVariant = $doctor['status_variant'] ?? null;
                    $statusLabel = $doctor['status'] ?? null;
                    ?>
                    <div class="col-12 col-lg-4">
                        <div class="box <?= $statusVariant ? 'ribbon-box' : '' ?>">
                            <?php if ($statusVariant && $statusLabel): ?>
                                <div class="ribbon-two ribbon-two-<?= htmlspecialchars($statusVariant, ENT_QUOTES, 'UTF-8') ?>">
                                    <span><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="box-header no-border p-0">
                                <a href="<?= htmlspecialchars($doctor['detail_url'], ENT_QUOTES, 'UTF-8') ?>">
                                    <img class="img-fluid" src="<?= htmlspecialchars($coverUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($doctor['display_name'], ENT_QUOTES, 'UTF-8') ?>">
                                </a>
                            </div>
                            <div class="box-body">
                                <div class="text-center">
                                    <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>"
                                         class="avatar avatar-xl rounded-circle border-3 border-white shadow mb-15"
                                         alt="<?= htmlspecialchars($doctor['display_name'], ENT_QUOTES, 'UTF-8') ?>">

                                    <div class="user-contact list-inline text-center mb-10">
                                        <?php if (!empty($doctor['email'])): ?>
                                            <a href="mailto:<?= htmlspecialchars($doctor['email'], ENT_QUOTES, 'UTF-8') ?>"
                                               class="btn btn-circle mb-5 btn-warning" title="Enviar correo">
                                                <i class="fa fa-envelope"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?= htmlspecialchars($doctor['detail_url'], ENT_QUOTES, 'UTF-8') ?>"
                                           class="btn btn-circle mb-5 btn-primary-light" title="Ver detalles">
                                            <i class="fa fa-id-card-o"></i>
                                        </a>
                                    </div>

                                    <h3 class="my-10">
                                        <a href="<?= htmlspecialchars($doctor['detail_url'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($doctor['display_name'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    </h3>
                                    <h6 class="user-info mt-0 mb-10 text-fade">
                                        <?= htmlspecialchars($doctor['especialidad'] ?? 'Especialidad no registrada', ENT_QUOTES, 'UTF-8') ?>
                                    </h6>
                                    <?php if (!empty($doctor['subespecialidad'])): ?>
                                        <p class="mb-5 text-fade"><?= htmlspecialchars($doctor['subespecialidad'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($doctor['sede'])): ?>
                                        <p class="mb-0 text-fade">
                                            <i class="mdi mdi-hospital-building me-5"></i>
                                            <?= htmlspecialchars($doctor['sede'], ENT_QUOTES, 'UTF-8') ?>
                                        </p>
                                    <?php endif; ?>

                                    <div class="d-flex justify-content-center gap-10 mt-15 flex-wrap">
                                        <a href="<?= htmlspecialchars($doctor['detail_url'], ENT_QUOTES, 'UTF-8') ?>"
                                           class="btn btn-sm btn-primary">
                                            <i class="fa fa-eye me-5"></i> Ver detalles
                                        </a>
                                        <?php if (!empty($doctor['email'])): ?>
                                            <a href="mailto:<?= htmlspecialchars($doctor['email'], ENT_QUOTES, 'UTF-8') ?>"
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fa fa-paper-plane me-5"></i> Contactar
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
    <!-- /.content -->
</div>

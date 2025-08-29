<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\DashboardController;
use Controllers\UserController;

$controller = new UserController($pdo);
$dashboardController = new DashboardController($pdo);

$id = $_GET['id'] ?? null;
if (!$id) {
    die('ID de usuario no especificado');
}

$user = $controller->getUserModel()->getUserById($id);
@$username = $dashboardController->getAuthenticatedUser();

// Render compacto para modal
if (isset($_GET['modal'])) {
    if (!$user) {
        echo '<div class="p-4">Usuario no encontrado.</div>';
        exit;
    }
    ?>
    <div class="modal-header">
        <h5 class="modal-title">Perfil
            de <?= htmlspecialchars($user['nombre'] ?? $user['username'] ?? 'Usuario') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
    </div>
    <div class="modal-body">
        <div class="d-md-flex align-items-start mb-3">
            <img src="/public/images/avatar/avatar-1.png" class="bg-primary-light rounded10 me-3" alt=""
                 style="width:72px;height:72px;object-fit:cover;">
            <div>
                <div class="mb-1"><strong>Rol:</strong> <?= htmlspecialchars($user['role'] ?? 'Usuario') ?></div>
                <div class="mb-1">
                    <strong>Ingreso:</strong> <?= date('d M Y, h:i A', strtotime($user['created_at'] ?? 'now')) ?></div>
            </div>
        </div>
        <div class="mb-2"><strong>Correo:</strong> <?= htmlspecialchars($user['email'] ?? 'No registrado') ?></div>
        <div class="mb-2"><strong>Usuario:</strong> <?= htmlspecialchars($user['username'] ?? '---') ?></div>
        <div class="mb-2"><strong>Especialidad:</strong> <?= htmlspecialchars($user['especialidad'] ?? '---') ?></div>
        <div class="mb-2"><strong>Subespecialidad:</strong> <?= htmlspecialchars($user['subespecialidad'] ?? '---') ?>
        </div>
        <?php if (!empty($user['firma'])): ?>
            <div class="mt-3"><strong>Firma:</strong><br>
                <img src="<?= htmlspecialchars($user['firma']) ?>" alt="Firma" style="max-height:120px;">
            </div>
        <?php endif; ?>
        <div class="mt-3">
            <strong>Biografía</strong>
            <p class="mb-0"><?= htmlspecialchars($user['biografia'] ?? 'Este usuario aún no ha agregado una biografía.') ?></p>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
    </div>
    <?php
    exit;
}

// Ejecutar agenda.php automáticamente con un rango de 15 días
//$hoy = date('Y-m-d');
//$hasta = date('Y-m-d', strtotime('+15 days'));
//$logPath = __DIR__ . "/../../public/fix/logs/agenda_profile_" . date('Y-m-d_His') . ".log";
//$cmd = "php " . __DIR__ . "/../../fix/agenda.php start=$hoy end=$hasta debug=1 > $logPath 2>&1 &";
//shell_exec($cmd);
?>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="/public/images/favicon.ico">

    <title>Asistente CIVE - Dashboard</title>

    <!-- Vendors Style-->
    <link rel="stylesheet" href="/public/css/vendors_css.css">

    <!-- Style-->
    <link rel="stylesheet" href="/public/css/horizontal-menu.css">
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="/public/css/skin_color.css">

</head>
<body class="layout-top-nav light-skin theme-primary fixed">

<div class="wrapper">
    <div id="loader"></div>

    <?php include __DIR__ . '/../components/header.php'; ?>
    <div class="content-wrapper">

        <div class="container-full">
            <!-- Content Header (Page header) -->
            <div class="content-header">
                <div class="d-flex align-items-center">
                    <div class="me-auto">
                        <h3 class="page-title">Doctor Details</h3>
                        <div class="d-inline-block align-items-center">
                            <nav>
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="#"><i class="mdi mdi-home-outline"></i></a>
                                    </li>
                                    <li class="breadcrumb-item active" aria-current="page">Doctor Details</li>
                                </ol>
                            </nav>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Main content -->
            <section class="content">
                <div class="row">
                    <div class="col-xl-4 col-12">
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
                                            <img src="/public/images/avatar/1.jpg" class="me-10 avatar rounded-circle"
                                                 alt="">
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
                                            <img src="/public/images/avatar/2.jpg" class="me-10 avatar rounded-circle"
                                                 alt="">
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
                                            <img src="/public/images/avatar/3.jpg" class="me-10 avatar rounded-circle"
                                                 alt="">
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
                    </div>
                    <div class="col-xl-8 col-12">
                        <div class="box">
                            <div class="box-body text-end min-h-150"
                                 style="background-image:url(/public/images/gallery/landscape14.jpg); background-repeat: no-repeat; background-position: center;background-size: cover;">
                                <div class="bg-primary rounded10 p-15 fs-18 d-inline"><i class="fa fa-user-md"></i>
                                    <?= htmlspecialchars($user['role'] ?? 'Usuario') ?>
                                </div>
                            </div>
                            <div class="box-body wed-up position-relative">
                                <div class="d-md-flex align-items-end">
                                    <img src="/public/images/avatar/avatar-1.png"
                                         class="bg-primary-light rounded10 me-20" alt="">
                                    <div>
                                        <h4><?= htmlspecialchars($user['nombre'] ?? 'Nombre no disponible') ?></h4>
                                        <p><i class="fa fa-clock-o"></i>
                                            Ingreso: <?= date('d M Y, h:i A', strtotime($user['created_at'] ?? 'now')) ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="box-body">
                                <h4>Información de contacto</h4>
                                <p>
                                    <strong>Correo:</strong> <?= htmlspecialchars($user['email'] ?? 'No registrado') ?>
                                </p>
                                <p><strong>Usuario:</strong> <?= htmlspecialchars($user['username'] ?? '---') ?></p>
                                <p>
                                    <strong>Especialidad:</strong> <?= htmlspecialchars($user['especialidad'] ?? '---') ?>
                                </p>
                                <p>
                                    <strong>Subspecialidad:</strong> <?= htmlspecialchars($user['subespecialidad'] ?? '---') ?>
                                </p>

                                <h4 class="mt-20">Biografía</h4>
                                <p><?= htmlspecialchars($user['biografia'] ?? 'Este usuario aún no ha agregado una biografía.') ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <!-- /.content -->
        </div>
    </div>
    <?php include __DIR__ . '/../components/footer.php'; ?>
</div>
<!-- ./wrapper -->

<!-- Vendor JS -->
<script src="/public/js/vendors.min.js"></script>
<script src="/public/js/pages/chat-popup.js"></script>
<script src="/public/assets/icons/feather-icons/feather.min.js"></script>

<!-- Doclinic App -->
<script src="/public/js/jquery.smartmenus.js"></script>
<script src="/public/js/menus.js"></script>
<script src="/public/js/template.js"></script>
</body>
</html>
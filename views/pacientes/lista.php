<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\DashboardController;
use Controllers\PacienteController;

$pacienteController = new PacienteController($pdo);
$dashboardController = new DashboardController($pdo);

$username = $dashboardController->getAuthenticatedUser();
$pacientes = $pacienteController->obtenerPacientesConUltimaConsulta();
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

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <div class="container-full">
            <!-- Content Header (Page header) -->
            <div class="content-header">
                <div class="d-flex align-items-center">
                    <div class="me-auto">
                        <h3 class="page-title">Patients</h3>
                        <div class="d-inline-block align-items-center">
                            <nav>
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="#"><i class="mdi mdi-home-outline"></i></a>
                                    </li>
                                    <li class="breadcrumb-item active" aria-current="page">Patients</li>
                                </ol>
                            </nav>
                        </div>
                    </div>

                </div>
            </div>
            <!-- Contenido principal -->
            <section class="content">
                <div class="row">
                    <div class="col-12">
                        <div class="box">
                            <div class="box-body">
                                <div class="table-responsive rounded card-table">
                                    <table class="table border-no" id="example1">
                                        <thead>
                                        <tr>
                                            <th>ID del Paciente</th>
                                            <th>Fecha de Ingreso</th>
                                            <th>Nombre del Paciente</th>
                                            <th>Afiliación</th>
                                            <th>Estado</th>
                                            <th></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (!empty($pacientes)): ?>
                                            <?php foreach ($pacientes as $row): ?>
                                                <tr class="hover-primary"
                                                    onclick="window.location='detalles.php?hc_number=<?= $row['hc_number']; ?>';"
                                                    style="cursor:pointer;">
                                                    <td><?= $row['hc_number']; ?></td>
                                                    <td><?= !empty($row['ultima_fecha']) ? date('d/m/Y', strtotime($row['ultima_fecha'])) : 'No disponible'; ?></td>
                                                    <td><?= $row['full_name']; ?></td>
                                                    <td><?= !empty($row['afiliacion']) ? $row['afiliacion'] : 'N/A'; ?></td>
                                                    <td>
                                                        <?php
                                                        $cobertura = $pacienteController->verificarCoberturaPaciente($row['hc_number']);
                                                        echo match ($cobertura) {
                                                            'Con Cobertura' => '<span class="badge badge-success-light">Con Cobertura</span>',
                                                            'Sin Cobertura' => '<span class="badge badge-danger-light">Sin Cobertura</span>',
                                                            default => '<span class="badge badge-warning-light">N/A</span>',
                                                        };
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group">
                                                            <a class="hover-primary dropdown-toggle no-caret"
                                                               data-bs-toggle="dropdown"><i
                                                                        class="fa fa-ellipsis-h"></i></a>
                                                            <div class="dropdown-menu">
                                                                <a class="dropdown-item"
                                                                   href="patients/patient_details.php?hc_number=<?= $row['hc_number']; ?>">Ver
                                                                    Detalles</a>
                                                                <a class="dropdown-item" href="#">Editar</a>
                                                                <a class="dropdown-item" href="#">Eliminar</a>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8">No hay datos disponibles</td>
                                            </tr>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <!-- /.content -->
        </div>
    </div>
    <!-- /.content-wrapper -->

    <?php include __DIR__ . '/../components/footer.php'; ?>
</div>
<!-- ./wrapper -->

<!-- Vendor JS -->
<script src="/public/js/vendors.min.js"></script>
<script src="/public/js/pages/chat-popup.js"></script>
<script src="/public/assets/icons/feather-icons/feather.min.js"></script>
<script src="/public/assets/vendor_components/datatable/datatables.min.js"></script>

<!-- Doclinic App -->
<script src="/public/js/jquery.smartmenus.js"></script>
<script src="/public/js/menus.js"></script>
<script src="/public/js/template.js"></script>
<script src="/public/js/pages/patients.js"></script>
</body>
</html>

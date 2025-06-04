<?php
require_once __DIR__ . '/../../../bootstrap.php';

use Controllers\DashboardController;
use Controllers\PacienteController;
use Controllers\GuardarProyeccionController;

$pacienteController = new PacienteController($pdo);
$procedimientoController = new GuardarProyeccionController($pdo);
$dashboardController = new DashboardController($pdo);

$username = $dashboardController->getAuthenticatedUser();
$pacientes = $pacienteController->obtenerPacientesConUltimaConsulta();
$flujoPacientes = $procedimientoController->obtenerFlujoPacientes(); // devuelve todas las solicitudes con estado 'Agendado'

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="/public/images/favicon.ico">

    <title>Asistente CIVE - Dashboard</title>

    <!-- Material Design Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font/css/materialdesignicons.min.css">

    <!-- Vendors Style-->
    <link rel="stylesheet" href="/public/css/vendors_css.css">

    <!-- Style-->
    <link rel="stylesheet" href="/public/css/horizontal-menu.css">
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="/public/css/skin_color.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/pickadate/lib/themes/default.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/pickadate/lib/themes/default.date.css">
    <link rel="stylesheet" href="style.css">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="layout-top-nav light-skin theme-primary fixed">

<div class="wrapper">
    <div id="loader"></div>

    <?php include __DIR__ . '/../../components/header.php'; ?>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <div class="container-full">
            <!-- Content Header (Page header) -->
            <div class="content-header">
                <div class="d-flex align-items-center">
                    <div class="me-auto">
                        <h3 class="page-title">Tablero de Flujo</h3>
                        <div class="d-inline-block align-items-center">
                            <nav>
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="#"><i class="mdi mdi-home-outline"></i></a>
                                    </li>
                                    <li class="breadcrumb-item active" aria-current="page">Tablero de Flujo
                                    </li>
                                </ol>
                            </nav>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Main content -->
            <?php
            // Obtener datos del flujo de pacientes desde el controlador
            ?>
            <section class="content">
                <ul class="nav nav-tabs nav-fill" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link tab-kanban active" data-tipo="kanban-summary"
                           href="javascript:void(0);"><span><i class="ion-home"></i></span> <span
                                    class="hidden-xs-down ms-15">Todos</span></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link tab-kanban" data-tipo="cirugia"
                           href="javascript:void(0);"><span><i class="ion-person"></i></span> <span
                                    class="hidden-xs-down ms-15">Cirugías</span></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link tab-kanban" data-tipo="consulta"
                           href="javascript:void(0);"><span><i class="ion-settings"></i></span> <span
                                    class="hidden-xs-down ms-15">Consultas</span></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link tab-kanban" data-tipo="examen"
                           href="javascript:void(0);"><span><i class="ion-email"></i></span> <span
                                    class="hidden-xs-down ms-15">Exámenes</span></a>
                    </li>
                </ul>
                <div class="tab-content tabcontent-border">
                    <!-- Filtros Kanban -->
                    <div class="row mb-3">
                        <div class="col-md-3 mb-2">
                            <label for="kanbanDateFilter" class="form-label">Fecha</label>
                            <input type="text" id="kanbanDateFilter" class="datepicker form-control"
                                   placeholder="Seleccione una fecha">
                        </div>
                        <div class="col-md-3 mb-2">
                            <label for="kanbanAfiliacionFilter" class="form-label">Afiliación</label>
                            <select id="kanbanAfiliacionFilter" class="form-select">
                                <option value="">Todas</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label for="kanbanDoctorFilter" class="form-label">Doctor</label>
                            <select id="kanbanDoctorFilter" class="form-select">
                                <option value="">Todos</option>
                                <!-- Aquí se llenará dinámicamente con JS -->
                            </select>
                        </div>
                    </div>
                    <!-- Kanban Board Container -->
                    <div class="kanban-board d-flex justify-content-between p-3 bg-light"
                         style="gap: 1rem; overflow-x: auto;">
                        <!-- Las columnas serán generadas dinámicamente por JS según la vista seleccionada -->
                    </div>
                </div>
            </section>            <!-- /.content -->

        </div>
    </div>    <!-- /.content-wrapper -->

    <?php include __DIR__ . '/../../components/footer.php'; ?>
</div>
<!-- ./wrapper -->

<!-- Vendor JS -->
<script src="/public/js/vendors.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script src="/public/js/pages/chat-popup.js"></script>
<script src="/public/assets/icons/feather-icons/feather.min.js"></script>
<script src="/public/assets/vendor_components/datatable/datatables.min.js"></script>
<script src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/pickadate/lib/picker.js"></script>
<script src="https://cdn.jsdelivr.net/npm/pickadate/lib/picker.date.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.7.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip-utils/0.1.0/jszip-utils.min.js"></script>
<script src="js/kanban_base.js"></script>
<script src="js/kanban_tabs.js"></script>
<script src="js/kanban_cirugia.js"></script>
<script src="js/kanban_consulta.js"></script>
<script src="js/kanban_examenes.js"></script>

<!-- Doclinic App -->
<script src="/public/js/jquery.smartmenus.js"></script>
<script src="/public/js/menus.js"></script>
<script src="/public/js/template.js"></script>
</body>
</html>
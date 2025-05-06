<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\DashboardController;
use Controllers\PacienteController;
use Controllers\ReporteCirugiasController;

$reporteCirugiasController = new ReporteCirugiasController($pdo);
$pacienteController = new PacienteController($pdo);
$dashboardController = new DashboardController($pdo);

$cirugias = $reporteCirugiasController->obtenerCirugias();
$username = $dashboardController->getAuthenticatedUser();
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
                        <h3 class="page-title">Reporte de Cirugías</h3>
                        <div class="d-inline-block align-items-center">
                            <nav>
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="#"><i class="mdi mdi-home-outline"></i></a>
                                    </li>
                                    <li class="breadcrumb-item active" aria-current="page">Reporte de Cirugías</li>
                                </ol>
                            </nav>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Main content -->
            <section class="content">
                <div class="row">
                    <div class="col-12">
                        <div class="box">
                            <div class="box-body">
                                <h4 class="box-title">Cirugías Realizadas</h4>
                                <div class="table-responsive">
                                    <table id="surgeryTable" class="table table-striped table-hover">
                                        <thead>
                                        <tr>
                                            <th class="bb-2">No.</th>
                                            <th class="bb-2">C.I.</th>
                                            <th class="bb-2">Nombre</th>
                                            <th class="bb-2">Afiliación</th>
                                            <th class="bb-2">Fecha</th>
                                            <th class="bb-2">Procedimiento</th>
                                            <th class="bb-2"><i class="mdi mdi-file-document"></i></th>
                                            <th class="bb-2"><i class="mdi mdi-printer"></i></th>
                                        </tr>
                                        </thead>
                                        <tbody id="patientTableBody">
                                        <?php
                                        $counter = 1;
                                        foreach ($cirugias as $cirugia) {
                                            $printed = $cirugia->printed ?? 0;
                                            $buttonClass = $printed ? 'active' : '';
                                            $estado = $cirugia->getEstado();
                                            $badgeEstado = match ($estado) {
                                                'revisado' => "<span class='badge bg-success'><i class='fa fa-check'></i></span>",
                                                'no revisado' => "<span class='badge bg-warning'><i class='fa fa-exclamation-triangle'></i></span>",
                                                default => "<span class='badge bg-danger'><i class='fa fa-times'></i></span>"
                                            };
                                            $onclick = $estado === 'revisado'
                                                ? "togglePrintStatus(" . $cirugia->form_id . ", '" . $cirugia->hc_number . "', this, 1)"
                                                : "Swal.fire({ icon: 'warning', title: 'Pendiente revisión', text: 'Debe revisar el protocolo antes de imprimir.' })";
                                            $badgePrinted = $printed ? "<span class='badge bg-success'><i class='fa fa-check'></i></span>" : "";

                                            echo "<tr>
                                                    <td>" . htmlspecialchars($cirugia->form_id ?? '', ENT_QUOTES, 'UTF-8') . "</td>
                                                    <td>" . htmlspecialchars($cirugia->hc_number ?? '', ENT_QUOTES, 'UTF-8') . "</td>
                                                    <td>" . htmlspecialchars($cirugia->getNombreCompleto(), ENT_QUOTES, 'UTF-8') . "</td>
                                                    <td>" . htmlspecialchars($cirugia->afiliacion ?? '', ENT_QUOTES, 'UTF-8') . "</td>
                                                    <td>" . date('d/m/Y', strtotime($cirugia->fecha_inicio)) . "</td>
                                                    <td>" . htmlspecialchars($cirugia->membrete ?? '', ENT_QUOTES, 'UTF-8') . "</td>
                                                    <td colspan='2'>
                                                        <a href='#'
                                                           title='Ver protocolo quirúrgico'
                                                           class='btn btn-app btn-info'
                                                           data-bs-toggle='modal'
                                                           data-bs-target='#resultModal'
                                                           data-cirugia='" . htmlspecialchars(json_encode($cirugia->toArray()), ENT_QUOTES, "UTF-8") . "'
                                                           onclick='loadResultFromElement(this)'>
                                                           $badgeEstado
                                                           <i class='mdi mdi-file-document'></i> Protocolo
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <a class='btn btn-app btn-primary'
                                                           title='Imprimir protocolo'
                                                           onclick=\"" . htmlspecialchars($onclick, ENT_QUOTES, 'UTF-8') . "\">
                                                           $badgePrinted
                                                           <i class='fa fa-print'></i> Imprimir
                                                        </a>
                                                    </td>
                                                </tr>";
                                            $counter++;
                                        }
                                        ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <!-- /.box-body -->
                        </div>
                    </div>
                </div>
            </section>
            <!-- /.content -->

        </div>
    </div>
    <!-- /.content-wrapper -->

    <!--Model Popup Area-->
    <!-- result modal content -->
    <div class="modal fade" id="resultModal">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title" id="result-proyectado">Resultados</h4>
                    <h4 class="modal-title" id="result-popup">Resultados</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row justify-content-between">
                        <div class="col-md-7 col-12">
                            <h4 id="test-name">Diagnóstico</h4>
                        </div>
                        <div class="col-md-5 col-12">
                            <h4 class="text-end" id="lab-order-id">Orden ID</h4>
                        </div>
                    </div>
                    <!-- Nueva tabla para Diagnósticos -->
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="bg-secondary">
                            <tr>
                                <th scope="col">CIE10</th>
                                <th scope="col">Detalle</th>
                            </tr>
                            </thead>
                            <tbody id="diagnostico-table">
                            <!-- Se llenará dinámicamente -->
                            </tbody>
                        </table>
                    </div>
                    <!-- Nueva tabla para Procedimientos -->
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="bg-secondary">
                            <tr>
                                <th scope="col">Código</th>
                                <th scope="col">Nombre del Procedimiento</th>
                            </tr>
                            </thead>
                            <tbody id="procedimientos-table">
                            <!-- Se llenará dinámicamente -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Nueva tabla para mostrar fecha de inicio, hora de inicio, hora de fin, y duración -->
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="bg-secondary">
                            <tr>
                                <th>Fecha de Inicio</th>
                                <th>Hora de Inicio</th>
                                <th>Hora de Fin</th>
                                <th>Duración</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr id="timing-row">
                                <!-- Se llenará dinámicamente con 4 <td> -->
                            </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="bg-secondary">
                            <tr>
                                <th scope="col" colspan="2">Procedimiento</th>
                            </tr>
                            </thead>
                            <tbody id="result-table">
                            <!-- Se llenará dinámicamente -->
                            </tbody>
                        </table>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="bg-secondary">
                            <tr>
                                <th scope="col" colspan="2">Staff Quirúrgico</th>
                            </tr>
                            </thead>
                            <tbody id="staff-table">
                            <!-- Se llenará dinámicamente -->
                            </tbody>
                        </table>
                    </div>
                    <div class="comment">
                        <p><span class="fw-600">Comentario</span> : <span class="comment-here text-mute"></span></p>
                    </div>
                    <!-- Agregar checkbox para marcar como revisado
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="markAsReviewed">
                        <label class="form-check-label" for="markAsReviewed">Marcar como revisado</label>
                    </div> -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger pull-right" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-info pull-right" onclick="redirectToEditProtocol()">Revisar
                        Protocolo
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- /.modal-dialog -->
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

</div>
<!-- ./wrapper -->

<!-- Page Content overlay -->
<script>
    function togglePrintStatus(form_id, hc_number, button, currentStatus) {
        // Verificar si el botón está activo o no
        var isActive = button.classList.contains('active');
        var newStatus = isActive ? 1 : 0;

        // Cambiar visualmente el estado del botón
        if (isActive) {
            button.classList.remove('active');
            button.setAttribute('aria-pressed', 'false');
        } else {
            button.classList.add('active');
            button.setAttribute('aria-pressed', 'true');
        }

        // ⬇️ Si el usuario está activando el botón (impreso = 1), ABRIMOS EL PDF INMEDIATAMENTE
        if (newStatus === 0) {  // OJO: aquí es 0 porque apenas damos click, el toggle no se ha cambiado
            window.open('/public/ajax/generate_protocolo_pdf.php?form_id=' + form_id + '&hc_number=' + hc_number, '_blank');
        }

        // Enviar la actualización del estado al servidor (independientemente)
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/public/update_print_status.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send('form_id=' + form_id + '&hc_number=' + hc_number + '&printed=' + (isActive ? 0 : 1)); // Cambiamos el valor aquí
    }

    let currentFormId;  // Variable para almacenar el form_id actual
    let currentHcNumber;  // Variable para almacenar el hc_number actual

    function redirectToEditProtocol() {
        // Construir la URL de edición
        const url = `wizard_cirugia/wizard.php?form_id=${encodeURIComponent(currentFormId)}&hc_number=${encodeURIComponent(currentHcNumber)}`;
        // Redirigir al usuario
        window.location.href = url;
    }

    function reloadPatientTable() {
        // Hacer una petición AJAX al mismo archivo
        const xhr = new XMLHttpRequest();
        xhr.open('GET', window.location.href, true);  // Hacer una petición GET al mismo archivo PHP
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');  // Esto ayuda a diferenciar solicitudes AJAX

        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4 && xhr.status === 200) {
                // Actualizar el contenido del tbody con el nuevo HTML de las filas
                const parser = new DOMParser();
                const htmlDoc = parser.parseFromString(xhr.responseText, 'text/html');
                const newTableBody = htmlDoc.getElementById('patientTableBody').innerHTML;
                document.getElementById('patientTableBody').innerHTML = newTableBody;
            }
        };
        xhr.send();
    }
</script>
<!-- Vendor JS -->
<script src="/public/js/vendors.min.js"></script>
<script src="/public/js/pages/chat-popup.js"></script>
<script src="/public/assets/icons/feather-icons/feather.min.js"></script>
<script src="/public/assets/vendor_components/datatable/datatables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $.fn.dataTable.ext.type.order['dd-mm-yyyy-pre'] = function (d) {
        if (!d) {
            return 0;
        }
        var parts = d.split('/');
        return new Date(parts[2], parts[1] - 1, parts[0]).getTime();
    };

    $(document).ready(function () {
        $('#surgeryTable').DataTable({
            "paging": true,
            "lengthChange": true,
            "searching": true,
            "ordering": true,
            "info": true,
            "autoWidth": false,
            "pageLength": 25,
            "columnDefs": [
                {
                    "targets": 4,  // Índice de la columna "Fecha de Inicio"
                    "type": "dd-mm-yyyy"  // Tipo personalizado para ordenar fechas dd/mm/yyyy
                }
            ]
        });
    });
</script>


<!-- Doclinic App -->
<script src="/public/js/jquery.smartmenus.js"></script>
<script src="/public/js/menus.js"></script>
<script src="/public/js/template.js"></script>
<script src="/public/js/pages/appointments.js"></script>
<script src="/public/js/modules/cirugias_modal.js"></script>


</body>
</html>

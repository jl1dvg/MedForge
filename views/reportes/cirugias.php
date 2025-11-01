<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\DashboardController;
use Modules\Pacientes\Services\PacienteService;
use Controllers\ReporteCirugiasController;

$reporteCirugiasController = new ReporteCirugiasController($pdo);
$pacienteService = new PacienteService($pdo);
$dashboardController = new DashboardController($pdo);

$cirugias = $reporteCirugiasController->obtenerListaCirugias();
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
    <style>
        table.table td, table.table th {
            font-size: 0.875rem; /* slightly smaller font */
        }
    </style>

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
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h4 class="box-title">Cirugías Realizadas</h4>
                                    <select id="descargarZipMes" class="form-select w-auto">
                                        <option value="">Descargar planillas del mes...</option>
                                        <?php
                                        $mesesUnicos = [];
                                        foreach ($cirugias as $c) {
                                            $mes = substr($c->fecha_inicio, 0, 7); // formato YYYY-MM
                                            $mesesUnicos[$mes] = date('F Y', strtotime($mes . '-01'));
                                        }
                                        krsort($mesesUnicos);
                                        foreach ($mesesUnicos as $val => $label) {
                                            echo "<option value=\"$val\">$label</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
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
                                            <th class="bb-2" title="Ver protocolo"><i class="mdi mdi-file-document"></i>
                                            </th>
                                            <th class="bb-2" title="Imprimir protocolo"><i class="mdi mdi-printer"></i>
                                            </th>
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
                                                    <td>
                                                        <a href='#'
                                                           title='Ver protocolo quirúrgico'
                                                           class='btn btn-app btn-info'
                                                           data-bs-toggle='modal'
                                                           data-bs-target='#resultModal'
                                                           data-form-id='" . htmlspecialchars($cirugia->form_id, ENT_QUOTES, "UTF-8") . "'
                                                           data-hc-number='" . htmlspecialchars($cirugia->hc_number, ENT_QUOTES, "UTF-8") . "'
                                                           onclick='loadProtocolData(this)'>
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
    <?php include __DIR__ . '/modal_protocolo.php'; ?>
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
        xhr.onerror = function () {
            Swal.fire('Error', 'No se pudo actualizar el estado de impresión.', 'error');
        };
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
<script>
    function loadProtocolData(button) {
        const formId = button.getAttribute('data-form-id');
        const hcNumber = button.getAttribute('data-hc-number');
        currentFormId = formId;
        currentHcNumber = hcNumber;

        fetch(`/public/ajax/get_protocolo.php?form_id=${formId}&hc_number=${hcNumber}`)
            .then(response => response.json())
            .then(data => {
                // 🔍 Depuración: ver JSON completo en la consola
                console.log('[DEBUG] Protocolo cargado:', data);

                // Cargar diagnósticos
                const diagTable = document.getElementById("diagnostico-table");
                diagTable.innerHTML = '';
                (data.diagnosticos || []).forEach(d => {
                    diagTable.innerHTML += `<tr><td>${d.cie10}</td><td>${d.detalle}</td></tr>`;
                });

                // Cargar procedimientos
                const procTable = document.getElementById("procedimientos-table");
                procTable.innerHTML = '';
                (data.procedimientos || []).forEach(p => {
                    procTable.innerHTML += `<tr><td>${p.codigo}</td><td>${p.nombre}</td></tr>`;
                });

                // Cargar tiempos
                const timingRow = document.getElementById("timing-row");
                timingRow.innerHTML = `
                    <td>${data.fecha_inicio}</td>
                    <td>${data.hora_inicio}</td>
                    <td>${data.hora_fin}</td>
                    <td>${data.duracion || ''}</td>
                `;

                // Cargar detalles operatorios
                const resultTable = document.getElementById("result-table");
                resultTable.innerHTML = '';
                resultTable.innerHTML += `<tr><td>Dieresis</td><td>${data.dieresis}</td></tr>`;
                resultTable.innerHTML += `<tr><td>Exposición</td><td>${data.exposicion}</td></tr>`;
                resultTable.innerHTML += `<tr><td>Hallazgo</td><td>${data.hallazgo}</td></tr>`;
                resultTable.innerHTML += `<tr><td>Operatorio</td><td>${data.operatorio}</td></tr>`;

                // Cargar staff (solo los que existen)
                const staffTable = document.getElementById("staff-table");
                staffTable.innerHTML = '';
                Object.entries(data.staff || {}).forEach(([rol, nombre]) => {
                    if (nombre && nombre.trim() !== '') {
                        staffTable.innerHTML += `<tr><td>${rol}</td><td>${nombre}</td></tr>`;
                    }
                });

                // Comentario
                document.querySelector(".comment-here").textContent = data.comentario || '';
            })
            .catch(() => {
                Swal.fire('Error', 'No se pudo cargar el protocolo.', 'error');
            });
    }

    // Descargar ZIP por mes
    document.getElementById('descargarZipMes').addEventListener('change', function () {
        const mes = this.value;
        if (mes) {
            window.open(`/public/index.php/billing/exportar_mes?mes=${mes}`, '_blank');
            this.value = '';
        }
    });
</script>
<!-- Vendor JS -->
<script src="/public/js/vendors.min.js"></script>
<script src="/public/js/pages/chat-popup.js"></script>
<script src="/public/assets/icons/feather-icons/feather.min.js"></script>
<script src="/public/assets/vendor_components/datatable/datatables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- DataTables Buttons (Excel export) -->
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script>
    $.fn.dataTable.ext.type.order['dd-mm-yyyy-pre'] = function (d) {
        if (!d) {
            return 0;
        }
        var parts = d.split('/');
        return new Date(parts[2], parts[1] - 1, parts[0]).getTime();
    };

    $(document).ready(function () {

        const table = $('#surgeryTable').DataTable({
            "paging": true,
            "lengthChange": true,
            "searching": true,
            "ordering": true,
            "info": true,
            "autoWidth": false,
            "pageLength": 25,
            "order": [[4, "desc"]],
            "columnDefs": [
                {
                    "targets": 4,  // Índice de la columna "Fecha de Inicio"
                    "type": "dd-mm-yyyy"  // Tipo personalizado para ordenar fechas dd/mm/yyyy
                }
            ],
            "rowGroup": {
                dataSrc: function (row) {
                    let fecha = row[4];
                    if (!fecha) return "Sin fecha";
                    const partes = fecha.split('/');
                    const mes = partes[1];
                    const anio = partes[2];
                    const nombreMeses = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
                        "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
                    return `${nombreMeses[parseInt(mes, 10) - 1]} ${anio}`;
                }
            },
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: 'Exportar a Excel',
                    title: 'Reporte de Cirugías Agrupado',
                    exportOptions: {
                        columns: ':visible'
                    }
                }
            ],
            initComplete: function () {
                this.api().columns([3]).every(function () {
                    var column = this;
                    var select = $('<select><option value="">Filtrar</option></select>')
                        .appendTo($(column.header()).empty())
                        .on('change', function () {
                            var val = $.fn.dataTable.util.escapeRegex($(this).val());
                            column.search(val ? '^' + val + '$' : '', true, false).draw();
                        });
                    column.data().unique().sort().each(function (d, j) {
                        select.append('<option value="' + d + '">' + d + '</option>')
                    });
                });
            }
        });

        // Filtro por mes/año
        const grupos = new Set();
        table.rows().data().each(function (row) {
            let fecha = row[4];
            if (!fecha) return;
            const partes = fecha.split('/');
            const mes = partes[1];
            const anio = partes[2];
            const nombreMeses = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
                "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
            const grupo = `${nombreMeses[parseInt(mes, 10) - 1]} ${anio}`;
            grupos.add(grupo);
        });

        const filtroGrupo = $('<select id="filtroGrupo" class="form-select mb-2"><option value="">Todos los meses</option></select>');
        grupos.forEach(grupo => filtroGrupo.append(`<option value="${grupo}">${grupo}</option>`));
        $('#surgeryTable_wrapper').prepend(filtroGrupo);

        filtroGrupo.on('change', function () {
            const valor = this.value;
            $.fn.dataTable.ext.search.push(function (settings, data) {
                let fecha = data[4];
                if (!fecha) return false;
                const partes = fecha.split('/');
                const mes = partes[1];
                const anio = partes[2];
                const nombreMeses = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
                    "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
                const grupo = `${nombreMeses[parseInt(mes, 10) - 1]} ${anio}`;
                return valor === "" || grupo === valor;
            });
            table.draw();
        });

    });
</script>


<!-- DataTables RowGroup plugin -->
<script src="https://cdn.datatables.net/rowgroup/1.3.1/js/dataTables.rowGroup.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/rowgroup/1.3.1/css/rowGroup.dataTables.min.css">

<!-- Doclinic App -->
<script src="/public/js/jquery.smartmenus.js"></script>
<script src="/public/js/menus.js"></script>
<script src="/public/js/template.js"></script>
<script src="/public/js/pages/appointments.js"></script>
<script src="/public/js/modules/cirugias_modal.js"></script>


</body>
</html>

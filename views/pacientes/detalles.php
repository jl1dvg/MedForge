<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\DashboardController;
use Controllers\PacienteController;

$pacienteController = new PacienteController($pdo);
$dashboardController = new DashboardController($pdo);

$username = $dashboardController->getAuthenticatedUser();
$pacientes = $pacienteController->obtenerPacientesConUltimaConsulta();
$hc_number = $_GET['hc_number'] ?? null;
$patientData = $pacienteController->getPatientDetails($hc_number);
$diagnosticos = $pacienteController->getDiagnosticosPorPaciente($hc_number);
$medicos = $pacienteController->getDoctoresAsignados($hc_number);
$solicitudes = $pacienteController->getSolicitudesPorPaciente($hc_number);
$prefacturas = $pacienteController->getPrefacturasPorPaciente($hc_number);

// Unificar ambas listas con etiquetas
foreach ($solicitudes as &$item) {
    $item['origen'] = 'Solicitud';
}
foreach ($prefacturas as &$item) {
    $item['origen'] = 'Prefactura';
}

$timelineItems = array_merge($solicitudes, $prefacturas);

// Ordenar cronológicamente
usort($timelineItems, function ($a, $b) {
    return strtotime($b['fecha']) - strtotime($a['fecha']);
});
$eventos = $pacienteController->getEventosTimeline($hc_number);
$documentos = $pacienteController->getDocumentosDescargables($hc_number);
$estadisticas = $pacienteController->getEstadisticasProcedimientos($hc_number);
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

    <?php include __DIR__ . '/../components/header.php'; ?>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <div class="container-full">
            <!-- Content Header (Page header) -->
            <div class="content-header">
                <div class="d-flex align-items-center">
                    <div class="me-auto">
                        <h3 class="page-title">Detalles del paciente</h3>
                        <div class="d-inline-block align-items-center">
                            <nav>
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="#"><i class="mdi mdi-home-outline"></i></a>
                                    </li>
                                    <li class="breadcrumb-item active" aria-current="page">Detalles del paciente</li>
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
                            <div class="box-body box-profile">
                                <div class="row">
                                    <div class="col-12">
                                        <div>
                                            <p>Fecha de Nacimiento :<span
                                                        class="text-gray ps-10"><?php echo $patientData['fecha_nacimiento']; ?></span>
                                            </p>
                                            <p>Celular :<span
                                                        class="text-gray ps-10"><?php echo $patientData['celular']; ?></span>
                                            </p>
                                            <p>Dirección :<span
                                                        class="text-gray ps-10"><?php echo $patientData['ciudad']; ?></span>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="pb-15">
                                            <p class="mb-10">Social Profile</p>
                                            <div class="user-social-acount">
                                                <button class="btn btn-circle btn-social-icon btn-facebook"><i
                                                            class="fa fa-facebook"></i></button>
                                                <button class="btn btn-circle btn-social-icon btn-twitter"><i
                                                            class="fa fa-twitter"></i></button>
                                                <button class="btn btn-circle btn-social-icon btn-instagram"><i
                                                            class="fa fa-instagram"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div>
                                            <div class="map-box">
                                                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2805244.1745767146!2d-86.32675167439648!3d29.383165774894163!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x88c1766591562abf%3A0xf72e13d35bc74ed0!2sFlorida%2C+USA!5e0!3m2!1sen!2sin!4v1501665415329"
                                                        width="100%" height="175" frameborder="0" style="border:0"
                                                        allowfullscreen></iframe>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- /.box-body -->
                        </div>
                        <div class="box">
                            <div class="box-header border-0 pb-0">
                                <h4 class="box-title">Antecedentes Patológicos</h4>
                            </div>
                            <div class="box-body">
                                <div class="widget-timeline-icon">
                                    <ul>
                                        <?php foreach ($diagnosticos as $diagnosis): ?>
                                            <li>
                                                <div class="icon bg-primary fa fa-heart-o"></div>
                                                <a class="timeline-panel text-muted" href="#">
                                                    <h4 class="mb-2 mt-1"><?php echo htmlspecialchars($diagnosis['idDiagnostico']); ?></h4>
                                                    <p class="fs-15 mb-0 "><?php echo htmlspecialchars($diagnosis['fecha']); ?></p>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="box">
                            <div class="box-header border-0 pb-0">
                                <h4 class="box-title">Médicos asignados</h4>
                            </div>
                            <div class="box-body">
                                <?php foreach ($medicos as $doctorData): ?>
                                    <div class="d-flex align-items-center mb-15">
                                        <img src="../images/avatar/avatar-10.png"
                                             class="w-100 bg-primary-light rounded10 me-15" alt=""/>
                                        <div>
                                            <h4 class="mb-0">
                                                <?php
                                                $formattedName = 'Md. ' . ucwords(strtolower($doctorData['doctor']));
                                                echo htmlspecialchars($formattedName);
                                                ?></h4>
                                            <p class="text-muted">Oftalmólogo</p>
                                            <div class="d-flex">
                                                <i class="text-warning fa fa-star"></i>
                                                <i class="text-warning fa fa-star"></i>
                                                <i class="text-warning fa fa-star"></i>
                                                <i class="text-warning fa fa-star"></i>
                                                <i class="text-warning fa fa-star-half"></i>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="box">
                            <div class="box-header with-border">
                                <h4 class="box-title">Solicitudes</h4>
                                <ul class="box-controls pull-right d-md-flex d-none">
                                    <li class="dropdown">
                                        <button class="btn btn-primary dropdown-toggle px-10 " data-bs-toggle="dropdown"
                                                href="#">Crear
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a class="dropdown-item" href="#"><i class="ti-import"></i> Import</a>
                                            <a class="dropdown-item" href="#"><i class="ti-export"></i> Export</a>
                                            <a class="dropdown-item" href="#"><i class="ti-printer"></i> Print</a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item" href="#"><i class="ti-settings"></i> Settings</a>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                            <div class="box-body">
                                <?php foreach ($timelineItems as $procedimientoData):
                                    $colorMap = [
                                        'solicitud' => 'bg-primary',
                                        'prefactura' => 'bg-info',
                                        'cirugia' => 'bg-danger',
                                        'interconsulta' => 'bg-warning'
                                    ];
                                    $bulletColor = $colorMap[$procedimientoData['tipo']] ?? $colorMap[strtolower($procedimientoData['origen'])] ?? 'bg-secondary';
                                    $fechaSolicitud = new DateTime($procedimientoData['fecha']);
                                    $fechaActual = new DateTime();
                                    $interval = $fechaActual->diff($fechaSolicitud);
                                    $diasRespuesta = $interval->days;
                                    ?>
                                    <div class="d-flex align-items-center mb-25">
                                        <span class="bullet bullet-bar <?= $bulletColor ?> align-self-stretch"></span>
                                        <div class="h-20 mx-20 flex-shrink-0">
                                            <?php $checkboxId = 'md_checkbox_' . uniqid(); ?>
                                            <input type="checkbox" id="<?= $checkboxId ?>"
                                                   class="filled-in chk-col-<?= $bulletColor ?>">
                                            <label for="<?= $checkboxId ?>" class="h-20 p-10 mb-0"></label>
                                        </div>
                                        <div class="d-flex flex-column flex-grow-1">
                                            <a href="#" class="text-dark hover-<?= $bulletColor ?> fw-500 fs-16">
                                                <?= nl2br(htmlspecialchars($procedimientoData['nombre'])) ?>
                                            </a>
                                            <span class="text-fade fw-500">
                                            <?= ucfirst($procedimientoData['origen']) ?> creado el <?= date('d/m/Y', strtotime($procedimientoData['fecha'])) ?>
                                        </span>
                                        </div>
                                        <?php if ($procedimientoData['origen'] === 'Solicitud'): ?>
                                            <div class="dropdown">
                                                <a class="px-10 pt-5" href="#" data-bs-toggle="dropdown"><i
                                                            class="ti-more-alt"></i></a>
                                                <div class="dropdown-menu dropdown-menu-end">
                                                    <a class="dropdown-item flexbox" href="#" data-bs-toggle="modal"
                                                       data-bs-target="#modalSolicitud"
                                                       data-form-id="<?= $procedimientoData['form_id'] ?>"
                                                       data-hc="<?= $hc_number ?>">
                                                        <span>Ver Detalles</span>
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>


                    </div>
                    <div class="col-xl-8 col-12">
                        <div class="box">
                            <?php
                            // Determinar la imagen de fondo en función del seguro
                            $insurance = strtolower($patientData['afiliacion']);
                            $backgroundImage = '/public/assets/logos_seguros/5.png'; // Imagen predeterminada

                            $generalInsurances = [
                                'contribuyente voluntario', 'conyuge', 'conyuge pensionista', 'seguro campesino', 'seguro campesino jubilado',
                                'seguro general', 'seguro general jubilado', 'seguro general por montepío', 'seguro general tiempo parcial'
                            ];

                            foreach ($generalInsurances as $generalInsurance) {
                                if (strpos($insurance, $generalInsurance) !== false) {
                                    $backgroundImage = '/public/assets/logos_seguros/1.png';
                                    break;
                                }
                            }

                            if (strpos($insurance, 'issfa') !== false) {
                                $backgroundImage = '/public/assets/logos_seguros/2.png';
                            } elseif (strpos($insurance, 'isspol') !== false) {
                                $backgroundImage = '/public/assets/logos_seguros/3.png';
                            } elseif (strpos($insurance, 'msp') !== false) {
                                $backgroundImage = '/public/assets/logos_seguros/4.png';
                            }

                            // Determinar la imagen del avatar en función del sexo
                            $gender = strtolower($patientData['sexo']);
                            $avatarImage = '/public/images/avatar/female.png'; // Imagen predeterminada

                            if (strpos($gender, 'masculino') !== false) {
                                $avatarImage = '/public/images/avatar/male.png';
                            }
                            ?>

                            <div class="box-body text-end min-h-150"
                                 style="background-image:url(<?php echo $backgroundImage; ?>); background-repeat: no-repeat; background-position: center; background-size: cover;">
                            </div>
                            <div class="box-body wed-up position-relative">
                                <div class="d-md-flex align-items-center">
                                    <div class=" me-20 text-center text-md-start">
                                        <img src="<?php echo $avatarImage; ?>" style="height: 150px"
                                             class="bg-success-light rounded10"
                                             alt=""/>
                                        <div class="text-center my-10">
                                            <p class="mb-0">Afiliación</p>
                                            <h4><?php echo $patientData['afiliacion']; ?></h4>
                                        </div>
                                    </div>
                                    <div class="mt-40">
                                        <h4 class="fw-600 mb-5"><?php
                                            echo $patientData['fname'] . " " . $patientData['mname'] . " " . $patientData['lname'] . " " . $patientData['lname2'];
                                            ?></h4>
                                        <h5 class="fw-500 mb-5"><?php echo "C. I.: " . $patientData['hc_number']; ?></h5>
                                        <p><i class="fa fa-clock-o"></i> Edad: <?
                                            echo $pacienteController->calcularEdad($patientData['fecha_nacimiento']) . " años";
                                            ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <?php if (!empty($eventos)): ?>
                                    <section class="cd-horizontal-timeline">
                                        <div class="timeline">
                                            <div class="events-wrapper">
                                                <div class="events">
                                                    <ol>
                                                        <?php foreach ($eventos as $index => $row): ?>
                                                            <li>
                                                                <?php
                                                                $fecha_raw = $row['fecha'];
                                                                $fecha_valida = strtotime($fecha_raw) ? date('d/m/Y', strtotime($fecha_raw)) : '01/01/2000';
                                                                $texto_fecha = strtotime($fecha_raw) ? date('d M', strtotime($fecha_raw)) : '01 Jan';
                                                                ?>
                                                                <a href="#0"
                                                                   data-date="<?php echo $fecha_valida; ?>"
                                                                   class="<?php echo $index === 0 ? 'selected' : ''; ?>">
                                                                    <?php echo $texto_fecha; ?>
                                                                </a>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ol>
                                                    <span class="filling-line" aria-hidden="true"></span>
                                                </div>
                                                <!-- .events -->
                                            </div>
                                            <!-- .events-wrapper -->
                                            <ul class="cd-timeline-navigation">
                                                <li><a href="#0" class="prev inactive">Prev</a></li>
                                                <li><a href="#0" class="next">Next</a></li>
                                            </ul>
                                            <!-- .cd-timeline-navigation -->
                                        </div>
                                        <!-- .timeline -->
                                        <div class="events-content">
                                            <ol>
                                                <?php foreach ($eventos as $index => $row):
                                                    $procedimiento_parts = explode(' - ', $row['procedimiento_proyectado']);
                                                    $nombre_procedimiento = implode(' - ', array_slice($procedimiento_parts, 2));
                                                    ?>
                                                    <li data-date="<?php echo date('d/m/Y', strtotime($row['fecha'])); ?>"
                                                        class="<?php echo $index === 0 ? 'selected' : ''; ?>">
                                                        <h2><?php echo htmlspecialchars($nombre_procedimiento); ?></h2>
                                                        <small><?php echo date('F jS, Y', strtotime($row['fecha'])); ?></small>
                                                        <hr class="my-30">
                                                        <p class="pb-30"><?php echo nl2br(htmlspecialchars($row['contenido'])); ?></p>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ol>
                                        </div>
                                        <!-- .events-content -->
                                    </section>
                                <?php else: ?>
                                    <p>No hay datos disponibles para mostrar en el timeline.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-xl-6 col-12">
                                <script>
                                    document.addEventListener('DOMContentLoaded', function () {
                                        filterDocuments('last_3_months'); // Filtrar por defecto los últimos 3 meses
                                    });

                                    function filterDocuments(filter) {
                                        const items = document.querySelectorAll('.media-list .media');
                                        const now = new Date();
                                        items.forEach(item => {
                                            const dateElement = item.querySelector('.text-fade');
                                            const dateText = dateElement ? dateElement.textContent.trim() : '';
                                            const itemDate = new Date(dateText);
                                            let showItem = true;

                                            switch (filter) {
                                                case 'ultimo_mes':
                                                    const lastMonth = new Date();
                                                    lastMonth.setMonth(now.getMonth() - 1);
                                                    showItem = itemDate >= lastMonth;
                                                    break;
                                                case 'ultimos_3_meses':
                                                    const last3Months = new Date();
                                                    last3Months.setMonth(now.getMonth() - 3);
                                                    showItem = itemDate >= last3Months;
                                                    break;
                                                case 'ultimos_6_meses':
                                                    const last6Months = new Date();
                                                    last6Months.setMonth(now.getMonth() - 6);
                                                    showItem = itemDate >= last6Months;
                                                    break;
                                                default:
                                                    showItem = true;
                                            }

                                            item.style.display = showItem ? 'flex' : 'none';
                                        });
                                    }
                                </script>

                                <div class="box">
                                    <div class="box-header with-border">
                                        <h4 class="box-title">Descargar Archivos</h4>
                                        <div class="dropdown pull-right">
                                            <h6 class="dropdown-toggle mb-0" data-bs-toggle="dropdown">Filtro</h6>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="#"
                                                   onclick="filterDocuments('todos'); return false;">Todos</a>
                                                <a class="dropdown-item" href="#"
                                                   onclick="filterDocuments('ultimo_mes'); return false;">Último Mes</a>
                                                <a class="dropdown-item" href="#"
                                                   onclick="filterDocuments('ultimos_3_meses'); return false;">Últimos 3
                                                    Meses</a>
                                                <a class="dropdown-item" href="#"
                                                   onclick="filterDocuments('ultimos_6_meses'); return false;">Últimos 6
                                                    Meses</a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="box-body">
                                        <div class="media-list media-list-divided">
                                            <?php foreach ($documentos as $documento): ?>
                                                <div class="media media-single px-0">
                                                    <div class="ms-0 me-15 bg-<?php echo isset($documento['membrete']) ? 'success' : 'primary'; ?>-light h-50 w-50 l-h-50 rounded text-center d-flex align-items-center justify-content-center">
                                                        <span class="fs-24 text-<?php echo isset($documento['membrete']) ? 'success' : 'primary'; ?>"><i
                                                                    class="fa fa-file-<?php echo isset($documento['membrete']) ? 'pdf' : 'text'; ?>-o"></i></span>
                                                    </div>
                                                    <div class="d-flex flex-column flex-grow-1">
                                                        <span class="title fw-500 fs-16 text-truncate"
                                                              style="max-width: 200px;"><?php echo htmlspecialchars(isset($documento['membrete']) ? $documento['membrete'] : $documento['procedimiento']); ?></span>
                                                        <span class="text-fade fw-500 fs-12"><?php echo date('d M Y', strtotime(isset($documento['fecha_inicio']) ? $documento['fecha_inicio'] : $documento['created_at'])); ?></span>
                                                    </div>
                                                    <a class="fs-18 text-gray hover-info"
                                                       href="#"
                                                       onclick="<?php if (isset($documento['membrete'])): ?>
                                                               descargarPDFsSeparados('<?= $documento['form_id'] ?>', '<?= $documento['hc_number'] ?>')
                                                       <?php else: ?>
                                                               window.open('../reports/solicitud_quirurgica/solicitud_qx_pdf.php?hc_number=<?= $documento['hc_number'] ?>&form_id=<?= $documento['form_id'] ?>', '_blank')
                                                       <?php endif; ?>">
                                                        <i class="fa fa-download"></i>
                                                    </a>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-6 col-12">

                                <div class="box">
                                    <div class="box-header no-border">
                                        <h4 class="box-title">Estadísticas de Citas</h4>
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
<script src="/public/assets/vendor_components/apexcharts-bundle/dist/apexcharts.js"></script>
<script src="/public/assets/vendor_components/horizontal-timeline/js/horizontal-timeline.js"></script>


<!-- Doclinic App -->
<script src="/public/js/jquery.smartmenus.js"></script>
<script src="/public/js/menus.js"></script>
<script src="/public/js/template.js"></script>
<script>
    $(function () {
        'use strict';

        var options = {
            series: <?php echo json_encode(array_values($estadisticas)); ?>,
            chart: {
                type: 'donut',
            },
            colors: ['#3246D3', '#00D0FF', '#ee3158', '#ffa800', '#05825f'],
            legend: {
                position: 'bottom'
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '45%',
                    }
                }
            },
            labels: <?php echo json_encode(array_keys($estadisticas)); ?>,
            responsive: [{
                breakpoint: 1600,
                options: {
                    chart: {
                        width: 330,
                    }
                }
            }, {
                breakpoint: 500,
                options: {
                    chart: {
                        width: 280,
                    }
                }
            }]
        };

        var chart = new ApexCharts(document.querySelector("#chart123"), options);
        chart.render();
    });
</script>
<div class="modal fade" id="modalSolicitud" tabindex="-1" aria-labelledby="modalSolicitudLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalSolicitudLabel">Detalle de la Solicitud</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3 p-3 rounded" id="solicitudContainer" style="background-color: #e9f5ff;">
                    <p class="mb-1"><strong>Fecha:</strong> <span id="modalFecha"
                                                                  class="float-end badge bg-light text-dark"></span></p>
                    <p class="mb-1"><strong>Procedimiento:</strong>
                        <span id="modalProcedimiento"></span></p>
                    <p class="mb-1"><strong>Ojo:</strong>
                        <span id="modalOjo"></span></p>
                    <p class="mb-1"><strong>Diagnóstico:</strong>
                        <span id="modalDiagnostico"></span></p>
                    <p class="mb-1"><strong>Doctor:</strong>
                        <span id="modalDoctor"></span>
                    </p>
                    <p class="mb-1"><strong>Estado:</strong>
                        <span id="modalEstado" class="float-end badge bg-secondary"></span>
                        <span id="modalSemaforo" class="float-end me-2 badge"
                              style="width: 16px; height: 16px; border-radius: 50%;"></span>
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
<script>
    function descargarPDFsSeparados(formId, hcNumber) {
        const paginas = ['protocolo', '005', 'medicamentos', 'signos_vitales', 'insumos', 'saveqx', 'transanestesico'];

        let index = 0;

        function abrirVentana() {
            if (index >= paginas.length) return;

            const pagina = paginas[index];
            const url = `/public/ajax/generate_protocolo_pdf.php?form_id=${formId}&hc_number=${hcNumber}&modo=separado&pagina=${pagina}`;
            const ventana = window.open(url, '_blank');

            // Aumentar el delay solo para transanestesico
            const tiempoEspera = pagina === 'transanestesico' ? 9000 : 2500;

            setTimeout(() => {
                if (ventana) ventana.close();
                index++;
                setTimeout(abrirVentana, 300); // pequeño espacio entre llamadas
            }, tiempoEspera);
        }

        abrirVentana();
    }
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('modalSolicitud');
        modal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const hcNumber = button.getAttribute('data-hc');
            const formId = button.getAttribute('data-form-id');
            fetch(`/public/ajax/detalle_solicitud.php?hc_number=${hcNumber}&form_id=${formId}`)
                .then(response => response.text())
                .then(text => {
                    console.log('Raw response:', text);
                    const data = JSON.parse(text);
                    // Fecha
                    if (data.fecha) {
                        const parts = data.fecha.split('-');
                        document.getElementById('modalFecha').textContent = `${parts[2]}/${parts[1]}/${parts[0]}`;
                    } else {
                        document.getElementById('modalFecha').textContent = '—';
                    }
                    document.getElementById('modalProcedimiento').textContent = data.procedimiento ?? '—';
                    // Diagnóstico
                    const diagnosticosArray = (() => {
                        try {
                            return JSON.parse(data.diagnosticos);
                        } catch {
                            return [];
                        }
                    })();
                    document.getElementById('modalDiagnostico').innerHTML = diagnosticosArray.length
                        ? diagnosticosArray.map((d, i) => `${i + 1}. ${d.idDiagnostico} (${d.ojo})`).join('<br>')
                        : '—';
                    document.getElementById('modalDoctor').textContent = data.doctor ?? '—';
                    document.getElementById('modalDescripcion').textContent = data.plan ?? '—';
                    document.getElementById('modalOjo').textContent = data.ojo ?? '—';
                    document.getElementById('modalEstado').textContent = data.estado ?? '—';
                    document.getElementById('modalMotivo').textContent = data.motivo_consulta ?? '—';
                    document.getElementById('modalEnfermedad').textContent = data.enfermedad_actual ?? '—';

                    // Semaforización visual
                    const semaforo = document.getElementById('modalSemaforo');
                    const estado = data.estado?.toLowerCase();
                    const fechaStr = data.fecha;
                    let color = 'gray';

                    if (estado === 'recibido' && fechaStr) {
                        const fechaSolicitud = new Date(fechaStr);
                        const hoy = new Date();
                        const diffDias = Math.floor((hoy - fechaSolicitud) / (1000 * 60 * 60 * 24));

                        if (diffDias > 14) {
                            color = 'red';
                        } else if (diffDias > 7) {
                            color = 'yellow';
                        } else {
                            color = 'green';
                        }
                    }
                    semaforo.style.backgroundColor = color;
                })
                .catch(error => {
                    console.error('Error cargando los detalles:', error);
                });
        });
    });
</script>
</body>
</html>

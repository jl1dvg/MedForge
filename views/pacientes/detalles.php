<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\DashboardController;
use Controllers\PacienteController;
use Helpers\PacientesHelper;

$pacienteController = new PacienteController($pdo);
$dashboardController = new DashboardController($pdo);

// Obtener todas las afiliaciones disponibles para el select
$username = $dashboardController->getAuthenticatedUser();
$pacientes = $pacienteController->obtenerPacientesConUltimaConsulta();
$hc_number = $_GET['hc_number'] ?? null;
$patientData = $pacienteController->getPatientDetails($hc_number);
$afiliacionesDisponibles = $pacienteController->getAfiliacionesDisponibles();

// Manejar actualización del paciente si se envía el formulario POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_paciente'])) {
    $nuevoNombre = $_POST['fname'] ?? '';
    $nuevoSegundoNombre = $_POST['mname'] ?? '';
    $nuevoApellido = $_POST['lname'] ?? '';
    $nuevoSegundoApellido = $_POST['lname2'] ?? '';
    $nuevaAfiliacion = $_POST['afiliacion'] ?? '';
    $nuevaFechaNacimiento = $_POST['fecha_nacimiento'] ?? '';
    $nuevoSexo = $_POST['sexo'] ?? '';
    $nuevoCelular = $_POST['celular'] ?? '';
    $pacienteController->actualizarPaciente($hc_number, $nuevoNombre, $nuevoSegundoNombre, $nuevoApellido, $nuevoSegundoApellido, $nuevaAfiliacion, $nuevaFechaNacimiento, $nuevoSexo, $nuevoCelular);
    header("Location: detalles.php?hc_number=$hc_number");
    exit;
}
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
                                                    <h4 class="mb-2 mt-1"><?php echo PacientesHelper::safe($diagnosis['idDiagnostico']); ?></h4>
                                                    <p class="fs-15 mb-0 "><?php echo PacientesHelper::safe($diagnosis['fecha']); ?></p>
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
                                                echo PacientesHelper::safe($formattedName);
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
                                                <?= nl2br(PacientesHelper::safe($procedimientoData['nombre'])) ?>
                                            </a>
                                            <span class="text-fade fw-500">
                                            <?= ucfirst($procedimientoData['origen']) ?> creado el <?= PacientesHelper::formatDateSafe($procedimientoData['fecha']) ?>
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
                        <?php include __DIR__ . '/components/tarjeta_paciente.php'; ?>
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
                                                              style="max-width: 200px;"><?php echo PacientesHelper::safe(isset($documento['membrete']) ? $documento['membrete'] : $documento['procedimiento']); ?></span>
                                                        <span class="text-fade fw-500 fs-12"><?php echo PacientesHelper::formatDateSafe($documento['fecha_inicio'] ?? $documento['created_at'], 'd M Y'); ?></span>
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


$controller = new PacientesController($pdo);
$controller->detalles();

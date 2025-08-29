<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/SolicitudHelper.php';

use Controllers\DashboardController;
use Controllers\SolicitudController;
use Helpers\SolicitudHelper;

$dashboardController = new DashboardController($pdo);
$solicitudController = new SolicitudController($pdo);

$username = $dashboardController->getAuthenticatedUser();

$solicitudes = $solicitudController->getSolicitudesConDetalles();

?>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="icon" href="/public/images/favicon.ico">

    <title>Asistente CIVE - Dashboard</title>

    <!-- Vendors Style-->
    <link rel="stylesheet" href="/public/css/vendors_css.css">

    <!-- Style-->
    <link rel="stylesheet" href="/public/css/horizontal-menu.css">
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="/public/css/skin_color.css">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <style>
        .kanban-card {
            border: 1px solid #e1e5eb;
            background: #fff;
            box-shadow: 0 2px 8px 0 rgba(60, 60, 100, 0.04);
            margin-bottom: 1rem;
            padding: 1rem 1rem 0.8rem 1rem;
            border-radius: 12px;
            transition: box-shadow 0.2s, background 0.2s;
            min-width: 100%;
            max-width: 100%;
            width: 100%;
            font-size: 1em;
            position: relative;
        }

        .kanban-card strong {
            font-size: 1.11em;
        }

        .kanban-card:hover, .kanban-card.active {
            background: #f5faff;
            box-shadow: 0 8px 20px 0 rgba(0, 150, 255, 0.08);
        }

        .kanban-items {
            min-height: 150px;
            padding: 0.5em;
            border-radius: 10px;
        }

        .kanban-column {
            min-width: 260px;
            max-width: 260px;
            width: 260px;
            background: #f8fafc;
            border: 1px solid #eef1f5;
            border-radius: 16px;
            box-shadow: 0 1px 6px rgba(140, 150, 180, 0.04);
            margin-right: 8px;
        }

        .kanban-column h5 {
            font-weight: 600;
            font-size: 1.13em;
            padding: 10px 0 0 0;
            background: transparent;
            position: sticky;
            top: 0;
            z-index: 2;
            margin-bottom: 0.2em;
        }

        .kanban-board {
            background: #f1f5fb;
            padding-bottom: 1em;
            overflow-x: auto;
            display: flex;
            flex-wrap: nowrap;
            gap: 1rem;
        }

        #kanban-summary {
            background: linear-gradient(90deg, #e0f4ff 0%, #fafffd 100%);
            border: 1px solid #bee6fd;
            color: #185068;
            font-size: 1.13em;
            border-radius: 16px;
            margin-bottom: 1em;
            box-shadow: 0 2px 8px 0 rgba(60, 120, 200, 0.05);
        }

        @media (max-width: 900px) {
            .kanban-column {
                min-width: 170px;
                max-width: 170px;
                width: 170px;
            }
        }

        .kanban-card.dragging {
            opacity: 0.5;
            transform: scale(1.02);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }

        .kanban-items.drop-area-highlight {
            background-color: #f0f8ff;
            border: 2px dashed #007bff;
            transition: background-color 0.2s ease;
            min-height: 120px;
        }
    </style>

</head>
<body class="layout-top-nav light-skin theme-primary fixed">

<div class="wrapper">
    <!-- <div id="loader"></div> -->
    <?php include __DIR__ . '/../components/header.php'; ?>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <div class="container-full">
            <!-- Content Header (Page header) -->
            <div class="content-header">
                <div class="d-flex align-items-center">
                    <div class="me-auto">
                        <h3 class="page-title">Reporte de Solicitudes de Cirugías</h3>
                        <div class="d-inline-block align-items-center">
                            <nav>
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="#"><i class="mdi mdi-home-outline"></i></a>
                                    </li>
                                    <li class="breadcrumb-item active" aria-current="page">Reporte de Solicitudes de
                                        Cirugías
                                    </li>
                                </ol>
                            </nav>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Main content -->
            <section class="content">
                <!-- Filtros Kanban -->
                <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2">
                    <div class="row mb-3">
                        <div class="col-md-3 mb-2">
                            <label for="kanbanDateFilter" class="form-label">Fecha</label>
                            <input type="text" id="kanbanDateFilter" class="datepicker form-control"
                                   placeholder="Seleccione una fecha">
                        </div>
                        <div class="col-md-3 mb-2">
                            <label for="kanbanAfiliacionFilter" class="form-label">Afiliación</label>
                            <select id="kanbanAfiliacionFilter" class="form-select">
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label for="kanbanDoctorFilter" class="form-label">Doctor</label>
                            <select id="kanbanDoctorFilter" class="form-select">
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label for="kanbanSemaforoFilter" class="form-label">Prioridad</label>
                            <select id="kanbanSemaforoFilter" class="form-select">
                                <option value="">Todas</option>
                                <option value="normal">🟢 Normal (≤ 3 días)</option>
                                <option value="pendiente">🟡 Pendiente (4–7 días)</option>
                                <option value="urgente">🔴 Urgente (&gt; 7 días)</option>
                            </select>
                        </div>
                    </div>
                </div>
                <!-- Kanban Board Container -->
                <div class="kanban-board kanban-board-wrapper d-flex justify-content-between p-3 bg-light">
                    <?php
                    $estados = [
                        'Recibido' => 'recibido',
                        'Revisión Códigos' => 'revision-codigos',
                        'Docs Completos' => 'docs-completos',
                        'Aprobación Anestesia' => 'aprobacion-anestesia',
                        'Listo para Agenda' => 'listo-para-agenda'
                    ];
                    foreach ($estados as $estadoLabel => $estadoId) {
                        echo "<div class='kanban-column kanban-column-wrapper bg-white rounded shadow-sm p-1 me-0'>";
                        echo "<h5 class='text-center'>$estadoLabel <span class='badge bg-primary' id='count-$estadoId' aria-label='Número de solicitudes en estado $estadoLabel'>0</span></h5>";
                        echo "<div class='kanban-items' id='kanban-$estadoId'></div>";
                        echo "</div>";
                    }
                    ?>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="box">
                            <div class="box-body">
                                <div class="col-xs-6 col-md-2">
                                    <div class="media media-single px-0">
                                        <div class="ms-0 me-15 bg-danger-light h-50 w-50 l-h-50 rounded text-center d-flex align-items-center justify-content-center">
                                                        <span class="fs-24 text-danger"><i
                                                                    class="fa fa-file-zip-o"></i></span>
                                        </div>
                                        <div class="d-flex flex-column flex-grow-1">
                                                        <span class="title fw-500 fs-16 text-truncate"
                                                              style="max-width: 200px;">Exportar ZIP</span>
                                        </div>
                                        <a id="exportExcel" class="fs-18 text-gray hover-info"
                                           href="#">
                                            <i class="fa fa-download"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>            <!-- /.content -->

        </div>
    </div>
    <!-- /.content-wrapper -->

</div>


<?php include __DIR__ . '/../components/footer.php'; ?>
</div>

<!-- Vendor JS -->
<script src="/public/js/vendors.min.js"></script>
<script src="/public/js/pages/chat-popup.js"></script>
<script src="/public/assets/icons/feather-icons/feather.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
<script src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.7.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip-utils/0.1.0/jszip-utils.min.js"></script>
<script>
    window.allSolicitudes = <?php echo json_encode(SolicitudHelper::formatearParaFrontend($solicitudes), JSON_UNESCAPED_UNICODE); ?>;
</script>

<script type="module" src="solicitudes.js"></script>

<!-- Doclinic App -->
<script src="/public/js/jquery.smartmenus.js"></script>
<script src="/public/js/menus.js"></script>
<script src="/public/js/template.js"></script>
<div class="modal fade" id="prefacturaModal" tabindex="-1" role="dialog" aria-modal="true"
     aria-labelledby="prefacturaModalLabel" aria-describedby="prefacturaContent">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="prefacturaModalLabel">Detalle de Solicitud</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="prefacturaContent">
                Cargando información...
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" id="btnRevisarCodigos">✅ Códigos Revisado</button>
                <button id="btnSolicitarCobertura" class="btn btn-warning">
                    📤 Solicitar Cobertura
                </button>
            </div>
        </div>
    </div>
</div>
<script>
    // Función para actualizar los contadores de tarjetas Kanban por estado
    function actualizarContadoresKanban() {
        Object.keys(agrupadasPorEstado).forEach(estado => {
            const contador = document.getElementById(`count-${estado}`);
            if (contador) {
                contador.textContent = agrupadasPorEstado[estado].length;
            }
        });
    }
</script>
</body>
<!-- Toast container -->
<div id="toastContainer" style="position: fixed; top: 1rem; right: 1rem; z-index: 1055;"></div>
</html>

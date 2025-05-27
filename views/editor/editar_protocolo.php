<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\DashboardController;
use Controllers\ProcedimientoController;

$procedimientoController = new ProcedimientoController($pdo);
$dashboardController = new DashboardController($pdo);

$idDuplicar = $_GET['duplicar'] ?? null;
$id = $_GET['id'] ?? null;

if ($idDuplicar) {
    // Obtener datos del protocolo original
    $protocoloOriginal = $procedimientoController->obtenerProtocoloPorId($idDuplicar);
    if (!$protocoloOriginal) {
        die("Error: No se encontró el protocolo a duplicar.");
    }

    // Indicar que estamos duplicando, el nuevo ID se generará en guardar_protocolo.php
    $duplicando = true;
    $protocolo = $protocoloOriginal;
    $protocolo['id'] = ''; // Limpiar el ID original para forzar generación nueva basada en el nombre corto
    // Cargar también los datos relacionados desde otras tablas
    $protocolo['codigos'] = $procedimientoController->obtenerCodigosDeProcedimiento($idDuplicar);
    $protocolo['staff'] = $procedimientoController->obtenerStaffDeProcedimiento($idDuplicar);
    $protocolo['insumos'] = $procedimientoController->obtenerInsumosDeProtocolo($idDuplicar);
    $protocolo['medicamentos'] = $procedimientoController->obtenerMedicamentosDeProtocolo($idDuplicar);
} elseif ($id) {
    $protocolo = $procedimientoController->obtenerProtocoloPorId($id);
    if (!$protocolo) {
        die("Error: No se encontró el protocolo.");
    }
    $duplicando = false;
} else {
    die('Error: ID de protocolo no especificado.');
}
$medicamentos = $duplicando ? ($protocolo['medicamentos'] ?? []) : $procedimientoController->obtenerMedicamentosDeProtocolo($protocolo['id']);
$opcionesMedicamentos = $procedimientoController->obtenerOpcionesMedicamentos();
$categorias = $procedimientoController->obtenerCategoriasInsumos();
$insumosDisponibles = $procedimientoController->obtenerInsumosDisponibles();
$insumosPaciente = $duplicando ? ($protocolo['insumos'] ?? []) : $procedimientoController->obtenerInsumosDeProtocolo($protocolo['id']);
$codigos = $protocolo['codigos'] ?? $procedimientoController->obtenerCodigosDeProcedimiento($protocolo['id']);
$staff = $protocolo['staff'] ?? $procedimientoController->obtenerStaffDeProcedimiento($protocolo['id']);


$username = $dashboardController->getAuthenticatedUser();
$vias = ['INTRAVENOSA', 'VIA INFILTRATIVA', 'SUBCONJUNTIVAL', 'TOPICA', 'INTRAVITREA'];
$responsables = ['Asistente', 'Anestesiólogo', 'Cirujano Principal'];
?>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="/public/images/favicon.ico">

    <title>Asistente CIVE - Dashboard</title>

    <!-- Vendors Style-->
    <link rel="stylesheet" href="/public/css/vendors_css.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Style-->
    <link rel="stylesheet" href="/public/css/horizontal-menu.css">
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="/public/css/skin_color.css">
    <!-- SweetAlert2 -->

    <style>
        .autocomplete-box {
            position: absolute;
            background-color: #f9f9f9; /* softer off-white */
            border: 1px solid #bbb; /* darker border for contrast */
            z-index: 9999;
            max-height: 200px;
            overflow-y: auto;
            display: none;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15); /* deeper shadow */
            border-radius: 6px; /* slightly more rounded corners */
            padding: 4px 0;
        }

        .autocomplete-box .suggestion {
            padding: 8px 12px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .autocomplete-box .suggestion:hover,
        .autocomplete-box .suggestion.active {
            background-color: #e0e0e0; /* highlight on hover/active */
        }

        .operatorio-editor {
            border: 1px solid #ccc;
            padding: 8px;
            min-height: 100px;
            white-space: pre-wrap;
            overflow-y: auto;
        }

        .operatorio-editor .tag {
            background-color: #fffb91;
            padding: 2px 4px;
            border-radius: 4px;
        }

        #insumosTable td, #insumosTable th {
            padding: 0.35rem 0.5rem;
            font-size: 1rem;
        }

        #insumosTable td[contenteditable="true"] {
            border: 1px dashed #ccc;
            cursor: text;
        }

        #insumosTable td[contenteditable="true"]:focus {
            outline: 2px solid #007bff;
            background-color: #fff;
        }

        #insumosTable td,
        #insumosTable th {
            text-align: center;
            vertical-align: middle;
        }
    </style>

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
                        <h3 class="page-title">Editar Protocolo</h3>
                        <div class="d-inline-block align-items-center">
                            <nav>
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="/views/dashboard.php"><i
                                                    class="mdi mdi-home-outline"></i> Inicio</a></li>
                                    <li class="breadcrumb-item"><a
                                                href="/views/editor/lista_protocolos.php">Protocolos</a></li>
                                    <li class="breadcrumb-item active"
                                        aria-current="page"><?= htmlspecialchars($protocolo['membrete']) ?></li>
                                </ol>
                            </nav>
                        </div>
                        <?php if (!empty($duplicando) && $duplicando): ?>
                            <div class="alert alert-info mt-10">
                                <strong>Duplicando protocolo:</strong> estás creando una copia basada en
                                <em><?= htmlspecialchars($idDuplicar) ?></em>.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <section class="content">
                <div class="row">
                    <div class="col-lg-12 col-12">
                        <div class="box">
                            <div class="box-header with-border">
                                <h4 class="box-title">Editar Protocolo</h4>
                            </div>
                            <!-- /.box-header -->
                            <form id="editarProtocoloForm" action="/views/editor/guardar_protocolo.php" method="POST"
                                  class="form">
                                <section>
                                    <div class="box-body">
                                        <div class="accordion mb-3" id="accordionGeneral">

                                            <!-- Requerido -->
                                            <div class="accordion-item">
                                                <h4 class="accordion-header" id="headingRequerido">
                                                    <button class="accordion-button collapsed box-title text-info mt-20"
                                                            type="button"
                                                            data-bs-toggle="collapse"
                                                            data-bs-target="#collapseRequerido"
                                                            aria-expanded="false" aria-controls="collapseRequerido"
                                                            data-bs-parent="#accordionGeneral">
                                                        <i class="fa fa-asterisk me-15"></i> Requerido
                                                    </button>
                                                </h4>
                                                <div id="collapseRequerido" class="accordion-collapse collapse"
                                                     aria-labelledby="headingRequerido">
                                                    <div class="accordion-body">
                                                        <?php
                                                        $codigos = $codigos ?? $protocolo['codigos'] ?? [];
                                                        include __DIR__ . '/secciones/requerido.php'; ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Staff -->
                                            <div class="accordion-item">
                                                <h4 class="accordion-header" id="headingStaff">
                                                    <button class="accordion-button collapsed box-title text-info mt-20"
                                                            type="button"
                                                            data-bs-toggle="collapse"
                                                            data-bs-target="#collapseStaff"
                                                            aria-expanded="false" aria-controls="collapseStaff"
                                                            data-bs-parent="#accordionGeneral">
                                                        <i class="fa fa-user-md me-15"></i> Staff
                                                    </button>
                                                </h4>
                                                <div id="collapseStaff" class="accordion-collapse collapse"
                                                     aria-labelledby="headingStaff">
                                                    <div class="accordion-body">
                                                        <?php
                                                        $staff = $staff ?? $protocolo['staff'] ?? [];
                                                        include __DIR__ . '/secciones/staff.php'; ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Operatorio -->
                                            <div class="accordion-item">
                                                <h4 class="accordion-header" id="headingOperatorio">
                                                    <button class="accordion-button collapsed box-title text-info mt-20"
                                                            type="button"
                                                            data-bs-toggle="collapse"
                                                            data-bs-target="#collapseOperatorio"
                                                            aria-expanded="false" aria-controls="collapseOperatorio"
                                                            data-bs-parent="#accordionGeneral">
                                                        <i class="fa fa-server me-15"></i> Operatorio
                                                    </button>
                                                </h4>
                                                <div id="collapseOperatorio" class="accordion-collapse collapse"
                                                     aria-labelledby="headingOperatorio">
                                                    <div class="accordion-body">
                                                        <?php include __DIR__ . '/secciones/operatorio.php'; ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Evolución -->
                                            <div class="accordion-item">
                                                <h4 class="accordion-header" id="headingEvolucion">
                                                    <button class="accordion-button collapsed box-title text-info mt-20"
                                                            type="button"
                                                            data-bs-toggle="collapse"
                                                            data-bs-target="#collapseEvolucion"
                                                            aria-expanded="false" aria-controls="collapseEvolucion"
                                                            data-bs-parent="#accordionGeneral">
                                                        <i class="fa fa-notes-medical me-15"></i> Evolución
                                                    </button>
                                                </h4>
                                                <div id="collapseEvolucion" class="accordion-collapse collapse"
                                                     aria-labelledby="headingEvolucion">
                                                    <div class="accordion-body">
                                                        <?php include __DIR__ . '/secciones/evolucion.php'; ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Kardex -->
                                            <div class="accordion-item">
                                                <h4 class="accordion-header" id="headingKardex">
                                                    <button class="accordion-button collapsed box-title text-info mt-20"
                                                            type="button"
                                                            data-bs-toggle="collapse"
                                                            data-bs-target="#collapseKardex"
                                                            aria-expanded="false" aria-controls="collapseKardex"
                                                            data-bs-parent="#accordionGeneral">
                                                        <i class="fa fa-prescription-bottle-alt me-15"></i> Kardex
                                                    </button>
                                                </h4>
                                                <div id="collapseKardex" class="accordion-collapse collapse"
                                                     aria-labelledby="headingKardex">
                                                    <div class="accordion-body">
                                                        <?php include __DIR__ . '/secciones/kardex.php'; ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Insumos -->
                                            <div class="accordion-item">
                                                <h4 class="accordion-header" id="headingInsumos">
                                                    <button class="accordion-button collapsed box-title text-info mt-20"
                                                            type="button"
                                                            data-bs-toggle="collapse"
                                                            data-bs-target="#collapseInsumos"
                                                            aria-expanded="false" aria-controls="collapseInsumos"
                                                            data-bs-parent="#accordionGeneral">
                                                        <i class="fa fa-boxes me-15"></i> Lista de Insumos
                                                    </button>
                                                </h4>
                                                <div id="collapseInsumos" class="accordion-collapse collapse"
                                                     aria-labelledby="headingInsumos">
                                                    <div class="accordion-body">
                                                        <?php include __DIR__ . '/secciones/insumos.php'; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Footer del form -->
                                        <div class="box-footer">
                                            <button type="button" class="btn btn-warning me-1"
                                                    onclick="window.history.back();">
                                                <i class="ti-trash"></i> Cancelar
                                            </button>
                                            <button type="button" id="guardarProtocolo" class="btn btn-primary">
                                                <i class="ti-save-alt"></i> Guardar
                                            </button>
                                        </div>

                                    </div>
                                </section>
                            </form>
                        </div>
                        <!-- /.box -->
                    </div>
                </div>
            </section>
            <!-- /.content -->

        </div>
    </div>
    <!-- /.content-wrapper -->
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

<!-- Vendor JS -->
<script src="/public/js/vendors.min.js"></script> <!-- contiene jQuery -->
<script src="/public/js/pages/chat-popup.js"></script>
<script src="/public/assets/icons/feather-icons/feather.min.js"></script>
<script src="/public/assets/vendor_components/datatable/datatables.min.js"></script>
<script src="/public/assets/vendor_components/tiny-editable/mindmup-editabletable.js"></script>
<script src="/public/assets/vendor_components/tiny-editable/numeric-input-example.js"></script>


<!-- Doclinic App -->
<script src="/public/js/jquery.smartmenus.js"></script>
<script src="/public/js/menus.js"></script>
<script src="/public/js/template.js"></script>
<script>
    const insumosDisponibles = <?= json_encode($insumosDisponibles); ?>;
    const opcionesMedicamentos = <?= json_encode($opcionesMedicamentos); ?>;
    const vias = <?= json_encode($vias); ?>;
    const responsables = <?= json_encode($responsables); ?>;
</script>
<script>
    const codigos = <?= json_encode($protocolo['codigos'] ?? []); ?>;
    const staff = <?= json_encode($protocolo['staff'] ?? []); ?>;
</script>
<input type="hidden" id="medicamentosInput" name="medicamentos"
       value='<?= json_encode($protocolo['medicamentos'] ?? []) ?>'>
<input type="hidden" id="insumosInput" name="insumos"
       value='<?= json_encode($protocolo['insumos'] ?? ["equipos" => [], "quirurgicos" => [], "anestesia" => []]) ?>'>
<script src="/public/js/editor-protocolos.js"></script>
<script src="/public/js/autocomplete-operatorio.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>

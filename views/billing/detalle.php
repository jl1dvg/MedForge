<?php
require_once __DIR__ . '/../../bootstrap.php'; // Autoload y setup inicial
require_once __DIR__ . '/../../helpers/format.php';

use Controllers\BillingController;
use Controllers\DashboardController;
$dashboardController = new DashboardController($pdo);
// Paso 1: Obtener todas las facturas disponibles
$username = $dashboardController->getAuthenticatedUser();

if (!isset($_GET['form_id'])) {
    die("Error: falta el parámetro form_id.");
}

$formId = $_GET['form_id'];
$db = require __DIR__ . '/../../config/database.php';
$controller = new BillingController($db);
$datos = $controller->obtenerDatos($formId);

if (!$datos) {
    die("No se encontró información para el form_id: $formId");
}

$paciente = $datos['paciente'];
$procedimientos = $datos['procedimientos'];
$derechos = $datos['derechos'];
$insumos = $datos['insumos'];
$medicamentos = $datos['medicamentos'];
$oxigeno = $datos['oxigeno'];
$anestesia = $datos['anestesia'];

// Obtener datos adicionales para encabezado
$billing = $controller->obtenerFacturasDisponibles();
$billing = array_filter($billing, fn($b) => $b['form_id'] === $formId);
$billing = array_shift($billing);

$nombreCompleto = trim(($paciente['lname'] ?? '') . ' ' . ($paciente['lname2'] ?? '') . ' ' . ($paciente['fname'] ?? '') . ' ' . ($paciente['mname'] ?? ''));
$hcNumber = $paciente['hc_number'] ?? '';
$afiliacion = strtoupper($paciente['afiliacion'] ?? '-');

$codigoDerivacion = null;
$doctor = null;
$fecha_registro = null;
$fecha_vigencia = null;
$diagnostico = null;

if (!empty($billing)) {
    $derivacionData = $controller->obtenerDerivacionPorFormId($billing['form_id']);
    $codigoDerivacion = $derivacionData['cod_derivacion'] ?? null;
    $doctor = $derivacionData['referido'] ?? null;
    $fecha_registro = $derivacionData['fecha_registro'] ?? null;
    $fecha_vigencia = $derivacionData['fecha_vigencia'] ?? null;
    $diagnostico = $derivacionData['diagnostico'] ?? null;
}

// Definir clases para grupos (asumiendo que no viene definido)
$grupoClases = [
    'CIRUJANO' => 'table-primary',
    'AYUDANTE' => 'table-secondary',
    'ANESTESIA' => 'table-danger',
    'FARMACIA' => 'table-success',
    'INSUMOS' => 'table-warning',
    'DERECHOS' => 'table-info',
];

// Funciones auxiliares para cálculos y formateo
function formatearMoneda($monto)
{
    return number_format($monto, 2, ',', '.');
}

function aplicarIVA($monto, $iva = 0.15)
{
    return $monto * (1 + $iva);
}

// Agrupar procedimientos por grupo y calcular subtotales
$grupos = ['CIRUJANO', 'AYUDANTE', 'ANESTESIA', 'FARMACIA', 'INSUMOS', 'DERECHOS'];
$detallePorGrupo = [];
foreach ($grupos as $grupo) {
    $detallePorGrupo[$grupo] = [];
}

foreach ($procedimientos as $proc) {
    $grupo = strtoupper($proc['grupo'] ?? '');
    if (in_array($grupo, $grupos)) {
        $detallePorGrupo[$grupo][] = $proc;
    }
}

// Agregar insumos y medicamentos a INSUMOS y FARMACIA respectivamente
foreach ($insumos as $item) {
    $detallePorGrupo['INSUMOS'][] = $item + ['proc_codigo' => $item['codigo'], 'proc_detalle' => $item['nombre'], 'proc_precio' => $item['precio']];
}
foreach ($medicamentos as $item) {
    $detallePorGrupo['FARMACIA'][] = $item + ['proc_codigo' => $item['codigo'], 'proc_detalle' => $item['nombre'], 'proc_precio' => $item['precio']];
}
// Derechos en DERECHOS
foreach ($derechos as $item) {
    $detallePorGrupo['DERECHOS'][] = $item + ['proc_codigo' => $item['codigo'], 'proc_detalle' => $item['detalle'], 'proc_precio' => $item['precio_afiliacion']];
}
// Anestesia en ANESTESIA
foreach ($anestesia as $item) {
    $detallePorGrupo['ANESTESIA'][] = $item + ['proc_codigo' => $item['codigo'], 'proc_detalle' => $item['nombre'], 'proc_precio' => $item['precio']];
}

// Calcular subtotales por grupo
$subtotales = [];
foreach ($detallePorGrupo as $grupo => $items) {
    $totalGrupo = 0;
    foreach ($items as $item) {
        $precio = $item['proc_precio'] ?? 0;
        $cantidad = $item['cantidad'] ?? 1;
        $totalGrupo += $precio * $cantidad;
    }
    $subtotales[$grupo] = $totalGrupo;
}

// Calcular total general
$totalSinIVA = array_sum($subtotales);
$iva = $totalSinIVA * 0.15;
$totalConIVA = $totalSinIVA + $iva;

?><!DOCTYPE html>
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
                        <h3 class="page-title">Informe ISSFA</h3>
                        <div class="d-inline-block align-items-center">
                            <nav>
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="#"><i class="mdi mdi-home-outline"></i></a>
                                    </li>
                                    <li class="breadcrumb-item active" aria-current="page">Consolidado y por factura
                                    </li>
                                </ol>
                            </nav>
                        </div>
                    </div>

                </div>
            </div>
            <div class="content">
                <!-- Main content -->
                <div class="row">
                    <div class="col-lg-12 col-12">
                        <div class="box">
                            <div class='row invoice-info mb-3'>
                                <div class="col-md-6 invoice-col">
                                    <strong>Desde</strong>
                                    <address>
                                        <strong class="text-blue fs-24">Clínica Internacional de Visión del Ecuador
                                            -
                                            CIVE</strong><br>
                                        <span class="d-inline">Parroquia satélite La Aurora de Daule, km 12 Av. León Febres-Cordero.</span><br>
                                        <strong>Teléfono: (04) 372-9340 &nbsp;&nbsp;&nbsp; Email:
                                            info@cive.ec</strong>
                                    </address>
                                </div>
                                <div class="col-md-6 invoice-col text-end">
                                    <strong>Paciente</strong>
                                    <address>
                                        <strong class="text-blue fs-24"><?= htmlspecialchars($nombreCompleto) ?></strong><br>
                                        HC: <span class="badge bg-primary"><?= htmlspecialchars($hcNumber) ?></span><br>
                                        Afiliación: <span class="badge bg-info"><?= $afiliacion ?></span><br>
                                        <?php if (!empty($paciente['ci'])): ?>
                                            Cédula: <?= htmlspecialchars($paciente['ci']) ?><br>
                                        <?php endif; ?>
                                        <?php if (!empty($paciente['fecha_nacimiento'])): ?>
                                            F. Nacimiento: <?= date('d/m/Y', strtotime($paciente['fecha_nacimiento'])) ?>
                                            <br>
                                        <?php endif; ?>
                                    </address>
                                </div>
                                <div class="col-sm-12 invoice-col mb-15">
                                    <div class="invoice-details row no-margin">
                                        <div class="col-md-6 col-lg-3"><b>Pedido:</b> <?= $codigoDerivacion ?></div>
                                        <div class="col-md-6 col-lg-3"><b>Fecha
                                                Registro:</b> <?= !empty($fecha_registro) ? date('d/m/Y', strtotime($fecha_registro ?? '')) : '--' ?>
                                        </div>
                                        <div class="col-md-6 col-lg-3"><b>Fecha
                                                Vigencia:</b> <?= !empty($fecha_vigencia) ? date('d/m/Y', strtotime($fecha_vigencia)) : '--' ?>
                                        </div>
                                        <div class="col-md-6 col-lg-3">
                                            <b>Médico:</b> <?= htmlspecialchars($doctor) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="mb-3">
                                    <strong>Leyenda de colores:</strong>
                                    <div class="d-flex flex-wrap gap-2 mt-2">
                                        <span class="badge bg-primary">Cirujano</span>
                                        <span class="badge bg-info text-dark">Ayudante</span>
                                        <span class="badge bg-danger">Anestesia</span>
                                        <span class="badge bg-warning text-dark">Farmacia</span>
                                        <span class="badge bg-success">Farmacia Especial</span>
                                        <span class="badge bg-light text-dark">Insumos</span>
                                        <span class="badge bg-secondary">Derechos / Institucionales</span>
                                    </div>
                                </div>
                                <div class="container mt-4">
                                    <div class="mb-3">
                                        <?php include __DIR__ . '/../informes/components/scrapping_procedimientos.php'; ?>
                                    </div>

                                    <div class="col-12 table-responsive mb-4">
                                        <table class="table table-bordered table-striped align-middle">
                                            <thead class="table-dark">
                                            <tr>
                                                <th>#</th>
                                                <th>Código</th>
                                                <th>Descripción</th>
                                                <th>Anestesia</th>
                                                <th>%Pago</th>
                                                <th>Cantidad</th>
                                                <th>Valor Unitario</th>
                                                <th>Subtotal</th>
                                                <th>%Bodega</th>
                                                <th>%IVA</th>
                                                <th>+10% Gestión</th>
                                                <th>Total</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php
                                            $n = 1;
                                            $total = 0;
                                            foreach ($detallePorGrupo as $grupo => $items):
                                                if (count($items) === 0) continue;
                                                ?>
                                                <tr class="<?= $grupoClases[$grupo] ?? '' ?>">
                                                    <td colspan="12"><strong><?= htmlspecialchars($grupo) ?></strong>
                                                    </td>
                                                </tr>
                                                <?php foreach ($items as $item): ?>
                                                <?php
                                                $codigo = $item['proc_codigo'] ?? '';
                                                $detalle = $item['proc_detalle'] ?? '';
                                                $anestesia = $item['anestesia'] ?? '';
                                                $porcentaje_pago = isset($item['porcentaje_pago']) ? $item['porcentaje_pago'] : '';
                                                $cantidad = $item['cantidad'] ?? 1;
                                                $precio = $item['proc_precio'] ?? 0;
                                                $subtotal = $cantidad * $precio;
                                                $porc_bodega = $item['porc_bodega'] ?? '';
                                                $porc_iva = $item['porc_iva'] ?? '';
                                                $gestion = $item['gestion'] ?? '';
                                                // Lógica de cálculo de total por fila
                                                $valor_iva = $porc_iva !== '' ? ($subtotal * floatval($porc_iva) / 100) : 0;
                                                $valor_gestion = $gestion !== '' ? ($subtotal * floatval($gestion) / 100) : 0;
                                                $valor_bodega = $porc_bodega !== '' ? ($subtotal * floatval($porc_bodega) / 100) : 0;
                                                $total_fila = $subtotal + $valor_iva + $valor_gestion + $valor_bodega;
                                                $total += $total_fila;
                                                ?>
                                                <tr>
                                                    <td><?= $n++ ?></td>
                                                    <td><?= htmlspecialchars($codigo) ?></td>
                                                    <td><?= htmlspecialchars($detalle) ?></td>
                                                    <td><?= htmlspecialchars($anestesia) ?></td>
                                                    <td><?= htmlspecialchars($porcentaje_pago) ?></td>
                                                    <td><?= $cantidad ?></td>
                                                    <td>$<?= formatearMoneda($precio) ?></td>
                                                    <td>$<?= formatearMoneda($subtotal) ?></td>
                                                    <td><?= $porc_bodega !== '' ? $porc_bodega . '%' : '' ?></td>
                                                    <td><?= $porc_iva !== '' ? $porc_iva . '%' : '' ?></td>
                                                    <td><?= $gestion !== '' ? $gestion . '%' : '' ?></td>
                                                    <td>$<?= formatearMoneda($total_fila) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                                <tr class="table-secondary">
                                                    <td colspan="11" class="text-end">
                                                        <strong>Subtotal <?= htmlspecialchars($grupo) ?>:</strong></td>
                                                    <td><strong>$<?= formatearMoneda($subtotales[$grupo]) ?></strong>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                            <tfoot>
                                            <tr class="table-dark">
                                                <td colspan="11" class="text-end"><strong>Total General:</strong></td>
                                                <td><strong>$<?= formatearMoneda($total) ?></strong></td>
                                            </tr>
                                            </tfoot>
                                        </table>
                                    </div>

                                    <div class="mb-4">
                                        <h4>Resumen de Factura</h4>
                                        <table class="table table-sm table-bordered w-50">
                                            <tbody>
                                            <tr>
                                                <td><strong>Subtotal sin IVA</strong></td>
                                                <td>$<?= formatearMoneda($totalSinIVA) ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>IVA 15%</strong></td>
                                                <td>$<?= formatearMoneda($iva) ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Total Final</strong></td>
                                                <td><strong>$<?= formatearMoneda($totalConIVA) ?></strong></td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="mb-4">
                                        <a href="export_excel.php?form_id=<?= urlencode($formId) ?>"
                                           class="btn btn-success">Exportar
                                            Excel</a>
                                        <a href="index.php" class="btn btn-outline-primary">Volver al consolidado</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
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

</body>
</html>
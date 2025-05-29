<?php
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/InformesHelper.php';

use Controllers\BillingController;
use Controllers\PacienteController;
use Controllers\DashboardController;
use Helpers\InformesHelper;

$billingController = new BillingController($pdo);

$pacienteController = new PacienteController($pdo);
$dashboardController = new DashboardController($pdo);

// Paso 1: Obtener todas las facturas disponibles
$username = $dashboardController->getAuthenticatedUser();
$facturas = $billingController->obtenerFacturasDisponibles();

// Obtener modo de informe
$modo = $_GET['modo'] ?? 'individual';

// Paso 2: Detectar si hay selección
$billingId = $_GET['billing_id'] ?? null;
$formId = null;
$datos = [];

// Filtro de mes para modo consolidado
if ($modo === 'consolidado') {
    $mesSeleccionado = $_GET['mes'] ?? '';
}

if ($billingId) {
    // Buscar form_id relacionado
    $stmt = $pdo->prepare("SELECT form_id FROM billing_main WHERE id = ?");
    $stmt->execute([$billingId]);
    $formId = $stmt->fetchColumn();

    if ($formId) {
        $datos = $billingController->obtenerDatos($formId);
    }
}
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
                        <h3 class="page-title">Informe ISSPOL</h3>
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
                            <form method="GET" class="mb-4">
                                <label for="modo" class="form-label">Modo de informe:</label>
                                <select name="modo" id="modo" class="form-select mb-3" onchange="this.form.submit()">
                                    <option value="individual" <?= ($modo === 'individual' ? 'selected' : '') ?>>Por
                                        paciente
                                    </option>
                                    <option value="consolidado" <?= ($modo === 'consolidado' ? 'selected' : '') ?>>
                                        Consolidado
                                        mensual
                                    </option>
                                </select>

                                <?php if ($modo !== 'consolidado'): ?>
                                    <label for="billing_id" class="form-label">Selecciona una factura:</label>
                                    <select name="billing_id" id="billing_id" class="form-select"
                                            onchange="this.form.submit()">
                                        <option value="">-- Selecciona una factura --</option>
                                        <?php foreach ($facturas as $factura): ?>
                                            <?php
                                            $pacienteInfo = $pacienteController->getPatientDetails($factura['hc_number']);
                                            if (strtoupper($pacienteInfo['afiliacion'] ?? '') !== 'ISSPOL') continue;
                                            $fechaFormateada = date('d/m/Y', strtotime($factura['fecha_inicio']));
                                            $nombrePaciente = $pacienteInfo['fname'] . ' ' . $pacienteInfo['lname'];
                                            $formIdTexto = $factura['form_id'] ?? 'Sin proceso';
                                            ?>
                                            <option value="<?= $factura['id'] ?>" <?= ($factura['id'] == $billingId ? 'selected' : '') ?>>
                                                <?= "{$nombrePaciente} | Proceso: {$formIdTexto} | {$fechaFormateada}" ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>

                                <?php if ($modo === 'consolidado'): ?>
                                    <!-- Filtros adicionales para consolidado -->
                                    <form method="GET" class="row g-3 mb-3">
                                        <input type="hidden" name="modo" value="consolidado">
                                        <div class="col-md-4">
                                            <label for="mes" class="form-label">Mes</label>
                                            <select name="mes" id="mes" class="form-select"
                                                    onchange="this.form.submit()">
                                                <option value="">-- Todos los meses --</option>
                                                <?php
                                                $mesesDisponibles = array_unique(array_map(function ($factura) {
                                                    return date('Y-m', strtotime($factura['fecha_inicio']));
                                                }, $facturas));
                                                sort($mesesDisponibles);
                                                foreach ($mesesDisponibles as $mesOption):
                                                    $selected = ($_GET['mes'] ?? '') === $mesOption ? 'selected' : '';
                                                    echo "<option value='$mesOption' $selected>" . date('F Y', strtotime($mesOption . "-01")) . "</option>";
                                                endforeach;
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="apellido" class="form-label">Apellido del paciente</label>
                                            <input type="text" name="apellido" id="apellido" class="form-control"
                                                   value="<?= htmlspecialchars($_GET['apellido'] ?? '') ?>"
                                                   placeholder="Buscar por apellido">
                                        </div>
                                        <!--
                                        <div class="col-md-4">
                                            <label for="cie10" class="form-label">Código CIE10</label>
                                            <input type="text" name="cie10" id="cie10" class="form-control"
                                                   value="<?= htmlspecialchars($_GET['cie10'] ?? '') ?>"
                                                   placeholder="Buscar por CIE10">
                                        </div>
                                        -->
                                        <!--
                                        <div class="col-md-4">
                                            <label for="medico" class="form-label">Médico tratante</label>
                                            <input type="text" name="medico" id="medico" class="form-control"
                                                   value="<?= htmlspecialchars($_GET['medico'] ?? '') ?>"
                                                   placeholder="Buscar por médico">
                                        </div>
                                        -->
                                        <div class="col-md-12">
                                            <button type="submit" class="btn btn-primary">Aplicar filtros</button>
                                            <a href="/views/informes/informe_isspol.php?modo=consolidado"
                                               class="btn btn-secondary">Limpiar</a>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </form>

                            <?php if ($formId && $datos): ?>
                                <h5>
                                    Paciente: <?= $datos['paciente']['lname'] . ' ' . $datos['paciente']['lname2'] . ' ' . $datos['paciente']['fname'] . ' ' . $datos['paciente']['mname'] ?? '' ?>
                                    (<?= $datos['paciente']['hc_number'] ?? '' ?>)</h5>
                                <h6>Afiliación: <?= strtoupper($datos['paciente']['afiliacion'] ?? '-') ?></h6>
                                <div class="table-responsive"
                                     style="overflow-x: auto; max-width: 100%; font-size: 0.85rem;">
                                    <table class="table table-bordered table-striped mt-4">
                                        <thead class="table-dark">
                                        <tr>
                                            <th>Tipo Prestación</th>
                                            <th>Cédula Paciente</th>
                                            <th>Período</th>
                                            <th>Grupo-tipo</th>
                                            <th>Tipo de procedimiento</th>
                                            <th>Cédula del médico</th>
                                            <th>Fecha de prestación</th>
                                            <th>Código</th>
                                            <th>Descripción</th>
                                            <th>Anestesia</th>
                                            <th>%Pago</th>
                                            <th>Cantidad</th>
                                            <th>Valor Unitario</th>
                                            <th>Subtotal</th>
                                            <th>%Bodega</th>
                                            <th>%IVA</th>
                                            <th>Total</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $total = 0;
                                        $periodo = $datos['formulario']['fecha_inicio'] ?? '';
                                        $fecha = $periodo;
                                        $cedulaMedico = $datos['paciente']['cedula_medico'] ?? '';
                                        $cedulaPaciente = $datos['paciente']['ci'] ?? $datos['paciente']['hc_number'];

                                        foreach ($datos['procedimientos'] as $index => $p) {
                                            $codigo = $p['proc_codigo'] ?? '';
                                            $descripcion = $p['proc_detalle'] ?? '';
                                            $precio = (float)($p['proc_precio'] ?? 0);
                                            $cantidad = 1;

                                            if ($index === 0 || stripos($descripcion, 'separado') !== false) {
                                                $porcentaje = 1;
                                            } else {
                                                $porcentaje = 0.5;
                                            }

                                            if ($codigo === '67036') {
                                                $porcentaje = 0.625;
                                            }

                                            $valorUnitario = $precio;
                                            $subtotal = $valorUnitario * $cantidad * $porcentaje;
                                            $total += $subtotal;

                                            $tipo = 'AMBULATORIO';
                                            $grupo = 'HONORARIOS PROFESIONALES';
                                            $tipoProc = 'CIRUJANO';
                                            $anestesia = 'NO';
                                            $porcentajePago = $porcentaje * 100;
                                            $bodega = 0;
                                            $iva = 0;
                                            $montoTotal = $subtotal;

                                            echo "<tr>
                        <td>{$tipo}</td>
                        <td>{$cedulaPaciente}</td>
                        <td>{$periodo}</td>
                        <td>{$grupo}</td>
                        <td>{$tipoProc}</td>
                        <td>{$cedulaMedico}</td>
                        <td>{$fecha}</td>
                        <td>{$codigo}</td>
                        <td>{$descripcion}</td>
                        <td>{$anestesia}</td>
                        <td>{$porcentajePago}</td>
                        <td>{$cantidad}</td>
                        <td>" . number_format($valorUnitario, 2) . "</td>
                        <td>" . number_format($subtotal, 2) . "</td>
                        <td>{$bodega}</td>
                        <td>{$iva}</td>
                        <td>" . number_format($montoTotal, 2) . "</td>
                    </tr>";
                                        }

                                        if (!empty($datos['protocoloExtendido']['cirujano_2']) || !empty($datos['protocoloExtendido']['primer_ayudante'])) {
                                            foreach ($datos['procedimientos'] as $index => $p) {
                                                $codigo = $p['proc_codigo'] ?? '';
                                                $descripcion = $p['proc_detalle'] ?? '';
                                                $precio = (float)($p['proc_precio'] ?? 0);
                                                $cantidad = 1;

                                                $porcentaje = ($index === 0) ? 0.2 : 0.1;
                                                $valorUnitario = $precio;
                                                $subtotal = $valorUnitario * $cantidad * $porcentaje;
                                                $total += $subtotal;

                                                $tipo = 'AMBULATORIO';
                                                $grupo = 'HONORARIOS PROFESIONALES';
                                                $tipoProc = 'AYUDANTE';
                                                $anestesia = 'NO';
                                                $porcentajePago = $porcentaje * 100;
                                                $bodega = 0;
                                                $iva = 0;
                                                $montoTotal = $subtotal;

                                                echo "<tr>
                            <td>{$tipo}</td>
                            <td>{$cedulaPaciente}</td>
                            <td>{$periodo}</td>
                            <td>{$grupo}</td>
                            <td>{$tipoProc}</td>
                            <td>{$cedulaMedico}</td>
                            <td>{$fecha}</td>
                            <td>{$codigo}</td>
                            <td>{$descripcion}</td>
                            <td>{$anestesia}</td>
                            <td>{$porcentajePago}</td>
                            <td>{$cantidad}</td>
                            <td>" . number_format($valorUnitario, 2) . "</td>
                            <td>" . number_format($subtotal, 2) . "</td>
                            <td>{$bodega}</td>
                            <td>{$iva}</td>
                            <td>" . number_format($montoTotal, 2) . "</td>
                        </tr>";
                                            }
                                        }

                                        foreach ($datos['anestesia'] as $a) {
                                            $codigo = $a['codigo'];
                                            $descripcion = $a['nombre'];
                                            $cantidad = (float)$a['tiempo'];
                                            $valorUnitario = (float)$a['valor2'];
                                            $subtotal = $cantidad * $valorUnitario;
                                            $total += $subtotal;
                                            $tipo = 'AMBULATORIO';
                                            $grupo = 'HONORARIOS PROFESIONALES';
                                            $tipoProc = 'ANESTESIOLOGO';
                                            $anestesia = 'SI';
                                            $porcentajePago = 100;
                                            $bodega = 0;
                                            $iva = 0;
                                            $montoTotal = $subtotal;

                                            echo "<tr>
                        <td>{$tipo}</td>
                        <td>{$cedulaPaciente}</td>
                        <td>{$periodo}</td>
                        <td>{$grupo}</td>
                        <td>{$tipoProc}</td>
                        <td>{$cedulaMedico}</td>
                        <td>{$fecha}</td>
                        <td>{$codigo}</td>
                        <td>{$descripcion}</td>
                        <td>{$anestesia}</td>
                        <td>{$porcentajePago}</td>
                        <td>{$cantidad}</td>
                        <td>" . number_format($valorUnitario, 2) . "</td>
                        <td>" . number_format($subtotal, 2) . "</td>
                        <td>{$bodega}</td>
                        <td>{$iva}</td>
                        <td>" . number_format($montoTotal, 2) . "</td>
                    </tr>";
                                        }
                                        if (!empty($datos['procedimientos'][0])) {
                                            $codigoAnestesia = $datos['procedimientos'][0]['proc_codigo'] ?? '';
                                            $precioReal = $codigoAnestesia ? $billingController->obtenerValorAnestesia($codigoAnestesia) : null;

                                            $p = $datos['procedimientos'][0];
                                            $codigo = $p['proc_codigo'] ?? '';
                                            $descripcion = $p['proc_detalle'] ?? '';
                                            $precio = (float)$p['proc_precio'] ?? 0;
                                            $cantidad = 1;
                                            $porcentaje = 1;
                                            $valorUnitario = $precioReal ?? $precio;
                                            $subtotal = $valorUnitario * $cantidad * $porcentaje;
                                            $total += $subtotal;
                                            $tipo = 'AMBULATORIO';
                                            $grupo = 'HONORARIOS PROFESIONALES';
                                            $tipoProc = 'ANESTESIOLOGO';
                                            $anestesia = 'SI';
                                            $porcentajePago = $porcentaje * 100;
                                            $bodega = 0;
                                            $iva = 0;
                                            $montoTotal = $subtotal;

                                            echo "<tr>
                        <td>{$tipo}</td>
                        <td>{$cedulaPaciente}</td>
                        <td>{$periodo}</td>
                        <td>{$grupo}</td>
                        <td>{$tipoProc}</td>
                        <td>{$cedulaMedico}</td>
                        <td>{$fecha}</td>
                        <td>{$codigo}</td>
                        <td>{$descripcion}</td>
                        <td>{$anestesia}</td>
                        <td>{$porcentajePago}</td>
                        <td>{$cantidad}</td>
                        <td>" . number_format($valorUnitario, 2) . "</td>
                        <td>" . number_format($subtotal, 2) . "</td>
                        <td>{$bodega}</td>
                        <td>{$iva}</td>
                        <td>" . number_format($montoTotal, 2) . "</td>
                    </tr>";
                                        }

                                        // FARMACIA e INSUMOS
                                        $fuenteDatos = [
                                            ['grupo' => 'FARMACIA', 'items' => array_merge($datos['medicamentos'], $datos['oxigeno'])],
                                            ['grupo' => 'INSUMOS', 'items' => $datos['insumos']],
                                        ];

                                        foreach ($fuenteDatos as $bloque) {
                                            $grupo = $bloque['grupo'];
                                            foreach ($bloque['items'] as $item) {
                                                $descripcion = $item['nombre'] ?? $item['detalle'] ?? '';
                                                $codigo = $item['codigo'] ?? '';
                                                if (isset($item['litros']) && isset($item['tiempo']) && isset($item['valor2'])) {
                                                    $cantidad = (float)$item['tiempo'] * (float)$item['litros'] * 60;
                                                    $valorUnitario = (float)$item['valor2'];
                                                } else {
                                                    $cantidad = $item['cantidad'] ?? 1;
                                                    $valorUnitario = $item['precio'] ?? 0;
                                                }
                                                $subtotal = $valorUnitario * $cantidad;
                                                $bodega = 1;
                                                $iva = ($grupo === 'FARMACIA') ? 0 : 1;
                                                $montoTotal = $subtotal + ($iva ? $subtotal * 0.1 : 0);
                                                $total += $montoTotal;
                                                $tipo = 'AMBULATORIO';
                                                $grupoTipo = $grupo;
                                                $tipoProc = '';
                                                $anestesia = 'NO';
                                                $porcentajePago = 100;

                                                echo "<tr>
                            <td>{$tipo}</td>
                            <td>{$cedulaPaciente}</td>
                            <td>{$periodo}</td>
                            <td>{$grupoTipo}</td>
                            <td>{$tipoProc}</td>
                            <td>{$cedulaMedico}</td>
                            <td>{$fecha}</td>
                            <td>{$codigo}</td>
                            <td>{$descripcion}</td>
                            <td>{$anestesia}</td>
                            <td>{$porcentajePago}</td>
                            <td>{$cantidad}</td>
                            <td>" . number_format($valorUnitario, 2) . "</td>
                            <td>" . number_format($subtotal, 2) . "</td>
                            <td>{$bodega}</td>
                            <td>{$iva}</td>
                            <td>" . number_format($montoTotal, 2) . "</td>
                        </tr>";
                                            }
                                        }

                                        // SERVICIOS INSTITUCIONALES (derechos)
                                        foreach ($datos['derechos'] as $servicio) {
                                            $codigo = $servicio['codigo'];
                                            $descripcion = $servicio['detalle'];
                                            $cantidad = $servicio['cantidad'];
                                            $valorUnitario = $servicio['precio_afiliacion'];
                                            $subtotal = $valorUnitario * $cantidad;
                                            $bodega = 0;
                                            $iva = 0;
                                            $montoTotal = $subtotal;
                                            $total += $montoTotal;

                                            echo "<tr>
                        <td>AMBULATORIO</td>
                        <td>{$cedulaPaciente}</td>
                        <td>{$periodo}</td>
                        <td>SERVICIOS INSTITUCIONALES</td>
                        <td></td>
                        <td>{$cedulaMedico}</td>
                        <td>{$fecha}</td>
                        <td>{$codigo}</td>
                        <td>{$descripcion}</td>
                        <td>NO</td>
                        <td>100</td>
                        <td>{$cantidad}</td>
                        <td>" . number_format($valorUnitario, 2) . "</td>
                        <td>" . number_format($subtotal, 2) . "</td>
                        <td>{$bodega}</td>
                        <td>{$iva}</td>
                        <td>" . number_format($montoTotal, 2) . "</td>
                    </tr>";
                                        }
                                        ?>
                                        <tr class="table-secondary fw-bold">
                                            <td colspan="16" class="text-end">Total a Pagar</td>
                                            <td class="text-end"><?= number_format($total, 2) ?></td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <a href="/generar_excel_isspol.php?billing_id=<?= $billingId ?>"
                                   class="btn btn-success mt-3">
                                    Descargar Excel
                                </a>
                                <a href="/views/informes/informe_isspol.php?modo=consolidado<?= isset($mesSeleccionado) ? '&mes=' . urlencode($mesSeleccionado) : '' ?>"
                                   class="btn btn-secondary mt-3 ms-2">
                                    ← Regresar al consolidado
                                </a>
                            <?php elseif ($billingId): ?>
                                <div class="alert alert-warning mt-4">No se encontraron datos para esta factura.</div>
                                </table>
                            <?php elseif ($modo === 'consolidado'): ?>
                                <h4>Consolidado mensual de pacientes ISSPOL</h4>
                                <?php
                                $consolidado = [];
                                foreach ($facturas as $factura) {
                                    $pacienteInfo = $pacienteController->getPatientDetails($factura['hc_number']);
                                    if (strtoupper($pacienteInfo['afiliacion'] ?? '') !== 'ISSPOL') continue;

                                    $datosPaciente = $billingController->obtenerDatos($factura['form_id']);
                                    if (!$datosPaciente) continue;

                                    $fechaFactura = $factura['fecha_inicio'];
                                    $mes = date('Y-m', strtotime($fechaFactura));
                                    if (isset($mesSeleccionado) && $mesSeleccionado && $mes !== $mesSeleccionado) continue;
                                    if (!isset($consolidado[$mes])) $consolidado[$mes] = [];

                                    $total = InformesHelper::calcularTotalFactura($datosPaciente, $billingController);

                                    $consolidado[$mes][] = [
                                        'nombre' => $pacienteInfo['lname'] . ' ' . $pacienteInfo['fname'],
                                        'hc_number' => $factura['hc_number'],
                                        'form_id' => $factura['form_id'],
                                        'fecha' => $fechaFactura,
                                        'total' => $total,
                                        'id' => $factura['id'],
                                    ];
                                }

                                echo "</body>";

                                foreach ($consolidado as $mes => $pacientes) {
// Aplicar filtros de apellido, cie10 y medico
                                    $apellidoFiltro = strtolower(trim($_GET['apellido'] ?? ''));
// $cie10Filtro = strtolower(trim($_GET['cie10'] ?? ''));
// $medicoFiltro = strtolower(trim($_GET['medico'] ?? ''));

                                    $pacientes = array_filter($pacientes, function ($p) use ($apellidoFiltro, $pacienteController, $billingController) {
                                        $pacienteInfo = $pacienteController->getPatientDetails($p['hc_number']);
                                        $datosPaciente = $billingController->obtenerDatos($p['form_id']);

                                        $apellidoCompleto = strtolower(trim(($pacienteInfo['lname'] ?? '') . ' ' . ($pacienteInfo['lname2'] ?? '')));
// $cie10 = strtolower($datosPaciente['formulario']['diagnostico1_codigo'] ?? '');
// $medico = strtolower($datosPaciente['paciente']['cedula_medico'] ?? '');

                                        return (!$apellidoFiltro || str_contains($apellidoCompleto, $apellidoFiltro));
                                    });

                                    echo "<h5 class='mt-4'>Mes: " . date('F Y', strtotime($mes)) . "</h5>";
                                    echo "
<table class='table table-bordered table-striped'>
    <thead class='table-dark'>
    <tr>
        <th># Expediente</th>
        <th>Cédula</th>
        <th>Apellidos</th>
        <th>Nombre</th>
        <th>Fecha Ingreso</th>
        <th>Fecha Egreso</th>
        <th>CIE10</th>
        <th>Descripción</th>
        <th># Hist. C.</th>
        <th>Edad</th>
        <th>Ge</th>
        <th>Items</th>
        <th>Monto Sol.</th>
        <th>Acción</th>
    </tr>
    </thead>
    <tbody>";
                                    $n = 1;
                                    foreach ($pacientes as $p) {
                                        // Obtener info detallada para columnas extra
                                        $pacienteInfo = $pacienteController->getPatientDetails($p['hc_number']);
                                        $datosPaciente = $billingController->obtenerDatos($p['form_id']);
                                        $edad = $pacienteController->calcularEdad($pacienteInfo['fecha_nacimiento']);
                                        $genero = isset($pacienteInfo['sexo']) && $pacienteInfo['sexo'] ? strtoupper(substr($pacienteInfo['sexo'], 0, 1)) :
                                            '--';
                                        $url = "/views/informes/informe_isspol.php?modo=individual&billing_id=" . urlencode($p['id']);
                                        echo InformesHelper::renderConsolidadoFila($n, $p, $pacienteInfo, $datosPaciente, $edad, $genero, $url);
                                        $n++;
                                    }
                                    echo "
    </tbody>
</table>
";
                                }
                                ?>
                                <?php if ($modo === 'consolidado'): ?>
                                    <a href="/generar_consolidado_isspol.php<?= isset($mesSeleccionado) && $mesSeleccionado ? '?mes=' . urlencode($mesSeleccionado) : '' ?>"
                                       class="btn btn-primary mt-3">
                                        Descargar Consolidado
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
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
</body>
</html>
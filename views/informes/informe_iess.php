<?php
ob_start();

// Manejo de parámetros para scraping derivación
$form_id = $_POST['form_id_scrape'] ?? $_GET['form_id'] ?? null;
$hc_number = $_POST['hc_number_scrape'] ?? $_GET['hc_number'] ?? null;

if (isset($_POST['scrape_derivacion']) || (isset($_POST['form_id_scrape']) && isset($_POST['hc_number_scrape']))) {
    // Si faltan parámetros requeridos, redirigir con mensaje de error
    if (!$form_id || !$hc_number) {
        header("Location: ./informe_iess.php?scrape_exito=1&form_id=" . urlencode($form_id ?? '') . "&hc_number=" . urlencode($hc_number ?? '') . "&msg=" . urlencode("❌ Faltan parámetros requeridos."));
        exit;
    }
    $command = "/usr/bin/python3 /homepages/26/d793096920/htdocs/cive/public/scrapping/scrape_log_admision.py " . escapeshellarg($form_id) . " " . escapeshellarg($hc_number);
    shell_exec($command);
    header("Location: ./informe_iess.php?scrape_exito=1&form_id={$form_id}&hc_number={$hc_number}&msg=" . urlencode("✅ Código derivación obtenido y guardado correctamente."));
    exit;
}

$safe_hc_number = escapeshellarg($hc_number);

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

// Precargar datos agrupados por mes para evitar llamadas repetidas durante la creación del dropdown
$cachePorMes = [];
foreach ($facturas as $factura) {
    $mes = date('Y-m', strtotime($factura['fecha_inicio']));
    $hc = $factura['hc_number'];
    $formId = $factura['form_id'];

    if (!isset($cachePorMes[$mes]['pacientes'][$hc])) {
        $cachePorMes[$mes]['pacientes'][$hc] = $pacienteController->getPatientDetails($hc);
    }

    if (!isset($cachePorMes[$mes]['datos'][$formId])) {
        $cachePorMes[$mes]['datos'][$formId] = $billingController->obtenerDatos($formId);
    }
}
// Obtener modo de informe
$modo = 'consolidado';

// Definir filtros centralizados
$filtros = [
    'modo' => $modo,
    'billing_id' => $_GET['billing_id'] ?? null,
    'mes' => $_GET['mes'] ?? '',
    'apellido' => $_GET['apellido'] ?? '',
];

$billingId = $filtros['billing_id'];
$formId = null;
$datos = [];

// Filtro de mes para modo consolidado
$mesSeleccionado = $filtros['mes'];

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
                        <h3 class="page-title">Informe IESS</h3>
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
                            <div class="card shadow-sm mb-4">
                                <div class="card-body">
                                    <form method="GET" class="row g-3 align-items-end">
                                        <input type="hidden" name="modo" value="consolidado">

                                        <div class="col-md-4">
                                            <label for="mes" class="form-label fw-bold">
                                                <i class="mdi mdi-calendar"></i> Selecciona un mes:
                                            </label>
                                            <select name="mes" id="mes" class="form-select"
                                                    onchange="this.form.submit()">
                                                <option value="">-- Todos los meses --</option>
                                                <?php
                                                // Solo mostrar meses con al menos una factura de paciente IESS
                                                $afiliacionesIESS = [
                                                    'contribuyente voluntario',
                                                    'conyuge',
                                                    'conyuge pensionista',
                                                    'seguro campesino',
                                                    'seguro campesino jubilado',
                                                    'seguro general',
                                                    'seguro general jubilado',
                                                    'seguro general por montepío',
                                                    'seguro general tiempo parcial'
                                                ];
                                                $mesesValidos = [];
                                                foreach ($facturas as $factura) {
                                                    $mes = date('Y-m', strtotime($factura['fecha_inicio']));
                                                    $hc = $factura['hc_number'];
                                                    // Precargar detalles si no existen en cache
                                                    if (!isset($cachePorMes[$mes]['pacientes'][$hc])) {
                                                        $cachePorMes[$mes]['pacientes'][$hc] = $pacienteController->getPatientDetails($hc);
                                                    }
                                                    $afiliacion = strtolower(trim($cachePorMes[$mes]['pacientes'][$hc]['afiliacion'] ?? ''));
                                                    if (in_array($afiliacion, $afiliacionesIESS, true)) {
                                                        $mesesValidos[$mes] = true;
                                                    }
                                                }
                                                $mesesValidos = array_keys($mesesValidos);
                                                sort($mesesValidos);
                                                foreach ($mesesValidos as $mesOption):
                                                    $selected = ($filtros['mes'] === $mesOption) ? 'selected' : '';
                                                    echo "<option value='$mesOption' $selected>" . date('F Y', strtotime($mesOption . "-01")) . "</option>";
                                                endforeach;
                                                ?>
                                            </select>
                                        </div>

                                        <div class="col-md-4">
                                            <label for="apellido" class="form-label fw-bold">
                                                <i class="mdi mdi-account-search"></i> Apellido del paciente
                                            </label>
                                            <input type="text" name="apellido" id="apellido" class="form-control"
                                                   value="<?= htmlspecialchars($filtros['apellido']) ?>"
                                                   placeholder="Buscar por apellido">
                                        </div>

                                        <div class="col-md-4 d-flex gap-2">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="mdi mdi-magnify"></i> Buscar
                                            </button>
                                            <a href="/views/informes/informe_iess.php?modo=consolidado"
                                               class="btn btn-outline-secondary">
                                                <i class="mdi mdi-filter-remove"></i> Limpiar
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <?php if ($formId && $datos):
                            $paciente = $datos['paciente'] ?? [];
                            $nombreCompleto = trim(($paciente['lname'] ?? '') . ' ' . ($paciente['lname2'] ?? '') . ' ' . ($paciente['fname'] ?? '') . ' ' . ($paciente['mname'] ?? ''));
                            $hcNumber = $paciente['hc_number'] ?? '';
                            $afiliacion = strtoupper($paciente['afiliacion'] ?? '-');
                            ?>
                            <div class="row invoice-info mb-3">
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
                                        <div class="col-md-6 col-lg-3"><b>Pedido:</b> <?= $formId ?? '--' ?></div>
                                        <div class="col-md-6 col-lg-3"><b>Fecha
                                                Ingreso:</b> <?= !empty($datos['formulario']['fecha_inicio']) ? date('d/m/Y', strtotime($datos['formulario']['fecha_inicio'])) : '--' ?>
                                        </div>
                                        <div class="col-md-6 col-lg-3"><b>Fecha
                                                Egreso:</b> <?= !empty($datos['formulario']['fecha_fin']) ? date('d/m/Y', strtotime($datos['formulario']['fecha_fin'])) : '--' ?>
                                        </div>
                                        <div class="col-md-6 col-lg-3">
                                            <b>Médico:</b> <?= htmlspecialchars($paciente['medico'] ?? $paciente['doctor'] ?? '--') ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12 table-responsive">
                                    <table class="table table-bordered align-middle mb-0">
                                        <thead class="table-dark">
                                        <tr>
                                            <th class="text-center">#</th>
                                            <th class="text-center">Código</th>
                                            <th class="text-center">Descripción</th>
                                            <th class="text-center">Anestesia</th>
                                            <th class="text-center">%Pago</th>
                                            <th class="text-end">Cantidad</th>
                                            <th class="text-end">Valor Unitario</th>
                                            <th class="text-end">Subtotal</th>
                                            <th class="text-center">%Bodega</th>
                                            <th class="text-center">%IVA</th>
                                            <th class="text-end">Total</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $total = 0;
                                        $n = 1;

                                        // Procedimientos
                                        foreach ($datos['procedimientos'] as $index => $p) {
                                            $codigo = $p['proc_codigo'] ?? '';
                                            $descripcion = $p['proc_detalle'] ?? '';
                                            $valorUnitario = (float)($p['proc_precio'] ?? 0);
                                            $cantidad = 1;
                                            $porcentaje = ($index === 0 || stripos($descripcion, 'separado') !== false) ? 1 : 0.5;
                                            if ($codigo === '67036') $porcentaje = 0.625;
                                            $subtotal = $valorUnitario * $cantidad * $porcentaje;
                                            $total += $subtotal;

                                            $anestesia = 'NO';
                                            $porcentajePago = $porcentaje * 100;
                                            $bodega = 0;
                                            $iva = 0;
                                            $montoTotal = $subtotal;

                                            echo "<tr>
                                                        <td class='text-center'>{$n}</td>
                                                        <td class='text-center'>{$codigo}</td>
                                                        <td>{$descripcion}</td>
                                                        <td class='text-center'>{$anestesia}</td>
                                                        <td class='text-center'>{$porcentajePago}</td>
                                                        <td class='text-end'>{$cantidad}</td>
                                                        <td class='text-end'>" . number_format($valorUnitario, 2) . "</td>
                                                        <td class='text-end'>" . number_format($subtotal, 2) . "</td>
                                                        <td class='text-center'>{$bodega}</td>
                                                        <td class='text-center'>{$iva}</td>
                                                        <td class='text-end'>" . number_format($montoTotal, 2) . "</td>
                                                    </tr>";
                                            $n++;
                                        }

                                        // AYUDANTE
                                        if (!empty($datos['protocoloExtendido']['cirujano_2']) || !empty($datos['protocoloExtendido']['primer_ayudante'])) {
                                            foreach ($datos['procedimientos'] as $index => $p) {
                                                $codigo = $p['proc_codigo'] ?? '';
                                                $descripcion = $p['proc_detalle'] ?? '';
                                                $valorUnitario = (float)($p['proc_precio'] ?? 0);
                                                $cantidad = 1;
                                                $porcentaje = ($index === 0) ? 0.2 : 0.1;
                                                $subtotal = $valorUnitario * $cantidad * $porcentaje;
                                                $total += $subtotal;

                                                $anestesia = 'NO';
                                                $porcentajePago = $porcentaje * 100;
                                                $bodega = 0;
                                                $iva = 0;
                                                $montoTotal = $subtotal;

                                                echo "<tr>
                                                            <td class='text-center'>{$n}</td>
                                                            <td class='text-center'>{$codigo}</td>
                                                            <td>{$descripcion}</td>
                                                            <td class='text-center'>{$anestesia}</td>
                                                            <td class='text-center'>{$porcentajePago}</td>
                                                            <td class='text-end'>{$cantidad}</td>
                                                            <td class='text-end'>" . number_format($valorUnitario, 2) . "</td>
                                                            <td class='text-end'>" . number_format($subtotal, 2) . "</td>
                                                            <td class='text-center'>{$bodega}</td>
                                                            <td class='text-center'>{$iva}</td>
                                                            <td class='text-end'>" . number_format($montoTotal, 2) . "</td>
                                                        </tr>";
                                                $n++;
                                            }
                                        }

                                        // ANESTESIA
                                        foreach ($datos['anestesia'] as $a) {
                                            $codigo = $a['codigo'] ?? '';
                                            $descripcion = $a['nombre'] ?? '';
                                            $cantidad = (float)($a['tiempo'] ?? 0);
                                            $valorUnitario = (float)($a['valor2'] ?? 0);
                                            $subtotal = $cantidad * $valorUnitario;
                                            $total += $subtotal;
                                            $anestesia = 'SI';
                                            $porcentajePago = 100;
                                            $bodega = 0;
                                            $iva = 0;
                                            $montoTotal = $subtotal;

                                            echo "<tr>
                                                        <td class='text-center'>{$n}</td>
                                                        <td class='text-center'>{$codigo}</td>
                                                        <td>{$descripcion}</td>
                                                        <td class='text-center'>{$anestesia}</td>
                                                        <td class='text-center'>{$porcentajePago}</td>
                                                        <td class='text-end'>{$cantidad}</td>
                                                        <td class='text-end'>" . number_format($valorUnitario, 2) . "</td>
                                                        <td class='text-end'>" . number_format($subtotal, 2) . "</td>
                                                        <td class='text-center'>{$bodega}</td>
                                                        <td class='text-center'>{$iva}</td>
                                                        <td class='text-end'>" . number_format($montoTotal, 2) . "</td>
                                                    </tr>";
                                            $n++;
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
                                                $anestesia = 'NO';
                                                $porcentajePago = 100;

                                                echo "<tr>
                                                            <td class='text-center'>{$n}</td>
                                                            <td class='text-center'>{$codigo}</td>
                                                            <td>{$descripcion}</td>
                                                            <td class='text-center'>{$anestesia}</td>
                                                            <td class='text-center'>{$porcentajePago}</td>
                                                            <td class='text-end'>{$cantidad}</td>
                                                            <td class='text-end'>" . number_format($valorUnitario, 2) . "</td>
                                                            <td class='text-end'>" . number_format($subtotal, 2) . "</td>
                                                            <td class='text-center'>{$bodega}</td>
                                                            <td class='text-center'>{$iva}</td>
                                                            <td class='text-end'>" . number_format($montoTotal, 2) . "</td>
                                                        </tr>";
                                                $n++;
                                            }
                                        }

                                        // SERVICIOS INSTITUCIONALES (derechos)
                                        foreach ($datos['derechos'] as $servicio) {
                                            $codigo = $servicio['codigo'] ?? '';
                                            $descripcion = $servicio['detalle'] ?? '';
                                            $cantidad = $servicio['cantidad'] ?? 1;
                                            $valorUnitario = $servicio['precio_afiliacion'] ?? 0;
                                            $subtotal = $valorUnitario * $cantidad;
                                            $bodega = 0;
                                            $iva = 0;
                                            $montoTotal = $subtotal;
                                            $total += $montoTotal;
                                            $anestesia = 'NO';
                                            $porcentajePago = 100;

                                            echo "<tr>
                                                        <td class='text-center'>{$n}</td>
                                                        <td class='text-center'>{$codigo}</td>
                                                        <td>{$descripcion}</td>
                                                        <td class='text-center'>{$anestesia}</td>
                                                        <td class='text-center'>{$porcentajePago}</td>
                                                        <td class='text-end'>{$cantidad}</td>
                                                        <td class='text-end'>" . number_format($valorUnitario, 2) . "</td>
                                                        <td class='text-end'>" . number_format($subtotal, 2) . "</td>
                                                        <td class='text-center'>{$bodega}</td>
                                                        <td class='text-center'>{$iva}</td>
                                                        <td class='text-end'>" . number_format($montoTotal, 2) . "</td>
                                                    </tr>";
                                            $n++;
                                        }
                                        ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Bloque total estilo invoice -->
                                <div class="row mt-3">
                                    <div class="col-12 text-end">
                                        <p class="lead mb-1">
                                            <b>Total a pagar</b>
                                            <span class="text-danger ms-2" style="font-size: 1.25em;">
                                                $<?= number_format($total, 2) ?>
                                            </span>
                                        </p>
                                        <!-- Si quieres puedes agregar detalles adicionales, como subtotal, descuentos, etc. aquí -->
                                        <!-- <div>
                                            <p>Sub - Total amount: $<?= number_format($subtotal, 2) ?></p>
                                            <p>Tax (IVA 12%): $<?= number_format($iva, 2) ?></p>
                                        </div> -->
                                        <div class="total-payment mt-2">
                                            <h4 class="fw-bold">
                                                <span class="text-success"><b>Total :</b></span>
                                                $<?= number_format($total, 2) ?>
                                            </h4>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-4">
                                    <div class="col-12 text-end">
                                        <a href="/public/index.php/billing/excel?form_id=<?= $formId ?>&grupo=IESS"
                                           class="btn btn-success btn-lg me-2">
                                            <i class="fa fa-file-excel-o"></i> Descargar Excel
                                        </a>
                                        <a href="/views/informes/informe_iess.php?modo=consolidado<?= $filtros['mes'] ? '&mes=' . urlencode($filtros['mes']) : '' ?>"
                                           class="btn btn-outline-secondary btn-lg">
                                            <i class="fa fa-arrow-left"></i> Regresar al consolidado
                                        </a>
                                    </div>
                                </div>
                                <?php elseif ($billingId): ?>
                                    <div class="alert alert-warning mt-4">No se encontraron datos para esta factura.
                                    </div>
                                    </table>
                                <?php else: ?>
                                    <h4>Consolidado mensual de pacientes IESS</h4>
                                    <?php
                                    // $filtros ya está definido arriba
                                    $pacientesCache = $cachePorMes[$mesSeleccionado]['pacientes'] ?? [];
                                    $datosCache = $cachePorMes[$mesSeleccionado]['datos'] ?? [];
                                    $consolidado = InformesHelper::obtenerConsolidadoFiltrado(
                                        $facturas,
                                        $filtros,
                                        $billingController,
                                        $pacienteController,
                                        $afiliacionesIESS
                                    );

                                    foreach ($consolidado as $mes => $pacientes) {
                                        // Aplicar filtros de apellido usando helper
                                        $apellidoFiltro = strtolower(trim($filtros['apellido']));
                                        $pacientes = InformesHelper::filtrarPacientes($pacientes, $pacientesCache, $datosCache, $pacienteController, $billingController, $apellidoFiltro);

                                        // Calcular totales del mes
                                        $totalMes = 0;
                                        $totalPacientes = count($pacientes);
                                        foreach ($pacientes as $p) {
                                            $datosPaciente = $datosCache[$p['form_id']] ?? [];
                                            $totalMes += InformesHelper::calcularTotalFactura($datosPaciente, $billingController);
                                        }

                                        $formatter = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE, 'America/Guayaquil', IntlDateFormatter::GREGORIAN, "LLLL 'de' yyyy");
                                        $mesFormateado = $formatter->format(strtotime($mes . '-15'));
                                        echo "<div class='d-flex justify-content-between align-items-center mt-4'>
                                            <h5>Mes: {$mesFormateado}</h5>
                                            <div>
                                                🧮 Total pacientes: {$totalPacientes} &nbsp;&nbsp; 💵 Monto total: $" . number_format($totalMes, 2) . "
                                            </div>
                                          </div>";
                                        echo "<div class='table-responsive' style='overflow-x: auto; max-width: 100%; font-size: 0.85rem;'>";
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
        <th>Médico</th>
        <th># Hist. C.</th>
        <th>Edad</th>
        <th>Ge</th>
        <th>Monto Sol.</th>
        <th>Cod. Derivacion</th>
        <th>Acción</th>
    </tr>
    </thead>
    <tbody>";
                                        $n = 1;
                                        foreach ($pacientes as $p) {
                                            $pacienteInfo = $pacientesCache[$p['hc_number']] ?? [];
                                            $datosPaciente = $datosCache[$p['form_id']] ?? [];
                                            $edad = $pacienteController->calcularEdad($pacienteInfo['fecha_nacimiento']);
                                            $genero = isset($pacienteInfo['sexo']) && $pacienteInfo['sexo'] ? strtoupper(substr($pacienteInfo['sexo'], 0, 1)) : '--';
                                            $url = "/views/informes/informe_iess.php?billing_id=" . urlencode($p['id']);
                                            $afiliacion = strtoupper($pacienteInfo['afiliacion'] ?? '');
                                            $derivacion = $billingController->obtenerDerivacionPorFormId($p['form_id']);
                                            $codigoDerivacion = $derivacion['cod_derivacion'] ?? '';
                                            $referido = $derivacion['referido'] ?? '';
                                            $diagnostico = $derivacion['diagnostico'] ?? '';
                                            echo InformesHelper::renderConsolidadoFila($n, $p, $pacienteInfo, $datosPaciente, $edad, $genero, $url, $codigoDerivacion, $referido, $diagnostico, $afiliacion);
                                            $n++;
                                        }
                                        echo "
    </tbody>
</table>
";
                                        echo "</div>";
                                    }
                                    ?>
                                    <a href="/views/informes/generar_consolidado_iess.php<?= isset($mesSeleccionado) && $mesSeleccionado ? '?mes=' . urlencode($mesSeleccionado) : '' ?>"
                                       class="btn btn-primary mt-3">
                                        Descargar Consolidado
                                    </a>
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
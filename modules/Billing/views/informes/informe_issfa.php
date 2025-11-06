<?php
// Legacy billing report view relocated under modules/Billing/views/informes.
// Configuración de clases CSS por grupo para filas de la tabla
$grupoClases = [
    'CIRUJANO' => 'table-primary',
    'AYUDANTE' => 'table-info',
    'ANESTESIA' => 'table-danger',
    'FARMACIA' => 'table-warning',
    'FARMACIA_ML' => 'table-success',
    'INSUMOS' => 'table-light',
    'DERECHOS' => 'table-secondary',
];

// Funciones reutilizables para formato y cálculo monetario
function formatearMoneda($valor)
{
    return number_format((float)$valor, 2, '.', '');
}

function aplicarGestion($subtotal)
{
    return round($subtotal * 0.10, 2);
}

function aplicarIVA($subtotal)
{
    return round($subtotal * 0.15, 2);
}

ob_start();

// Manejo de parámetros para scraping derivación
$form_id = $_POST['form_id_scrape'] ?? $_GET['form_id'] ?? null;
$hc_number = $_POST['hc_number_scrape'] ?? $_GET['hc_number'] ?? null;

// Scraping output variable
$scrapingOutput = null;

if (isset($_POST['scrape_derivacion']) && !empty($form_id) && !empty($hc_number)) {
    $script = BASE_PATH . '/scrapping/scrape_log_admision.py';
    $command = sprintf(
        '/usr/bin/python3 %s %s %s',
        escapeshellarg($script),
        escapeshellarg((string)$form_id),
        escapeshellarg((string)$hc_number)
    );
    $scrapingOutput = shell_exec($command);
}

if (!defined('BASE_PATH')) {
    require_once dirname(__DIR__, 4) . '/bootstrap.php';
}
require_once BASE_PATH . '/helpers/InformesHelper.php';

use Controllers\BillingController;
use Modules\Pacientes\Services\PacienteService;
use Controllers\DashboardController;
use Helpers\InformesHelper;

$billingController = new BillingController($pdo);
$pacienteService = new PacienteService($pdo);
$dashboardController = new DashboardController($pdo);
// Paso 1: Obtener todas las facturas disponibles
$username = $dashboardController->getAuthenticatedUser();
$facturas = $billingController->obtenerFacturasDisponibles();

// Precargar datos agrupados por mes para evitar llamadas repetidas durante la creación del dropdown
$cachePorMes = [];
foreach ($facturas as $factura) {
    $fechaInicioRaw = $factura['fecha_inicio'] ?? null;
    $mes = $fechaInicioRaw ? date('Y-m', strtotime($fechaInicioRaw)) : 'sin_fecha';
    $hc = $factura['hc_number'];
    $formId = $factura['form_id'];

    if (!isset($cachePorMes[$mes]['pacientes'][$hc])) {
        $cachePorMes[$mes]['pacientes'][$hc] = $pacienteService->getPatientDetails($hc);
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

// Inicializar variables por clave para mejorar legibilidad y evitar errores si alguna clave no existe
$billing = $datos['billing'] ?? [];
$paciente = $datos['paciente'] ?? [];
$procedimientos = $datos['procedimientos'] ?? [];
$derechos = $datos['derechos'] ?? [];
$insumos = $datos['insumos'] ?? [];
$medicamentos = $datos['medicamentos'] ?? [];
$formulario = $datos['formulario'] ?? [];
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
<body class="hold-transition light-skin sidebar-mini theme-primary fixed">

<div class="wrapper">

    <?php include BASE_PATH . '/views/partials/header.php'; ?>
    <?php include BASE_PATH . '/views/partials/navbar.php'; ?>

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
                                                $afiliacionesISSFA = ['issfa'];
                                                $mesesValidos = [];
                                                foreach ($facturas as $factura) {
                                                    $mes = date('Y-m', strtotime($factura['fecha_inicio']));
                                                    $hc = $factura['hc_number'];
                                                    // Precargar detalles si no existen en cache
                                                    if (!isset($cachePorMes[$mes]['pacientes'][$hc])) {
                                                        $cachePorMes[$mes]['pacientes'][$hc] = $pacienteService->getPatientDetails($hc);
                                                    }
                                                    $afiliacion = strtolower(trim($cachePorMes[$mes]['pacientes'][$hc]['afiliacion'] ?? ''));
                                                    if (in_array($afiliacion, $afiliacionesISSFA, true)) {
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
                                            <a href="/informes/iess?modo=consolidado"
                                               class="btn btn-outline-secondary">
                                                <i class="mdi mdi-filter-remove"></i> Limpiar
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <?php if ($formId && $datos):
                            $nombreCompleto = trim(($paciente['lname'] ?? '') . ' ' . ($paciente['lname2'] ?? '') . ' ' . ($paciente['fname'] ?? '') . ' ' . ($paciente['mname'] ?? ''));
                            $hcNumber = $paciente['hc_number'] ?? '';
                            $afiliacion = strtoupper($paciente['afiliacion'] ?? '-');
                            // Definir $codigoDerivacion para el detalle de la factura de forma segura
                            $codigoDerivacion = null;
                            $derivacionData = $billingController->obtenerDerivacionPorFormId($billing['form_id']);
                            $codigoDerivacion = $derivacionData['cod_derivacion'];
                            $doctor = $derivacionData['referido'];
                            $fecha_registro = $derivacionData['fecha_registro'] ?? null;
                            $fecha_vigencia = $derivacionData['fecha_vigencia'] ?? null;
                            $diagnostico = $derivacionData['diagnostico'] ?? null;
                            //echo '<pre>🧾 Datos de la factura: ' . print_r($datos, true) . '</pre>';
                            echo "<div class='row invoice-info mb-3'>";
                            include __DIR__ . '/components/header_factura.php';
                            echo "</div>";

                            if (!empty($hcNumber)) {
                                echo "<div class='mb-4 text-end'>
                                        <form method='post' action='/informes/issfa?billing_id=" . htmlspecialchars($filtros['billing_id']) . "'>
                                            <input type='hidden' name='form_id_scrape' value='" . htmlspecialchars($billing['form_id'] ?? '') . "'>
                                            <input type='hidden' name='hc_number_scrape' value='" . htmlspecialchars($hcNumber) . "'>
                                            <button type='submit' name='scrape_derivacion' class='btn btn-warning'>
                                                📋 Ver todas las atenciones por cobrar
                                            </button>
                                        </form>
                                    </div>";
                            }

                            include __DIR__ . '/components/scrapping_procedimientos.php';
                            ?>
                             <div class="row">
                                <!-- Leyenda de colores -->
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
                                            <th class="text-end">+10% Gestión</th>
                                            <th class="text-end">Total</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $total = 0;
                                        $n = 1;

                                        // Procedimientos
                                        foreach ($procedimientos as $index => $p) {
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
                                            $grupo = 'CIRUJANO';
                                            $class = $grupoClases[$grupo] ?? '';

                                            echo "<tr class='{$class}'>
                                                        <td class='text-center'>{$n}</td>
                                                        <td class='text-center'>{$codigo}</td>
                                                        <td>{$descripcion}</td>
                                                        <td class='text-center'>{$anestesia}</td>
                                                        <td class='text-center'>{$porcentajePago}</td>
                                                        <td class='text-end'>{$cantidad}</td>
                                                        <td class='text-end'>" . formatearMoneda($valorUnitario) . "</td>
                                                        <td class='text-end'>" . formatearMoneda($subtotal) . "</td>
                                                        <td class='text-center'>{$bodega}</td>
                                                        <td class='text-center'>{$iva}</td>
                                                        <td class='text-end'>0.00</td>
                                                        <td class='text-end'>" . formatearMoneda($montoTotal) . "</td>
                                                </tr>";
                                            $n++;
                                        }

                                        // AYUDANTE
                                        if (isset($datos['protocoloExtendido']) && (!empty($datos['protocoloExtendido']['cirujano_2']) || !empty($datos['protocoloExtendido']['primer_ayudante']))) {
                                            foreach ($procedimientos as $index => $p) {
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
                                                $grupo = 'AYUDANTE';
                                                $class = $grupoClases[$grupo] ?? '';

                                                echo "<tr class='{$class}'>
                                                            <td class='text-center'>{$n}</td>
                                                            <td class='text-center'>{$codigo}</td>
                                                            <td>{$descripcion}</td>
                                                            <td class='text-center'>{$anestesia}</td>
                                                            <td class='text-center'>{$porcentajePago}</td>
                                                            <td class='text-end'>{$cantidad}</td>
                                                            <td class='text-end'>" . formatearMoneda($valorUnitario) . "</td>
                                                            <td class='text-end'>" . formatearMoneda($subtotal) . "</td>
                                                            <td class='text-center'>{$bodega}</td>
                                                            <td class='text-center'>{$iva}</td>
                                                            <td class='text-end'>0.00</td>
                                                            <td class='text-end'>" . formatearMoneda($montoTotal) . "</td>
                                                       </tr>";
                                                $n++;
                                            }
                                        }

                                        // ANESTESIA
                                        $anestesiaEspecialYaCobrada = false;
                                        $codigosEspeciales = ['99149AA', '99150AA'];

                                        foreach ($datos['anestesia'] as $a) {
                                            if (in_array($a['codigo'], $codigosEspeciales)) {
                                                $anestesiaEspecialYaCobrada = true;
                                                break;
                                            }
                                        }

                                        if ($anestesiaEspecialYaCobrada) {
                                            // Solo imprimir los códigos de anestesia especiales
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
                                                $grupo = 'ANESTESIA';
                                                $class = $grupoClases[$grupo] ?? '';

                                                echo "<tr class='{$class}'>
                                                        <td class='text-center'>{$n}</td>
                                                        <td class='text-center'>{$codigo}</td>
                                                        <td>{$descripcion}</td>
                                                        <td class='text-center'>{$anestesia}</td>
                                                        <td class='text-center'>{$porcentajePago}</td>
                                                        <td class='text-end'>{$cantidad}</td>
                                                        <td class='text-end'>" . formatearMoneda($valorUnitario) . "</td>
                                                        <td class='text-end'>" . formatearMoneda($subtotal) . "</td>
                                                        <td class='text-center'>{$bodega}</td>
                                                        <td class='text-center'>{$iva}</td>
                                                        <td class='text-end'>0.00</td>
                                                        <td class='text-end'>" . formatearMoneda($montoTotal) . "</td>
                                                      </tr>";
                                                $n++;
                                            }
                                        } else {
                                            // Agregar valor fijo del 16% del primer procedimiento
                                            if (!empty($procedimientos[0])) {
                                                $p = $procedimientos[0];
                                                $codigo = $p['proc_codigo'] ?? '';
                                                $descripcion = $p['proc_detalle'] ?? '';
                                                $valorUnitario = (float)($p['proc_precio'] ?? 0);
                                                $cantidad = 1;
                                                $porcentaje = 0.16;
                                                $subtotal = $valorUnitario * $cantidad * $porcentaje;
                                                $total += $subtotal;

                                                $anestesia = 'SI';
                                                $porcentajePago = 16;
                                                $bodega = 0;
                                                $iva = 0;
                                                $montoTotal = $subtotal;
                                                $grupo = 'ANESTESIA';
                                                $class = $grupoClases[$grupo] ?? '';

                                                echo "<tr class='{$class}'>
                                                        <td class='text-center'>{$n}</td>
                                                        <td class='text-center'>{$codigo}</td>
                                                        <td>{$descripcion}</td>
                                                        <td class='text-center'>{$anestesia}</td>
                                                        <td class='text-center'>{$porcentajePago}</td>
                                                        <td class='text-end'>{$cantidad}</td>
                                                        <td class='text-end'>" . formatearMoneda($valorUnitario) . "</td>
                                                        <td class='text-end'>" . formatearMoneda($subtotal) . "</td>
                                                        <td class='text-center'>{$bodega}</td>
                                                        <td class='text-center'>{$iva}</td>
                                                        <td class='text-end'>0.00</td>
                                                        <td class='text-end'>" . formatearMoneda($montoTotal) . "</td>
                                                      </tr>";
                                                $n++;
                                            }

                                            // Agregar los tiempos de anestesia
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
                                                $grupo = 'ANESTESIA';
                                                $class = $grupoClases[$grupo] ?? '';

                                                echo "<tr class='{$class}'>
                                                        <td class='text-center'>{$n}</td>
                                                        <td class='text-center'>{$codigo}</td>
                                                        <td>{$descripcion}</td>
                                                        <td class='text-center'>{$anestesia}</td>
                                                        <td class='text-center'>{$porcentajePago}</td>
                                                        <td class='text-end'>{$cantidad}</td>
                                                        <td class='text-end'>" . formatearMoneda($valorUnitario) . "</td>
                                                        <td class='text-end'>" . formatearMoneda($subtotal) . "</td>
                                                        <td class='text-center'>{$bodega}</td>
                                                        <td class='text-center'>{$iva}</td>
                                                        <td class='text-end'>0.00</td>
                                                        <td class='text-end'>" . formatearMoneda($montoTotal) . "</td>
                                                      </tr>";
                                                $n++;
                                            }
                                        }

                                        // FARMACIA e INSUMOS
                                        $fuenteDatos = [
                                            ['grupo' => 'FARMACIA', 'items' => array_merge($medicamentos, $datos['oxigeno'])],
                                            ['grupo' => 'INSUMOS', 'items' => $insumos],
                                        ];

                                        foreach ($fuenteDatos as $bloque) {
                                            $grupo = $bloque['grupo'];
                                            $class = $grupoClases[$grupo] ?? '';
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
                                                // No sumar el 10% en la columna final, igualando al Excel
                                                $montoTotal = ($grupo === 'FARMACIA') ? $subtotal * 1.10 : $subtotal;
                                                $total += $montoTotal;
                                                $anestesia = 'NO';
                                                $porcentajePago = 100;

                                                echo "<tr class='{$class}'>
                                                            <td class='text-center'>{$n}</td>
                                                            <td class='text-center'>{$codigo}</td>
                                                            <td>{$descripcion}</td>
                                                            <td class='text-center'>{$anestesia}</td>
                                                            <td class='text-center'>{$porcentajePago}</td>
                                                            <td class='text-end'>{$cantidad}</td>
                                                            <td class='text-end'>" . formatearMoneda($valorUnitario) . "</td>
                                                            <td class='text-end'>" . formatearMoneda($subtotal) . "</td>
                                                            <td class='text-center'>{$bodega}</td>
                                                            <td class='text-center'>{$iva}</td>
                                                            <td class='text-end'>0.00</td>
                                                            <td class='text-end'>" . formatearMoneda($montoTotal) . "</td>
                                                      </tr>";
                                                $n++;
                                            }
                                        }

                                        // SERVICIOS INSTITUCIONALES (derechos)
                                        foreach ($derechos as $servicio) {
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
                                            $grupo = 'DERECHOS';
                                            $class = $grupoClases[$grupo] ?? '';

                                            echo "<tr class='{$class}'>
                                                        <td class='text-center'>{$n}</td>
                                                        <td class='text-center'>{$codigo}</td>
                                                        <td>{$descripcion}</td>
                                                        <td class='text-center'>{$anestesia}</td>
                                                        <td class='text-center'>{$porcentajePago}</td>
                                                        <td class='text-end'>{$cantidad}</td>
                                                        <td class='text-end'>" . formatearMoneda($valorUnitario) . "</td>
                                                        <td class='text-end'>" . formatearMoneda($subtotal) . "</td>
                                                        <td class='text-center'>{$bodega}</td>
                                                        <td class='text-center'>{$iva}</td>
                                                        <td class='text-end'>0.00</td>
                                                        <td class='text-end'>" . formatearMoneda($montoTotal) . "</td>
                                                </tr>";
                                            $n++;
                                        }
                                        ?>
                                        </tbody>
                                    </table>
                                </div>

                                <?php
                                // Cálculo y presentación del IVA del 15% al final, como en el Excel
                                $totalPlanilla = $total;
                                $ivaGeneral = aplicarIVA($totalPlanilla);
                                $totalConIVA = $totalPlanilla + $ivaGeneral;
                                ?>
                                <!-- Bloque total estilo invoice -->
                                <div class="row mt-3">
                                    <div class="col-12 text-end">
                                        <p class="lead mb-1">
                                            <b>Subtotal:</b>
                                            <span class="text-danger ms-2" style="font-size: 1.25em;">
                                                $<?= formatearMoneda($totalPlanilla) ?>
                                            </span>
                                        </p>
                                        <div>
                                            <p class="lead mb-1">
                                                <b>IVA 15%:</b>
                                                <span class="text-info ms-2" style="font-size: 1em;">
                                                $<?= formatearMoneda($ivaGeneral) ?>
                                                </span>
                                            </p>
                                        </div>
                                        <div class="total-payment mt-2">
                                            <h4 class="fw-bold">
                                                <span class="text-success"><b>Total :</b></span>
                                                $<?= formatearMoneda($totalConIVA) ?>
                                            </h4>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-4">
                                    <div class="col-12 text-end">
                                        <a href="/public/index.php/billing/excel?form_id=<?= $formId ?>&grupo=ISSFA"
                                           class="btn btn-success btn-lg me-2">
                                            <i class="fa fa-file-excel-o"></i> Descargar Excel
                                        </a>
                                        <a href="/informes/issfa?modo=consolidado<?= $filtros['mes'] ? '&mes=' . urlencode($filtros['mes']) : '' ?>"
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
                                    <h4>Consolidado mensual de pacientes ISSFA</h4>
                                    <?php
                                    // $filtros ya está definido arriba
                                    $pacientesCache = $cachePorMes[$mesSeleccionado]['pacientes'] ?? [];
                                    $datosCache = $cachePorMes[$mesSeleccionado]['datos'] ?? [];
                                    $consolidado = InformesHelper::obtenerConsolidadoFiltrado(
                                        $facturas,
                                        $filtros,
                                        $billingController,
                                        $pacienteService,
                                        $afiliacionesISSFA
                                    );
                                    foreach ($consolidado as $mes => $pacientes) {
                                        // Aplicar filtros de apellido usando helper
                                        $apellidoFiltro = strtolower(trim($filtros['apellido']));
                                        $pacientes = InformesHelper::filtrarPacientes($pacientes, $pacientesCache, $datosCache, $pacienteService, $billingController, $apellidoFiltro);

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
                                            $edad = $pacienteService->calcularEdad($pacienteInfo['fecha_nacimiento']);
                                            $genero = isset($pacienteInfo['sexo']) && $pacienteInfo['sexo'] ? strtoupper(substr($pacienteInfo['sexo'], 0, 1)) : '--';
                                            $url = "/informes/issfa?billing_id=" . urlencode($p['id']);
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
                                    <a href="/informes/issfa/consolidado<?= isset($mesSeleccionado) && $mesSeleccionado ? '?mes=' . urlencode($mesSeleccionado) : '' ?>"
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
    <?php include BASE_PATH . '/views/partials/footer.php'; ?>

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
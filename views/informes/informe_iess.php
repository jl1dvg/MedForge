<?php
ob_start();

// Manejo de parámetros para scraping derivación
$form_id = $_POST['form_id_scrape'] ?? $_GET['form_id'] ?? null;
$hc_number = $_POST['hc_number_scrape'] ?? $_GET['hc_number'] ?? null;

// Scraping output variable
$scrapingOutput = null;

if (isset($_POST['scrape_derivacion']) && !empty($form_id) && !empty($hc_number)) {
    $command = "/usr/bin/python3 /homepages/26/d793096920/htdocs/cive/public/scrapping/scrape_log_admision.py " . escapeshellarg($form_id) . " " . escapeshellarg($hc_number);
    $scrapingOutput = shell_exec($command);
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

// Agrupar facturas por código de derivación
$grupos = [];
foreach ($facturas as $factura) {
    $form_id = $factura['form_id'];
    $derivacion = $billingController->obtenerDerivacionPorFormId($form_id);
    $codigo = $derivacion['codigo_derivacion'] ?? null;

    $keyAgrupacion = $codigo ?: 'SIN_CODIGO';

    $grupo = [
        'factura' => $factura,
        'codigo' => $codigo,
        'form_id' => $form_id,
        'tiene_codigo' => !empty($codigo),
    ];

    $grupos[$keyAgrupacion][] = $grupo;
}

// Precargar datos agrupados por mes para evitar llamadas repetidas durante la creación del dropdown
$cachePorMes = [];
foreach ($facturas as $factura) {
    $fechaInicio = $factura['fecha_inicio'] ?? null;
    $mes = $fechaInicio ? date('Y-m', strtotime($fechaInicio)) : '';
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

$billingIds = isset($filtros['billing_id']) ? explode(',', $filtros['billing_id']) : [];
$formId = null;
$datos = [];

// Filtro de mes para modo consolidado
$mesSeleccionado = $filtros['mes'];

$formIds = [];
$datosFacturas = [];

if (!empty($billingIds)) {
    $placeholders = implode(',', array_fill(0, count($billingIds), '?'));
    $stmt = $pdo->prepare("SELECT id, form_id FROM billing_main WHERE id IN ($placeholders)");
    $stmt->execute($billingIds);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $formId = $row['form_id'];
        $formIds[] = $formId;
        $datosFacturas[] = $billingController->obtenerDatos($formId);
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
                                                    'seguro general por montepio',
                                                    'seguro general tiempo parcial'
                                                ];
                                                $mesesValidos = [];
                                                foreach ($facturas as $factura) {
                                                    $mes = date('Y-m', strtotime($factura['fecha_ordenada']));
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
                            <?php if (!empty($datosFacturas)):
                                $primerDato = $datosFacturas[0] ?? [];
                                $paciente = $primerDato['paciente'] ?? [];
                                $nombreCompleto = trim(($paciente['lname'] ?? '') . ' ' . ($paciente['lname2'] ?? '') . ' ' . ($paciente['fname'] ?? '') . ' ' . ($paciente['mname'] ?? ''));
                                $hcNumber = $paciente['hc_number'] ?? '';
                                $afiliacion = strtoupper($paciente['afiliacion'] ?? '-');
                                // Definir $codigoDerivacion para el detalle de la factura
                                $codigoDerivacion = null;
                                $derivacionData = $billingController->obtenerDerivacionPorFormId($primerDato['billing']['form_id']);
                                $codigoDerivacion = $derivacionData['cod_derivacion'];
                                $doctor = $derivacionData['referido'];
                                $fecha_registro = $derivacionData['fecha_registro'] ?? null;
                                $fecha_vigencia = $derivacionData['fecha_vigencia'] ?? null;
                                $diagnostico = $derivacionData['diagnostico'] ?? null;
                                //echo '<pre>🧾 Datos de la factura: ' . print_r($derivacionData, true) . '</pre>';

                                echo "<div class='row invoice-info mb-3'>";
                                include __DIR__ . '/components/header_factura.php';
                                echo "</div>";

                                if (!empty($hcNumber)) {
                                    echo "<div class='mb-4 text-end'>
                                        <form method='post' action='informe_iess.php?billing_id=" . htmlspecialchars($filtros['billing_id']) . "'>
                                            <input type='hidden' name='form_id_scrape' value='" . htmlspecialchars($primerDato['billing']['form_id'] ?? '') . "'>
                                            <input type='hidden' name='hc_number_scrape' value='" . htmlspecialchars($hcNumber) . "'>
                                            <button type='submit' name='scrape_derivacion' class='btn btn-warning'>
                                                📋 Ver todas las atenciones por cobrar
                                            </button>
                                        </form>
                                    </div>";
                                }

                                include __DIR__ . '/components/scrapping_procedimientos.php';

                                foreach ($datosFacturas as $datos):
                                    include __DIR__ . '/components/detalle_factura_iess.php';
                                endforeach; ?>

                                <div class="row mt-4">
                                    <div class="col-12 text-end">
                                        <?php $formIdsParam = implode(',', $formIds); ?>
                                        <a href="/public/index.php/billing/excel?form_id=<?= urlencode($formIdsParam) ?>&grupo=IESS"
                                           class="btn btn-success btn-lg me-2">
                                            <i class="fa fa-file-excel-o"></i> Descargar Excel
                                        </a>
                                        <a href="/views/informes/informe_iess.php?modo=consolidado<?= $filtros['mes'] ? '&mes=' . urlencode($filtros['mes']) : '' ?>"
                                           class="btn btn-outline-secondary btn-lg">
                                            <i class="fa fa-arrow-left"></i> Regresar al consolidado
                                        </a>
                                    </div>
                                </div>
                            <?php elseif ($billingIds): ?>
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

                                $consolidadoAgrupado = [];
                                echo "<pre>✔️ Facturas encontradas: " . count($facturas) . "</pre>";
                                echo "<pre>✔️ Consolidado generado: " . count($consolidado) . "</pre>";

                                foreach ($consolidado as $grupo) {
                                    foreach ($grupo as $p) {
                                        // Asegurar compatibilidad con agrupación
                                        if (!isset($p['fecha_ordenada']) && isset($p['fecha'])) {
                                            $p['fecha_ordenada'] = $p['fecha'];
                                        }

                                        if (empty($p['fecha_ordenada'])) {
                                            continue;
                                        }

                                        $hc = $p['hc_number'];
                                        $mesKey = date('Y-m', strtotime($p['fecha_ordenada']));
                                        $key = $hc;

                                        if (!isset($consolidadoAgrupado[$mesKey][$key])) {
                                            $consolidadoAgrupado[$mesKey][$key] = [
                                                'paciente' => $p,
                                                'form_ids' => [],
                                                'fecha_ingreso' => $p['fecha_ordenada'],
                                                'fecha_egreso' => $p['fecha_ordenada'],
                                                'total' => 0,
                                                'procedimientos' => [],
                                                'cie10' => [],
                                                'afiliacion' => '',
                                            ];
                                        }

                                        $consolidadoAgrupado[$mesKey][$key]['form_ids'][] = $p['form_id'];
                                        $fechaActual = $p['fecha_ordenada'];
                                        $consolidadoAgrupado[$mesKey][$key]['fecha_ingreso'] = min($consolidadoAgrupado[$mesKey][$key]['fecha_ingreso'], $fechaActual);
                                        $consolidadoAgrupado[$mesKey][$key]['fecha_egreso'] = max($consolidadoAgrupado[$mesKey][$key]['fecha_egreso'], $fechaActual);

                                        $datosPaciente = $datosCache[$p['form_id']] ?? [];
                                        $consolidadoAgrupado[$mesKey][$key]['total'] += InformesHelper::calcularTotalFactura($datosPaciente, $billingController);
                                        $consolidadoAgrupado[$mesKey][$key]['procedimientos'][] = $datosPaciente['procedimientos'] ?? [];

                                        $derivacion = $billingController->obtenerDerivacionPorFormId($p['form_id']);
                                        if (!empty($derivacion['diagnostico'])) {
                                            $consolidadoAgrupado[$mesKey][$key]['cie10'][] = $derivacion['diagnostico'];
                                        }
                                        if (!empty($derivacion['cod_derivacion'])) {
                                            $consolidadoAgrupado[$mesKey][$key]['cod_derivacion'][] = $derivacion['cod_derivacion'];
                                        }

                                        $consolidadoAgrupado[$mesKey][$key]['afiliacion'] = strtoupper($pacientesCache[$hc]['afiliacion'] ?? '-');
                                    }
                                }
                                $n = 1;

                                // Ejemplo de cómo iterar sobre los grupos de facturas por código de derivación:
                                foreach ($grupos as $codigoDerivacion => $grupoFacturas):
                                    // Insertar alerta si hay alguna factura sin código en este grupo
                                    $tieneAlgunoSinCodigo = false;
                                    foreach ($grupoFacturas as $item) {
                                        if (!$item['tiene_codigo']) {
                                            $tieneAlgunoSinCodigo = true;
                                            break;
                                        }
                                    }
                                    if ($tieneAlgunoSinCodigo) {
                                        echo "<div class='alert alert-warning'>⚠️ Este grupo contiene facturas sin código de derivación</div>";
                                    }
                                    // ... aquí se puede mostrar el contenido del grupo ...
                                endforeach;

                                foreach ($consolidadoAgrupado as $mes => $pacientesAgrupados):
                                    $listaPacientes = array_values($pacientesAgrupados);
                                    echo "<pre>📌 Pacientes agrupados para $mes: " . count($pacientesAgrupados) . "</pre>";
                                    $formatter = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE, 'America/Guayaquil', IntlDateFormatter::GREGORIAN, "LLLL 'de' yyyy");
                                    $mesFormateado = $formatter->format(strtotime($mes . '-15'));
                                    ?>
                                    <div class="d-flex justify-content-between align-items-center mt-4">
                                        <h5>Mes: <?= $mesFormateado ?></h5>
                                        <div>🧮 Total pacientes: <?= count($pacientesAgrupados) ?> &nbsp;&nbsp; 💵
                                            Monto total:
                                            $<?= number_format(array_sum(array_column($listaPacientes, 'total')), 2) ?></div>
                                    </div>

                                    <div class="table-responsive"
                                         style="overflow-x: auto; max-width: 100%; font-size: 0.85rem;">
                                        <table class="table table-bordered table-striped">
                                            <thead class="table-success text-center">
                                            <tr>
                                                <th>#</th>
                                                <th>🏛️</th>
                                                <th>🪪 Cédula</th>
                                                <th>👤 Apellidos</th>
                                                <th>🧍 Nombre</th>
                                                <th>📅➕</th>
                                                <th>📅➖</th>
                                                <th>📝 CIE10</th>
                                                <th>🔬 Proc</th>
                                                <th>⏳</th>
                                                <th>⚧️</th>
                                                <th>💲 Total</th>
                                                <th>🧾Fact.</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($pacientesAgrupados as $hc => $info):
                                                $pacienteInfo = $pacientesCache[$hc] ?? [];
                                                $edad = $pacienteController->calcularEdad($pacienteInfo['fecha_nacimiento']);
                                                $genero = strtoupper(substr($pacienteInfo['sexo'] ?? '--', 0, 1));
                                                $procedimientos = InformesHelper::formatearListaProcedimientos($info['procedimientos']);
                                                $cie10 = InformesHelper::extraerCie10(implode('; ', array_unique($info['cie10'])));
                                                $codigoDerivacion = implode('; ', array_unique($info['cod_derivacion'] ?? []));
                                                $nombre = $pacienteInfo['fname'] . ' ' . $pacienteInfo['mname'];
                                                $apellido = $pacienteInfo['lname'] . ' ' . $pacienteInfo['lname2'];
                                                $form_ids = implode(', ', $info['form_ids']);
                                                ?>
                                                <tr style='font-size: 12.5px;'>
                                                    <td class="text-center"><?= $n ?></td>
                                                    <td class="text-center"><?= strtoupper(implode('', array_map(fn($w) => $w[0], explode(' ', $info['afiliacion'])))) ?></td>
                                                    <td class="text-center"><?= $pacienteInfo['hc_number'] ?></td>
                                                    <td><?= $apellido ?></td>
                                                    <td><?= $nombre ?></td>
                                                    <td><?= $info['fecha_ingreso'] ?></td>
                                                    <td><?= $info['fecha_egreso'] ?></td>
                                                    <td><?= $cie10 ?></td>
                                                    <td><?= $form_ids ?></td>
                                                    <td class="text-center"><?= $edad ?></td>
                                                    <td class="text-center"><?=
                                                        (!empty($codigoDerivacion)
                                                            ? "<span class='badge badge-success'>" . htmlspecialchars($codigoDerivacion) . "</span>"
                                                            : "<form method='post' style='display:inline;'>
                                                                <input type='hidden' name='form_id_scrape' value='" . htmlspecialchars($form_ids) . "'>
                                                                <input type='hidden' name='hc_number_scrape' value='" . htmlspecialchars($pacienteInfo['hc_number']) . "'>
                                                                <button type='submit' name='scrape_derivacion' class='btn btn-sm btn-warning'>📌 Obtener Código Derivación</button>
                                                                </form>"
                                                        )
                                                        ?></td>
                                                    <td class="text-end">
                                                        $<?= number_format($info['total'], 2) ?></td>
                                                    <?php
                                                    $billingIds = [];
                                                    foreach ($info['form_ids'] as $formIdLoop) {
                                                        $id = $billingController->obtenerBillingIdPorFormId($formIdLoop);
                                                        if ($id) {
                                                            $billingIds[] = $id;
                                                        }
                                                    }
                                                    $billingParam = implode(',', $billingIds);
                                                    $url = "/views/informes/informe_iess.php?billing_id=" . urlencode($billingParam);
                                                    ?>
                                                    <td><a href="<?= $url ?>" class="btn btn-sm btn-info">Ver
                                                            detalle</a></td>
                                                </tr>
                                                <?php
                                                $n++;
                                            endforeach;
                                            ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endforeach; ?>
                                <a
                                        href="/views/informes/generar_consolidado_iess.php<?= isset($mesSeleccionado) && $mesSeleccionado ? '?mes=' . urlencode($mesSeleccionado) : '' ?>"
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


<!-- Doclinic App -->
<script src="/public/js/jquery.smartmenus.js"></script>
<script src="/public/js/menus.js"></script>
<script src="/public/js/template.js"></script>
</body>
</html>
<?php
// Legacy billing report view relocated under modules/Billing/views/informes.
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
// Cache de derivaciones por form_id
$cacheDerivaciones = [];
// Paso 1: Obtener todas las facturas disponibles
$username = $dashboardController->getAuthenticatedUser();

// Obtener modo de informe
$modo = 'consolidado';

// Definir filtros centralizados
$filtros = [
    'modo' => $modo,
    'billing_id' => $_GET['billing_id'] ?? null,
    'mes' => $_GET['mes'] ?? '',
];

$mesSeleccionado = $filtros['mes'];

$facturas = $billingController->obtenerFacturasDisponibles($mesSeleccionado);

// Agrupar facturas por código de derivación
$grupos = [];
foreach ($facturas as $factura) {
    $form_id = $factura['form_id'];
    if (!isset($cacheDerivaciones[$form_id])) {
        $cacheDerivaciones[$form_id] = $billingController->obtenerDerivacionPorFormId($form_id);
    }
    $derivacion = $cacheDerivaciones[$form_id];
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

$billingIds = isset($filtros['billing_id']) ? explode(',', $filtros['billing_id']) : [];
$formId = null;
$datos = [];

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
        $datosFacturas[] = [
            'billing' => $row
        ]; // solo guarda billing_main
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
                                                        $cachePorMes[$mes]['pacientes'][$hc] = $pacienteService->getPatientDetails($hc);
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


                                        <div class="col-md-4 d-flex gap-2">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="mdi mdi-magnify"></i> Buscar
                                            </button>
                                            <a href="/informes/iess/prueba?modo=consolidado"
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
                                $formIdPrimero = $primerDato['billing']['form_id'];
                                if (!isset($cacheDerivaciones[$formIdPrimero])) {
                                    $cacheDerivaciones[$formIdPrimero] = $billingController->obtenerDerivacionPorFormId($formIdPrimero);
                                }
                                $derivacionData = $cacheDerivaciones[$formIdPrimero];
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
                                        <form method='post' action='/informes/iess/prueba?billing_id=" . htmlspecialchars($filtros['billing_id']) . "'>
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
                                        <a href="/public/index.php/billing/excel?form_id=<?= urlencode($formIdsParam) ?>&grupo=IESS_SOAM"
                                           class="btn btn-outline-success btn-lg me-2">
                                            <i class="fa fa-file-excel-o"></i> Descargar SOAM
                                        </a>
                                        <?php
                                        // Conservar todos los filtros actuales en la URL excepto 'billing_id'
                                        $filtrosParaRegresar = $_GET;
                                        unset($filtrosParaRegresar['billing_id']); // quitamos el detalle
                                        $filtrosParaRegresar['modo'] = 'consolidado'; // aseguramos modo consolidado
                                        $queryString = http_build_query($filtrosParaRegresar);
                                        ?>
                                        <a href="/informes/iess/prueba?<?= htmlspecialchars($queryString) ?>"
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
                                <?php if (!empty($mesSeleccionado)): ?>
                                    <h4>Consolidado mensual de pacientes IESS</h4>
                                    <?php
                                    // $filtros ya está definido arriba
                                    $consolidado = InformesHelper::obtenerConsolidadoFiltrado(
                                        $facturas,
                                        $filtros,
                                        $billingController,
                                        $pacienteService,
                                        $afiliacionesIESS
                                    );

                                    $consolidadoAgrupado = [];

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

                                            $formIdLoop = $p['form_id'];
                                            if (!isset($cacheDerivaciones[$formIdLoop])) {
                                                $cacheDerivaciones[$formIdLoop] = $billingController->obtenerDerivacionPorFormId($formIdLoop);
                                            }
                                            $derivacion = $cacheDerivaciones[$formIdLoop];
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
                                        $formatter = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE, 'America/Guayaquil', IntlDateFormatter::GREGORIAN, "LLLL 'de' yyyy");
                                        $mesFormateado = $formatter->format(strtotime($mes . '-15'));
                                        ?>
                                        <div class="d-flex justify-content-between align-items-center mt-4">
                                            <h5>Mes: <?= $mesFormateado ?></h5>
                                            <div>🧮 Total pacientes: <?= count($pacientesAgrupados) ?> &nbsp;&nbsp; 💵
                                                Monto total:
                                                $<?= number_format(array_sum(array_column($listaPacientes, 'total')), 2) ?></div>
                                        </div>
                                        <div id="factura-detalle-container" class="mt-4"></div>
                                        <div class="table-responsive"
                                             style="overflow-x: auto; max-width: 100%; font-size: 0.85rem;">
                                            <table id="example"
                                                   class="table table-striped table-hover table-sm invoice-archive sticky-header">
                                                <thead class="bg-success-light">
                                                <tr>
                                                    <th>#</th>
                                                    <th>🏛️</th>
                                                    <th>🪪 Cédula</th>
                                                    <th>👤 Nombres</th>
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
                                                    $pacienteInfo = $pacienteService->getPatientDetails($hc);
                                                    $edad = $pacienteService->calcularEdad($pacienteInfo['fecha_nacimiento']);
                                                    $genero = strtoupper(substr($pacienteInfo['sexo'] ?? '--', 0, 1));
                                                    $procedimientos = InformesHelper::formatearListaProcedimientos($info['procedimientos']);
                                                    $cie10 = implode('; ', array_unique(array_map('trim', $info['cie10'])));
                                                    $cie10 = InformesHelper::extraerCie10($cie10);
                                                    $codigoDerivacion = implode('; ', array_unique($info['cod_derivacion'] ?? []));
                                                    $nombre = $pacienteInfo['fname'] . ' ' . $pacienteInfo['mname'];
                                                    $apellido = $pacienteInfo['lname'] . ' ' . $pacienteInfo['lname2'];
                                                    $form_ids = implode(', ', $info['form_ids']);
                                                    ?>
                                                    <tr style='font-size: 12.5px;'>
                                                        <td class="text-center"><?= $n ?></td>
                                                        <td class="text-center"><?= strtoupper(implode('', array_map(fn($w) => $w[0], explode(' ', $pacienteInfo['afiliacion'])))) ?></td>
                                                        <td class="text-center"><?= $pacienteInfo['hc_number'] ?></td>
                                                        <td><?= $apellido . ' ' . $nombre ?></td>
                                                        <td><?= date('d/m/Y', strtotime($info['fecha_ingreso'])) ?></td>
                                                        <td><?= date('d/m/Y', strtotime($info['fecha_egreso'])) ?></td>
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
                                                        $primerFormId = $info['form_ids'][0] ?? null;
                                                        ?>
                                                        <td>
                                                            <?php if ($primerFormId): ?>
                                                                <button class="btn btn-sm btn-info"
                                                                        onclick="cargarDetalleFactura('<?= $primerFormId ?>')">
                                                                    Ver detalle
                                                                </button>
                                                            <?php else: ?>
                                                                <span class="text-muted">Sin form_id</span>
                                                            <?php endif; ?>
                                                        </td>
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
                                            href="/informes/iess/consolidado<?= isset($mesSeleccionado) && $mesSeleccionado ? '?mes=' . urlencode($mesSeleccionado) : '' ?>"
                                            class="btn btn-primary mt-3">
                                        Descargar Consolidado
                                    </a>
                                <?php else: ?>
                                    <div class="alert alert-info">📅 Por favor selecciona un mes para ver el
                                        consolidado.
                                    </div>
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
<?php include BASE_PATH . '/views/partials/footer.php'; ?>

<!-- Vendor JS -->
<script src="/public/js/vendors.min.js"></script> <!-- contiene jQuery -->
<script src="/public/js/pages/chat-popup.js"></script>
<script src="/public/assets/icons/feather-icons/feather.min.js"></script>
<script src="/public/assets/vendor_components/datatable/datatables.min.js"></script>
<script src="/public/assets/vendor_components/tiny-editable/mindmup-editabletable.js"></script>
<script src="/public/assets/vendor_components/tiny-editable/numeric-input-example.js"></script>
<script src="/public/assets/vendor_components/datatable/datatables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function cargarDetalleFactura(formId) {
        console.log("➡️ Cargando detalle para form_id:", formId); // LOG
        const container = document.getElementById('factura-detalle-container');
        container.innerHTML = '🔄 Cargando...';
        fetch('/informes/api/detalle-factura?form_id=' + encodeURIComponent(formId))
            .then(res => res.text())
            .then(html => {
                console.log("🧾 Respuesta del detalle:", html); // LOG
                container.innerHTML = html;
            })
            .catch((err) => {
                console.error("❌ Error en AJAX:", err);
                container.innerHTML = '❌ Error al cargar el detalle.';
            });
    }
</script>


<!-- Doclinic App -->
<script src="/public/js/jquery.smartmenus.js"></script>
<script src="/public/js/menus.js"></script>
<script src="/public/js/template.js"></script>
<script src="/public/js/pages/data-table.js"></script>
</body>
</html>
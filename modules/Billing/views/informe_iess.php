<?php
use Helpers\InformesHelper;

$facturas = $facturas ?? [];
$grupos = $grupos ?? [];
$cachePorMes = $cachePorMes ?? [];
$cacheDerivaciones = $cacheDerivaciones ?? [];
$datosFacturas = $datosFacturas ?? [];
$billingIds = $billingIds ?? [];
$formIds = $formIds ?? [];
$filtros = $filtros ?? [];
$mesSeleccionado = $mesSeleccionado ?? '';
$pacienteService = $pacienteService ?? null;
$billingController = $billingController ?? null;
$pacientesCache = $pacientesCache ?? [];
$datosCache = $datosCache ?? [];
$scrapingOutput = $scrapingOutput ?? null;

$afiliacionesIESS = [
        'contribuyente voluntario',
        'conyuge',
        'conyuge pensionista',
        'seguro campesino',
        'seguro campesino jubilado',
        'seguro general',
        'seguro general jubilado',
        'seguro general por montepio',
        'seguro general tiempo parcial',
        'hijos dependientes',
];
?>

<section class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Informe IESS</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Consolidado y por factura</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</section>

<script src="/public/js/vendors.min.js"></script>
<script src="/public/js/pages/chat-popup.js"></script>
<script src="/public/assets/icons/feather-icons/feather.min.js"></script>
<script src="/public/assets/vendor_components/datatable/datatables.min.js"></script>
<script src="/public/assets/vendor_components/tiny-editable/mindmup-editabletable.js"></script>
<script src="/public/assets/vendor_components/tiny-editable/numeric-input-example.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/public/js/jquery.smartmenus.js"></script>
<script src="/public/js/menus.js"></script>
<script src="/public/js/template.js"></script>
<script src="/public/js/pages/data-table.js"></script>

<section class="content">
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
                                <select name="mes" id="mes" class="form-select" onchange="this.form.submit()">
                                    <option value="">-- Todos los meses --</option>
                                    <?php
                                    $mesesValidos = [];
                                    foreach ($facturas as $factura) {
                                        if (empty($factura['fecha_ordenada'])) {
                                            continue;
                                        }
                                        $mes = date('Y-m', strtotime($factura['fecha_ordenada']));
                                        $hc = $factura['hc_number'];
                                        if (!isset($cachePorMes[$mes]['pacientes'][$hc]) && $pacienteService) {
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
                                        $selected = ($mesSeleccionado === $mesOption) ? 'selected' : '';
                                        $label = date('F Y', strtotime($mesOption . '-01'));
                                        echo "<option value='{$mesOption}' {$selected}>{$label}</option>";
                                    endforeach;
                                    ?>
                                </select>
                            </div>

                            <div class="col-md-4 d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="mdi mdi-magnify"></i> Buscar
                                </button>
                                <a href="/informes/iess" class="btn btn-outline-secondary">
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
                    $formIdPrimero = $primerDato['billing']['form_id'] ?? null;
                    if ($formIdPrimero && isset($cacheDerivaciones[$formIdPrimero])) {
                        $derivacionData = $cacheDerivaciones[$formIdPrimero];
                    } else {
                        $derivacionData = $formIdPrimero && $billingController ? $billingController->obtenerDerivacionPorFormId($formIdPrimero) : [];
                    }
                    $codigoDerivacion = $derivacionData['cod_derivacion'] ?? $derivacionData['codigo_derivacion'] ?? null;
                    $doctor = $derivacionData['referido'] ?? null;
                    $fecha_registro = $derivacionData['fecha_registro'] ?? null;
                    $fecha_vigencia = $derivacionData['fecha_vigencia'] ?? null;
                    $diagnostico = $derivacionData['diagnostico'] ?? null;
                    ?>

                    <div class="row invoice-info mb-3">
                        <?php include __DIR__ . '/components/header_factura.php'; ?>
                    </div>

                    <?php if (!empty($hcNumber)): ?>
                        <div class="mb-4 text-end">
                            <form method="post" action="/informes/iess?billing_id=<?= htmlspecialchars($filtros['billing_id']) ?>">
                                <input type="hidden" name="form_id_scrape" value="<?= htmlspecialchars($primerDato['billing']['form_id'] ?? '') ?>">
                                <input type="hidden" name="hc_number_scrape" value="<?= htmlspecialchars($hcNumber) ?>">
                                <button type="submit" name="scrape_derivacion" class="btn btn-warning">
                                    📋 Ver todas las atenciones por cobrar
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <?php $hc_number = $hcNumber; ?>
                    <?php include __DIR__ . '/components/scrapping_procedimientos.php'; ?>

                    <?php foreach ($datosFacturas as $datos): ?>
                        <?php include __DIR__ . '/components/detalle_factura_iess.php'; ?>
                    <?php endforeach; ?>

                    <div class="row mt-4">
                        <div class="col-12 text-end">
                            <?php $formIdsParam = implode(',', $formIds); ?>
                            <a href="/public/index.php/billing/excel?form_id=<?= urlencode($formIdsParam) ?>&grupo=IESS" class="btn btn-success btn-lg me-2">
                                <i class="fa fa-file-excel-o"></i> Descargar Excel
                                </a>
                            <a href="/public/index.php/billing/excel?form_id=<?= urlencode($formIdsParam) ?>&grupo=IESS_SOAM" class="btn btn-outline-success btn-lg me-2">
                                <i class="fa fa-file-excel-o"></i> Descargar SOAM
                            </a>
                            <?php
                            $filtrosParaRegresar = $_GET;
                            unset($filtrosParaRegresar['billing_id']);
                            $filtrosParaRegresar['modo'] = 'consolidado';
                            $queryString = http_build_query($filtrosParaRegresar);
                            ?>
                            <a href="/informes/iess?<?= htmlspecialchars($queryString) ?>" class="btn btn-outline-secondary btn-lg">
                                <i class="fa fa-arrow-left"></i> Regresar al consolidado
                            </a>
                        </div>
                    </div>

                <?php elseif ($billingIds): ?>
                    <div class="alert alert-warning mt-4">No se encontraron datos para esta factura.</div>
                <?php else: ?>
                    <?php if (!empty($mesSeleccionado) && $pacienteService && $billingController): ?>
                        <h4>Consolidado mensual de pacientes IESS</h4>
                        <?php
                        $consolidado = InformesHelper::obtenerConsolidadoFiltrado(
                            $facturas,
                            $filtros,
                            $billingController,
                            $pacienteService,
                            $afiliacionesIESS
                        );

                        $consolidadoAgrupado = [];
                        foreach ($consolidado as $grupoPacientes) {
                            foreach ($grupoPacientes as $p) {
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
                                        'cod_derivacion' => [],
                                        'afiliacion' => '',
                                    ];
                                }
                                $consolidadoAgrupado[$mesKey][$key]['form_ids'][] = $p['form_id'];
                                $fechaActual = $p['fecha_ordenada'];
                                $consolidadoAgrupado[$mesKey][$key]['fecha_ingreso'] = min($consolidadoAgrupado[$mesKey][$key]['fecha_ingreso'], $fechaActual);
                                $consolidadoAgrupado[$mesKey][$key]['fecha_egreso'] = max($consolidadoAgrupado[$mesKey][$key]['fecha_egreso'], $fechaActual);

                                $datosPaciente = $datosCache[$p['form_id']] ?? $billingController->obtenerDatos($p['form_id']);
                                if ($datosPaciente) {
                                    $consolidadoAgrupado[$mesKey][$key]['total'] += InformesHelper::calcularTotalFactura($datosPaciente, $billingController);
                                    $consolidadoAgrupado[$mesKey][$key]['procedimientos'][] = $datosPaciente['procedimientos'] ?? [];
                                    $datosCache[$p['form_id']] = $datosPaciente;
                                }

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

                                if (!isset($pacientesCache[$hc])) {
                                    $pacientesCache[$hc] = $pacienteService->getPatientDetails($hc);
                            }
                                $consolidadoAgrupado[$mesKey][$key]['afiliacion'] = strtoupper($pacientesCache[$hc]['afiliacion'] ?? '-');
                        }
                        }
                        $n = 1;
                        foreach ($consolidadoAgrupado as $mes => $pacientesAgrupados):
                            $listaPacientes = array_values($pacientesAgrupados);
                            $formatter = new \IntlDateFormatter('es_ES', \IntlDateFormatter::LONG, \IntlDateFormatter::NONE, 'America/Guayaquil', \IntlDateFormatter::GREGORIAN, "LLLL 'de' yyyy");
                            $mesFormateado = $formatter->format(strtotime($mes . '-15'));
                        ?>
                            <div class="d-flex justify-content-between align-items-center mt-4">
                                <h5>Mes: <?= $mesFormateado ?></h5>
                                <div>🧮 Total pacientes: <?= count($pacientesAgrupados) ?> &nbsp;&nbsp; 💵 Monto total: $<?= number_format(array_sum(array_column($listaPacientes, 'total')), 2) ?></div>
                            </div>

                            <div class="table-responsive" style="overflow-x: auto; max-width: 100%; font-size: 0.85rem;">
                                <table class="table table-striped table-hover table-sm invoice-archive sticky-header">
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
                                        $edad = $pacienteService->calcularEdad($pacienteInfo['fecha_nacimiento'] ?? null, $info['paciente']['fecha_ordenada'] ?? null);
                                        $genero = strtoupper(substr($pacienteInfo['sexo'] ?? '--', 0, 1));
                                        $cie10 = implode('; ', array_unique(array_map('trim', $info['cie10'])));
                                        $cie10 = InformesHelper::extraerCie10($cie10);
                                        $codigoDerivacion = implode('; ', array_unique($info['cod_derivacion'] ?? []));
                                        $nombre = trim(($pacienteInfo['fname'] ?? '') . ' ' . ($pacienteInfo['mname'] ?? ''));
                                    $apellido = trim(($pacienteInfo['lname'] ?? '') . ' ' . ($pacienteInfo['lname2'] ?? ''));
                                    $formIdsPaciente = implode(', ', $info['form_ids']);
                                    ?>
                                        <tr style='font-size: 12.5px;'>
                                        <td class="text-center"><?= $n ?></td>
                                            <td class="text-center"><?= strtoupper(implode('', array_map(fn($w) => $w[0], explode(' ', $pacienteInfo['afiliacion'] ?? '')))) ?></td>
                                        <td class="text-center"><?= htmlspecialchars($pacienteInfo['hc_number'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($apellido . ' ' . $nombre) ?></td>
                                        <td><?= $info['fecha_ingreso'] ? date('d/m/Y', strtotime($info['fecha_ingreso'])) : '--' ?></td>
                                        <td><?= $info['fecha_egreso'] ? date('d/m/Y', strtotime($info['fecha_egreso'])) : '--' ?></td>
                                            <td><?= htmlspecialchars($cie10) ?></td>
                                        <td><?= htmlspecialchars($formIdsPaciente) ?></td>
                                            <td class="text-center"><?= $edad ?></td>
                                        <td class="text-center">
                                            <?php if (!empty($codigoDerivacion)): ?>
                                                <span class="badge bg-success"><?= htmlspecialchars($codigoDerivacion) ?></span>
                                            <?php else: ?>
                                                    <form method="post" style="display:inline;">
                                                    <input type="hidden" name="form_id_scrape" value="<?= htmlspecialchars($formIdsPaciente) ?>">
                                                    <input type="hidden" name="hc_number_scrape" value="<?= htmlspecialchars($pacienteInfo['hc_number'] ?? '') ?>">
                                                    <button type="submit" name="scrape_derivacion" class="btn btn-sm btn-warning">📌 Obtener Código Derivación</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">$
                                            <?= number_format($info['total'], 2) ?></td>
                                            <?php
                                            $billingIdsDetalle = [];
                                            foreach ($info['form_ids'] as $formIdLoop) {
                                                $id = $billingController->obtenerBillingIdPorFormId($formIdLoop);
                                                if ($id) {
                                                    $billingIdsDetalle[] = $id;
                                                }
                                            }
                                            $billingParam = implode(',', $billingIdsDetalle);
                                            $urlDetalle = '/informes/iess?billing_id=' . urlencode($billingParam);
                                            ?>
                                            <td><a href="<?= $urlDetalle ?>" class="btn btn-sm btn-info" target="_blank">Ver detalle</a></td>
                                    </tr>
                                        <?php $n++; endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endforeach; ?>
                        <a href="/informes/iess/consolidado<?= $mesSeleccionado ? '?mes=' . urlencode($mesSeleccionado) : '' ?>" class="btn btn-primary mt-3">
                            Descargar Consolidado
                        </a>
                    <?php else: ?>
                        <div class="alert alert-info">📅 Por favor selecciona un mes para ver el consolidado.</div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

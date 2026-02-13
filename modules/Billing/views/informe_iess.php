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
$grupoConfig = $grupoConfig ?? [];
$vista = isset($vista) ? (string)$vista : '';
$basePath = rtrim($grupoConfig['basePath'] ?? '/informes/iess', '/');
$pageTitle = $grupoConfig['titulo'] ?? 'Informe IESS';
$excelButtons = $grupoConfig['excelButtons'] ?? [];
$scrapeButtonLabel = $grupoConfig['scrapeButtonLabel'] ?? '📋 Ver todas las atenciones por cobrar';
$consolidadoTitulo = $grupoConfig['consolidadoTitulo'] ?? 'Consolidado mensual de pacientes';
$enableApellidoFilter = !empty($grupoConfig['enableApellidoFilter']);
$tableOptions = $grupoConfig['tableOptions'] ?? [];
$pageLength = isset($tableOptions['pageLength']) ? (int)$tableOptions['pageLength'] : 25;
$defaultOrder = $tableOptions['defaultOrder'] ?? 'fecha_ingreso_desc';
$orderMap = [
        'fecha_ingreso_desc' => ['column' => 6, 'dir' => 'desc'],
        'fecha_ingreso_asc' => ['column' => 6, 'dir' => 'asc'],
        'nombre_asc' => ['column' => 5, 'dir' => 'asc'],
        'nombre_desc' => ['column' => 5, 'dir' => 'desc'],
        'monto_desc' => ['column' => 12, 'dir' => 'desc'],
        'monto_asc' => ['column' => 12, 'dir' => 'asc'],
];
$defaultOrderColumn = $orderMap[$defaultOrder]['column'] ?? 6;
$defaultOrderDir = $orderMap[$defaultOrder]['dir'] ?? 'desc';
$afiliacionesPermitidas = $grupoConfig['afiliaciones'] ?? [];
if (empty($afiliacionesPermitidas)) {
    $afiliacionesPermitidas = [
            'contribuyente voluntario',
            'conyuge',
            'conyuge pensionista',
            'seguro campesino',
            'seguro campesino jubilado',
            'seguro general',
            'seguro general jubilado',
            'seguro general por montepio',
            'seguro general tiempo parcial',
            'iess',
            'hijos dependientes',
    ];
}
$afiliacionesPermitidas = array_map(
        fn($afiliacion) => InformesHelper::normalizarAfiliacion($afiliacion),
        $afiliacionesPermitidas
);
$afiliacionSeleccionada = InformesHelper::normalizarAfiliacion($filtros['afiliacion'] ?? '');

?>
<style>
    /* Rehabilita checkboxes nativos en tablas de consolidado (el theme global los oculta sin label asociado) */
    .select-derivacion.form-check-input,
    .select-all-derivaciones.form-check-input {
        position: static !important;
        left: auto !important;
        opacity: 1 !important;
        visibility: visible !important;
        display: inline-block !important;
        margin: 0;
    }
</style>

<section class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title"><?= htmlspecialchars($pageTitle) ?></h3>
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
                        <form method="GET" action="<?= htmlspecialchars($basePath) ?>" class="row g-3 align-items-end">
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
                                        if (!isset($cachePorMes[$mes]['pacientes'][$hc])) {
                                            if (!isset($pacientesCache[$hc]) && $pacienteService) {
                                                $pacientesCache[$hc] = $pacienteService->getPatientDetails($hc);
                                            }
                                            if (isset($pacientesCache[$hc])) {
                                                $cachePorMes[$mes]['pacientes'][$hc] = $pacientesCache[$hc];
                                            }
                                        }
                                        $afiliacion = InformesHelper::normalizarAfiliacion($cachePorMes[$mes]['pacientes'][$hc]['afiliacion'] ?? '');
                                        if ($afiliacionSeleccionada && $afiliacionSeleccionada !== $afiliacion) {
                                            continue;
                                        }
                                        if (in_array($afiliacion, $afiliacionesPermitidas, true)) {
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

                            <?php if ($enableApellidoFilter): ?>
                                <div class="col-md-4">
                                    <label for="apellido" class="form-label fw-bold">
                                        <i class="mdi mdi-account-search"></i> Filtrar por apellido:
                                    </label>
                                    <input
                                            type="text"
                                            id="apellido"
                                            name="apellido"
                                            class="form-control"
                                            value="<?= htmlspecialchars($filtros['apellido'] ?? '') ?>"
                                            placeholder="Ej: Pérez"
                                    >
                                </div>
                            <?php endif; ?>

                            <div class="col-md-4">
                                <label for="hc_number" class="form-label fw-bold">
                                    <i class="mdi mdi-card-account-details"></i> Filtrar por cédula/HC:
                                </label>
                                <input
                                        type="text"
                                        id="hc_number"
                                        name="hc_number"
                                        class="form-control"
                                        value="<?= htmlspecialchars($filtros['hc_number'] ?? '') ?>"
                                        placeholder="Ej: 0102345678"
                                >
                            </div>

                            <div class="col-md-4">
                                <label for="afiliacion" class="form-label fw-bold">
                                    <i class="mdi mdi-domain"></i> Filtrar por afiliación:
                                </label>
                                <select name="afiliacion" id="afiliacion" class="form-select">
                                    <option value="">Todas</option>
                                    <?php
                                    $afiliacionesDisponibles = array_unique($afiliacionesPermitidas);
                                    sort($afiliacionesDisponibles);
                                    foreach ($afiliacionesDisponibles as $afiliacionOption):
                                        $normalizedOption = InformesHelper::normalizarAfiliacion($afiliacionOption);
                                        $selected = $afiliacionSeleccionada === $normalizedOption ? 'selected' : '';
                                        $label = ucwords($afiliacionOption);
                                        echo "<option value='{$normalizedOption}' {$selected}>{$label}</option>";
                                    endforeach;
                                    ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="derivacion" class="form-label fw-bold">
                                    <i class="mdi mdi-map-marker-check"></i> Derivación:
                                </label>
                                <select name="derivacion" id="derivacion" class="form-select">
                                    <option value="">Todas</option>
                                    <option value="con" <?= ($filtros['derivacion'] ?? '') === 'con' ? 'selected' : '' ?>>
                                        Solo con código
                                    </option>
                                    <option value="sin" <?= ($filtros['derivacion'] ?? '') === 'sin' ? 'selected' : '' ?>>
                                        Solo sin código
                                    </option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="vista" class="form-label fw-bold">
                                    <i class="mdi mdi-speedometer"></i> Vista:
                                </label>
                                <select name="vista" id="vista" class="form-select">
                                    <option value="rapida" <?= $vista === 'rapida' ? 'selected' : '' ?>>
                                        Rápida (carga ligera)
                                    </option>
                                    <option value="" <?= $vista === '' ? 'selected' : '' ?>>
                                        Completa (con derivaciones)
                                    </option>
                                </select>
                            </div>

                            <div class="col-md-4 d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="mdi mdi-magnify"></i> Buscar
                                </button>
                                <a href="<?= htmlspecialchars($basePath) ?>" class="btn btn-outline-secondary">
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

                    <?php if (!empty($hcNumber)):
                    $scrapeActionUrl = $basePath;
                    if (!empty($filtros['billing_id'])) {
                        $scrapeActionUrl .= '?billing_id=' . urlencode((string)$filtros['billing_id']);
                    }
                    ?>
                    <div class="mb-4 text-end">
                        <form method="post" action="<?= htmlspecialchars($scrapeActionUrl) ?>">
                            <input type="hidden" name="form_id_scrape"
                                   value="<?= htmlspecialchars($primerDato['billing']['form_id'] ?? '') ?>">
                            <input type="hidden" name="hc_number_scrape" value="<?= htmlspecialchars($hcNumber) ?>">
                            <button type="submit" name="scrape_derivacion" class="btn btn-warning">
                                <?= htmlspecialchars($scrapeButtonLabel) ?>
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
                            <?php foreach ($excelButtons as $button):
                                $grupoExcel = $button['grupo'] ?? '';
                                if (!$grupoExcel) {
                                    continue;
                                }
                                $labelExcel = $button['label'] ?? 'Descargar Excel';
                                $classExcel = $button['class'] ?? 'btn btn-success btn-lg me-2';
                                $iconExcel = $button['icon'] ?? 'fa fa-file-excel-o';
                                $excelUrl = '/public/index.php/billing/excel?form_id=' . urlencode($formIdsParam) . '&grupo=' . urlencode($grupoExcel);
                                ?>
                                <a href="<?= htmlspecialchars($excelUrl) ?>"
                                   class="<?= htmlspecialchars($classExcel) ?>">
                                    <?php if (!empty($iconExcel)): ?><i
                                        class="<?= htmlspecialchars($iconExcel) ?>"></i> <?php endif; ?>
                                    <?= htmlspecialchars($labelExcel) ?>
                                </a>
                            <?php endforeach; ?>
                            <?php
                            $filtrosParaRegresar = $_GET;
                            unset($filtrosParaRegresar['billing_id']);
                            $filtrosParaRegresar['modo'] = 'consolidado';
                            $queryString = http_build_query($filtrosParaRegresar);
                            $regresarUrl = $basePath . ($queryString ? '?' . $queryString : '');
                            ?>
                            <a href="<?= htmlspecialchars($regresarUrl) ?>" class="btn btn-outline-secondary btn-lg">
                                <i class="fa fa-arrow-left"></i> Regresar al consolidado
                            </a>
                        </div>
                    </div>

                <?php elseif ($billingIds): ?>
                    <div class="alert alert-warning mt-4">No se encontraron datos para esta factura.</div>
                <?php else: ?>
                <?php if (!empty($mesSeleccionado) && $pacienteService && $billingController): ?>
                    <h4><?= htmlspecialchars($consolidadoTitulo) ?></h4>
                    <?php if ($vista === 'rapida'): ?>
                        <div class="alert alert-info">
                            ⚡ Vista rápida activa: se omiten las consultas de derivación y el scraping para acelerar la carga.
                            Usa <strong>Vista completa</strong> si necesitas validar códigos de derivación o ejecutar scraping.
                        </div>
                    <?php endif; ?>
                <?php
                $consolidado = InformesHelper::obtenerConsolidadoFiltrado(
                        $facturas,
                        array_merge($filtros, ['vista' => $vista]),
                        $billingController,
                        $pacienteService,
                        $afiliacionesPermitidas,
                        null,
                        $cacheDerivaciones,
                        $pacientesCache,
                        $datosCache
                );

                $categoriasIess = [
                        'procedimientos' => 'IESS procedimientos',
                        'consulta' => 'IESS consulta',
                        'imagenes' => 'IESS imágenes',
                ];

                $consolidadoPorCategoria = array_fill_keys(array_keys($categoriasIess), []);

                foreach ($consolidado as $mes => $grupoPacientes) {
                    foreach ($grupoPacientes as $p) {
                        if (!isset($p['fecha_ordenada']) && isset($p['fecha'])) {
                            $p['fecha_ordenada'] = $p['fecha'];
                        }
                        $categoria = $p['categoria'] ?? 'procedimientos';
                        if (!isset($consolidadoPorCategoria[$categoria])) {
                            $categoria = 'procedimientos';
                        }
                        $consolidadoPorCategoria[$categoria][$mes][] = $p;
                    }
                }

                $consolidadoAgrupadoPorCategoria = InformesHelper::agruparConsolidadoPorPaciente(
                        $consolidadoPorCategoria,
                        $pacientesCache,
                        $datosCache,
                        $cacheDerivaciones,
                        $pacienteService,
                        $billingController
                );
                ?>
                    <ul class="nav nav-tabs" role="tablist">
                        <?php foreach ($categoriasIess as $slug => $label): ?>
                            <li class="nav-item" role="presentation">
                                <button
                                        class="nav-link <?= $slug === 'procedimientos' ? 'active' : '' ?>"
                                        id="tab-<?= htmlspecialchars($slug) ?>-tab"
                                        data-bs-toggle="tab"
                                        data-bs-target="#tab-<?= htmlspecialchars($slug) ?>"
                                        type="button"
                                        role="tab"
                                        aria-controls="tab-<?= htmlspecialchars($slug) ?>"
                                        aria-selected="<?= $slug === 'procedimientos' ? 'true' : 'false' ?>"
                                >
                                    <?= htmlspecialchars($label) ?>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="tab-content p-3 border border-top-0 rounded-bottom">
                        <?php foreach ($categoriasIess as $slug => $label): ?>
                            <?php $consolidadoAgrupado = $consolidadoAgrupadoPorCategoria[$slug] ?? []; ?>
                            <div
                                    class="tab-pane fade <?= $slug === 'procedimientos' ? 'show active' : '' ?>"
                                    id="tab-<?= htmlspecialchars($slug) ?>"
                                    role="tabpanel"
                                    aria-labelledby="tab-<?= htmlspecialchars($slug) ?>-tab"
                            >
                                <?php if (empty($consolidadoAgrupado)): ?>
                                    <div class="alert alert-info mb-0">No hay registros para esta categoría en el
                                        periodo seleccionado.
                                    </div>
                                <?php else: ?>
                                    <?php
                                    $n = 1;
                                    foreach ($consolidadoAgrupado as $mes => $pacientesAgrupados):
                                        $listaPacientes = array_values($pacientesAgrupados);
                                        $formatter = new \IntlDateFormatter('es_ES', \IntlDateFormatter::LONG, \IntlDateFormatter::NONE, 'America/Guayaquil', \IntlDateFormatter::GREGORIAN, "LLLL 'de' yyyy");
                                        $mesFormateado = $formatter->format(strtotime($mes . '-15'));
                                        $facturasMes = array_sum(array_map(static fn($info) => (int)($info['facturas'] ?? count($info['form_ids'] ?? [])), $pacientesAgrupados));
                                        ?>
                                        <div class="d-flex justify-content-between align-items-center mt-4">
                                            <h5>Mes: <?= $mesFormateado ?></h5>
                                            <div>
                                                🧮 Pacientes únicos: <?= count($pacientesAgrupados) ?>
                                                &nbsp;&nbsp; 📄 Facturas: <?= $facturasMes ?>
                                                &nbsp;&nbsp; 💵 Monto total:
                                                $<?= number_format(array_sum(array_column($listaPacientes, 'total')), 2) ?>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
                                            <div class="text-muted small">
                                                <?php if ($vista === 'rapida'): ?>
                                                    Selecciona filas para lanzar el scraping en lote.
                                                <?php else: ?>
                                                    Selecciona filas sin código de derivación para lanzar el scraping en lote.
                                                <?php endif; ?>
                                            </div>
                                            <form method="post" action="<?= htmlspecialchars($basePath) ?>"
                                                  class="d-flex gap-2 align-items-center bulk-derivaciones-form">
                                                <input type="hidden" name="scrape_derivacion" value="1">
                                                <div class="bulk-derivaciones-fields"></div>
                                                <button type="submit"
                                                        class="btn btn-warning btn-sm bulk-derivaciones-submit"
                                                        disabled>
                                                    <i class="mdi mdi-playlist-plus"></i> Obtener códigos seleccionados
                                                </button>
                                            </form>
                                            <?php if ($slug === 'consulta' && $basePath === '/informes/iess'): ?>
                                                <form method="get" action="<?= htmlspecialchars($basePath . '/consolidado') ?>"
                                                      class="d-flex gap-2 align-items-center bulk-download-form">
                                                    <?php if (!empty($mesSeleccionado)): ?>
                                                        <input type="hidden" name="mes" value="<?= htmlspecialchars($mesSeleccionado) ?>">
                                                    <?php endif; ?>
                                                    <?php if (!empty($filtros['afiliacion'])): ?>
                                                        <input type="hidden" name="afiliacion" value="<?= htmlspecialchars((string)$filtros['afiliacion']) ?>">
                                                    <?php endif; ?>
                                                    <?php if (!empty($filtros['apellido'])): ?>
                                                        <input type="hidden" name="apellido" value="<?= htmlspecialchars((string)$filtros['apellido']) ?>">
                                                    <?php endif; ?>
                                                    <?php if (!empty($filtros['hc_number'])): ?>
                                                        <input type="hidden" name="hc_number" value="<?= htmlspecialchars((string)$filtros['hc_number']) ?>">
                                                    <?php endif; ?>
                                                    <?php if (!empty($filtros['derivacion'])): ?>
                                                        <input type="hidden" name="derivacion" value="<?= htmlspecialchars((string)$filtros['derivacion']) ?>">
                                                    <?php endif; ?>
                                                    <input type="hidden" name="categoria" value="consulta">
                                                    <input type="hidden" name="formato" value="IESS_SOAM">
                                                    <input type="hidden" name="zip" value="0" class="bulk-download-zip">
                                                    <div class="bulk-download-fields"></div>
                                                    <button type="submit"
                                                            class="btn btn-primary btn-sm bulk-download-submit"
                                                            data-zip="0"
                                                            disabled>
                                                        <i class="mdi mdi-file-check"></i> SOAM seleccionados
                                                    </button>
                                                    <button type="submit"
                                                            class="btn btn-outline-danger btn-sm bulk-download-submit"
                                                            data-zip="1"
                                                            disabled>
                                                        <i class="mdi mdi-zip-box"></i> SOAM + PDF seleccionados
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>

                                        <div class="table-responsive"
                                             style="overflow-x: auto; max-width: 100%; font-size: 0.85rem;">
                                            <table
                                                    class="table table-striped table-hover table-sm invoice-archive sticky-header consolidado-table"
                                                    data-page-length="<?= $pageLength ?>"
                                                    data-order-column="<?= $defaultOrderColumn ?>"
                                                    data-order-dir="<?= htmlspecialchars($defaultOrderDir) ?>"
                                            >
                                                <thead class="bg-success-light">
                                                <tr>
                                                    <th class="text-center">
                                                        <input type="checkbox"
                                                               class="form-check-input select-all-derivaciones"
                                                               title="Seleccionar visibles">
                                                    </th>
                                                    <th>#</th>
                                                    <th>🏛️</th>
                                                    <th>Afiliación</th>
                                                    <th>🪪 Cédula</th>
                                                    <th>👤 Nombres</th>
                                                    <th>📅➕</th>
                                                    <th>📅➖</th>
                                                    <th>📝 CIE10</th>
                                                    <th>🔬 Proc</th>
                                                    <th>⏳</th>
                                                    <th>⚧️</th>
                                                    <th>💲 Total</th>
                                                    <th>👤 Facturó</th>
                                                    <th>🧾Fact.</th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                <?php foreach ($pacientesAgrupados as $hc => $info):
                                                    if (!isset($pacientesCache[$hc]) && $pacienteService) {
                                                        $pacientesCache[$hc] = $pacienteService->getPatientDetails($hc);
                                                    }
                                                    $pacienteInfo = $pacientesCache[$hc] ?? [];
                                                    $edad = $pacienteService->calcularEdad($pacienteInfo['fecha_nacimiento'] ?? null, $info['paciente']['fecha_ordenada'] ?? null);
                                                    $genero = strtoupper(substr($pacienteInfo['sexo'] ?? '--', 0, 1));
                                                    $cie10 = implode('; ', array_unique(array_map('trim', $info['cie10'])));
                                                    $cie10 = InformesHelper::extraerCie10($cie10);
                                                    $codigoDerivacion = implode('; ', array_unique($info['cod_derivacion'] ?? []));
                                                    $nombre = trim(($pacienteInfo['fname'] ?? '') . ' ' . ($pacienteInfo['mname'] ?? ''));
                                                    $apellido = trim(($pacienteInfo['lname'] ?? '') . ' ' . ($pacienteInfo['lname2'] ?? ''));
                                                    $afiliacionTexto = $pacienteInfo['afiliacion'] ?? '--';
                                                    $formIdsPaciente = implode(', ', $info['form_ids']);
                                                    $facturadores = [];
                                                    foreach ($info['form_ids'] as $formIdLoop) {
                                                        if (!isset($datosCache[$formIdLoop])) {
                                                            $datosCache[$formIdLoop] = $billingController->obtenerDatos($formIdLoop);
                                                        }
                                                        $datosFactura = $datosCache[$formIdLoop] ?? null;
                                                        if (!$datosFactura) {
                                                            continue;
                                                        }
                                                        $facturadorNombre = $datosFactura['billing']['facturador_nombre']
                                                            ?? $datosFactura['billing']['facturador']
                                                            ?? null;
                                                        if (!empty($facturadorNombre)) {
                                                            $facturadores[] = $facturadorNombre;
                                                        }
                                                    }
                                                    $facturadores = array_values(array_unique($facturadores));
                                                    $facturadoresTexto = $facturadores ? implode(', ', $facturadores) : '—';
                                                    $procHtml = htmlspecialchars($formIdsPaciente);
                                                    if ($slug === 'consulta' && !empty($info['form_ids'])) {
                                                        $hcNumberConsulta = $pacienteInfo['hc_number'] ?? '';
                                                        $consultaLinks = array_map(static function ($formId) use ($hcNumberConsulta) {
                                                            $url = '/reports/consulta/pdf?form_id=' . urlencode((string)$formId)
                                                                    . '&hc_number=' . urlencode((string)$hcNumberConsulta);
                                                            return '<a href="' . htmlspecialchars($url)
                                                                    . '" class="text-decoration-none" target="_blank">'
                                                                    . htmlspecialchars((string)$formId)
                                                                    . '</a>';
                                                        }, $info['form_ids']);
                                                        $procHtml = implode(', ', $consultaLinks);
                                                    }
                                                    $puedeScrapear = $vista === 'rapida' || empty($codigoDerivacion);
                                                    $puedeSeleccionar = trim($formIdsPaciente) !== '';
                                                    ?>
                                                    <tr style='font-size: 12.5px;'>
                                                        <td class="text-center">
                                                            <input
                                                                    type="checkbox"
                                                                    class="form-check-input select-derivacion"
                                                                    data-form-ids="<?= htmlspecialchars($formIdsPaciente) ?>"
                                                                    data-hc="<?= htmlspecialchars($pacienteInfo['hc_number'] ?? '') ?>"
                                                                    data-puede-scrape="<?= $puedeScrapear ? '1' : '0' ?>"
                                                                    <?= $puedeSeleccionar ? '' : 'disabled' ?>
                                                            >
                                                        </td>
                                                        <td class="text-center"><?= $n ?></td>
                                                        <td class="text-center"><?= strtoupper(implode('', array_map(fn($w) => $w[0], explode(' ', $pacienteInfo['afiliacion'] ?? '')))) ?></td>
                                                        <td><?= htmlspecialchars($afiliacionTexto) ?></td>
                                                        <td class="text-center"><?= htmlspecialchars($pacienteInfo['hc_number'] ?? '') ?></td>
                                                        <td><?= htmlspecialchars($apellido . ' ' . $nombre) ?></td>
                                                        <td><?= $info['fecha_ingreso'] ? date('d/m/Y', strtotime($info['fecha_ingreso'])) : '--' ?></td>
                                                        <td><?= $info['fecha_egreso'] ? date('d/m/Y', strtotime($info['fecha_egreso'])) : '--' ?></td>
                                                        <td><?= htmlspecialchars($cie10) ?></td>
                                                        <td><?= $procHtml ?></td>
                                                        <td class="text-center"><?= $edad ?></td>
                                                        <td class="text-center">
                                                            <?php if ($vista === 'rapida'): ?>
                                                                <span class="badge bg-secondary">—</span>
                                                            <?php elseif (!empty($codigoDerivacion)): ?>
                                                                <span class="badge bg-success"><?= htmlspecialchars($codigoDerivacion) ?></span>
                                                            <?php else: ?>
                                                                <form method="post" style="display:inline;">
                                                                    <input type="hidden" name="form_id_scrape"
                                                                           value="<?= htmlspecialchars($formIdsPaciente) ?>">
                                                                    <input type="hidden" name="hc_number_scrape"
                                                                           value="<?= htmlspecialchars($pacienteInfo['hc_number'] ?? '') ?>">
                                                                    <button type="submit" name="scrape_derivacion"
                                                                            class="btn btn-sm btn-warning">📌 Obtener
                                                                        Código Derivación
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-end">$
                                                            <?= number_format($info['total'], 2) ?></td>
                                                        <td><?= htmlspecialchars($facturadoresTexto) ?></td>
                                                        <?php
                                                        $billingIdsDetalle = [];
                                                        foreach ($info['form_ids'] as $formIdLoop) {
                                                            $id = $billingController->obtenerBillingIdPorFormId($formIdLoop);
                                                            if ($id) {
                                                                $billingIdsDetalle[] = $id;
                                                            }
                                                        }
                                                        $billingParam = implode(',', $billingIdsDetalle);
                                                        $urlDetalle = $basePath . '?billing_id=' . urlencode($billingParam);
                                                        ?>
                                                        <td><a href="<?= htmlspecialchars($urlDetalle) ?>"
                                                               class="btn btn-sm btn-info" target="_blank">Ver
                                                                detalle</a></td>
                                                    </tr>
                                                    <?php $n++; endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php
                $buildConsolidadoUrl = static function (?string $categoriaSlug = null, string $formato = 'IESS', bool $zip = false) use ($basePath, $mesSeleccionado, $filtros): string {
                    $params = [];
                    if (!empty($mesSeleccionado)) {
                        $params['mes'] = $mesSeleccionado;
                    }
                    if (!empty($filtros['afiliacion'])) {
                        $params['afiliacion'] = $filtros['afiliacion'];
                    }
                    if (!empty($filtros['apellido'])) {
                        $params['apellido'] = $filtros['apellido'];
                    }
                    if (!empty($filtros['hc_number'])) {
                        $params['hc_number'] = $filtros['hc_number'];
                    }
                    if (!empty($filtros['derivacion'])) {
                        $params['derivacion'] = $filtros['derivacion'];
                    }
                    if (!empty($categoriaSlug)) {
                        $params['categoria'] = $categoriaSlug;
                    }
                    if (!empty($formato)) {
                        $params['formato'] = $formato;
                    }
                    if ($zip) {
                        $params['zip'] = 1;
                    }
                    $query = $params ? '?' . http_build_query($params) : '';
                    return $basePath . '/consolidado' . $query;
                };
                ?>
                    <div class="card border-0 shadow-sm mt-3">
                        <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <i class="mdi mdi-download-outline text-primary"></i>
                                <div>
                                    <div class="fw-semibold">Acciones de exportación</div>
                                    <div class="text-muted small">Descarga consolidado mensual según formato y
                                        categoría.
                                    </div>
                                </div>
                            </div>
                            <div class="text-muted small">
                                <i class="mdi mdi-information-outline"></i>
                                Tip: SOAM suele ser el formato requerido para carga.
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="row g-3">
                                <!-- CONSOLIDADO GENERAL -->
                                <div class="col-12 col-lg-4">
                                    <div class="box p-3 bb-3 border-primary h-100">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <div class="fw-semibold">
                                                <i class="mdi mdi-grid me-1"></i> Consolidado general
                                            </div>
                                            <span class="badge bg-light text-dark" data-bs-toggle="tooltip"
                                                  title="Incluye procedimientos (categoría principal) para el periodo seleccionado">Base</span>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2">
                                            <a
                                                    href="<?= htmlspecialchars($buildConsolidadoUrl(null, 'IESS')) ?>"
                                                    class="btn btn-success btn-sm d-inline-flex align-items-center gap-2"
                                                    data-bs-toggle="tooltip"
                                                    title="Excel IESS (44 columnas). Recomendado para consolidado estándar."
                                            >
                                                <i class="mdi mdi-file-excel"></i>
                                                <span>Excel</span>
                                                <span class="badge bg-white text-primary">44 col</span>
                                            </a>

                                            <a
                                                    href="<?= htmlspecialchars($buildConsolidadoUrl(null, 'IESS_SOAM')) ?>"
                                                    class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2"
                                                    data-bs-toggle="tooltip"
                                                    title="Excel SOAM. Útil para carga/plantillas SOAM."
                                            >
                                                <i class="mdi mdi-file-check"></i>
                                                <span>SOAM</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <!-- CONSULTAS -->
                                <div class="col-12 col-lg-4">
                                    <div class="box p-3 bb-3 border-success h-100">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <div class="fw-semibold">
                                                <i class="mdi mdi-stethoscope me-1"></i> Consultas
                                            </div>
                                            <span class="badge bg-light text-dark" data-bs-toggle="tooltip"
                                                  title="Filtra el consolidado a la categoría de consultas">Categoría</span>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2">
                                            <a
                                                    href="<?= htmlspecialchars($buildConsolidadoUrl('consulta', 'IESS')) ?>"
                                                    class="btn btn-success btn-sm d-inline-flex align-items-center gap-2"
                                                    data-bs-toggle="tooltip"
                                                    title="Excel IESS (44 columnas) solo para consultas."
                                            >
                                                <i class="mdi mdi-file-excel"></i>
                                                <span>Excel</span>
                                                <span class="badge bg-white text-primary">44 col</span>
                                            </a>

                                            <a
                                                    href="<?= htmlspecialchars($buildConsolidadoUrl('consulta', 'IESS_SOAM')) ?>"
                                                    class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2"
                                                    data-bs-toggle="tooltip"
                                                    title="Excel SOAM solo para consultas."
                                            >
                                                <i class="mdi mdi-file-check"></i>
                                                <span>SOAM</span>
                                            </a>

                                            <a
                                                    href="<?= htmlspecialchars($buildConsolidadoUrl('consulta', 'IESS_SOAM', true)) ?>"
                                                    class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-2"
                                                    data-bs-toggle="tooltip"
                                                    title="Genera ZIP con Excel SOAM + PDFs individuales de consultas. Puede tardar más."
                                            >
                                                <i class="mdi mdi-zip-box"></i>
                                                <span>SOAM</span>
                                                <span class="badge bg-danger text-white"><i
                                                            class="mdi mdi-file-pdf-box"></i></span>
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <!-- IMÁGENES -->
                                <div class="col-12 col-lg-4">
                                    <div class="box p-3 bb-3 border-info h-100">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <div class="fw-semibold">
                                                <i class="mdi mdi-image-multiple me-1"></i> Imágenes
                                            </div>
                                            <span class="badge bg-light text-dark" data-bs-toggle="tooltip"
                                                  title="Filtra el consolidado a la categoría de imágenes">Categoría</span>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2">
                                            <a
                                                    href="<?= htmlspecialchars($buildConsolidadoUrl('imagenes', 'IESS')) ?>"
                                                    class="btn btn-success btn-sm d-inline-flex align-items-center gap-2"
                                                    data-bs-toggle="tooltip"
                                                    title="Excel IESS (44 columnas) solo para imágenes."
                                            >
                                                <i class="mdi mdi-file-excel"></i>
                                                <span>Excel</span>
                                                <span class="badge bg-white text-primary">44 col</span>
                                            </a>

                                            <a
                                                    href="<?= htmlspecialchars($buildConsolidadoUrl('imagenes', 'IESS_SOAM')) ?>"
                                                    class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2"
                                                    data-bs-toggle="tooltip"
                                                    title="Excel SOAM solo para imágenes."
                                            >
                                                <i class="mdi mdi-file-check"></i>
                                                <span>SOAM</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
                                <div class="text-muted small">
                                    <i class="mdi mdi-filter-variant"></i>
                                    Los filtros actuales (mes/afiliación/apellido/HC/derivación) se aplican a todas las
                                    descargas.
                                </div>
                                <div class="text-muted small">
                                    <i class="mdi mdi-shield-check-outline"></i>
                                    Verifica derivaciones faltantes antes de exportar para evitar observaciones.
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                        (function () {
                            // Bootstrap 5 tooltips (si está disponible)
                            try {
                                if (window.bootstrap && typeof window.bootstrap.Tooltip === 'function') {
                                    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                                        new window.bootstrap.Tooltip(el);
                                    });
                                }
                            } catch (e) {
                                // noop
                            }
                        })();
                    </script>
                <?php else: ?>
                    <div class="alert alert-info">📅 Por favor selecciona un mes para ver el consolidado.</div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<script>
    $(function () {
        const attachBulkSelection = function ($table, dataTableInstance) {
            if ($table.data('bulk-init')) {
                return;
            }
            $table.data('bulk-init', true);
            const $bulkForm = $table.closest('.tab-pane').find('.bulk-derivaciones-form');
            const $fieldsContainer = $bulkForm.find('.bulk-derivaciones-fields');
            const $submitBtn = $bulkForm.find('.bulk-derivaciones-submit');
            const $bulkDownloadForm = $table.closest('.tab-pane').find('.bulk-download-form').first();
            const $downloadFields = $bulkDownloadForm.find('.bulk-download-fields');
            const $downloadButtons = $bulkDownloadForm.find('.bulk-download-submit');
            const $downloadZipInput = $bulkDownloadForm.find('.bulk-download-zip');
            const $selectAll = $table.find('.select-all-derivaciones');

            const getRows = function () {
                if (dataTableInstance && dataTableInstance.rows) {
                    return dataTableInstance.rows({search: 'applied', page: 'all'}).nodes();
                }
                return $table.find('tbody tr');
            };

            const syncSelection = function () {
                $fieldsContainer.empty();
                $downloadFields.empty();
                let seleccionadasScrape = 0;
                const $rows = getRows();
                const formIdsDescarga = new Set();

                $($rows).find('.select-derivacion:checked').each(function () {
                    const formIds = String($(this).data('form-ids') || '').split(',').map(v => v.trim()).filter(Boolean);
                    const hc = String($(this).data('hc') || '').trim();
                    const puedeScrapear = String($(this).data('puede-scrape') || '0') === '1';
                    formIds.forEach(function (fid) {
                        if (puedeScrapear) {
                            $fieldsContainer.append('<input type="hidden" name="form_id_scrape[]" value="' + fid + '">');
                            $fieldsContainer.append('<input type="hidden" name="hc_number_scrape[]" value="' + hc + '">');
                            seleccionadasScrape++;
                        }
                        formIdsDescarga.add(fid);
                    });
                });

                formIdsDescarga.forEach(function (fid) {
                    $downloadFields.append('<input type="hidden" name="form_ids[]" value="' + fid + '">');
                });

                $submitBtn.prop('disabled', seleccionadasScrape === 0);
                $downloadButtons.prop('disabled', formIdsDescarga.size === 0);
                syncSelectAllState();
            };

            const syncSelectAllState = function () {
                const nodes = getRows();
                const $enabled = $(nodes).find('.select-derivacion:enabled');
                const enabledCount = $enabled.length;
                const checkedCount = $enabled.filter(':checked').length;
                const allChecked = enabledCount > 0 && checkedCount === enabledCount;
                const anyChecked = checkedCount > 0;

                $selectAll.prop('checked', allChecked);
                $selectAll.prop('indeterminate', !allChecked && anyChecked);
            };

            $table.off('change.bulkSelectDerivacion').on('change.bulkSelectDerivacion', '.select-derivacion', syncSelection);
            $table.off('change.bulkSelectAllDerivacion').on('change.bulkSelectAllDerivacion', '.select-all-derivaciones', function () {
                const checked = $(this).is(':checked');
                const nodes = getRows();
                $(nodes).find('.select-derivacion:enabled').prop('checked', checked);
                syncSelection();
            });
            $bulkDownloadForm.off('click.bulkDownloadMode').on('click.bulkDownloadMode', '.bulk-download-submit', function () {
                const zip = String($(this).data('zip') || '0');
                $downloadZipInput.val(zip === '1' ? '1' : '0');
            });

            syncSelection();

            if (dataTableInstance && dataTableInstance.on) {
                dataTableInstance.on('draw', function () {
                    syncSelectAllState();
                });
            }
        };

        const initConsolidadoTables = function () {
            $('.consolidado-table').each(function () {
                const $table = $(this);
                const pageLength = parseInt($table.data('page-length'), 10) || 25;
                const orderColumn = parseInt($table.data('order-column'), 10);
                const orderDir = ($table.data('order-dir') || 'desc').toLowerCase();

                const options = {
                    paging: true,
                    lengthChange: true,
                    searching: true,
                    ordering: true,
                    info: true,
                    autoWidth: false,
                    pageLength: pageLength,
                };

                if (!Number.isNaN(orderColumn)) {
                    options.order = [[orderColumn, orderDir]];
                }

                let dataTableInstance = null;
                if ($.fn.dataTable && $.fn.dataTable.isDataTable($table)) {
                    dataTableInstance = $table.DataTable();
                } else if ($.fn.DataTable) {
                    dataTableInstance = $table.DataTable(options);
                }

                attachBulkSelection($table, dataTableInstance);
            });
        };

        const waitForDataTables = window.dataTablesReadyPromise;
        if (waitForDataTables && typeof waitForDataTables.then === 'function') {
            waitForDataTables.then(initConsolidadoTables).catch(initConsolidadoTables);
        } else if ($.fn && $.fn.DataTable) {
            initConsolidadoTables();
        } else {
            setTimeout(initConsolidadoTables, 600);
        }
    });
</script>

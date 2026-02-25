<?php
/** @var array<int, array<string,mixed>> $imagenesRealizadas */
/** @var array<string, string> $filters */

if (!isset($styles) || !is_array($styles)) {
    $styles = [];
}

$styles[] = 'https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css';
$styles[] = 'https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css';

if (!isset($scripts) || !is_array($scripts)) {
    $scripts = [];
}

array_push(
        $scripts,
        'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js',
        'https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js',
        'https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js',
        'https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js',
        'https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js'
);

if (!isset($filters) || !is_array($filters)) {
    $filters = [
            'fecha_inicio' => '',
            'fecha_fin' => '',
            'afiliacion' => '',
            'tipo_examen' => '',
            'paciente' => '',
            'estado_agenda' => '',
    ];
}

$parseProcedimiento = static function (string $raw): array {
    $texto = trim($raw);
    $ojo = '';

    if ($texto !== '' && preg_match('/\s-\s(AMBOS OJOS|IZQUIERDO|DERECHO|OD|OI|AO)\s*$/i', $texto, $match)) {
        $ojo = strtoupper(trim($match[1]));
        $texto = trim(substr($texto, 0, -strlen($match[0])));
    }

    if ($texto !== '') {
        $partes = preg_split('/\s-\s/', $texto) ?: [];
        if (isset($partes[0]) && strcasecmp(trim($partes[0]), 'IMAGENES') === 0) {
            array_shift($partes);
        }
        if (isset($partes[0]) && preg_match('/^IMA[-_]/i', trim($partes[0]))) {
            array_shift($partes);
        }
        $texto = trim(implode(' - ', array_map('trim', $partes)));
    }

    $ojoMap = [
            'OD' => 'Derecho',
            'OI' => 'Izquierdo',
            'AO' => 'Ambos ojos',
            'DERECHO' => 'Derecho',
            'IZQUIERDO' => 'Izquierdo',
            'AMBOS OJOS' => 'Ambos ojos',
    ];

    return [
        'texto' => $texto,
        'ojo' => $ojoMap[$ojo] ?? $ojo,
    ];
};

$badgePalette = [
    'badge-primary-light',
    'badge-success-light',
    'badge-info-light',
    'badge-warning-light',
    'badge-danger-light',
    'badge-secondary-light',
];

$resolveBadgeClass = static function (string $value) use ($badgePalette): string {
    $normalized = trim(mb_strtolower($value, 'UTF-8'));
    if ($normalized === '') {
        return 'badge-secondary-light';
    }
    $hash = 0;
    $len = mb_strlen($normalized, 'UTF-8');
    for ($i = 0; $i < $len; $i++) {
        $char = mb_substr($normalized, $i, 1, 'UTF-8');
        $hash = ($hash * 31 + ord($char)) % 997;
    }
    $index = $hash % count($badgePalette);
    return $badgePalette[$index];
};

$estadoOpciones = [];
foreach ($imagenesRealizadas as $row) {
    $estado = trim((string)($row['estado_agenda'] ?? ''));
    if ($estado !== '' && !in_array($estado, $estadoOpciones, true)) {
        $estadoOpciones[] = $estado;
    }
}
sort($estadoOpciones);
?>

<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Imágenes · Procedimientos proyectados</h3>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                <li class="breadcrumb-item active" aria-current="page">Imágenes</li>
            </ol>
        </div>
    </div>
</div>

<section class="content">
    <div class="box">
        <div class="box-header with-border d-flex justify-content-between align-items-center">
            <h4 class="box-title mb-0">Listado por fecha, afiliación y paciente</h4>
            <div class="d-flex gap-2">
                <a href="/imagenes/dashboard" class="btn btn-outline-info btn-sm">
                    <i class="mdi mdi-chart-line"></i> Dashboard
                </a>
                <button type="button" class="btn btn-outline-primary btn-sm" id="btnPrintTable">
                    <i class="mdi mdi-printer"></i> Imprimir lista
                </button>
            </div>
        </div>
        <div class="box-body">
            <?php
            $totalInformados = 0;
            $totalNoInformados = 0;
            $totalSinNas = 0;
            foreach ($imagenesRealizadas as $row) {
                $informado = !empty($row['informe_id']);
                if ($informado) {
                    $totalInformados++;
                } else {
                    $totalNoInformados++;
                }
            }
            ?>
            <ul class="nav nav-tabs mb-3" id="tabInformes">
                <li class="nav-item">
                    <button class="nav-link active" type="button" data-tab="no-informados">
                        No informados
                        <span class="badge badge-secondary-light ms-1"><?= (int) $totalNoInformados ?></span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" type="button" data-tab="informados">
                        Informados
                        <span class="badge badge-success-light ms-1"><?= (int) $totalInformados ?></span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" type="button" data-tab="sin-nas">
                        Sin imágenes NAS
                        <span class="badge badge-warning-light ms-1"><?= (int) $totalSinNas ?></span>
                    </button>
                </li>
            </ul>
            <form class="row g-2 align-items-end mb-3" method="get" id="filtrosImagenes">
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Desde</label>
                    <input type="date" class="form-control" name="fecha_inicio"
                           value="<?= htmlspecialchars($filters['fecha_inicio'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Hasta</label>
                    <input type="date" class="form-control" name="fecha_fin"
                           value="<?= htmlspecialchars($filters['fecha_fin'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Afiliación</label>
                    <select class="form-select" name="afiliacion" id="filtroAfiliacion"
                            data-current="<?= htmlspecialchars($filters['afiliacion'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <option value="">Todas</option>
                    </select>
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Tipo examen</label>
                    <select class="form-select" name="tipo_examen" id="filtroTipoExamen"
                            data-current="<?= htmlspecialchars($filters['tipo_examen'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <option value="">Todos</option>
                    </select>
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Paciente/Cédula</label>
                    <input type="text" class="form-control" name="paciente" placeholder="Nombre o ID"
                           value="<?= htmlspecialchars($filters['paciente'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Estado</label>
                    <select class="form-select" name="estado_agenda">
                        <option value="">Todos</option>
                        <?php foreach ($estadoOpciones as $estado): ?>
                            <option value="<?= htmlspecialchars($estado, ENT_QUOTES, 'UTF-8') ?>" <?= ($filters['estado_agenda'] ?? '') === $estado ? 'selected' : '' ?>>
                                <?= htmlspecialchars($estado, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="mdi mdi-filter-variant"></i> Aplicar filtros
                    </button>
                    <a href="/imagenes/examenes-realizados" class="btn btn-outline-secondary btn-sm">
                        <i class="mdi mdi-close-circle-outline"></i> Limpiar
                    </a>
                </div>
            </form>
            <div class="table-responsive">
                <table id="tablaImagenesRealizadas" class="table table-lg invoice-archive">
                    <thead>
                    <tr>
                        <th class="text-center">
                            <input type="checkbox" class="form-check-input" id="selectAllInformados"
                                   aria-label="Seleccionar todos">
                        </th>
                        <th>Fecha</th>
                        <th>Afiliación</th>
                        <th>Paciente</th>
                        <th>Cédula</th>
                        <th>Imagen</th>
                        <th>Procedimiento</th>
                        <th>Ojo</th>
                        <th>Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($imagenesRealizadas as $row): ?>
                        <?php
                        $fechaRaw = (string)($row['fecha_examen'] ?? '');
                        $fechaUi = $fechaRaw !== '' ? date('d-m-Y', strtotime($fechaRaw)) : '—';
                        $imagenRuta = trim((string)($row['imagen_ruta'] ?? ''));
                        $imagenNombre = trim((string)($row['imagen_nombre'] ?? ''));
                        $tipoExamenRaw = trim((string)($row['tipo_examen'] ?? $row['examen_nombre'] ?? ''));
                        $tipoParsed = $parseProcedimiento($tipoExamenRaw);
                        $tipoExamen = $tipoParsed['texto'];
                        $ojoExamen = $tipoParsed['ojo'];
                        ?>
                        <?php $informado = !empty($row['informe_id']); ?>
                        <tr data-id="<?= (int)($row['id'] ?? 0) ?>"
                            data-form-id="<?= htmlspecialchars((string)($row['form_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-hc-number="<?= htmlspecialchars((string)($row['hc_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-fecha-examen="<?= htmlspecialchars((string)($row['fecha_examen'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-estado-agenda="<?= htmlspecialchars((string)($row['estado_agenda'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-afiliacion="<?= htmlspecialchars((string)($row['afiliacion'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-paciente="<?= htmlspecialchars((string)($row['full_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-examen="<?= htmlspecialchars($tipoExamen, ENT_QUOTES, 'UTF-8') ?>"
                            data-tipo-raw="<?= htmlspecialchars($tipoExamenRaw, ENT_QUOTES, 'UTF-8') ?>"
                            data-nas-status="pendiente"
                            data-informado="<?= $informado ? '1' : '0' ?>">
                            <td class="text-center select-cell">
                                <input type="checkbox" class="form-check-input row-select"
                                       value="<?= htmlspecialchars((string)($row['form_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    <?= $informado ? '' : 'disabled' ?>>
                            </td>
                            <td><?= htmlspecialchars($fechaUi, ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php
                                $afiliacionText = (string)($row['afiliacion'] ?? 'Sin afiliación');
                                $afiliacionBadge = $resolveBadgeClass($afiliacionText);
                                ?>
                                <span class="badge <?= htmlspecialchars($afiliacionBadge, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($afiliacionText, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars((string)($row['full_name'] ?? 'Sin nombre'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($row['cedula'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-info btn-view-nas">
                                    <i class="mdi mdi-folder-image"></i> Ver imágenes
                                </button>
                            </td>
                            <td>
                                <?php
                                $examenBadge = $resolveBadgeClass($tipoExamen);
                                ?>
                                <span class="badge <?= htmlspecialchars($examenBadge, ENT_QUOTES, 'UTF-8') ?> tipo-examen-label">
                                    <?= htmlspecialchars($tipoExamen !== '' ? $tipoExamen : 'No definido', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($ojoExamen !== '' ? $ojoExamen : '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-success btn-print-item" <?= $informado ? '' : 'disabled' ?>>
                                    <i class="mdi mdi-printer"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<style>
    .table-group-row td {
        background-color: #f8f9fa;
    }
    #tablaImagenesRealizadas th:first-child,
    #tablaImagenesRealizadas td:first-child {
        width: 44px;
        min-width: 44px;
    }
    #tablaImagenesRealizadas .row-select,
    #tablaImagenesRealizadas .form-check-input {
        appearance: auto;
        -webkit-appearance: checkbox;
        width: 18px;
        height: 18px;
        opacity: 1;
        visibility: visible;
        display: inline-block;
        position: static;
        margin: 0;
        cursor: pointer;
    }
    #tablaImagenesRealizadas .select-cell {
        cursor: pointer;
        vertical-align: middle;
    }
    .nas-slider-stage {
        height: clamp(500px, 58vh, 760px);
        min-height: 500px;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 0.5rem;
        overflow: hidden;
    }
    .nas-slider-preview-wrap {
        width: 100%;
        height: 100%;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    .nas-slider-preview-img {
        width: auto;
        height: auto;
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        display: block;
        background: #fff;
    }
    .nas-slider-preview-pdf {
        width: 100%;
        height: 100%;
        min-height: 0;
        border: 0;
        background: #fff;
    }
    .nas-slider-thumbs {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.5rem;
        max-height: 170px;
        overflow: auto;
    }
    .nas-nav-btn {
        min-width: 108px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
    }
    .nas-nav-btn .nav-arrow {
        font-size: 1rem;
        line-height: 1;
    }
    .nas-nav-btn:disabled {
        opacity: 0.5;
    }
    .nas-thumb-item {
        text-align: left;
        border: 1px solid #dee2e6;
        background: #fff;
        border-radius: 0.4rem;
        padding: 0.4rem 0.5rem;
        font-size: 0.8rem;
        line-height: 1.2;
    }
    .nas-thumb-item.active {
        border-color: #0d6efd;
        box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.15);
    }
    .nas-file-type-badge {
        font-size: 0.7rem;
    }
    @media (max-width: 1199.98px) {
        .nas-slider-stage {
            height: clamp(280px, 44vh, 560px);
            min-height: 280px;
        }
        .nas-slider-preview-pdf {
            min-height: 0;
        }
    }
</style>
</section>

<div class="modal fade" id="modalInformeImagen" tabindex="-1" aria-hidden="true" aria-labelledby="modalInformeImagenLabel">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalInformeImagenLabel">Informar examen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" style="min-height: 70vh;">
                <div class="row g-3 h-100">
                    <div class="col-12 col-xl-6 order-2 order-xl-1">
                        <div id="informeLoader" class="d-none text-center text-muted small py-2">
                            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                            Cargando informe...
                        </div>
                        <div id="informeTemplateContainer"></div>
                    </div>
                    <div class="col-12 col-xl-6 order-1 order-xl-2">
                        <div class="border rounded p-3 h-100 d-flex flex-column">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                <h6 class="mb-0">Imágenes del NAS</h6>
                                <span class="text-muted small" id="informeImagenesStatus"></span>
                            </div>
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary nas-nav-btn" id="btnNasPrev" aria-label="Archivo anterior">
                                    <span class="nav-arrow" aria-hidden="true">&larr;</span>
                                    <span>Anterior</span>
                                </button>
                                <span class="small text-muted" id="informeNasCounter">0/0</span>
                                <button type="button" class="btn btn-sm btn-outline-secondary nas-nav-btn" id="btnNasNext" aria-label="Archivo siguiente">
                                    <span>Siguiente</span>
                                    <span class="nav-arrow" aria-hidden="true">&rarr;</span>
                                </button>
                            </div>
                            <div id="informeImagenesContainer" class="nas-slider-stage flex-grow-1 d-flex align-items-center justify-content-center"></div>
                            <div class="d-flex justify-content-end mt-2">
                                <a href="#" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary disabled" id="btnNasOpenCurrent">
                                    <i class="mdi mdi-open-in-new"></i> Abrir archivo
                                </a>
                            </div>
                            <div id="informeNasThumbs" class="nas-slider-thumbs mt-3"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <span class="text-muted small me-auto" id="informeEstado"></span>
                <button type="button" class="btn btn-primary" id="btnGuardarInforme">Guardar informe</button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        function postJson(url, body) {
            return fetch(url, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(body || {})
            }).then(r => r.json());
        }

        document.addEventListener('DOMContentLoaded', function () {
            function uniqueSorted(values) {
                return Array.from(new Set(values.filter(Boolean))).sort(function (a, b) {
                    return a.localeCompare(b, 'es', {sensitivity: 'base'});
                });
            }

            function populateSelect(selectEl, values) {
                if (!selectEl) return;
                const current = selectEl.getAttribute('data-current') || '';
                const currentTrim = current.trim();
                const options = uniqueSorted(values);

                options.forEach(function (value) {
                    const opt = document.createElement('option');
                    opt.value = value;
                    opt.textContent = value;
                    selectEl.appendChild(opt);
                });

                if (currentTrim !== '') {
                    const exists = Array.from(selectEl.options).some(function (opt) {
                        return opt.value === currentTrim;
                    });
                    if (!exists) {
                        const opt = document.createElement('option');
                        opt.value = currentTrim;
                        opt.textContent = currentTrim;
                        selectEl.appendChild(opt);
                    }
                    selectEl.value = currentTrim;
                }
            }

            const rows = Array.from(document.querySelectorAll('#tablaImagenesRealizadas tbody tr[data-id]'));
            const afiliaciones = rows.map(function (row) {
                return (row.dataset.afiliacion || '').trim();
            });
            const examenes = rows.map(function (row) {
                return (row.dataset.examen || '').trim();
            });
            populateSelect(document.getElementById('filtroAfiliacion'), afiliaciones);
            populateSelect(document.getElementById('filtroTipoExamen'), examenes);

            let activeTab = 'no-informados';
            const selectAllInformados = document.getElementById('selectAllInformados');
            let dataTable = null;
            const nasStatusCache = new Map();
            const nasListCache = new Map();
            const nasStatusPending = new Map();
            let nasStatusRefreshTimer = null;
            const nasWarmCasePending = new Set();
            const nasWarmCaseDone = new Set();
            const nasWarmRequestQueue = [];
            const nasWarmRequestSet = new Set();
            let nasWarmRequestInFlight = false;
            const NAS_WARM_CASES_PER_PASS = 4;
            const NAS_STATUS_CASES_PER_PASS = 6;

            function rowIsInformado(row) {
                return (row && row.dataset && row.dataset.informado === '1');
            }

            function rowIsSinNas(row) {
                return (row && row.dataset && row.dataset.nasStatus === 'sin-archivos');
            }

            function rowInActiveTab(row) {
                if (!row || !row.dataset || !row.dataset.id) {
                    return true;
                }
                const informado = rowIsInformado(row);
                const sinNas = rowIsSinNas(row);
                if (activeTab === 'informados') {
                    return informado;
                }
                if (activeTab === 'sin-nas') {
                    return !informado && sinNas;
                }
                return !informado && !sinNas;
            }

            function getDataRows() {
                return Array.from(document.querySelectorAll('#tablaImagenesRealizadas tbody tr[data-id]'));
            }

            function getCurrentPageRows() {
                if (dataTable) {
                    return dataTable
                        .rows({page: 'current', search: 'applied'})
                        .nodes()
                        .toArray()
                        .filter(function (row) {
                            return !!(row && row.dataset && row.dataset.id);
                        });
                }
                return getDataRows().filter(function (row) {
                    return row.style.display !== 'none';
                });
            }

            function getVisibleSelectableRows() {
                return getCurrentPageRows().filter(function (row) {
                    return rowIsInformado(row);
                });
            }

            function updateSelectAllState() {
                if (!selectAllInformados) return;
                const rowsVisible = getVisibleSelectableRows();
                if (!rowsVisible.length) {
                    selectAllInformados.checked = false;
                    selectAllInformados.indeterminate = false;
                    selectAllInformados.disabled = activeTab !== 'informados';
                    return;
                }
                const total = rowsVisible.length;
                const checked = rowsVisible.filter(function (row) {
                    const checkbox = row.querySelector('.row-select');
                    return checkbox && checkbox.checked;
                }).length;
                selectAllInformados.checked = checked > 0 && checked === total;
                selectAllInformados.indeterminate = checked > 0 && checked < total;
                selectAllInformados.disabled = activeTab !== 'informados';
            }

            function refreshTabCounts() {
                const totalRows = rows.filter(function (row) {
                    return !!(row && row.dataset && row.dataset.id);
                });
                const totalInformados = totalRows.filter(function (row) {
                    return rowIsInformado(row);
                }).length;
                const totalSinNas = totalRows.filter(function (row) {
                    return !rowIsInformado(row) && rowIsSinNas(row);
                }).length;
                const totalNoInformados = totalRows.length - totalInformados - totalSinNas;
                const badgeNo = document.querySelector('#tabInformes [data-tab="no-informados"] .badge');
                const badgeSi = document.querySelector('#tabInformes [data-tab="informados"] .badge');
                const badgeSinNas = document.querySelector('#tabInformes [data-tab="sin-nas"] .badge');
                if (badgeNo) badgeNo.textContent = String(totalNoInformados);
                if (badgeSi) badgeSi.textContent = String(totalInformados);
                if (badgeSinNas) badgeSinNas.textContent = String(totalSinNas);
            }

            function applyTabFilter(tab) {
                activeTab = tab;
                if (dataTable) {
                    dataTable.draw(false);
                    dataTable.columns.adjust();
                } else {
                    getDataRows().forEach(function (row) {
                        const visible = rowInActiveTab(row);
                        row.style.display = visible ? '' : 'none';
                    });
                    applyPatientGrouping();
                }
                refreshTabCounts();
                updateSelectAllState();
                runActiveTabBackgroundTasks();
            }

            document.querySelectorAll('#tabInformes [data-tab]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    document.querySelectorAll('#tabInformes .nav-link').forEach(function (item) {
                        item.classList.remove('active');
                    });
                    btn.classList.add('active');
                    applyTabFilter(btn.getAttribute('data-tab'));
                });
            });

            if (selectAllInformados) {
                selectAllInformados.addEventListener('change', function () {
                    const shouldSelect = !!selectAllInformados.checked;
                    getVisibleSelectableRows().forEach(function (row) {
                        const checkbox = row.querySelector('.row-select');
                        if (!checkbox || checkbox.disabled) return;
                        checkbox.checked = shouldSelect;
                    });
                    updateSelectAllState();
                });
            }

            const tbody = document.querySelector('#tablaImagenesRealizadas tbody');
            if (tbody) {
                tbody.addEventListener('click', function (event) {
                    const cell = event.target.closest && event.target.closest('.select-cell');
                    if (!cell || !tbody.contains(cell)) return;
                    const checkbox = cell.querySelector('.row-select');
                    if (!checkbox || checkbox.disabled) return;
                    if (event.target && event.target.classList && event.target.classList.contains('row-select')) {
                        event.stopPropagation();
                        setTimeout(updateSelectAllState, 0);
                        return;
                    }
                    event.preventDefault();
                    event.stopPropagation();
                    checkbox.checked = !checkbox.checked;
                    updateSelectAllState();
                });
                tbody.addEventListener('change', function (event) {
                    if (event.target && event.target.classList && event.target.classList.contains('row-select')) {
                        updateSelectAllState();
                    }
                });
            }

            const modalEl = document.getElementById('modalInformeImagen');
            const templateContainer = document.getElementById('informeTemplateContainer');
            const btnGuardarInforme = document.getElementById('btnGuardarInforme');
            const estadoInforme = document.getElementById('informeEstado');
            const imagenesContainer = document.getElementById('informeImagenesContainer');
            const imagenesStatus = document.getElementById('informeImagenesStatus');
            const imagenesThumbs = document.getElementById('informeNasThumbs');
            const nasCounter = document.getElementById('informeNasCounter');
            const btnNasPrev = document.getElementById('btnNasPrev');
            const btnNasNext = document.getElementById('btnNasNext');
            const btnNasOpenCurrent = document.getElementById('btnNasOpenCurrent');
            const informeLoader = document.getElementById('informeLoader');
            const modalInstance = window.bootstrap && modalEl ? new bootstrap.Modal(modalEl) : null;
            let informeContext = null;
            let nasFiles = [];
            let nasCurrentIndex = 0;
            let nasLoadToken = 0;
            const nasPreviewNodes = new Map();
            const templateHtmlCache = new Map();

            function setEstado(texto) {
                if (estadoInforme) {
                    estadoInforme.textContent = texto || '';
                }
            }

            function setImagenesStatus(texto) {
                if (imagenesStatus) {
                    imagenesStatus.textContent = texto || '';
                }
            }

            function setInformeLoading(loading) {
                if (!informeLoader) return;
                informeLoader.classList.toggle('d-none', !loading);
            }

            function isPdf(file) {
                const ext = String(file && file.ext ? file.ext : '').toLowerCase();
                const type = String(file && file.type ? file.type : '').toLowerCase();
                return ext === 'pdf' || type === 'application/pdf';
            }

            function isImage(file) {
                const ext = String(file && file.ext ? file.ext : '').toLowerCase();
                const type = String(file && file.type ? file.type : '').toLowerCase();
                return ['png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp'].indexOf(ext) !== -1 || type.indexOf('image/') === 0;
            }

            function getNasFileKey(file) {
                if (file && file.url) {
                    return String(file.url);
                }
                return String(file && file.name ? file.name : '');
            }

            function getNasDisplayUrl(file) {
                if (file && file._cachedUrl) {
                    return String(file._cachedUrl);
                }
                return String(file && file.url ? file.url : '#');
            }

            function releaseNasFileCaches(list) {
                (Array.isArray(list) ? list : []).forEach(function (file) {
                    if (file && file._cachedUrl) {
                        try {
                            URL.revokeObjectURL(file._cachedUrl);
                        } catch (e) {}
                        file._cachedUrl = '';
                    }
                    if (file) {
                        file._preloadPromise = null;
                    }
                });
            }

            function extractNasSuffixToken(name) {
                const raw = String(name || '');
                const stem = raw.replace(/\.[^.]+$/, '');
                const idMatch = stem.match(/,\s*\d{6,}$/);
                if (!idMatch) return '';
                const left = stem.slice(0, idMatch.index).trim();
                const tokenMatch = left.match(/([A-Za-z]{1,5})$/);
                if (!tokenMatch) return '';
                const token = tokenMatch[1];
                if (token !== token.toLowerCase()) {
                    return '';
                }
                return token;
            }

            function rankNasFileName(name) {
                const token = extractNasSuffixToken(name);
                if (!token) return 0;
                const map = {
                    gy: 1,
                    gc: 1,
                    gcl: 1,
                    hno: 2,
                    onh: 2,
                    rnfl: 2
                };
                return map[token] || 3;
            }

            function orderNasFiles(files) {
                return (Array.isArray(files) ? files.slice() : []).sort(function (a, b) {
                    const rankA = rankNasFileName(a && a.name ? a.name : '');
                    const rankB = rankNasFileName(b && b.name ? b.name : '');
                    if (rankA !== rankB) {
                        return rankA - rankB;
                    }
                    const mtimeA = Number(a && a.mtime ? a.mtime : 0);
                    const mtimeB = Number(b && b.mtime ? b.mtime : 0);
                    if (mtimeA !== mtimeB) {
                        return mtimeB - mtimeA;
                    }
                    return String(a && a.name ? a.name : '').localeCompare(String(b && b.name ? b.name : ''), 'es', {sensitivity: 'base'});
                });
            }

            function preloadNasFile(file, token) {
                if (!file || !file.url) {
                    return Promise.resolve();
                }
                if (file._cachedUrl) {
                    return Promise.resolve();
                }
                if (file._preloadPromise) {
                    return file._preloadPromise;
                }
                file._preloadPromise = fetch(file.url, {credentials: 'same-origin'})
                    .then(function (r) {
                        if (!r.ok) throw new Error('status');
                        return r.blob();
                    })
                    .then(function (blob) {
                        if (!blob) return;
                        const objectUrl = URL.createObjectURL(blob);
                        if (token !== nasLoadToken) {
                            try {
                                URL.revokeObjectURL(objectUrl);
                            } catch (e) {}
                            return;
                        }
                        file._cachedUrl = objectUrl;
                    })
                    .catch(function () {
                    })
                    .finally(function () {
                        file._preloadPromise = null;
                    });
                return file._preloadPromise;
            }

            function preloadNasFiles(files, token) {
                const queue = Array.isArray(files) ? files.slice() : [];
                if (!queue.length) return;
                const workers = Math.min(6, queue.length);
                for (let i = 0; i < workers; i++) {
                    const run = function () {
                        if (!queue.length) return Promise.resolve();
                        const file = queue.shift();
                        return preloadNasFile(file, token).finally(run);
                    };
                    run();
                }
            }

            function clearNasPreviewStage() {
                nasPreviewNodes.clear();
                if (imagenesContainer) {
                    imagenesContainer.innerHTML = '';
                }
            }

            function buildNasPreviewNode(file) {
                const current = file || {};
                const name = current.name ? String(current.name) : 'Archivo';
                const url = getNasDisplayUrl(current);

                const wrapper = document.createElement('div');
                wrapper.className = 'nas-slider-preview-wrap d-none';
                wrapper.dataset.nasKey = getNasFileKey(current);

                const typeBadge = document.createElement('div');
                typeBadge.className = 'position-absolute top-0 end-0 m-2';
                typeBadge.innerHTML = isPdf(current)
                    ? '<span class="badge bg-danger">PDF</span>'
                    : '<span class="badge bg-info text-dark">Imagen</span>';
                wrapper.appendChild(typeBadge);

                if (isImage(current)) {
                    const img = document.createElement('img');
                    img.src = url;
                    img.alt = name;
                    img.className = 'nas-slider-preview-img';
                    wrapper.appendChild(img);
                } else if (isPdf(current)) {
                    const iframe = document.createElement('iframe');
                    iframe.src = url;
                    iframe.className = 'nas-slider-preview-pdf';
                    iframe.title = name;
                    wrapper.appendChild(iframe);
                } else {
                    const fallback = document.createElement('div');
                    fallback.className = 'w-100 h-100 d-flex flex-column align-items-center justify-content-center text-muted';
                    fallback.innerHTML = '<i class="mdi mdi-file-outline fs-1 mb-2"></i><div class="small">Archivo no previsualizable</div>';
                    wrapper.appendChild(fallback);
                }

                return wrapper;
            }

            function updateNasControls() {
                const total = nasFiles.length;
                if (nasCounter) {
                    nasCounter.textContent = total ? (String(nasCurrentIndex + 1) + '/' + String(total)) : '0/0';
                }
                if (btnNasPrev) {
                    btnNasPrev.disabled = total === 0 || nasCurrentIndex <= 0;
                }
                if (btnNasNext) {
                    btnNasNext.disabled = total === 0 || nasCurrentIndex >= total - 1;
                }
                if (btnNasOpenCurrent) {
                    const current = total ? nasFiles[nasCurrentIndex] : null;
                    if (current && current.url) {
                        btnNasOpenCurrent.classList.remove('disabled');
                        btnNasOpenCurrent.href = current.url;
                    } else {
                        btnNasOpenCurrent.classList.add('disabled');
                        btnNasOpenCurrent.href = '#';
                    }
                }
            }

            function renderNasThumbs() {
                if (!imagenesThumbs) return;
                imagenesThumbs.innerHTML = '';
                if (!nasFiles.length) return;
                nasFiles.forEach(function (file, index) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'nas-thumb-item' + (index === nasCurrentIndex ? ' active' : '');

                    const top = document.createElement('div');
                    top.className = 'd-flex align-items-center justify-content-between gap-2 mb-1';
                    const order = document.createElement('strong');
                    order.textContent = '#' + String(index + 1);
                    const badge = document.createElement('span');
                    badge.className = 'badge nas-file-type-badge ' + (isPdf(file) ? 'bg-danger' : 'bg-info');
                    badge.textContent = isPdf(file) ? 'PDF' : 'Imagen';
                    top.appendChild(order);
                    top.appendChild(badge);

                    const name = document.createElement('div');
                    name.className = 'text-truncate';
                    name.title = file && file.name ? file.name : 'Archivo';
                    name.textContent = file && file.name ? file.name : 'Archivo';

                    btn.appendChild(top);
                    btn.appendChild(name);
                    btn.addEventListener('click', function () {
                        nasCurrentIndex = index;
                        renderImagenesNas();
                    });
                    imagenesThumbs.appendChild(btn);
                });
            }

            function renderImagenesNas(files) {
                if (!imagenesContainer) return;
                if (Array.isArray(files)) {
                    releaseNasFileCaches(nasFiles);
                    nasFiles = files.slice();
                    nasCurrentIndex = 0;
                    clearNasPreviewStage();
                }
                if (!nasFiles.length) {
                    imagenesContainer.innerHTML = '<div class="text-muted small px-3">No se encontraron archivos en el NAS.</div>';
                    renderNasThumbs();
                    updateNasControls();
                    return;
                }
                if (nasCurrentIndex >= nasFiles.length) {
                    nasCurrentIndex = nasFiles.length - 1;
                }
                if (nasCurrentIndex < 0) {
                    nasCurrentIndex = 0;
                }

                const current = nasFiles[nasCurrentIndex];
                const key = getNasFileKey(current);
                if (!nasPreviewNodes.has(key)) {
                    nasPreviewNodes.set(key, buildNasPreviewNode(current));
                }

                nasPreviewNodes.forEach(function (node) {
                    if (!imagenesContainer.contains(node)) {
                        imagenesContainer.appendChild(node);
                    }
                    node.classList.add('d-none');
                });

                const currentNode = nasPreviewNodes.get(key);
                if (currentNode) {
                    currentNode.classList.remove('d-none');
                }

                renderNasThumbs();
                updateNasControls();
            }

            function scheduleNasStatusRefresh() {
                if (nasStatusRefreshTimer !== null) {
                    clearTimeout(nasStatusRefreshTimer);
                }
                nasStatusRefreshTimer = setTimeout(function () {
                    nasStatusRefreshTimer = null;
                    if (dataTable) {
                        dataTable.draw(false);
                    } else {
                        getDataRows().forEach(function (row) {
                            row.style.display = rowInActiveTab(row) ? '' : 'none';
                        });
                        applyPatientGrouping();
                        refreshTabCounts();
                        updateSelectAllState();
                    }
                }, 120);
            }

            function resolveNasStatus(formId, hcNumber) {
                const form = String(formId || '').trim();
                const hc = String(hcNumber || '').trim();
                if (!form || !hc) {
                    return Promise.resolve('error');
                }
                const key = form + '|' + hc;
                if (nasStatusCache.has(key)) {
                    return Promise.resolve(nasStatusCache.get(key));
                }
                if (nasStatusPending.has(key)) {
                    return nasStatusPending.get(key);
                }
                const promise = fetch('/imagenes/examenes-realizados/nas/list?hc_number=' + encodeURIComponent(hc) + '&form_id=' + encodeURIComponent(form))
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (!res || !res.success) {
                            return 'error';
                        }
                        const files = orderNasFiles(Array.isArray(res.files) ? res.files : []);
                        nasListCache.set(key, files);
                        return files.length > 0
                            ? 'con-archivos'
                            : 'sin-archivos';
                    })
                    .catch(function () {
                        return 'error';
                    })
                    .then(function (status) {
                        if (status === 'con-archivos' || status === 'sin-archivos') {
                            nasStatusCache.set(key, status);
                        }
                        return status;
                    })
                    .finally(function () {
                        nasStatusPending.delete(key);
                    });
                nasStatusPending.set(key, promise);
                return promise;
            }

            function setRowNasStatus(row, status, shouldRefresh) {
                if (!row || !row.dataset || !row.dataset.id) return;
                if (status !== 'con-archivos' && status !== 'sin-archivos' && status !== 'pendiente') return;
                if ((row.dataset.nasStatus || 'pendiente') === status) return;
                row.dataset.nasStatus = status;
                const form = String(row.dataset.formId || '').trim();
                const hc = String(row.dataset.hcNumber || '').trim();
                if (form && hc && (status === 'con-archivos' || status === 'sin-archivos')) {
                    nasStatusCache.set(form + '|' + hc, status);
                }
                if (shouldRefresh !== false) {
                    scheduleNasStatusRefresh();
                }
            }

            function scanNasStatusForVisibleRows() {
                const shouldWarmModal = activeTab === 'no-informados';
                const candidates = getCurrentPageRows()
                    .filter(function (row) {
                        return !rowIsInformado(row);
                    })
                    .filter(function (row) {
                        const status = String(row.dataset.nasStatus || 'pendiente');
                        return status === 'pendiente';
                    })
                    .slice(0, NAS_STATUS_CASES_PER_PASS);
                if (!candidates.length) {
                    return;
                }
                const queue = candidates.slice();
                const workers = Math.min(3, queue.length);
                for (let i = 0; i < workers; i++) {
                    const run = function () {
                        if (!queue.length) {
                            return Promise.resolve();
                        }
                        const row = queue.shift();
                        const form = String(row.dataset.formId || '').trim();
                        const hc = String(row.dataset.hcNumber || '').trim();
                        if (!form || !hc) {
                            return Promise.resolve().finally(run);
                        }
                        return resolveNasStatus(form, hc)
                            .then(function (status) {
                                if (status === 'con-archivos' || status === 'sin-archivos') {
                                    setRowNasStatus(row, status, true);
                                }
                                if (shouldWarmModal && status === 'con-archivos') {
                                    warmFirstNasFileForCase(form, hc);
                                }
                            })
                            .finally(run);
                    };
                    run();
                }
            }

            function enqueueNasWarmCase(formId, hcNumber) {
                const form = String(formId || '').trim();
                const hc = String(hcNumber || '').trim();
                if (!form || !hc) return;
                const caseKey = form + '|' + hc;
                if (nasWarmRequestSet.has(caseKey)) return;
                nasWarmRequestSet.add(caseKey);
                nasWarmRequestQueue.push({form_id: form, hc_number: hc, key: caseKey});
                pumpNasWarmCaseQueue();
            }

            function pumpNasWarmCaseQueue() {
                if (nasWarmRequestInFlight) return;
                if (!nasWarmRequestQueue.length) return;

                const batch = nasWarmRequestQueue.splice(0, 4);
                const items = batch.map(function (it) {
                    return {form_id: it.form_id, hc_number: it.hc_number};
                });
                nasWarmRequestInFlight = true;

                postJson('/imagenes/examenes-realizados/nas/warm', {items: items})
                    .catch(function () {
                    })
                    .finally(function () {
                        batch.forEach(function (it) {
                            nasWarmRequestSet.delete(it.key);
                        });
                        nasWarmRequestInFlight = false;
                        if (nasWarmRequestQueue.length) {
                            setTimeout(pumpNasWarmCaseQueue, 80);
                        }
                    });
            }

            function warmFirstNasFileForCase(formId, hcNumber) {
                const form = String(formId || '').trim();
                const hc = String(hcNumber || '').trim();
                if (!form || !hc) return;
                const caseKey = form + '|' + hc;
                if (nasWarmCaseDone.has(caseKey) || nasWarmCasePending.has(caseKey)) {
                    return;
                }
                nasWarmCasePending.add(caseKey);
                resolveNasStatus(form, hc)
                    .then(function (status) {
                        if (status !== 'con-archivos') return;
                        const files = nasListCache.get(caseKey) || [];
                        if (!files.length) return;
                        const first = files[0];
                        if (!first || !first.name) return;
                        enqueueNasWarmCase(form, hc);
                    })
                    .finally(function () {
                        nasWarmCasePending.delete(caseKey);
                        nasWarmCaseDone.add(caseKey);
                    });
            }

            function warmInformadosVisibleRows() {
                if (activeTab !== 'informados') return;
                const visibleRows = getCurrentPageRows().filter(function (row) {
                    return rowIsInformado(row);
                });
                if (!visibleRows.length) return;

                let processed = 0;
                visibleRows.some(function (row) {
                    if (processed >= NAS_WARM_CASES_PER_PASS) return true;
                    const form = String(row.dataset.formId || '').trim();
                    const hc = String(row.dataset.hcNumber || '').trim();
                    if (!form || !hc) return false;
                    processed += 1;
                    warmFirstNasFileForCase(form, hc);
                    return false;
                });
            }

            function runActiveTabBackgroundTasks() {
                if (activeTab === 'informados') {
                    warmInformadosVisibleRows();
                    return;
                }
                scanNasStatusForVisibleRows();
            }

            function cargarImagenesNas(formId, hcNumber, row) {
                if (!imagenesContainer) return;
                nasLoadToken += 1;
                const token = nasLoadToken;
                const cacheKey = String(formId).trim() + '|' + String(hcNumber).trim();
                releaseNasFileCaches(nasFiles);
                nasFiles = [];
                nasCurrentIndex = 0;
                renderImagenesNas([]);
                setImagenesStatus('Cargando imágenes...');
                const cachedFiles = nasListCache.get(cacheKey);
                if (Array.isArray(cachedFiles)) {
                    renderImagenesNas(cachedFiles);
                    preloadNasFiles(cachedFiles, token);
                    if (row) {
                        setRowNasStatus(row, cachedFiles.length ? 'con-archivos' : 'sin-archivos', false);
                    }
                    setImagenesStatus(cachedFiles.length
                        ? (cachedFiles.length + ' archivo(s) en caché...')
                        : 'Sin archivos en caché');
                }
                fetch('/imagenes/examenes-realizados/nas/list?hc_number=' + encodeURIComponent(hcNumber) + '&form_id=' + encodeURIComponent(formId))
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (!res || !res.success) {
                            renderImagenesNas([]);
                            setImagenesStatus(res && res.error ? res.error : 'No se pudieron cargar las imágenes.');
                            if (row) {
                                setRowNasStatus(row, 'pendiente', false);
                            }
                            return;
                        }
                        const files = orderNasFiles(res.files || []);
                        nasListCache.set(cacheKey, files);
                        renderImagenesNas(files);
                        preloadNasFiles(files, token);
                        if (row) {
                            setRowNasStatus(row, files.length ? 'con-archivos' : 'sin-archivos', true);
                        }
                        setImagenesStatus(files.length
                            ? (files.length + ' archivo(s) encontrado(s)')
                            : 'Sin archivos en el NAS');
                    })
                    .catch(function () {
                        renderImagenesNas([]);
                        setImagenesStatus('Error al conectar con el NAS.');
                        if (row) {
                            setRowNasStatus(row, 'pendiente', false);
                        }
                    });
            }

            if (btnNasPrev) {
                btnNasPrev.addEventListener('click', function () {
                    if (nasCurrentIndex <= 0) return;
                    nasCurrentIndex -= 1;
                    renderImagenesNas();
                });
            }
            if (btnNasNext) {
                btnNasNext.addEventListener('click', function () {
                    if (nasCurrentIndex >= nasFiles.length - 1) return;
                    nasCurrentIndex += 1;
                    renderImagenesNas();
                });
            }

            function cssEscape(value) {
                if (window.CSS && typeof window.CSS.escape === 'function') {
                    return window.CSS.escape(value);
                }
                return String(value).replace(/[^a-zA-Z0-9_-]/g, '\\$&');
            }

            function escapeRegex(value) {
                return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            }

            function updateTargetValue(target, text, checked) {
                if (!target || !text) return;
                const current = target.value || '';
                if (checked) {
                    if (current.trim().length > 0) {
                        target.value = current + ', ' + text;
                    } else {
                        target.value = text;
                    }
                    return;
                }

                const regex = new RegExp('(?:^|,\\s*)' + escapeRegex(text) + '(?=,|$)', 'g');
                const cleaned = current.replace(regex, '').replace(/\\s*,\\s*/g, ', ').replace(/^,\\s*|,\\s*$/g, '').trim();
                target.value = cleaned;
            }

            function initChecklist(container) {
                if (!container) return;
                container.querySelectorAll('.informe-checkbox').forEach(function (checkbox) {
                    checkbox.addEventListener('change', function () {
                        const targetId = checkbox.getAttribute('data-target') || '';
                        const text = checkbox.getAttribute('data-text') || '';
                        if (!targetId || !text) return;
                        const target = container.querySelector('#' + cssEscape(targetId));
                        if (!target) return;
                        updateTargetValue(target, text, checkbox.checked);
                    });
                });
            }

            function rebuildChecklistTargets(container) {
                if (!container) return;
                container.querySelectorAll('.informe-checkbox').forEach(function (checkbox) {
                    if (!checkbox.checked) return;
                    const targetId = checkbox.getAttribute('data-target') || '';
                    const text = checkbox.getAttribute('data-text') || '';
                    if (!targetId || !text) return;
                    const target = container.querySelector('#' + cssEscape(targetId));
                    if (!target) return;
                    if ((target.value || '').indexOf(text) === -1) {
                        updateTargetValue(target, text, true);
                    }
                });
            }

            function collectPayload(container) {
                const payload = {};
                if (!container) return payload;
                container.querySelectorAll('input, textarea, select').forEach(function (el) {
                    const key = el.id || el.name;
                    if (!key) return;
                    if (el.type === 'checkbox') {
                        payload[key] = !!el.checked;
                        return;
                    }
                    if (el.type === 'radio') {
                        if (el.checked) payload[key] = el.value;
                        return;
                    }
                    payload[key] = el.value;
                });
                return payload;
            }

            function applyPayload(container, payload) {
                if (!container || !payload || typeof payload !== 'object') return;
                Object.keys(payload).forEach(function (key) {
                    const selector = '#' + cssEscape(key);
                    let el = container.querySelector(selector);
                    if (!el) {
                        el = container.querySelector('[name="' + key + '"]');
                    }
                    if (!el) return;
                    if (el.type === 'checkbox') {
                        el.checked = !!payload[key];
                        return;
                    }
                    if (el.type === 'radio') {
                        if (el.value === payload[key]) {
                            el.checked = true;
                        }
                        return;
                    }
                    el.value = payload[key] ?? '';
                });
            }

            function normalizarPayload(payload, plantilla) {
                if (!payload || !plantilla) return payload;
                if (plantilla === 'octm') {
                    const defecto = 'Arquitectura retiniana bien definida, fóvea con depresión central bien delineada, epitelio pigmentario continuo y uniforme, membrana limitante interna es hiporreflectiva y continua, células de Müller están bien alineadas sin signos de edema o tracción.';
                    const ctmod = (payload.inputOD || '').trim();
                    const textOD = (payload.textOD || '').trim();
                    if (ctmod !== '' && textOD === '') {
                        payload.textOD = defecto;
                    }
                    const ctmoi = (payload.inputOI || '').trim();
                    const textOI = (payload.textOI || '').trim();
                    if (ctmoi !== '' && textOI === '') {
                        payload.textOI = defecto;
                    }
                }
                return payload;
            }

            function toNumber(value) {
                const normalized = (value || '').toString().trim().replace(',', '.');
                if (!normalized) return null;
                const n = Number(normalized);
                return Number.isFinite(n) ? n : null;
            }

            function wrapAxis(axis) {
                let a = Math.round(axis);
                while (a <= 0) a += 180;
                while (a > 180) a -= 180;
                return a;
            }

            function initCorneaTopografiaTemplate(container) {
                if (!container) return;
                const template = container.querySelector('[data-informe-template="cornea"]');
                if (!template) return;

                function recalcEye(prefix) {
                    const kFlatEl = container.querySelector('#kFlat' + prefix);
                    const axisFlatEl = container.querySelector('#axisFlat' + prefix);
                    const kSteepEl = container.querySelector('#kSteep' + prefix);
                    const axisSteepEl = container.querySelector('#axisSteep' + prefix);
                    const cilindroEl = container.querySelector('#cilindro' + prefix);
                    const kPromedioEl = container.querySelector('#kPromedio' + prefix);
                    if (!kFlatEl || !axisFlatEl || !kSteepEl || !axisSteepEl || !cilindroEl || !kPromedioEl) {
                        return;
                    }

                    const kFlat = toNumber(kFlatEl.value);
                    const axisFlat = toNumber(axisFlatEl.value);
                    const kSteep = toNumber(kSteepEl.value);

                    if (axisFlat !== null) {
                        const steepAxis = wrapAxis(axisFlat + 90);
                        axisSteepEl.value = String(steepAxis);
                    } else {
                        axisSteepEl.value = '';
                    }

                    if (kFlat !== null && kSteep !== null) {
                        cilindroEl.value = Math.abs(kSteep - kFlat).toFixed(2);
                        kPromedioEl.value = ((kSteep + kFlat) / 2).toFixed(2);
                    } else {
                        cilindroEl.value = '';
                        kPromedioEl.value = '';
                    }
                }

                ['OD', 'OI'].forEach(function (prefix) {
                    ['kFlat', 'axisFlat', 'kSteep'].forEach(function (field) {
                        const input = container.querySelector('#' + field + prefix);
                        if (!input) return;
                        if (!input.dataset.corneaBound) {
                            input.addEventListener('input', function () {
                                recalcEye(prefix);
                            });
                            input.dataset.corneaBound = '1';
                        }
                    });
                    recalcEye(prefix);
                });
            }

            function abrirInformeModal(row) {
                if (!row) return;
                const formId = (row.dataset.formId || '').trim();
                const hcNumber = (row.dataset.hcNumber || '').trim();
                const tipoRaw = (row.dataset.tipoRaw || '').trim();

                if (!formId || !tipoRaw) {
                    alert('Faltan datos del examen para informar.');
                    return;
                }

                if (modalInstance) {
                    modalInstance.show();
                }

                if (templateContainer) {
                    templateContainer.innerHTML = '';
                }
                setInformeLoading(true);
                setEstado('Cargando plantilla...');
                if (btnGuardarInforme) {
                    btnGuardarInforme.disabled = true;
                }

                if (formId && hcNumber) {
                    cargarImagenesNas(formId, hcNumber, row);
                }

                fetch('/imagenes/informes/datos?form_id=' + encodeURIComponent(formId) + '&tipo_examen=' + encodeURIComponent(tipoRaw))
                    .then(function (r) {
                        return r.json();
                    })
                    .then(function (res) {
                        if (!res || !res.success) {
                            alert((res && res.error) ? res.error : 'No se pudo cargar la plantilla.');
                            setEstado('');
                            setInformeLoading(false);
                            return;
                        }

                        informeContext = {
                            formId: formId,
                            hcNumber: hcNumber,
                            tipoRaw: tipoRaw,
                            plantilla: res.plantilla,
                            payload: res.payload || null,
                            row: row
                        };

                        if (templateHtmlCache.has(res.plantilla)) {
                            return templateHtmlCache.get(res.plantilla);
                        }

                        return fetch('/imagenes/informes/plantilla?plantilla=' + encodeURIComponent(res.plantilla))
                            .then(function (response) {
                                if (!response.ok) {
                                    throw new Error('No se pudo cargar la plantilla');
                                }
                                return response.text();
                            })
                            .then(function (html) {
                                templateHtmlCache.set(res.plantilla, html);
                                return html;
                            });
                    })
                    .then(function (html) {
                        if (!html || !templateContainer) return;
                        templateContainer.innerHTML = html;
                        initChecklist(templateContainer);
                        initCorneaTopografiaTemplate(templateContainer);
                        if (informeContext && informeContext.payload) {
                            applyPayload(templateContainer, informeContext.payload);
                            initCorneaTopografiaTemplate(templateContainer);
                            rebuildChecklistTargets(templateContainer);
                            setEstado('Informe existente cargado.');
                        } else {
                            setEstado('Informe nuevo.');
                        }
                        if (btnGuardarInforme) {
                            btnGuardarInforme.disabled = false;
                        }
                        setInformeLoading(false);
                    })
                    .catch(function () {
                        setEstado('');
                        setInformeLoading(false);
                        if (btnGuardarInforme) {
                            btnGuardarInforme.disabled = false;
                        }
                        alert('Error de red al cargar la plantilla.');
                    });
            }

            document.querySelectorAll('#tablaImagenesRealizadas tbody tr').forEach(function (row) {
                row.addEventListener('click', function (event) {
                    if (event.target.closest('button, a, input, select, textarea, label')) {
                        return;
                    }
                    abrirInformeModal(row);
                });
            });

            document.querySelectorAll('.btn-view-nas').forEach(function (btn) {
                btn.addEventListener('click', function (event) {
                    event.preventDefault();
                    const row = btn.closest('tr');
                    if (!row) return;
                    abrirInformeModal(row);
                });
            });

            if (btnGuardarInforme) {
                btnGuardarInforme.addEventListener('click', function () {
                    if (!informeContext || !templateContainer) {
                        return;
                    }
                    const payload = normalizarPayload(collectPayload(templateContainer), informeContext.plantilla);
                    setEstado('Guardando informe...');
                    postJson('/imagenes/informes/guardar', {
                        form_id: informeContext.formId,
                        hc_number: informeContext.hcNumber,
                        tipo_examen: informeContext.tipoRaw,
                        plantilla: informeContext.plantilla,
                        payload: payload,
                        firmante_id: payload.firmante_id || ''
                    }).then(function (res) {
                        if (!res || !res.success) {
                            setEstado('No se pudo guardar el informe.');
                            return;
                        }
                        setEstado('Informe guardado.');
                        if (informeContext && informeContext.row) {
                            const row = informeContext.row;
                            row.dataset.informado = '1';
                            const checkbox = row.querySelector('.row-select');
                            if (checkbox) {
                                checkbox.disabled = false;
                            }
                            const printBtn = row.querySelector('.btn-print-item');
                            if (printBtn) {
                                printBtn.disabled = false;
                            }
                            applyTabFilter(activeTab);
                        }
                        if (modalInstance) {
                            modalInstance.hide();
                        }
                    }).catch(function () {
                        setEstado('Error de red al guardar.');
                    });
                });
            }

                if (modalEl) {
                modalEl.addEventListener('hidden.bs.modal', function () {
                    nasLoadToken += 1;
                    if (templateContainer) {
                        templateContainer.innerHTML = '';
                    }
                    releaseNasFileCaches(nasFiles);
                    clearNasPreviewStage();
                    if (imagenesThumbs) {
                        imagenesThumbs.innerHTML = '';
                    }
                    nasFiles = [];
                    nasCurrentIndex = 0;
                    updateNasControls();
                    setImagenesStatus('');
                    setInformeLoading(false);
                    if (btnGuardarInforme) {
                        btnGuardarInforme.disabled = false;
                    }
                    informeContext = null;
                    setEstado('');
                });
            }

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            function getPacienteGroup(row) {
                const hc = (row.dataset.hcNumber || '').trim();
                const paciente = (row.dataset.paciente || '').trim();
                if (hc) {
                    return {
                        key: 'HC:' + hc,
                        label: paciente ? (paciente + ' · HC ' + hc) : ('HC ' + hc)
                    };
                }
                if (paciente) {
                    return {
                        key: 'PAC:' + paciente,
                        label: paciente
                    };
                }
                return {
                    key: 'SIN',
                    label: 'Paciente sin identificar'
                };
            }

            function getSelectedRowsByGroup(groupKey) {
                return Array.from(document.querySelectorAll('#tablaImagenesRealizadas tbody tr[data-group-key]'))
                    .filter(function (row) {
                        return row.dataset.groupKey === groupKey;
                    })
                    .filter(function (row) {
                        const checkbox = row.querySelector('.row-select');
                        return checkbox && checkbox.checked;
                    });
            }

            function getGroupRows(groupKey) {
                return Array.from(document.querySelectorAll('#tablaImagenesRealizadas tbody tr[data-group-key]'))
                    .filter(function (row) {
                        return row.dataset.groupKey === groupKey;
                    });
            }

            function toggleGroupSelection(groupKey, shouldSelect) {
                const rows = getGroupRows(groupKey);
                rows.forEach(function (row) {
                    const checkbox = row.querySelector('.row-select');
                    if (!checkbox || checkbox.disabled) return;
                    checkbox.checked = shouldSelect;
                });
            }

            function allGroupSelected(groupKey) {
                const rows = getGroupRows(groupKey);
                if (!rows.length) return false;
                return rows.every(function (row) {
                    const checkbox = row.querySelector('.row-select');
                    return checkbox && checkbox.checked;
                });
            }

            function buildItemsPayload(rows) {
                return rows.map(function (row) {
                    return {
                        id: parseInt((row.dataset.id || '0').trim(), 10) || null,
                        form_id: (row.dataset.formId || '').trim(),
                        hc_number: (row.dataset.hcNumber || '').trim(),
                        fecha_examen: (row.dataset.fechaExamen || '').trim(),
                        estado_agenda: (row.dataset.estadoAgenda || '').trim(),
                        tipo_examen: (row.dataset.tipoRaw || row.dataset.examen || '').trim()
                    };
                }).filter(function (item) {
                    return item.form_id && item.hc_number;
                });
            }

            function setButtonLoading(btn, loading, label) {
                if (!btn) return;
                if (!btn.dataset.originalHtml) {
                    btn.dataset.originalHtml = btn.innerHTML;
                }
                if (loading) {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' +
                        (label || 'Generando...');
                } else {
                    btn.disabled = false;
                    btn.innerHTML = btn.dataset.originalHtml;
                }
            }

            function descargarPaquete(items, triggerBtn) {
                if (!items.length) {
                    alert('Selecciona al menos un examen informado.');
                    return;
                }
                let filename = 'paquete.pdf';
                setButtonLoading(triggerBtn, true);
                fetch('/imagenes/informes/012b/paquete/seleccion', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({items: items})
                })
                    .then(function (res) {
                        if (!res.ok) {
                            return res.json().then(function (data) {
                                throw new Error(data && data.error ? data.error : 'No se pudo generar el paquete.');
                            });
                        }
                        const disposition = res.headers.get('Content-Disposition') || '';
                        const match = disposition.match(/filename=\"?([^\";]+)\"?/i);
                        if (match && match[1]) {
                            filename = match[1];
                        }
                        return res.blob();
                    })
                    .then(function (blob) {
                        const url = window.URL.createObjectURL(blob);
                        const link = document.createElement('a');
                        link.href = url;
                        link.download = filename;
                        document.body.appendChild(link);
                        link.click();
                        link.remove();
                        window.URL.revokeObjectURL(url);
                    })
                    .catch(function (err) {
                        alert(err.message || 'No se pudo generar el paquete.');
                    })
                    .finally(function () {
                        setButtonLoading(triggerBtn, false);
                    });
            }

            function applyPatientGrouping() {
                const tbody = document.querySelector('#tablaImagenesRealizadas tbody');
                if (!tbody) return;
                tbody.querySelectorAll('tr.table-group-row').forEach(function (row) {
                    row.remove();
                });

                const rows = getCurrentPageRows();
                if (!rows.length) {
                    return;
                }

                const counts = new Map();
                rows.forEach(function (row) {
                    const group = getPacienteGroup(row);
                    row.dataset.groupKey = group.key;
                    counts.set(group.key, (counts.get(group.key) || 0) + 1);
                });

                let lastKey = null;
                rows.forEach(function (row) {
                    const group = getPacienteGroup(row);
                    if (group.key === lastKey) return;
                    lastKey = group.key;
                    const tr = document.createElement('tr');
                    tr.className = 'table-group-row';
                    const td = document.createElement('td');
                    td.colSpan = row.children.length;
                    const count = counts.get(group.key) || 0;
                    let extra = '<span class="text-muted small ms-2">' + count + ' exámenes en esta página</span>';
                    if (activeTab === 'informados') {
                        extra += '<button type="button" class="btn btn-sm btn-outline-secondary ms-3 btn-seleccionar-grupo" data-group-key="' +
                            escapeHtml(group.key) + '">Seleccionar todo</button>';
                        extra += '<button type="button" class="btn btn-sm btn-outline-primary ms-2 btn-descargar-grupo" data-group-key="' +
                            escapeHtml(group.key) + '">Descargar PDF paciente</button>';
                    }
                    td.innerHTML = '<strong>' + escapeHtml(group.label) + '</strong>' + extra;
                    tr.appendChild(td);
                    row.parentNode.insertBefore(tr, row);
                });

                tbody.querySelectorAll('.btn-seleccionar-grupo').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        const groupKey = btn.getAttribute('data-group-key') || '';
                        const selectAll = !allGroupSelected(groupKey);
                        toggleGroupSelection(groupKey, selectAll);
                        btn.textContent = selectAll ? 'Quitar selección' : 'Seleccionar todo';
                        updateSelectAllState();
                    });
                });

                tbody.querySelectorAll('.btn-descargar-grupo').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        const groupKey = btn.getAttribute('data-group-key') || '';
                        const selectedRows = getSelectedRowsByGroup(groupKey);
                        const items = buildItemsPayload(selectedRows);
                        if (!items.length) {
                            alert('Selecciona los exámenes informados que deseas descargar.');
                            return;
                        }
                        descargarPaquete(items, btn);
                    });
                });
            }

            if (window.jQuery && $.fn.DataTable) {
                const extSearch = $.fn.dataTable.ext.search;
                if (window.__imagenesRealizadasTabFilterFn) {
                    const oldFilterIndex = extSearch.indexOf(window.__imagenesRealizadasTabFilterFn);
                    if (oldFilterIndex !== -1) {
                        extSearch.splice(oldFilterIndex, 1);
                    }
                }

                const tabFilterFn = function (settings, data, dataIndex) {
                    if (!settings || !settings.nTable || settings.nTable.id !== 'tablaImagenesRealizadas') {
                        return true;
                    }
                    const rowRef = settings.aoData && settings.aoData[dataIndex] ? settings.aoData[dataIndex].nTr : null;
                    if (!rowRef || !rowRef.dataset || !rowRef.dataset.id) {
                        return true;
                    }
                    return rowInActiveTab(rowRef);
                };
                extSearch.push(tabFilterFn);
                window.__imagenesRealizadasTabFilterFn = tabFilterFn;

                dataTable = $('#tablaImagenesRealizadas').DataTable({
                    order: [[1, 'desc']],
                    language: {url: '//cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json'},
                    pageLength: 25,
                    autoWidth: false,
                    deferRender: true,
                    processing: true,
                    columnDefs: [
                        {targets: 0, orderable: false, searchable: false, className: 'text-center select-cell'}
                    ],
                    initComplete: function () {
                        $('#tablaImagenesRealizadas').css('width', '100%').removeAttr('style');
                        updateSelectAllState();
                    }
                });
                $('#tablaImagenesRealizadas').on('draw.dt', function () {
                    applyPatientGrouping();
                    refreshTabCounts();
                    updateSelectAllState();
                    runActiveTabBackgroundTasks();
                });
            }

            applyTabFilter('no-informados');
            applyPatientGrouping();

            function ajustarTabla() {
                if (dataTable) {
                    dataTable.columns.adjust();
                }
            }

            document.querySelectorAll('[data-toggle="push-menu"]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    setTimeout(ajustarTabla, 350);
                });
            });

            window.addEventListener('resize', function () {
                setTimeout(ajustarTabla, 150);
            });

            document.getElementById('btnPrintTable')?.addEventListener('click', function () {
                window.print();
            });

            document.querySelectorAll('.btn-print-item').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const row = btn.closest('tr');
                    if (!row) return;
                    if ((row.dataset.informado || '0') !== '1') {
                        alert('El examen todavía no está informado.');
                        return;
                    }
                    const formId = (row.dataset.formId || '').trim();
                    const hcNumber = (row.dataset.hcNumber || '').trim();
                    if (!formId || !hcNumber) return;
                    descargarPaquete([{
                        id: parseInt((row.dataset.id || '0').trim(), 10) || null,
                        form_id: formId,
                        hc_number: hcNumber,
                        fecha_examen: (row.dataset.fechaExamen || '').trim(),
                        estado_agenda: (row.dataset.estadoAgenda || '').trim(),
                        tipo_examen: (row.dataset.tipoRaw || row.dataset.examen || '').trim()
                    }], btn);
                });
            });

            function reconstruirTipoExamen(raw, nuevoTexto) {
                const limpio = (nuevoTexto || '').trim();
                if (!limpio) return raw;
                if (!raw) return limpio;
                const partes = raw.split(' - ').map(function (p) {
                    return p.trim();
                }).filter(Boolean);
                if (partes.length >= 2 && partes[0].toUpperCase() === 'IMAGENES' && /^IMA[-_]/i.test(partes[1])) {
                    return partes[0] + ' - ' + partes[1] + ' - ' + limpio;
                }
                return limpio;
            }

            document.querySelectorAll('.btn-edit').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const row = btn.closest('tr');
                    const id = row ? parseInt(row.getAttribute('data-id') || '0', 10) : 0;
                    if (!id) return;

                    const currentType = btn.getAttribute('data-tipo') || '';
                    const nuevoTipo = window.prompt('Editar tipo de examen:', currentType);
                    if (nuevoTipo === null) return;
                    const rawActual = row ? (row.dataset.tipoRaw || '') : '';
                    const rawFinal = reconstruirTipoExamen(rawActual, nuevoTipo.trim());

                    postJson('/imagenes/examenes-realizados/actualizar', {
                        id: id,
                        tipo_examen: rawFinal
                    }).then(function (res) {
                        if (!res || !res.success) {
                            alert('No se pudo actualizar el examen.');
                            return;
                        }

                        btn.setAttribute('data-tipo', nuevoTipo.trim());
                        if (row) {
                            row.dataset.tipoRaw = rawFinal;
                            row.dataset.examen = nuevoTipo.trim();
                        }
                        const badge = row.querySelector('.tipo-examen-label');
                        if (badge) badge.textContent = nuevoTipo.trim() || 'No definido';
                    }).catch(function () {
                        alert('Error de red al actualizar.');
                    });
                });
            });

            document.querySelectorAll('.btn-delete').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const row = btn.closest('tr');
                    const id = row ? parseInt(row.getAttribute('data-id') || '0', 10) : 0;
                    if (!id) return;

                    if (!window.confirm('¿Eliminar este procedimiento proyectado? Esta acción no se puede deshacer.')) {
                        return;
                    }

                    postJson('/imagenes/examenes-realizados/eliminar', {id: id})
                        .then(function (res) {
                            if (!res || !res.success) {
                                alert('No se pudo eliminar el examen.');
                                return;
                            }
                            row.remove();
                        })
                        .catch(function () {
                            alert('Error de red al eliminar.');
                        });
                });
            });
        });
    })();
</script>

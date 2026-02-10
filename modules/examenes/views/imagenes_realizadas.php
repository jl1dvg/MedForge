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
            <button type="button" class="btn btn-outline-primary btn-sm" id="btnPrintTable">
                <i class="mdi mdi-printer"></i> Imprimir lista
            </button>
        </div>
        <div class="box-body">
            <?php
            $totalInformados = 0;
            $totalNoInformados = 0;
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
                <table id="tablaImagenesRealizadas" class="table table-striped table-hover align-middle">
                    <thead>
                    <tr>
                        <th class="text-center">Sel</th>
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
                            data-afiliacion="<?= htmlspecialchars((string)($row['afiliacion'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-paciente="<?= htmlspecialchars((string)($row['full_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-examen="<?= htmlspecialchars($tipoExamen, ENT_QUOTES, 'UTF-8') ?>"
                            data-tipo-raw="<?= htmlspecialchars($tipoExamenRaw, ENT_QUOTES, 'UTF-8') ?>"
                            data-informado="<?= $informado ? '1' : '0' ?>">
                            <td class="text-center select-cell">
                                <div class="form-check d-inline-flex justify-content-center">
                                    <input type="checkbox" class="form-check-input row-select" <?= $informado ? '' : 'disabled' ?>>
                                </div>
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
    #tablaImagenesRealizadas .row-select {
        appearance: auto;
        -webkit-appearance: checkbox;
        width: 18px;
        height: 18px;
        opacity: 1;
        margin: 0;
        cursor: pointer;
    }
    #tablaImagenesRealizadas .select-cell {
        cursor: pointer;
        vertical-align: middle;
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
            <div class="mb-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <h6 class="mb-0">Imágenes del NAS</h6>
                    <span class="text-muted small" id="informeImagenesStatus"></span>
                </div>
                <div id="informeImagenesContainer" class="row g-2 mt-1"></div>
            </div>
            <div id="informeLoader" class="d-none text-center text-muted small py-2">
                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                Cargando informe...
            </div>
            <div id="informeTemplateContainer"></div>
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

            const rows = Array.from(document.querySelectorAll('#tablaImagenesRealizadas tbody tr'));
            const afiliaciones = rows.map(function (row) {
                return (row.dataset.afiliacion || '').trim();
            });
            const examenes = rows.map(function (row) {
                return (row.dataset.examen || '').trim();
            });
            populateSelect(document.getElementById('filtroAfiliacion'), afiliaciones);
            populateSelect(document.getElementById('filtroTipoExamen'), examenes);

            let activeTab = 'no-informados';

            function applyTabFilter(tab) {
                activeTab = tab;
                const showInformados = tab === 'informados';
                rows.forEach(function (row) {
                    const informado = (row.dataset.informado || '0') === '1';
                    const visible = showInformados ? informado : !informado;
                    row.style.display = visible ? '' : 'none';
                });
                if (dataTable) {
                    dataTable.columns.adjust();
                }
                applyPatientGrouping();
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

            const modalEl = document.getElementById('modalInformeImagen');
            const templateContainer = document.getElementById('informeTemplateContainer');
            const btnGuardarInforme = document.getElementById('btnGuardarInforme');
            const estadoInforme = document.getElementById('informeEstado');
            const imagenesContainer = document.getElementById('informeImagenesContainer');
            const imagenesStatus = document.getElementById('informeImagenesStatus');
            const informeLoader = document.getElementById('informeLoader');
            const modalInstance = window.bootstrap && modalEl ? new bootstrap.Modal(modalEl) : null;
            let informeContext = null;

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

            function renderImagenesNas(files) {
                if (!imagenesContainer) return;
                imagenesContainer.innerHTML = '';
                if (!files || !files.length) {
                    imagenesContainer.innerHTML = '<div class="text-muted small">No se encontraron archivos en el NAS.</div>';
                    return;
                }

                files.forEach(function (file) {
                    const col = document.createElement('div');
                    col.className = 'col-6 col-md-4 col-lg-3';
                    const card = document.createElement('div');
                    card.className = 'border rounded p-2 h-100';
                    const name = file.name || 'Archivo';
                    const ext = (file.ext || '').toLowerCase();
                    const url = file.url || '#';

                    if (['png', 'jpg', 'jpeg'].includes(ext)) {
                        const link = document.createElement('a');
                        link.href = url;
                        link.target = '_blank';
                        link.rel = 'noopener';
                        const img = document.createElement('img');
                        img.src = url;
                        img.alt = name;
                        img.className = 'img-fluid rounded mb-2';
                        img.style.maxHeight = '140px';
                        link.appendChild(img);
                        card.appendChild(link);
                    } else {
                        const icon = document.createElement('div');
                        icon.className = 'd-flex align-items-center gap-2 mb-2';
                        icon.innerHTML = '<i class="mdi mdi-file-pdf text-danger"></i><span class="small">PDF</span>';
                        card.appendChild(icon);
                    }

                    const linkName = document.createElement('a');
                    linkName.href = url;
                    linkName.target = '_blank';
                    linkName.rel = 'noopener';
                    linkName.className = 'small d-block text-truncate';
                    linkName.textContent = name;
                    card.appendChild(linkName);

                    col.appendChild(card);
                    imagenesContainer.appendChild(col);
                });
            }

            function cargarImagenesNas(formId, hcNumber) {
                if (!imagenesContainer) return;
                imagenesContainer.innerHTML = '';
                setImagenesStatus('Cargando imágenes...');
                fetch('/imagenes/examenes-realizados/nas/list?hc_number=' + encodeURIComponent(hcNumber) + '&form_id=' + encodeURIComponent(formId))
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (!res || !res.success) {
                            renderImagenesNas([]);
                            setImagenesStatus(res && res.error ? res.error : 'No se pudieron cargar las imágenes.');
                            return;
                        }
                        renderImagenesNas(res.files || []);
                        setImagenesStatus('');
                    })
                    .catch(function () {
                        renderImagenesNas([]);
                        setImagenesStatus('Error al conectar con el NAS.');
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

            function abrirInformeModal(row) {
                if (!row) return;
                const formId = (row.dataset.formId || '').trim();
                const hcNumber = (row.dataset.hcNumber || '').trim();
                const tipoRaw = (row.dataset.tipoRaw || '').trim();

                if (!formId || !tipoRaw) {
                    alert('Faltan datos del examen para informar.');
                    return;
                }

                if (formId && hcNumber) {
                    cargarImagenesNas(formId, hcNumber);
                }

                setEstado('Cargando plantilla...');
                setInformeLoading(true);
                if (btnGuardarInforme) {
                    btnGuardarInforme.disabled = true;
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
                            payload: res.payload || null
                        };

                        return fetch('/imagenes/informes/plantilla?plantilla=' + encodeURIComponent(res.plantilla));
                    })
                    .then(function (response) {
                        if (!response) return;
                        if (!response.ok) {
                            throw new Error('No se pudo cargar la plantilla');
                        }
                        return response.text();
                    })
                    .then(function (html) {
                        if (!html || !templateContainer) return;
                        templateContainer.innerHTML = html;
                        initChecklist(templateContainer);
                        if (informeContext && informeContext.payload) {
                            applyPayload(templateContainer, informeContext.payload);
                            rebuildChecklistTargets(templateContainer);
                            setEstado('Informe existente cargado.');
                        } else {
                            setEstado('Informe nuevo.');
                        }
                        if (btnGuardarInforme) {
                            btnGuardarInforme.disabled = false;
                        }
                        setInformeLoading(false);
                        if (modalInstance) {
                            modalInstance.show();
                        }
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

            document.querySelectorAll('#tablaImagenesRealizadas tbody .select-cell').forEach(function (cell) {
                cell.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    const checkbox = cell.querySelector('.row-select');
                    if (!checkbox || checkbox.disabled) return;
                    checkbox.checked = !checkbox.checked;
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
                    if (templateContainer) {
                        templateContainer.innerHTML = '';
                    }
                    if (imagenesContainer) {
                        imagenesContainer.innerHTML = '';
                    }
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
                        form_id: (row.dataset.formId || '').trim(),
                        hc_number: (row.dataset.hcNumber || '').trim(),
                        fecha_examen: (row.dataset.fechaExamen || '').trim()
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

                const rows = Array.from(tbody.querySelectorAll('tr[data-id]'))
                    .filter(function (row) {
                        return row.style.display !== 'none';
                    });
                if (!rows.length) return;

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

            let dataTable = null;
            if (window.jQuery && $.fn.DataTable) {
                dataTable = $('#tablaImagenesRealizadas').DataTable({
                    order: [[1, 'desc']],
                    language: {url: '//cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json'},
                    pageLength: 25,
                    autoWidth: false,
                    deferRender: true,
                    processing: true,
                    columnDefs: [
                        {targets: 0, orderable: false, searchable: false}
                    ],
                    initComplete: function () {
                        $('#tablaImagenesRealizadas').css('width', '100%').removeAttr('style');
                    }
                });
                $('#tablaImagenesRealizadas').on('draw.dt', function () {
                    applyPatientGrouping();
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
                    descargarPaquete([{form_id: formId, hc_number: hcNumber}], btn);
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

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
                        <th>Fecha</th>
                        <th>Afiliación</th>
                        <th>Paciente</th>
                        <th>Cédula</th>
                        <th>Imagen</th>
                        <th>Procedimiento</th>
                        <th>Ojo</th>
                        <th style="min-width: 260px;">Acciones</th>
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
                        <tr data-id="<?= (int)($row['id'] ?? 0) ?>"
                            data-form-id="<?= htmlspecialchars((string)($row['form_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-hc-number="<?= htmlspecialchars((string)($row['hc_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-afiliacion="<?= htmlspecialchars((string)($row['afiliacion'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-examen="<?= htmlspecialchars($tipoExamen, ENT_QUOTES, 'UTF-8') ?>"
                            data-tipo-raw="<?= htmlspecialchars($tipoExamenRaw, ENT_QUOTES, 'UTF-8') ?>">
                            <td><?= htmlspecialchars($fechaUi, ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($row['afiliacion'] ?? 'Sin afiliación'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($row['full_name'] ?? 'Sin nombre'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($row['cedula'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php if ($imagenRuta !== ''): ?>
                                    <a href="/public/<?= htmlspecialchars($imagenRuta, ENT_QUOTES, 'UTF-8') ?>"
                                       target="_blank" rel="noopener">
                                        <i class="mdi mdi-file-image text-info"></i>
                                        <?= htmlspecialchars($imagenNombre !== '' ? $imagenNombre : 'Ver imagen', ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">Sin imagen adjunta</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-primary-light tipo-examen-label">
                                    <?= htmlspecialchars($tipoExamen !== '' ? $tipoExamen : 'No definido', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($ojoExamen !== '' ? $ojoExamen : '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="d-flex flex-wrap gap-1">
                                <button type="button" class="btn btn-sm btn-info btn-informe">
                                    <i class="mdi mdi-information-outline"></i> Informar
                                </button>
                                <button type="button" class="btn btn-sm btn-secondary btn-print-item">
                                    <i class="mdi mdi-printer"></i> Imprimir
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<div class="modal fade" id="modalInformeImagen" tabindex="-1" aria-hidden="true" aria-labelledby="modalInformeImagenLabel">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalInformeImagenLabel">Informar examen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" style="min-height: 70vh;">
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

            const modalEl = document.getElementById('modalInformeImagen');
            const templateContainer = document.getElementById('informeTemplateContainer');
            const btnGuardarInforme = document.getElementById('btnGuardarInforme');
            const estadoInforme = document.getElementById('informeEstado');
            const modalInstance = window.bootstrap && modalEl ? new bootstrap.Modal(modalEl) : null;
            let informeContext = null;

            function setEstado(texto) {
                if (estadoInforme) {
                    estadoInforme.textContent = texto || '';
                }
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

                setEstado('Cargando plantilla...');
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
                        if (modalInstance) {
                            modalInstance.show();
                        }
                    })
                    .catch(function () {
                        setEstado('');
                        if (btnGuardarInforme) {
                            btnGuardarInforme.disabled = false;
                        }
                        alert('Error de red al cargar la plantilla.');
                    });
            }

            document.querySelectorAll('.btn-informe').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const row = btn.closest('tr');
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
                        payload: payload
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
                    if (btnGuardarInforme) {
                        btnGuardarInforme.disabled = false;
                    }
                    informeContext = null;
                    setEstado('');
                });
            }

            let dataTable = null;
            if (window.jQuery && $.fn.DataTable) {
                dataTable = $('#tablaImagenesRealizadas').DataTable({
                    order: [[0, 'desc']],
                    language: {url: '//cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json'},
                    pageLength: 25
                });
            }

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
                    const formId = (row.dataset.formId || '').trim();
                    const hcNumber = (row.dataset.hcNumber || '').trim();
                    if (!formId || !hcNumber) return;
                    const url = '/examenes/prefactura?hc_number=' + encodeURIComponent(hcNumber) + '&form_id=' + encodeURIComponent(formId);
                    window.open(url, '_blank', 'noopener');
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

<?php
/** @var string $username */
/** @var array $scripts */
/** @var array<int, array{value:string,label:string}> $afiliacionOptions */
/** @var array<int, array{value:string,label:string}> $afiliacionCategoriaOptions */
/** @var array<int, array{value:string,label:string}> $sedeOptions */
/** @var string $fechaInicioDefault */
/** @var string $fechaFinDefault */
$fechaInicioDefaultValue = htmlspecialchars((string)($fechaInicioDefault ?? ''), ENT_QUOTES, 'UTF-8');
$fechaFinDefaultValue = htmlspecialchars((string)($fechaFinDefault ?? ''), ENT_QUOTES, 'UTF-8');
$cirugiasV2WritesEnabled = isset($forceV2WritesEnabled)
    ? (bool) $forceV2WritesEnabled
    : filter_var(
        $_ENV['CIRUGIAS_V2_WRITES_ENABLED'] ?? getenv('CIRUGIAS_V2_WRITES_ENABLED') ?? '0',
        FILTER_VALIDATE_BOOLEAN
    );
$cirugiasV2ReadsEnabled = isset($forceV2ReadsEnabled)
    ? (bool) $forceV2ReadsEnabled
    : filter_var(
        $_ENV['CIRUGIAS_V2_READS_ENABLED'] ?? getenv('CIRUGIAS_V2_READS_ENABLED') ?? '0',
        FILTER_VALIDATE_BOOLEAN
    );
$cirugiasReadPrefix = $cirugiasV2ReadsEnabled ? '/v2' : '';
$cirugiasWritePrefix = $cirugiasV2WritesEnabled ? '/v2' : '';
$scripts = array_merge($scripts ?? [], [
    'assets/vendor_components/datatable/datatables.min.js',
    'js/pages/cirugias.js',
    'js/modules/cirugias_modal.js',
]);
?>
<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Reporte de Cirugías</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Reporte de Cirugías</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="row">
        <div class="col-12">
            <div class="box">
                <div class="box-body">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <h4 class="box-title mb-0">Cirugías realizadas</h4>
                    </div>
                    <form id="filtrosCirugias" class="row g-2 align-items-end mb-3">
                        <div class="col-sm-6 col-md-3">
                            <label for="filtroFechaInicio" class="form-label">Desde</label>
                            <input type="date"
                                   class="form-control"
                                   id="filtroFechaInicio"
                                   name="fecha_inicio"
                                   value="<?= $fechaInicioDefaultValue ?>"
                                   data-default="<?= $fechaInicioDefaultValue ?>">
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <label for="filtroFechaFin" class="form-label">Hasta</label>
                            <input type="date"
                                   class="form-control"
                                   id="filtroFechaFin"
                                   name="fecha_fin"
                                   value="<?= $fechaFinDefaultValue ?>"
                                   data-default="<?= $fechaFinDefaultValue ?>">
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <label for="filtroAfiliacion" class="form-label">Afiliación</label>
                            <select class="form-select" id="filtroAfiliacion" name="afiliacion">
                                <?php foreach (($afiliacionOptions ?? []) as $option): ?>
                                    <?php $value = (string)($option['value'] ?? ''); ?>
                                    <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string)($option['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <label for="filtroAfiliacionCategoria" class="form-label">Categoría afiliación</label>
                            <select class="form-select" id="filtroAfiliacionCategoria" name="afiliacion_categoria">
                                <?php foreach (($afiliacionCategoriaOptions ?? []) as $option): ?>
                                    <?php $value = (string)($option['value'] ?? ''); ?>
                                    <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string)($option['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <label for="filtroSede" class="form-label">Sede</label>
                            <select class="form-select" id="filtroSede" name="sede">
                                <?php foreach (($sedeOptions ?? []) as $option): ?>
                                    <?php $value = (string)($option['value'] ?? ''); ?>
                                    <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string)($option['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="mdi mdi-filter-variant"></i> Aplicar filtros
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnLimpiarFiltrosCirugias">
                                <i class="mdi mdi-close-circle-outline"></i> Limpiar
                            </button>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table id="surgeryTable" class="table table-striped table-hover">
                            <thead>
                            <tr>
                                <th class="bb-2">No.</th>
                                <th class="bb-2">C.I.</th>
                                <th class="bb-2">Nombre</th>
                                <th class="bb-2">Afiliación</th>
                                <th class="bb-2">Fecha</th>
                                <th class="bb-2">Procedimiento</th>
                                <th class="bb-2" title="Ver protocolo"><i class="mdi mdi-file-document"></i></th>
                                <th class="bb-2" title="Certificado de descanso"><i class="mdi mdi-file-document-box"></i></th>
                                <th class="bb-2" title="Imprimir protocolo"><i class="mdi mdi-printer"></i></th>
                            </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/components/modal_protocolo.php'; ?>

<script>
    window.cirugiasEndpoints = {
        datatable: <?= json_encode($cirugiasReadPrefix . '/cirugias/datatable', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        wizard: <?= json_encode($cirugiasReadPrefix . '/cirugias/wizard', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        protocolo: <?= json_encode($cirugiasReadPrefix . '/cirugias/protocolo', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        printed: <?= json_encode($cirugiasWritePrefix . '/cirugias/protocolo/printed', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        status: <?= json_encode($cirugiasWritePrefix . '/cirugias/protocolo/status', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        guardar: <?= json_encode($cirugiasWritePrefix . '/cirugias/wizard/guardar', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        autosave: <?= json_encode($cirugiasWritePrefix . '/cirugias/wizard/autosave', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        scrapeDerivacion: <?= json_encode($cirugiasWritePrefix . '/cirugias/wizard/scrape-derivacion', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
    };

    function emitirCertificadoDescanso(formId, hcNumber) {
        const value = window.prompt('Ingrese los dias de descanso postquirurgico', '5');
        if (value === null) {
            return;
        }

        const dias = Number.parseInt(value, 10);
        if (!Number.isFinite(dias) || dias <= 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Valor invalido',
                text: 'Debe ingresar un numero entero mayor a cero.',
            });
            return;
        }

        const params = new URLSearchParams({
            form_id: formId,
            hc_number: hcNumber,
            dias_descanso: String(dias),
        });

        window.open(`/v2/reports/cirugias/descanso/pdf?${params.toString()}`, '_blank');
    }

    function togglePrintStatus(form_id, hc_number, button, currentStatus) {
        const endpoints = window.cirugiasEndpoints || {};
        const printedEndpoint = endpoints.printed || '/cirugias/protocolo/printed';
        const isActive = button.classList.contains('active');
        const newStatus = isActive ? 0 : 1;

        if (!isActive) {
            window.open(`/v2/reports/protocolo/pdf?form_id=${form_id}&hc_number=${hc_number}`, '_blank');
        }

        button.classList.toggle('active');
        button.setAttribute('aria-pressed', button.classList.contains('active'));

        fetch(printedEndpoint, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({form_id, hc_number, printed: button.classList.contains('active') ? 1 : 0})
        }).then(response => {
            if (!response.ok) {
                throw new Error('Error al actualizar el estado');
            }
            return response.json();
        }).then(data => {
            if (!data.success) {
                throw new Error('Error al actualizar el estado');
            }
        }).catch(() => {
            Swal.fire('Error', 'No se pudo actualizar el estado de impresión.', 'error');
            button.classList.toggle('active');
            button.setAttribute('aria-pressed', button.classList.contains('active'));
        });
    }

    let currentFormId;
    let currentHcNumber;

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderProtocolAudit(auditoria) {
        const panel = document.getElementById('protocol-audit-panel');
        const summary = document.getElementById('protocol-audit-summary');
        const checksContainer = document.getElementById('protocol-audit-checks');

        if (!panel || !summary || !checksContainer) {
            return;
        }

        const data = auditoria && typeof auditoria === 'object' ? auditoria : {};
        const checks = Array.isArray(data.checks) ? data.checks : [];
        const summaryData = data.summary && typeof data.summary === 'object' ? data.summary : {};
        const status = String(data.status || 'warning');

        const alertClassMap = {
            ok: 'alert-success',
            warning: 'alert-warning',
            error: 'alert-danger'
        };
        const badgeClassMap = {
            ok: 'badge bg-success',
            warning: 'badge bg-warning text-dark',
            error: 'badge bg-danger'
        };
        const statusLabelMap = {
            ok: 'OK',
            warning: 'Advertencia',
            error: 'Alerta'
        };

        panel.style.display = checks.length > 0 ? '' : 'none';
        summary.className = `alert mb-2 ${alertClassMap[status] || 'alert-secondary'}`;
        summary.innerHTML = `
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <strong>Auditoría automática del protocolo</strong>
                    <div class="small">Se validó concordancia con lo proyectado y la plantilla quirúrgica.</div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <span class="${badgeClassMap[status] || 'badge bg-secondary'}">${statusLabelMap[status] || 'Estado'}</span>
                    <span class="badge bg-success">OK: ${Number(summaryData.ok || 0)}</span>
                    <span class="badge bg-warning text-dark">Advertencias: ${Number(summaryData.warning || 0)}</span>
                    <span class="badge bg-danger">Alertas: ${Number(summaryData.error || 0)}</span>
                </div>
            </div>
        `;

        checksContainer.innerHTML = checks.map((check) => {
            const details = check && typeof check.details === 'object' ? check.details : {};
            const missingList = Array.isArray(details.faltantes) ? details.faltantes : [];
            const projected = details.proyectado ? `<div><strong>Proyectado:</strong> ${escapeHtml(details.proyectado)}</div>` : '';
            const registered = details.registrado ? `<div><strong>Registrado:</strong> ${escapeHtml(details.registrado)}</div>` : '';
            const expected = Number.isFinite(Number(details.esperado)) ? `<div><strong>Esperado:</strong> ${escapeHtml(details.esperado)}</div>` : '';
            const actual = Number.isFinite(Number(details.registrado)) ? `<div><strong>Registrado:</strong> ${escapeHtml(details.registrado)}</div>` : registered;
            const missing = missingList.length > 0
                ? `<div><strong>Faltantes:</strong> ${missingList.map((item) => escapeHtml(item)).join(', ')}</div>`
                : '';

            return `
                <div class="border rounded p-2 mb-2">
                    <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                        <div>
                            <div class="fw-600">${escapeHtml(check.title || 'Validación')}</div>
                            <div class="small text-muted">${escapeHtml(check.message || '')}</div>
                        </div>
                        <span class="${badgeClassMap[check.status] || 'badge bg-secondary'}">${statusLabelMap[check.status] || 'Estado'}</span>
                    </div>
                    <div class="small mt-2">
                        ${projected}
                        ${actual}
                        ${expected}
                        ${registered && !actual ? registered : ''}
                        ${missing}
                    </div>
                </div>
            `;
        }).join('');
    }

    function redirectToEditProtocol() {
        const endpoints = window.cirugiasEndpoints || {};
        const wizardEndpoint = endpoints.wizard || '/cirugias/wizard';
        if (!currentFormId || !currentHcNumber) {
            return;
        }
        window.location.href = `${wizardEndpoint}?form_id=${encodeURIComponent(currentFormId)}&hc_number=${encodeURIComponent(currentHcNumber)}`;
    }

    function loadProtocolData(button) {
        const endpoints = window.cirugiasEndpoints || {};
        const protocoloEndpoint = endpoints.protocolo || '/cirugias/protocolo';
        const formId = button.getAttribute('data-form-id');
        const hcNumber = button.getAttribute('data-hc-number');
        currentFormId = formId;
        currentHcNumber = hcNumber;

        fetch(`${protocoloEndpoint}?form_id=${formId}&hc_number=${hcNumber}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    throw new Error(data.error);
                }
                const diagTable = document.getElementById('diagnostico-table');
                diagTable.innerHTML = '';
                (data.diagnosticos || []).forEach(d => {
                    diagTable.innerHTML += `<tr><td>${d.cie10}</td><td>${d.detalle}</td></tr>`;
                });

                const procTable = document.getElementById('procedimientos-table');
                procTable.innerHTML = '';
                (data.procedimientos || []).forEach(p => {
                    procTable.innerHTML += `<tr><td>${p.codigo}</td><td>${p.nombre}</td></tr>`;
                });

                const timingRow = document.getElementById('timing-row');
                timingRow.innerHTML = `
                    <td>${data.fecha_inicio ?? ''}</td>
                    <td>${data.hora_inicio ?? ''}</td>
                    <td>${data.hora_fin ?? ''}</td>
                    <td>${data.duracion ?? ''}</td>
                `;

                const resultTable = document.getElementById('result-table');
                resultTable.innerHTML = '';
                resultTable.innerHTML += `<tr><td>Dieresis</td><td>${data.dieresis ?? ''}</td></tr>`;
                resultTable.innerHTML += `<tr><td>Exposición</td><td>${data.exposicion ?? ''}</td></tr>`;
                resultTable.innerHTML += `<tr><td>Hallazgo</td><td>${data.hallazgo ?? ''}</td></tr>`;
                resultTable.innerHTML += `<tr><td>Operatorio</td><td>${data.operatorio ?? ''}</td></tr>`;

                const staffTable = document.getElementById('staff-table');
                staffTable.innerHTML = '';
                Object.entries(data.staff || {}).forEach(([rol, nombre]) => {
                    if (nombre && nombre.trim() !== '') {
                        staffTable.innerHTML += `<tr><td>${rol}</td><td>${nombre}</td></tr>`;
                    }
                });

                const comment = document.querySelector('.comment-here');
                if (comment) {
                    comment.textContent = data.comentario ?? '';
                }

                renderProtocolAudit(data.auditoria);
            })
            .catch(() => {
                Swal.fire('Error', 'No se pudo cargar el protocolo.', 'error');
            });
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php
$scripts = array_merge($scripts ?? [], [
    'assets/vendor_components/datatable/datatables.min.js',
    'assets/vendor_components/jquery.peity/jquery.peity.js',
]);
?>

<section class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Procedimientos no facturados</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Revisión de pendientes</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <?php include __DIR__ . '/components/no_facturados_table.php'; ?>
</section>

<?php include __DIR__ . '/components/no_facturados_preview_modal.php'; ?>
<script>
    document.addEventListener("DOMContentLoaded", () => {
        const previewModal = document.getElementById("previewModal");
        const previewContent = document.getElementById("previewContent");
        const facturarFormId = document.getElementById("facturarFormId");
        const facturarHcNumber = document.getElementById("facturarHcNumber");
        const resumenContainer = document.getElementById('resumenTotales');
        const filtrosForm = document.getElementById('filtrosNoFacturados');
        const seleccionadosInfo = document.getElementById('seleccionadosInfo');
        const btnFacturarLote = document.getElementById('btnFacturarLote');
        const btnMarcarRevisado = document.getElementById('btnMarcarRevisado');

        const previewEndpoint = <?= json_encode(buildAssetUrl('api/billing/billing_preview.php')); ?>;

        const PREVIEW_CACHE_TTL_MS = 2 * 60 * 1000; // 2 minutos
        const previewCache = new Map();

        const getCacheKey = (formId) => `preview-${formId}`;

        const cachePreview = (formId, payload) => {
            previewCache.set(getCacheKey(formId), {
                payload,
                expiresAt: Date.now() + PREVIEW_CACHE_TTL_MS,
            });
        };

        const getCachedPreview = (formId) => {
            const entry = previewCache.get(getCacheKey(formId));
            if (!entry) return null;
            if (Date.now() > entry.expiresAt) {
                previewCache.delete(getCacheKey(formId));
                return null;
            }
            return entry.payload;
        };

        const invalidatePreview = (formId) => {
            previewCache.delete(getCacheKey(formId));
        };

        const buildPreviewCandidates = (baseHref, formId, hcNumber) => {
            const candidateHrefs = new Set();
            const result = [];

            const registerCandidate = (url) => {
                url.searchParams.set('form_id', formId);
                url.searchParams.set('hc_number', hcNumber);
                const href = url.toString();
                if (!candidateHrefs.has(href)) {
                    candidateHrefs.add(href);
                    result.push(url);
                }
            };

            const baseUrl = new URL(baseHref, window.location.origin);
            const normalizedPath = baseUrl.pathname.replace(/\/+$/, '') || '/';

            registerCandidate(new URL(baseUrl.toString()));

            if (!normalizedPath.startsWith('/public/')) {
                const withPublic = new URL(baseUrl.toString());
                const suffix = normalizedPath.startsWith('/') ? normalizedPath.replace(/^\/+/, '') : normalizedPath;
                withPublic.pathname = `/public/${suffix}`.replace('/public//', '/public/');
                registerCandidate(withPublic);
            } else {
                const withoutPublic = new URL(baseUrl.toString());
                const trimmed = normalizedPath.replace(/^\/public/, '') || '/';
                withoutPublic.pathname = trimmed.startsWith('/') ? trimmed : `/${trimmed}`;
                registerCandidate(withoutPublic);
            }

            return result;
        };

        const formatCurrency = (value) => `$${Number(value || 0).toFixed(2)}`;

        const calculateTotals = (data) => {
            const suma = (arr, mapper) => (arr || []).reduce((acc, item) => acc + mapper(item), 0);

            const totals = {
                procedimientos: suma(data.procedimientos, (p) => Number(p.procPrecio) || 0),
                insumos: suma(data.insumos, (i) => (Number(i.precio) || 0) * (Number(i.cantidad) || 0)),
                oxigeno: suma(data.oxigeno, (o) => Number(o.precio) || 0),
                anestesia: suma(data.anestesia, (a) => Number(a.precio) || 0),
                derechos: suma(data.derechos, (d) => (Number(d.precioAfiliacion) || 0) * (Number(d.cantidad) || 0)),
            };

            return {
                ...totals,
                global:
                    totals.procedimientos +
                    totals.insumos +
                    totals.oxigeno +
                    totals.anestesia +
                    totals.derechos,
            };
        };

        const renderRules = (reglas = []) => {
            if (!reglas.length) {
                return "";
            }

            const items = reglas
                .map(
                    (regla) => `
                <li class="list-group-item d-flex align-items-start gap-3">
                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="mdi mdi-scale-balance"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">${regla.titulo || "Regla aplicada"}</div>
                        ${regla.detalle ? `<small>${regla.detalle}</small>` : ""}
                    </div>
                </li>
            `
                )
                .join("");

            return `
                <div class="card mb-3 preview-rules">
                    <div class="card-header bg-light fw-semibold">Reglas y tarifas aplicadas</div>
                    <ul class="list-group list-group-flush mb-0">${items}</ul>
                </div>
            `;
        };

        const renderTableSection = (rows, columns, buildRow, emptyMessage) => {
            if (!rows || !rows.length) {
                return `<p class="text-muted mb-0">${emptyMessage}</p>`;
            }

            const header = columns.map((c) => `<th>${c}</th>`).join("");
            const body = rows.map(buildRow).join("");

            return `
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>${header}</tr>
                        </thead>
                        <tbody>${body}</tbody>
                    </table>
                </div>
            `;
        };

        const renderAccordionItem = (id, title, content, open = false) => `
            <div class="accordion-item">
                <h2 class="accordion-header" id="heading-${id}">
                    <button class="accordion-button ${open ? "" : "collapsed"}" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-${id}" aria-expanded="${open}" aria-controls="collapse-${id}">
                        ${title}
                    </button>
                </h2>
                <div id="collapse-${id}" class="accordion-collapse collapse ${open ? "show" : ""}" aria-labelledby="heading-${id}" data-bs-parent="#previewAccordion">
                    <div class="accordion-body">${content}</div>
                </div>
            </div>
        `;

        const renderPreview = (data) => {
            const totals = calculateTotals(data);

            const procedimientosSection = renderAccordionItem(
                "procedimientos",
                "Procedimientos",
                renderTableSection(
                    data.procedimientos,
                    ["Código", "Detalle", "Precio"],
                    (p) => `
                        <tr>
                            <td>${p.procCodigo}</td>
                            <td>${p.procDetalle}</td>
                            <td class="text-end">${formatCurrency(p.procPrecio)}</td>
                        </tr>
                    `,
                    "Sin procedimientos registrados"
                ),
                true
            );

            const insumosContent = [
                renderTableSection(
                    data.insumos,
                    ["Código", "Detalle", "Cantidad", "Precio", "Subtotal"],
                    (i) => {
                        const unit = Number(i.precio) || 0;
                        const qty = Number(i.cantidad) || 0;
                        return `
                            <tr>
                                <td>${i.codigo}</td>
                                <td>${i.nombre}</td>
                                <td class="text-center">${qty}</td>
                                <td class="text-end">${formatCurrency(unit)}</td>
                                <td class="text-end">${formatCurrency(unit * qty)}</td>
                            </tr>
                        `;
                    },
                    "Sin insumos registrados"
                ),
            ];

            if (data.derechos?.length) {
                insumosContent.push(
                    `<div class="mt-3">
                        <h6 class="mb-2">Derechos</h6>
                        ${renderTableSection(
                            data.derechos,
                            ["Código", "Detalle", "Cantidad", "Precio", "Subtotal"],
                            (d) => {
                                const unit = Number(d.precioAfiliacion) || 0;
                                const qty = Number(d.cantidad) || 0;
                                return `
                                    <tr>
                                        <td>${d.codigo}</td>
                                        <td>${d.detalle}</td>
                                        <td class="text-center">${qty}</td>
                                        <td class="text-end">${formatCurrency(unit)}</td>
                                        <td class="text-end">${formatCurrency(unit * qty)}</td>
                                    </tr>
                                `;
                            },
                            "Sin derechos registrados"
                        )}
                    </div>`
                );
            }

            const insumosSection = renderAccordionItem(
                "insumos",
                "Insumos",
                insumosContent.join(""),
                false
            );

            const oxigenoSection = renderAccordionItem(
                "oxigeno",
                "Oxígeno",
                data.oxigeno?.length
                    ? data.oxigeno
                          .map(
                              (o) => `
                            <div class="alert alert-warning d-flex align-items-center mb-2" role="alert">
                                <div>
                                    <div class="fw-semibold">${o.codigo} - ${o.nombre}</div>
                                    <div class="small text-muted">Tiempo: ${o.tiempo} h · Litros: ${o.litros} L/min</div>
                                </div>
                                <span class="ms-auto badge bg-primary">${formatCurrency(o.precio)}</span>
                            </div>`
                          )
                          .join("")
                    : '<p class="text-muted mb-0">Sin consumos de oxígeno</p>',
                false
            );

            const anestesiaSection = renderAccordionItem(
                "anestesia",
                "Anestesia",
                renderTableSection(
                    data.anestesia,
                    ["Código", "Detalle", "Tiempo", "Precio"],
                    (a) => `
                        <tr>
                            <td>${a.codigo}</td>
                            <td>${a.nombre}</td>
                            <td class="text-center">${a.tiempo}</td>
                            <td class="text-end">${formatCurrency(a.precio)}</td>
                        </tr>
                    `,
                    "Sin registros de anestesia"
                ),
                false
            );

            const accordion = `
                <div class="accordion preview-accordion" id="previewAccordion">
                    ${procedimientosSection}
                    ${insumosSection}
                    ${oxigenoSection}
                    ${anestesiaSection}
                </div>
            `;

            const totalsBar = `
                <div class="preview-totals-bar card shadow-sm p-3 mt-4">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div>
                            <div class="text-muted small">Valor estimado</div>
                            <div class="h5 mb-0">${formatCurrency(totals.global)}</div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge rounded-pill bg-primary-subtle text-primary">Procedimientos: ${formatCurrency(totals.procedimientos)}</span>
                            <span class="badge rounded-pill bg-info-subtle text-info">Insumos: ${formatCurrency(totals.insumos)}</span>
                            <span class="badge rounded-pill bg-warning-subtle text-dark">Oxígeno: ${formatCurrency(totals.oxigeno)}</span>
                            <span class="badge rounded-pill bg-success-subtle text-success">Anestesia: ${formatCurrency(totals.anestesia)}</span>
                            <span class="badge rounded-pill bg-secondary-subtle text-secondary">Derechos: ${formatCurrency(totals.derechos)}</span>
                        </div>
                    </div>
                </div>
            `;

            previewContent.innerHTML = `
                <div class="preview-tabs">
                    <ul class="nav nav-pills mb-3" id="previewTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tab-resumen" data-bs-toggle="tab" data-bs-target="#panel-resumen" type="button" role="tab" aria-controls="panel-resumen" aria-selected="true">Resumen</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-detalle" data-bs-toggle="tab" data-bs-target="#panel-detalle" type="button" role="tab" aria-controls="panel-detalle" aria-selected="false">Detalle</button>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="panel-resumen" role="tabpanel" aria-labelledby="tab-resumen">
                            ${renderRules(data.reglas)}
                            ${totalsBar}
                        </div>
                        <div class="tab-pane fade" id="panel-detalle" role="tabpanel" aria-labelledby="tab-detalle">
                            ${accordion}
                            ${totalsBar}
                        </div>
                    </div>
                </div>
            `;
        };

        const fetchPreview = async (candidates) => {
            let lastError = null;
            for (const candidate of candidates) {
                try {
                    const response = await fetch(candidate.toString());
                    if (response.ok) {
                        return response;
                    }

                    lastError = new Error(`Respuesta inesperada ${response.status}`);
                } catch (error) {
                    lastError = error;
                }
            }

            throw lastError ?? new Error('No fue posible contactar el servicio de preview.');
        };

        if (previewModal) {
            previewModal.addEventListener("show.bs.modal", async (event) => {
                const button = event.relatedTarget;
                const formId = button?.getAttribute("data-form-id");
                const hcNumber = button?.getAttribute("data-hc-number");

                if (!formId || !hcNumber) {
                    previewContent.innerHTML = "<p class='text-danger'>❌ Datos incompletos para generar el preview.</p>";
                    return;
                }

                facturarFormId.value = formId;
                facturarHcNumber.value = hcNumber;
                previewContent.innerHTML = "<p class='text-muted'>🔄 Cargando datos...</p>";

                try {
                    const cached = getCachedPreview(formId);
                    if (cached) {
                        renderPreview(cached);
                        return;
                    }

                    const candidateUrls = buildPreviewCandidates(previewEndpoint, formId, hcNumber);
                    const res = await fetchPreview(candidateUrls);

                    const data = await res.json();
                    if (!data.success) {
                        const message = data.message ? String(data.message) : 'No fue posible generar el preview.';
                        previewContent.innerHTML = `<p class='text-danger'>❌ ${message}</p>`;
                        return;
                    }

                    cachePreview(formId, data);
                    renderPreview(data);
                } catch (error) {
                    previewContent.innerHTML = `<p class='text-danger'>❌ ${error?.message || error || 'No fue posible cargar el preview.'}</p>`;
                }
            });
        }

        document.getElementById('facturarForm')?.addEventListener('submit', () => {
            const formId = facturarFormId.value;
            if (formId) {
                invalidatePreview(formId);
            }
        });

        const selectionState = {
            revisados: new Set(),
            pendientes: new Set(),
            noQuirurgicos: new Set(),
        };

        const tableConfigs = {
            revisados: {
                tableId: 'tablaRevisados',
                selectAllId: 'selectAllRevisados',
                baseFilters: { estado_revision: 1, tipo: 'quirurgico' },
            },
            pendientes: {
                tableId: 'tablaPendientes',
                selectAllId: 'selectAllPendientes',
                baseFilters: { estado_revision: 0, tipo: 'quirurgico' },
            },
            noQuirurgicos: {
                tableId: 'tablaNoQuirurgicos',
                selectAllId: 'selectAllNoQuirurgicos',
                baseFilters: { tipo: 'no_quirurgico' },
            },
        };

        const formatFecha = (data) => {
            if (!data) return '';
            const date = new Date(data);
            if (Number.isNaN(date.getTime())) return data;
            return date.toLocaleDateString('es-EC');
        };

        const renderBadge = (label, type = 'secondary') => `<span class="badge bg-${type}">${label}</span>`;

        const getActiveTableKey = () => document.querySelector('#noFacturadosTabs .nav-link.active')?.id?.replace('tab', '')?.replace(/^./, (c) => c.toLowerCase()) || 'revisados';

        const updateSelectionInfo = () => {
            const key = getActiveTableKey();
            const total = selectionState[key]?.size ?? 0;
            if (seleccionadosInfo) {
                seleccionadosInfo.textContent = `${total} seleccionados`;
            }
            btnFacturarLote.disabled = total === 0;
            btnMarcarRevisado.disabled = total === 0;
        };

        const renderCheckbox = (row, tableKey) => {
            const formId = row.form_id ? String(row.form_id) : '';
            const checked = selectionState[tableKey].has(formId) ? 'checked' : '';
            return `<input type="checkbox" class="form-check-input row-select" data-table-key="${tableKey}" value="${formId}" aria-label="Seleccionar fila" ${checked}>`;
        };

        const renderPaciente = (row) => {
            const nombre = row.paciente || '';
            const hc = row.hc_number || '';
            return `<div class="fw-semibold">${nombre}</div><div class="text-muted small">HC ${hc}</div>`;
        };

        const buildColumns = (tableKey) => ([
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: (_, __, row) => renderCheckbox(row, tableKey),
            },
            { data: 'form_id' },
            { data: 'hc_number' },
            {
                data: null,
                defaultContent: '',
                render: (_, __, row) => renderPaciente(row),
            },
            {
                data: 'afiliacion',
                defaultContent: '',
                render: (data) => data ? `<span class="badge bg-info text-dark">${data}</span>` : '<span class="text-muted">Sin afiliación</span>',
            },
            {
                data: 'fecha',
                render: (data) => formatFecha(data),
            },
            {
                data: 'tipo',
                render: (data) => data === 'quirurgico'
                    ? renderBadge('Quirúrgico', 'success')
                    : renderBadge('No quirúrgico', 'primary'),
            },
            {
                data: 'estado_revision',
                render: (data, type, row) => {
                    if (row.tipo !== 'quirurgico') {
                        return renderBadge('N/A', 'secondary');
                    }
                    const estado = Number(data) === 1;
                    return estado
                        ? renderBadge('Revisado', 'success')
                        : renderBadge('Pendiente', 'warning text-dark');
                }
            },
            { data: 'procedimiento', defaultContent: '' },
            {
                data: 'valor_estimado',
                className: 'text-end',
                render: (data) => `$${Number(data || 0).toFixed(2)}`,
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function (data, type, row) {
                    const formId = row.form_id ? String(row.form_id) : '';
                    const hcNumber = row.hc_number ? String(row.hc_number) : '';
                    const previewBtn = `<button class="btn btn-sm btn-info me-1" data-form-id="${formId}" data-hc-number="${hcNumber}" data-bs-toggle="modal" data-bs-target="#previewModal"><i class="mdi mdi-eye"></i> Preview</button>`;
                    const facturarBtn = `<a class="btn btn-sm btn-secondary" href="/billing/facturar.php?form_id=${encodeURIComponent(formId)}">Facturar</a>`;
                    return previewBtn + facturarBtn;
                }
            }
        ]);

        const createTable = (tableKey) => {
            const config = tableConfigs[tableKey];
            const table = $(`#${config.tableId}`).DataTable({
                serverSide: true,
                processing: true,
                searching: false,
                ajax: {
                    url: '/api/billing/no-facturados',
                    data: (d) => {
                        const formData = new FormData(filtrosForm);
                        const filters = {};
                        formData.forEach((value, key) => {
                            filters[key] = value;
                        });
                        return { ...d, ...filters, ...config.baseFilters };
                    },
                },
                order: [[5, 'desc']],
                columns: buildColumns(tableKey),
            });

            table.on('draw', () => {
                updateSelectionInfo();
                const selectAll = document.getElementById(config.selectAllId);
                if (selectAll) {
                    const allSelected = table.rows().data().toArray().every((row) => selectionState[tableKey].has(String(row.form_id)));
                    const anySelected = table.rows().data().toArray().some((row) => selectionState[tableKey].has(String(row.form_id)));
                    selectAll.checked = allSelected && table.rows().count() > 0;
                    selectAll.indeterminate = !selectAll.checked && anySelected;
                }
            });

            table.on('xhr', function () {
                if (!resumenContainer || getActiveTableKey() !== tableKey) return;
                const json = table.ajax.json();
                const summary = json?.summary || {};
                const formatMonto = (valor) => `$${Number(valor || 0).toFixed(2)}`;

                resumenContainer.querySelector('[data-resumen="total-cantidad"]').textContent = summary.total ?? 0;
                resumenContainer.querySelector('[data-resumen="total-monto"]').textContent = formatMonto(summary.monto);
                resumenContainer.querySelector('[data-resumen="quirurgicos-cantidad"]').textContent = summary.quirurgicos?.cantidad ?? 0;
                resumenContainer.querySelector('[data-resumen="quirurgicos-monto"]').textContent = formatMonto(summary.quirurgicos?.monto);
                resumenContainer.querySelector('[data-resumen="no-quirurgicos-cantidad"]').textContent = summary.no_quirurgicos?.cantidad ?? 0;
                resumenContainer.querySelector('[data-resumen="no-quirurgicos-monto"]').textContent = formatMonto(summary.no_quirurgicos?.monto);
            });

            $(`#${config.tableId} tbody`).on('change', '.row-select', function () {
                const formId = this.value;
                if (this.checked) {
                    selectionState[tableKey].add(formId);
                } else {
                    selectionState[tableKey].delete(formId);
                }
                updateSelectionInfo();
                const selectAll = document.getElementById(config.selectAllId);
                if (selectAll) {
                    const totalRows = table.rows().data().toArray().length;
                    const checkedRows = table.rows().data().toArray().filter((row) => selectionState[tableKey].has(String(row.form_id))).length;
                    selectAll.indeterminate = checkedRows > 0 && checkedRows < totalRows;
                    selectAll.checked = checkedRows > 0 && checkedRows === totalRows;
                }
            });

            document.getElementById(config.selectAllId)?.addEventListener('change', (event) => {
                const checked = event.target.checked;
                table.rows().every(function () {
                    const data = this.data();
                    const formId = data.form_id ? String(data.form_id) : '';
                    if (checked) {
                        selectionState[tableKey].add(formId);
                    } else {
                        selectionState[tableKey].delete(formId);
                    }
                });
                table.rows().nodes().to$().find('.row-select').prop('checked', checked);
                updateSelectionInfo();
            });

            return table;
        };

        const tablas = {
            revisados: createTable('revisados'),
            pendientes: createTable('pendientes'),
            noQuirurgicos: createTable('noQuirurgicos'),
        };

        filtrosForm?.addEventListener('submit', (event) => {
            event.preventDefault();
            Object.values(tablas).forEach((tabla) => tabla.ajax.reload());
        });

        document.getElementById('btnLimpiarFiltros')?.addEventListener('click', () => {
            filtrosForm.reset();
            Object.values(tablas).forEach((tabla) => tabla.ajax.reload());
        });

        document.querySelectorAll('#noFacturadosTabs .nav-link').forEach((tab) => {
            tab.addEventListener('shown.bs.tab', () => updateSelectionInfo());
        });

        const getSelectedRows = (tableKey) => {
            const table = tablas[tableKey];
            const selectedIds = selectionState[tableKey];
            const rows = table.rows().data().toArray().filter((row) => selectedIds.has(String(row.form_id)));
            return { ids: Array.from(selectedIds), rows };
        };

        const handleBulkAction = (action) => {
            const key = getActiveTableKey();
            const { ids } = getSelectedRows(key);
            if (!ids.length) {
                alert('Selecciona al menos un registro para continuar.');
                return;
            }
            ids.forEach(invalidatePreview);
            const mensaje = action === 'facturar'
                ? 'Facturar en lote'
                : 'Marcar como revisado';
            alert(`${mensaje}: ${ids.join(', ')}`);
        };

        btnFacturarLote?.addEventListener('click', () => handleBulkAction('facturar'));
        btnMarcarRevisado?.addEventListener('click', () => handleBulkAction('revisar'));

        updateSelectionInfo();
    });
</script>

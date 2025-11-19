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
                    const candidateUrls = buildPreviewCandidates(previewEndpoint, formId, hcNumber);
                    const res = await fetchPreview(candidateUrls);

                    const data = await res.json();
                    if (!data.success) {
                        const message = data.message ? String(data.message) : 'No fue posible generar el preview.';
                        previewContent.innerHTML = `<p class='text-danger'>❌ ${message}</p>`;
                        return;
                    }
                    let total = 0;
                    let html = "";

                    const renderTable = (title, rows, columns, computeRow) => {
                        if (!rows || !rows.length) {
                            return;
                        }

                        html += `
                            <div class="mb-3">
                                <h6>${title}</h6>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                ${columns.map(c => `<th>${c}</th>`).join('')}
                                            </tr>
                                        </thead>
                                        <tbody>
                        `;

                        rows.forEach((row) => {
                            const { markup, subtotal } = computeRow(row);
                            total += subtotal;
                            html += markup;
                        });

                        html += `
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        `;
                    };

                    renderTable(
                        'Procedimientos',
                        data.procedimientos,
                        ['Código', 'Detalle', 'Precio'],
                        (p) => {
                            const subtotal = Number(p.procPrecio) || 0;
                            const markup = `
                                <tr>
                                    <td>${p.procCodigo}</td>
                                    <td>${p.procDetalle}</td>
                                    <td class="text-end">$${subtotal.toFixed(2)}</td>
                                </tr>
                            `;
                            return { markup, subtotal };
                        }
                    );

                    if (data.insumos?.length) {
                        html += `
                            <div class="card mb-3">
                                <div class="card-header bg-info text-white py-2 px-3">Insumos</div>
                                <ul class="list-group list-group-flush">
                        `;

                        data.insumos.forEach((i) => {
                            const precioUnitario = Number(i.precio) || 0;
                            const cantidad = Number(i.cantidad) || 0;
                            const subtotal = precioUnitario * cantidad;
                            total += subtotal;

                            html += `
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="fw-bold">${i.codigo}</span> - ${i.nombre}
                                        <br><small class="text-muted">x${cantidad} @ $${precioUnitario.toFixed(2)}</small>
                                    </div>
                                    <span class="badge bg-success rounded-pill">$${subtotal.toFixed(2)}</span>
                                </li>
                            `;
                        });

                        html += `
                                </ul>
                            </div>
                        `;
                    }

                    if (data.oxigeno?.length) {
                        data.oxigeno.forEach((o) => {
                            const subtotal = Number(o.precio) || 0;
                            total += subtotal;
                            html += `
                                <div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
                                    <div>
                                        <strong>Oxígeno:</strong> ${o.codigo} - ${o.nombre}<br>
                                        <span class="me-3">Tiempo: <span class="badge bg-info">${o.tiempo} h</span></span>
                                        <span class="me-3">Litros: <span class="badge bg-info">${o.litros} L/min</span></span>
                                        <span class="me-3">Precio: <span class="badge bg-primary">$${subtotal.toFixed(2)}</span></span>
                                    </div>
                                </div>
                            `;
                        });
                    }

                    renderTable(
                        'Anestesia',
                        data.anestesia,
                        ['Código', 'Nombre', 'Tiempo', 'Precio'],
                        (a) => {
                            const subtotal = Number(a.precio) || 0;
                            const markup = `
                                <tr>
                                    <td>${a.codigo}</td>
                                    <td>${a.nombre}</td>
                                    <td>${a.tiempo}</td>
                                    <td class="text-end">$${subtotal.toFixed(2)}</td>
                                </tr>
                            `;
                            return { markup, subtotal };
                        }
                    );

                    if (data.derechos?.length) {
                        html += `
                            <div class="card mb-3">
                                <div class="card-header bg-secondary text-white py-2 px-3">Derechos</div>
                                <ul class="list-group list-group-flush">
                        `;

                        data.derechos.forEach((d) => {
                            const precioUnitario = Number(d.precioAfiliacion) || 0;
                            const cantidad = Number(d.cantidad) || 0;
                            const subtotal = precioUnitario * cantidad;
                            total += subtotal;

                            html += `
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="fw-bold">${d.codigo}</span> - ${d.detalle}
                                        <br><small class="text-muted">x${cantidad} @ $${precioUnitario.toFixed(2)}</small>
                                    </div>
                                    <span class="badge bg-primary rounded-pill">$${subtotal.toFixed(2)}</span>
                                </li>
                            `;
                        });

                        html += `
                                </ul>
                            </div>
                        `;
                    }

                    previewContent.innerHTML = `
                        <div class="alert alert-secondary">Valor estimado: <strong>$${total.toFixed(2)}</strong></div>
                        ${html}
                    `;
                } catch (error) {
                    previewContent.innerHTML = `<p class='text-danger'>❌ ${error?.message || error || 'No fue posible cargar el preview.'}</p>`;
                }
            });
        }

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

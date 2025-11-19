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

        const dt = $("#noFacturadosTable").DataTable({
            serverSide: true,
            processing: true,
            searching: false,
            ajax: {
                url: '/api/billing/no-facturados',
                data: function (d) {
                    const form = document.getElementById('filtrosNoFacturados');
                    const formData = new FormData(form);
                    const filters = {};
                    formData.forEach((value, key) => {
                        filters[key] = value;
                    });
                    return { ...d, ...filters };
                },
            },
            order: [[4, 'desc']],
            columns: [
                { data: 'form_id' },
                { data: 'hc_number' },
                { data: 'paciente', defaultContent: '' },
                { data: 'afiliacion', defaultContent: '' },
                {
                    data: 'fecha',
                    render: function (data) {
                        if (!data) return '';
                        const date = new Date(data);
                        if (Number.isNaN(date.getTime())) return data;
                        return date.toLocaleDateString('es-EC');
                    }
                },
                {
                    data: 'tipo',
                    render: function (data) {
                        return data === 'quirurgico'
                            ? '<span class="badge bg-success">Quirúrgico</span>'
                            : '<span class="badge bg-primary">No quirúrgico</span>';
                    }
                },
                {
                    data: 'estado_revision',
                    render: function (data, type, row) {
                        if (row.tipo !== 'quirurgico') {
                            return '<span class="badge bg-secondary">N/A</span>';
                        }
                        const estado = Number(data) === 1;
                        return estado
                            ? '<span class="badge bg-success">Revisado</span>'
                            : '<span class="badge bg-warning text-dark">Pendiente</span>';
                    }
                },
                { data: 'procedimiento', defaultContent: '' },
                {
                    data: 'valor_estimado',
                    className: 'text-end',
                    render: function (data) {
                        const valor = Number(data) || 0;
                        return `$${valor.toFixed(2)}`;
                    }
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
            ]
        });

        const filtrosForm = document.getElementById('filtrosNoFacturados');
        filtrosForm?.addEventListener('submit', (event) => {
            event.preventDefault();
            dt.ajax.reload();
        });

        document.getElementById('btnLimpiarFiltros')?.addEventListener('click', () => {
            filtrosForm.reset();
            dt.ajax.reload();
        });

        if (resumenContainer) {
            dt.on('xhr', function () {
                const json = dt.ajax.json();
                const summary = json?.summary || {};
                const formatMonto = (valor) => `$${Number(valor || 0).toFixed(2)}`;

                resumenContainer.querySelector('[data-resumen="total-cantidad"]').textContent = summary.total ?? 0;
                resumenContainer.querySelector('[data-resumen="total-monto"]').textContent = formatMonto(summary.monto);
                resumenContainer.querySelector('[data-resumen="quirurgicos-cantidad"]').textContent = summary.quirurgicos?.cantidad ?? 0;
                resumenContainer.querySelector('[data-resumen="quirurgicos-monto"]').textContent = formatMonto(summary.quirurgicos?.monto);
                resumenContainer.querySelector('[data-resumen="no-quirurgicos-cantidad"]').textContent = summary.no_quirurgicos?.cantidad ?? 0;
                resumenContainer.querySelector('[data-resumen="no-quirurgicos-monto"]').textContent = formatMonto(summary.no_quirurgicos?.monto);
            });
        }
    });
</script>

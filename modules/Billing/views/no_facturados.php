<?php
$scripts = array_merge($scripts ?? [], [
    'assets/vendor_components/datatable/datatables.min.js',
    'assets/vendor_components/jquery.peity/jquery.peity.js',
    'https://cdn.jsdelivr.net/npm/sweetalert2@11',
]);
?>
<style>
    /* Rehabilita checkboxes nativos dentro de las tablas (el theme global los oculta esperando un label) */
    .row-select.form-check-input {
        position: static !important;
        left: auto !important;
        opacity: 1 !important;
        margin: 0;
    }

    .table-group-row td {
        background-color: #f8f9fa;
    }

    .table-group-row .form-check-input {
        position: static !important;
        left: auto !important;
        opacity: 1 !important;
        margin: 0;
    }
</style>

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
        const previewMeta = document.getElementById('previewMeta');
        const previewPaciente = document.getElementById('previewPaciente');
        const previewHc = document.getElementById('previewHc');
        const previewProcedimiento = document.getElementById('previewProcedimiento');
        const resumenContainer = document.getElementById('resumenTotales');
        const filtrosForm = document.getElementById('filtrosNoFacturados');
        const seleccionadosInfo = document.getElementById('seleccionadosInfo');
        const btnFacturarLote = document.getElementById('btnFacturarLote');
        const btnMarcarRevisado = document.getElementById('btnMarcarRevisado');
        const vistasSelect = document.getElementById('vistaGuardada');
        const btnGuardarVista = document.getElementById('btnGuardarVista');
        const btnBorrarVista = document.getElementById('btnBorrarVista');
        const afiliacionSelect = document.getElementById('fAfiliacion');
        const toggleImagenesAgrupar = document.getElementById('toggleImagenesAgrupar');

        const previewEndpoint = <?= json_encode(buildAssetUrl('api/billing/billing_preview.php')); ?>;

        const PREVIEW_CACHE_TTL_MS = 2 * 60 * 1000; // 2 minutos
        const previewCache = new Map();
        const STORAGE_VISTAS_KEY = 'billing.no-facturados.vistas';

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

        const renderPreviewPlaceholder = (message = 'Cargando datos...') => {
            previewContent.innerHTML = `
                <div class="d-flex flex-column align-items-center text-muted py-4">
                    <div class="spinner-border text-primary mb-3" role="status" aria-hidden="true"></div>
                    <div>${message}</div>
                </div>
            `;
        };

        const renderPreviewError = (message) => {
            previewContent.innerHTML = `
                <div class="alert alert-danger d-flex align-items-center gap-2 mb-0" role="alert">
                    <i class="mdi mdi-alert-outline fs-4 mb-0"></i>
                    <div>${message}</div>
                </div>
            `;
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

        const loadVistasGuardadas = () => {
            try {
                const raw = localStorage.getItem(STORAGE_VISTAS_KEY);
                const parsed = raw ? JSON.parse(raw) : [];
                return Array.isArray(parsed) ? parsed : [];
            } catch (error) {
                console.warn('No se pudo leer las vistas guardadas', error);
                return [];
            }
        };

        const persistirVistas = (vistas) => {
            localStorage.setItem(STORAGE_VISTAS_KEY, JSON.stringify(vistas));
        };

        const getFiltersFromForm = () => {
            if (!filtrosForm) return {};
            const filters = {};
            const formData = new FormData(filtrosForm);
            for (const [key, value] of formData.entries()) {
                if (filters[key] !== undefined) {
                    filters[key] = Array.isArray(filters[key]) ? [...filters[key], value] : [filters[key], value];
                } else {
                    filters[key] = value;
                }
            }
            return filters;
        };

        const normalizeFilters = (filters = {}) => {
            const normalized = {};
            Object.entries(filters).forEach(([key, value]) => {
                if (Array.isArray(value)) {
                    const items = value.filter((item) => item !== '' && item !== null && item !== undefined);
                    if (items.length) {
                        normalized[key] = items;
                    }
                    return;
                }

                if (value !== '' && value !== null && value !== undefined) {
                    normalized[key] = value;
                }
            });
            return normalized;
        };

        const applyFiltersToForm = (filters = {}) => {
            if (!filtrosForm) return;
            const entries = {...filters};
            Array.from(filtrosForm.elements).forEach((element) => {
                if (!element.name) return;
                const value = entries[element.name];

                if (element.tagName === 'SELECT' && element.multiple) {
                    const selectedValues = Array.isArray(value)
                        ? value.map(String)
                        : (value !== undefined ? [String(value)] : []);
                    Array.from(element.options).forEach((option) => {
                        option.selected = selectedValues.includes(option.value);
                    });
                } else {
                    element.value = value ?? '';
                }
            });
        };

        const cargarAfiliaciones = async () => {
            if (!afiliacionSelect) return;
            afiliacionSelect.innerHTML = '<option value="" disabled>Cargando afiliaciones...</option>';
            try {
                const response = await fetch('/api/billing/afiliaciones');
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const json = await response.json();
                const afiliaciones = Array.isArray(json?.data) ? json.data : [];
                afiliacionSelect.innerHTML = '';
                afiliaciones.forEach((item) => {
                    const option = document.createElement('option');
                    option.value = item;
                    option.textContent = item;
                    afiliacionSelect.appendChild(option);
                });
            } catch (error) {
                console.error('No se pudieron cargar las afiliaciones', error);
                afiliacionSelect.innerHTML = '<option value="" disabled>No se pudieron cargar afiliaciones</option>';
            }
        };

        const renderVistasGuardadas = (selected = '') => {
            if (!vistasSelect) return;
            const vistas = loadVistasGuardadas();
            vistasSelect.innerHTML = '<option value="">Vistas guardadas</option>';
            vistas.forEach((vista) => {
                const option = document.createElement('option');
                option.value = vista.nombre;
                option.textContent = vista.nombre;
                vistasSelect.appendChild(option);
            });
            vistasSelect.value = selected;
            if (btnBorrarVista) {
                btnBorrarVista.disabled = !vistas.length || !selected;
            }
        };

        const guardarVistaActual = () => {
            if (!filtrosForm) return;
            const nombre = prompt('¿Cómo quieres llamar a esta vista?');
            const vistaNombre = (nombre || '').trim();
            if (!vistaNombre) return;

            const vistas = loadVistasGuardadas();
            const filtros = normalizeFilters(getFiltersFromForm());
            const existingIndex = vistas.findIndex((vista) => vista.nombre === vistaNombre);
            if (existingIndex >= 0) {
                vistas[existingIndex] = {nombre: vistaNombre, filtros};
            } else {
                vistas.push({nombre: vistaNombre, filtros});
            }
            persistirVistas(vistas);
            renderVistasGuardadas(vistaNombre);
        };

        const eliminarVistaSeleccionada = () => {
            const selected = vistasSelect?.value || '';
            if (!selected) return;
            const vistas = loadVistasGuardadas().filter((vista) => vista.nombre !== selected);
            persistirVistas(vistas);
            renderVistasGuardadas('');
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
                    <table class="table table-lg invoice-archive">
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

            const insumosSection = renderAccordionItem(
                "insumos",
                "Insumos",
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
                false
            );

            const derechosSection = renderAccordionItem(
                "derechos",
                "Derechos",
                renderTableSection(
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
                ),
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
                    ${derechosSection}
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
                const paciente = button?.getAttribute("data-paciente") || '';
                const procedimiento = button?.getAttribute("data-procedimiento") || '';

                if (!formId || !hcNumber) {
                    renderPreviewError('Datos incompletos para generar el preview.');
                    return;
                }

                setPreviewMeta({paciente, hcNumber, procedimiento});
                facturarFormId.value = formId;
                facturarHcNumber.value = hcNumber;
                renderPreviewPlaceholder('Cargando datos de facturación...');

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
                        renderPreviewError(message);
                        return;
                    }

                    cachePreview(formId, data);
                    renderPreview(data);
                } catch (error) {
                    renderPreviewError(error?.message || error || 'No fue posible cargar el preview.');
                }
            });
        }

        const facturarForm = document.getElementById('facturarForm');
        facturarForm?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const formId = facturarFormId.value;
            if (formId) {
                invalidatePreview(formId);
            }

            const formData = new FormData(facturarForm);
            const action = facturarForm.getAttribute('action') || facturarForm.action || window.location.href;
            const method = (facturarForm.getAttribute('method') || facturarForm.method || 'POST').toUpperCase();

            try {
                if (window.Swal) {
                    Swal.fire({
                        title: 'Procesando facturación…',
                        text: 'Por favor espera un momento',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading(),
                    });
                }

                const response = await fetch(action, {
                    method,
                    body: formData,
                });

                let data = null;
                const contentType = response.headers.get('Content-Type') || '';
                if (contentType.includes('application/json')) {
                    data = await response.json();
                }

                const success = data ? !!data.success : response.ok;
                const message = data?.message || (success
                    ? 'La facturación se completó correctamente.'
                    : 'Ocurrió un problema al facturar.');

                if (window.Swal) {
                    Swal.fire({
                        icon: success ? 'success' : 'error',
                        title: success ? 'Facturación completada' : 'Error en la facturación',
                        text: message,
                    });
                } else {
                    alert(message);
                }

                if (success) {
                    if (previewModal) {
                        const modalInstance = bootstrap.Modal.getInstance(previewModal) || new bootstrap.Modal(previewModal);
                        modalInstance.hide();
                    }
                    recargarTablas();
                }
            } catch (error) {
                console.error(error);
                if (window.Swal) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error en la facturación',
                        text: error?.message || 'No fue posible completar la facturación.',
                    });
                } else {
                    alert('No fue posible completar la facturación.');
                }
            }
        });

        const selectionState = {
            revisados: new Set(),
            pendientes: new Set(),
            noQuirurgicos: new Set(),
            imagenes: new Set(),
            consultas: new Set(),
        };

        const groupingState = {
            imagenes: false,
        };

        const tableConfigs = {
            revisados: {
                tableId: 'tablaRevisados',
                selectAllId: 'selectAllRevisados',
                baseFilters: {estado_revision: 1, tipo: 'quirurgico'},
            },
            pendientes: {
                tableId: 'tablaPendientes',
                selectAllId: 'selectAllPendientes',
                baseFilters: {estado_revision: 0, tipo: 'quirurgico'},
            },
            noQuirurgicos: {
                tableId: 'tablaNoQuirurgicos',
                selectAllId: 'selectAllNoQuirurgicos',
                baseFilters: {tipo: 'no_quirurgico'},
            },
            imagenes: {
                tableId: 'tablaImagenes',
                selectAllId: 'selectAllImagenes',
                baseFilters: {tipo: 'imagen'},
            },
            consultas: {
                tableId: 'tablaConsultas',
                selectAllId: 'selectAllConsultas',
                baseFilters: {tipo: 'consulta'},
            },
        };

        const formatFecha = (data) => {
            if (!data) return '';
            const date = new Date(data);
            if (Number.isNaN(date.getTime())) return data;
            return date.toLocaleDateString('es-EC');
        };

        const showTableError = (tableId, message) => {
            const errorBox = document.querySelector(`[data-table-error="${tableId}"]`);
            if (!errorBox) return;
            const messageBox = errorBox.querySelector('[data-error-message]');
            if (messageBox) {
                messageBox.textContent = message || 'No fue posible cargar la tabla.';
            }
            errorBox.classList.remove('d-none');
        };

        const hideTableError = (tableId) => {
            const errorBox = document.querySelector(`[data-table-error="${tableId}"]`);
            if (errorBox) {
                errorBox.classList.add('d-none');
            }
        };

        let tablas = {};

        const recargarTablas = () => {
            Object.values(tablas).forEach((tabla) => tabla.ajax.reload());
        };

        const recalcularAnchoTablas = (delayMs = 0) => {
            if (!Object.keys(tablas).length) return;
            window.setTimeout(() => {
                Object.values(tablas).forEach((tabla) => tabla?.columns?.adjust?.());
            }, delayMs);
        };

        const renderBadge = (label, type = 'secondary') => `<span class="badge badge-pill badge-${type}">${label}</span>`;

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

        const escapeAttr = (value) => String(value ?? '').replace(/"/g, '&quot;');
        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');

        const getPacienteGroup = (row) => {
            const hc = row?.hc_number ? String(row.hc_number).trim() : '';
            const paciente = row?.paciente ? String(row.paciente).trim() : '';
            if (hc) {
                return {
                    key: `HC:${hc}`,
                    label: paciente ? `${paciente} · HC ${hc}` : `HC ${hc}`,
                };
            }
            if (paciente) {
                return {
                    key: `PAC:${paciente}`,
                    label: paciente,
                };
            }
            return {
                key: 'SIN',
                label: 'Paciente sin identificar',
            };
        };

        const buildGroupStats = (table) => {
            const groups = new Map();
            table.rows({page: 'current'}).every(function () {
                const row = this.data();
                const group = getPacienteGroup(row);
                if (!groups.has(group.key)) {
                    groups.set(group.key, {label: group.label, rows: []});
                }
                groups.get(group.key).rows.push(row);
            });
            return groups;
        };

        const updateGroupCheckboxes = (tableKey) => {
            if (tableKey !== 'imagenes' || !groupingState.imagenes) return;
            const table = tablas[tableKey];
            if (!table) return;
            const groups = buildGroupStats(table);
            const tbody = $(`#${tableConfigs[tableKey].tableId} tbody`);
            tbody.find('.group-select').each(function () {
                const groupKey = this.dataset.groupKey;
                const group = groups.get(groupKey);
                if (!group) return;
                const selectedCount = group.rows.filter((row) => selectionState[tableKey].has(String(row.form_id))).length;
                this.checked = group.rows.length > 0 && selectedCount === group.rows.length;
                this.indeterminate = selectedCount > 0 && selectedCount < group.rows.length;
            });
        };

        const applyPatientGrouping = (table, tableKey) => {
            if (tableKey !== 'imagenes') return;
            const tableId = tableConfigs[tableKey].tableId;
            const tbody = $(`#${tableId} tbody`);
            tbody.find('tr.table-group-row').remove();
            if (!groupingState.imagenes) return;

            const groups = buildGroupStats(table);
            let lastGroupKey = null;
            let groupIndex = 0;
            const columnCount = table.columns().count();

            table.rows({page: 'current'}).every(function () {
                const row = this.data();
                const group = getPacienteGroup(row);
                if (group.key === lastGroupKey) return;
                groupIndex += 1;
                lastGroupKey = group.key;
                const groupInfo = groups.get(group.key);
                if (!groupInfo) return;

                const selectedCount = groupInfo.rows.filter((item) => selectionState[tableKey].has(String(item.form_id))).length;
                const totalCount = groupInfo.rows.length;
                const checked = totalCount > 0 && selectedCount === totalCount ? 'checked' : '';
                const groupId = `group-${tableKey}-${groupIndex}`;

                const groupRow = `
                    <tr class="table-group-row" data-group-key="${escapeAttr(group.key)}">
                        <td colspan="${columnCount}">
                            <div class="d-flex align-items-center gap-2">
                                <input type="checkbox"
                                       class="form-check-input group-select"
                                       data-table-key="${tableKey}"
                                       data-group-key="${escapeAttr(group.key)}"
                                       id="${groupId}"
                                       ${checked}>
                                <label class="mb-0" for="${groupId}">
                                    <strong>${escapeHtml(groupInfo.label)}</strong>
                                    <span class="text-muted small ms-2">${totalCount} imágenes en esta página</span>
                                </label>
                            </div>
                        </td>
                    </tr>
                `;
                $(this.node()).before(groupRow);
            });

            updateGroupCheckboxes(tableKey);
        };

        const getProcedimientoDisplay = (row) => {
            if (!row) return '';
            if (row.tipo === 'imagen') {
                const codigo = row.procedimiento_codigo || '';
                const detalle = row.procedimiento_detalle || row.procedimiento || '';
                if (codigo && detalle) return `${codigo} (${String(detalle).toUpperCase()})`;
            }
            return row.procedimiento || '';
        };

        const formatProcedimiento = (value, row) => {
            if (row?.tipo === 'imagen') {
                return getProcedimientoDisplay(row);
            }
            if (row?.tipo === 'consulta') {
                return getProcedimientoDisplay(row) || value;
            }
            if (!value) return '';
            const text = String(value).toLowerCase();
            return text.charAt(0).toUpperCase() + text.slice(1);
        };

        const setPreviewMeta = ({paciente = '', hcNumber = '', procedimiento = ''} = {}) => {
            if (!previewMeta) return;
            const hasData = paciente || hcNumber || procedimiento;
            previewMeta.classList.toggle('d-none', !hasData);
            if (previewPaciente) previewPaciente.textContent = paciente || 'Paciente no disponible';
            if (previewHc) previewHc.textContent = hcNumber || 'N/D';
            if (previewProcedimiento) previewProcedimiento.textContent = formatProcedimiento(procedimiento || '') || '—';
        };

        const buildColumns = (tableKey) => ([
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: (_, __, row) => renderCheckbox(row, tableKey),
            },
            {data: 'form_id'},
            //{ data: 'hc_number' },
            {
                data: null,
                defaultContent: '',
                render: (_, __, row) => renderPaciente(row),
            },
            {
                data: 'afiliacion',
                defaultContent: '',
                render: (data) => data ? `<span class="badge badge-pill badge-secondary">${data}</span>` : '<span class="text-muted">Sin afiliación</span>',
            },
            {
                data: 'fecha',
                render: (data) => formatFecha(data),
            },
            {
                data: 'tipo',
                render: (data) => {
                    if (data === 'quirurgico') {
                        return renderBadge('Quirúrgico', 'primary');
                    }
                    if (data === 'imagen') {
                        return renderBadge('Imágenes', 'info');
                    }
                    if (data === 'consulta') {
                        return renderBadge('Consulta', 'dark');
                    }
                    return renderBadge('No quirúrgico', 'info');
                },
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
                        : renderBadge('Pendiente', 'warning');
                }
            },
            {
                data: 'procedimiento',
                defaultContent: '',
                render: (data, type, row) => formatProcedimiento(data, row),
            },
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
                    const paciente = row.paciente ? escapeAttr(row.paciente) : '';
                    const procedimiento = escapeAttr(getProcedimientoDisplay(row));

                    const previewLink = `
                        <a href="#"
                           class="list-icons-item me-10"
                           data-form-id="${formId}"
                           data-hc-number="${hcNumber}"
                           data-paciente="${paciente}"
                           data-procedimiento="${procedimiento}"
                           data-bs-toggle="modal"
               data-bs-target="#previewModal">
                <i class="fa fa-eye"
                   data-bs-toggle="tooltip"
                   data-bs-placement="top"
                   title="Preview"></i>
                        </a>
                    `;

                    const facturarLink = `
                        <a href="/billing/facturar.php?form_id=${encodeURIComponent(formId)}"
               class="list-icons-item">
                <i class="fa fa-file-text"
                   data-bs-toggle="tooltip"
                   data-bs-placement="top"
                   title="Facturar"></i>
                        </a>
                    `;

                    return `
                        <div class="text-center">
                            <div class="list-icons d-inline-flex">
                                ${previewLink}
                                ${facturarLink}
                            </div>
                        </div>
                    `;
                }
            }
        ]);

        $.fn.dataTable.ext.errMode = 'none';

        const createTable = (tableKey) => {
            const config = tableConfigs[tableKey];
            const table = $(`#${config.tableId}`).DataTable({
                serverSide: true,
                processing: true,
                searching: false,
                autoWidth: false,
                responsive: true,
                ajax: {
                    url: '/api/billing/no-facturados',
                    data: (d) => {
                        const filters = getFiltersFromForm();
                        return {...d, ...filters, ...config.baseFilters};
                    },
                    error: (xhr) => {
                        const responseMessage = xhr?.responseJSON?.message || xhr?.statusText || 'No fue posible cargar los datos.';
                        showTableError(config.tableId, responseMessage);
                    },
                },
                order: [[2, 'asc'], [4, 'desc']],
                columns: buildColumns(tableKey),
                language: {
                    emptyTable: 'No hay registros para mostrar.',
                    zeroRecords: 'No se encontraron resultados con los filtros aplicados.',
                    loadingRecords: 'Cargando registros...',
                    processing: '<div class="d-flex align-items-center gap-2 text-muted"><div class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div><span>Preparando tabla...</span></div>',
                },
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
                applyPatientGrouping(table, tableKey);
            });

            table.on('xhr', function () {
                hideTableError(config.tableId);
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
                updateGroupCheckboxes(tableKey);
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
                updateGroupCheckboxes(tableKey);
            });

            $(`#${config.tableId} tbody`).on('change', '.group-select', function () {
                if (tableKey !== 'imagenes') return;
                const shouldSelect = this.checked;
                const groupKey = this.dataset.groupKey;
                if (!groupKey) return;
                table.rows({page: 'current'}).every(function () {
                    const row = this.data();
                    const group = getPacienteGroup(row);
                    if (group.key !== groupKey) return;
                    const formId = row.form_id ? String(row.form_id) : '';
                    if (shouldSelect) {
                        selectionState[tableKey].add(formId);
                    } else {
                        selectionState[tableKey].delete(formId);
                    }
                    $(this.node()).find('.row-select').prop('checked', shouldSelect);
                });
                updateSelectionInfo();
                const selectAll = document.getElementById(config.selectAllId);
                if (selectAll) {
                    const totalRows = table.rows().data().toArray().length;
                    const checkedRows = table.rows().data().toArray().filter((row) => selectionState[tableKey].has(String(row.form_id))).length;
                    selectAll.indeterminate = checkedRows > 0 && checkedRows < totalRows;
                    selectAll.checked = checkedRows > 0 && checkedRows === totalRows;
                }
                updateGroupCheckboxes(tableKey);
            });

            table.on('error.dt', (_, __, ___, message) => {
                showTableError(config.tableId, message);
            });

            return table;
        };

        cargarAfiliaciones();

        tablas = {
            revisados: createTable('revisados'),
            pendientes: createTable('pendientes'),
            noQuirurgicos: createTable('noQuirurgicos'),
            imagenes: createTable('imagenes'),
            consultas: createTable('consultas'),
        };

        toggleImagenesAgrupar?.addEventListener('change', (event) => {
            groupingState.imagenes = event.target.checked;
            tablas.imagenes?.draw(false);
        });

        filtrosForm?.addEventListener('submit', (event) => {
            event.preventDefault();
            recargarTablas();
        });

        document.getElementById('btnLimpiarFiltros')?.addEventListener('click', () => {
            filtrosForm.reset();
            recargarTablas();
        });

        document.querySelectorAll('#noFacturadosTabs .nav-link').forEach((tab) => {
            tab.addEventListener('shown.bs.tab', () => {
                updateSelectionInfo();
                recalcularAnchoTablas(50);
            });
        });

        window.addEventListener('resize', () => recalcularAnchoTablas(100));
        $(document).on('expanded.pushMenu collapsed.pushMenu', () => recalcularAnchoTablas(250));

        const aplicarVista = (nombreVista) => {
            if (!nombreVista) return;
            const vista = loadVistasGuardadas().find((item) => item.nombre === nombreVista);
            if (!vista) return;
            applyFiltersToForm(vista.filtros);
            recargarTablas();
        };

        vistasSelect?.addEventListener('change', (event) => {
            const value = event.target.value;
            if (btnBorrarVista) {
                btnBorrarVista.disabled = !value;
            }
            aplicarVista(value);
        });

        btnGuardarVista?.addEventListener('click', () => guardarVistaActual());
        btnBorrarVista?.addEventListener('click', () => eliminarVistaSeleccionada());
        renderVistasGuardadas('');

        const getSelectedRows = (tableKey) => {
            const table = tablas[tableKey];
            const selectedIds = selectionState[tableKey];
            const rows = table.rows().data().toArray().filter((row) => selectedIds.has(String(row.form_id)));
            return {ids: Array.from(selectedIds), rows};
        };

        const facturarLote = async (rows, tableKey) => {
            const items = rows.map((row) => ({
                formId: String(row.form_id ?? ''),
                hcNumber: String(row.hc_number ?? ''),
            })).filter((item) => item.formId && item.hcNumber);

            if (!items.length) {
                alert('No se encontraron datos completos (form_id y HC) para facturar.');
                return;
            }

            if (window.Swal) {
                Swal.fire({
                    title: 'Facturando en lote…',
                    html: 'Procesando seleccionados, por favor espera.',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading(),
                });
            }

            const resultados = [];
            for (const item of items) {
                try {
                    const formData = new FormData();
                    formData.append('form_id', item.formId);
                    formData.append('hc_number', item.hcNumber);

                    const billingWriteEndpoint = document.getElementById('facturarForm')?.getAttribute('action') || '/billing/no-facturados/crear';
                    const response = await fetch(billingWriteEndpoint, {
                        method: 'POST',
                        body: formData,
                    });

                    resultados.push({
                        formId: item.formId,
                        success: response.ok,
                        status: response.status,
                    });
                } catch (error) {
                    resultados.push({
                        formId: item.formId,
                        success: false,
                        status: 'error',
                        message: error?.message || 'Error desconocido',
                    });
                }
            }

            const exitos = resultados.filter((r) => r.success);
            const fallidos = resultados.filter((r) => !r.success);

            selectionState[tableKey].clear();
            updateSelectionInfo();
            recargarTablas();

            if (window.Swal) {
                Swal.fire({
                    icon: fallidos.length ? 'warning' : 'success',
                    title: fallidos.length ? 'Facturación parcial' : 'Facturación completada',
                    html: `
                        <div class="text-start">
                            <div><strong>Éxitos:</strong> ${exitos.map((r) => r.formId).join(', ') || 'Ninguno'}</div>
                            <div><strong>Fallidos:</strong> ${fallidos.map((r) => `${r.formId} (${r.status})`).join(', ') || 'Ninguno'}</div>
                        </div>
                    `,
                });
            } else {
                alert(`Éxitos: ${exitos.map((r) => r.formId).join(', ') || 'Ninguno'}\nFallidos: ${fallidos.map((r) => `${r.formId} (${r.status})`).join(', ') || 'Ninguno'}`);
            }
        };

        const handleBulkAction = (action) => {
            const key = getActiveTableKey();
            const {ids, rows} = getSelectedRows(key);
            if (!ids.length) {
                alert('Selecciona al menos un registro para continuar.');
                return;
            }
            ids.forEach(invalidatePreview);

            if (action === 'facturar') {
                facturarLote(rows, key);
                return;
            }

            const mensaje = 'Marcar como revisado';
            if (window.Swal) {
                Swal.fire({
                    icon: 'info',
                    title: mensaje,
                    text: ids.join(', '),
                });
            } else {
                alert(`${mensaje}: ${ids.join(', ')}`);
            }
        };

        btnFacturarLote?.addEventListener('click', () => handleBulkAction('facturar'));
        btnMarcarRevisado?.addEventListener('click', () => handleBulkAction('revisar'));

        updateSelectionInfo();
    });
</script>

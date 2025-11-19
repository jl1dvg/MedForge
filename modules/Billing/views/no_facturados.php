<?php
$quirurgicos = $quirurgicosRevisados ?? [];
$quirurgicosNoRevisados = $quirurgicosNoRevisados ?? [];
$noQuirurgicos = $noQuirurgicos ?? [];
$scripts = array_merge($scripts ?? [], [
    'assets/vendor_components/datatable/datatables.min.js',
    'assets/vendor_components/jquery.peity/jquery.peity.js',
    'js/pages/data-table.js',
    'js/pages/data-ticket.js',
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
    <div class="row">
        <div class="col-lg-12 col-12">
            <?php include __DIR__ . '/components/no_facturados_table.php'; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/components/no_facturados_preview_modal.php'; ?>
<script>
    document.addEventListener("DOMContentLoaded", () => {
        const previewModal = document.getElementById("previewModal");
        const previewContent = document.getElementById("previewContent");
        const facturarFormId = document.getElementById("facturarFormId");
        const facturarHcNumber = document.getElementById("facturarHcNumber");

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

                    if (html === "") {
                        html = "<p class='text-muted'>No hay información para mostrar.</p>";
                    } else {
                        html += `
                            <div class="d-flex justify-content-end align-items-center mt-3">
                                <span class="fw-bold me-2">Total estimado: </span>
                                <span class="badge bg-primary fs-5">$${total.toFixed(2)}</span>
                            </div>
                        `;
                    }

                    previewContent.innerHTML = html;
                } catch (e) {
                    previewContent.innerHTML = "<p class='text-danger'>❌ Error al cargar preview</p>";
                    console.error(e);
                }
            });
        }
    });
</script>

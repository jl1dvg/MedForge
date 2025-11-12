<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\BillingController;
use Modules\Pacientes\Services\PacienteService;
use Controllers\DashboardController;

$billingController = new BillingController($pdo);
$pacienteService = new PacienteService($pdo);
$dashboardController = new DashboardController($pdo);
// Paso 1: Obtener todas las facturas disponibles
$username = $dashboardController->getAuthenticatedUser();
$clasificados = $billingController->procedimientosNoFacturadosClasificados();
$quirurgicos = $clasificados['quirurgicos_revisados'];
$quirurgicosNoRevisados = $clasificados['quirurgicos_no_revisados'];
$noQuirurgicos = $clasificados['no_quirurgicos'];
?>
    <html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" href="/public/images/favicon.ico">

        <title>Asistente CIVE - Dashboard</title>

        <!-- Vendors Style-->
        <link rel="stylesheet" href="/public/css/vendors_css.css">

        <!-- Style-->
        <link rel="stylesheet" href="/public/css/horizontal-menu.css">
        <link rel="stylesheet" href="/public/css/style.css">
        <link rel="stylesheet" href="/public/css/skin_color.css">
        <style>
            table.table td, table.table th {
                font-size: 0.875rem; /* slightly smaller font */
            }
        </style>
    </head>
    <body class="layout-top-nav light-skin theme-primary fixed">
    <div class="wrapper">

        <?php include __DIR__ . '/../components/header.php'; ?>
        <div class="content-wrapper">
            <div class="container-full">
                <div class="row">
                    <div class="col-lg-12 col-12">
                        <?php include __DIR__ . '/components/table_no_facturados.php'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/../components/footer.php'; ?>

    <script src="/public/js/vendors.min.js"></script> <!-- contiene jQuery -->
    <script src="/public/js/pages/chat-popup.js"></script>
    <script src="/public/assets/icons/feather-icons/feather.min.js"></script>
    <script src="/public/assets/vendor_components/datatable/datatables.min.js"></script>
    <script src="/public/assets/vendor_components/jquery.peity/jquery.peity.js"></script>


    <!-- Doclinic App -->
    <script src="/public/js/jquery.smartmenus.js"></script>
    <script src="/public/js/menus.js"></script>
    <script src="/public/js/template.js"></script>
    <script src="/public/js/pages/data-table.js"></script>
    <script src="/public/js/pages/app-ticket.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const previewModal = document.getElementById("previewModal");
            const previewContent = document.getElementById("previewContent");
            const facturarFormId = document.getElementById("facturarFormId");
            const facturarHcNumber = document.getElementById("facturarHcNumber");

            const rawBaseUrl = <?= json_encode(BASE_URL); ?>;

            const resolveBaseUrl = (raw) => {
                if (typeof raw === 'string' && raw.trim() !== '') {
                    const trimmed = raw.trim();

                    if (/^https?:\/\//i.test(trimmed)) {
                        return trimmed;
                    }

                    const normalizedPath = trimmed.startsWith('/') ? trimmed : `/${trimmed}`;
                    return `${window.location.origin}${normalizedPath}`;
                }

                return window.location.origin;
            };

            const baseUrl = resolveBaseUrl(rawBaseUrl).replace(/\/+$/, '/');

            if (!previewModal) {
                return;
            }

            previewModal.addEventListener("show.bs.modal", async (event) => {
                const button = event.relatedTarget;
                const formId = button.getAttribute("data-form-id");
                const hcNumber = button.getAttribute("data-hc-number");

                facturarFormId.value = formId;
                facturarHcNumber.value = hcNumber;
                previewContent.innerHTML = "<p class='text-muted'>🔄 Cargando datos...</p>";

                try {
                    const previewUrl = new URL('api/billing/billing_preview.php', baseUrl);
                    previewUrl.searchParams.set('form_id', formId);
                    previewUrl.searchParams.set('hc_number', hcNumber);

                    const res = await fetch(previewUrl.toString());
                    const data = await res.json();

                    // 🔍 Debug 1: API completo
                    console.log("📦 PREVIEW JSON COMPLETO:", data);

                    // 🔍 Debug 2: Insumos que vienen del PreviewService
                    console.log("🛠 INSUMOS PROCESADOS EN PREVIEW:", data.insumos);

                    // Bootstrap tables and cards for each section, with total sum
                    let total = 0;
                    let html = "";

                    // Procedimientos table
                    html += `
                  <div class="mb-3">
                    <h6>Procedimientos</h6>
                    <div class="table-responsive">
                      <table class="table table-bordered table-sm align-middle">
                        <thead class="table-light">
                          <tr>
                            <th>Código</th>
                            <th>Detalle</th>
                            <th class="text-end">Precio</th>
                          </tr>
                        </thead>
                        <tbody>
                `;
                    data.procedimientos.forEach(p => {
                        total += Number(p.procPrecio) || 0;
                        html += `
                    <tr>
                      <td>${p.procCodigo}</td>
                      <td>${p.procDetalle}</td>
                      <td class="text-end">$${parseFloat(p.procPrecio).toFixed(2)}</td>
                    </tr>
                  `;
                    });
                    html += `
                        </tbody>
                      </table>
                    </div>
                  </div>
                `;

                    // Insumos card/list-group
                    if (data.insumos.length) {
                        html += `
                    <div class="card mb-3">
                      <div class="card-header bg-info text-white py-2 px-3">
                        Insumos
                      </div>
                      <ul class="list-group list-group-flush">
                  `;
                        data.insumos.forEach(i => {
                            const precioUnitario = Number(i.precio) || 0;
                            const precioTotal = precioUnitario * Number(i.cantidad);
                            total += precioTotal;

                            html += `
                      <li class="list-group-item d-flex justify-content-between align-items-center">
        <div>
          <span class="fw-bold">${i.codigo}</span> - ${i.nombre}
          <br><small class="text-muted">x${i.cantidad} @ $${precioUnitario.toFixed(2)}</small>
        </div>
        <span class="badge bg-success rounded-pill">$${precioTotal.toFixed(2)}</span>
      </li>
                    `;
                        });
                        html += `
                      </ul>
                    </div>
                  `;
                    }

                    // Derechos table
                    if (data.derechos.length) {
                        html += `
                    <div class="card mb-3">
                      <div class="card-header bg-success text-white py-2 px-3">
                        Derechos
                      </div>
                      <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0">
                          <thead class="table-light">
                            <tr>
                              <th>Código</th>
                              <th>Detalle</th>
                              <th class="text-center">Cantidad</th>
                              <th class="text-end">Precio unitario</th>
                              <th class="text-end">Subtotal</th>
                            </tr>
                          </thead>
                          <tbody>
                    `;
                        data.derechos.forEach(d => {
                            const precioUnitario = Number(d.precioAfiliacion) || 0;
                            const subtotal = precioUnitario * Number(d.cantidad);
                            total += subtotal;
                            html += `
                          <tr>
                            <td><span class="fw-bold">${d.codigo}</span></td>
                            <td>${d.detalle}</td>
                            <td class="text-center">${d.cantidad}</td>
                            <td class="text-end">$${precioUnitario.toFixed(2)}</td>
                            <td class="text-end">$${subtotal.toFixed(2)}</td>
                          </tr>
                        `;
                        });
                        html += `
                          </tbody>
                        </table>
                      </div>
                    </div>
                    `;
                    }

                    // Oxígeno alert
                    if (data.oxigeno.length) {
                        data.oxigeno.forEach(o => {
                            total += Number(o.precio) || 0;
                            html += `
                      <div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
                        <div>
                          <strong>Oxígeno:</strong> ${o.codigo} - ${o.nombre}
                          <br>
                          <span class="me-3">Tiempo: <span class="badge bg-info">${o.tiempo} h</span></span>
                          <span class="me-3">Litros: <span class="badge bg-info">${o.litros} L/min</span></span>
                          <span class="me-3">Precio: <span class="badge bg-primary">$${parseFloat(o.precio).toFixed(2)}</span></span>
                        </div>
                      </div>
                    `;
                        });
                    }

                    // Anestesia table
                    if (data.anestesia.length) {
                        html += `
                    <div class="mb-3">
                      <h6>Anestesia</h6>
                      <div class="table-responsive">
                        <table class="table table-bordered table-sm align-middle">
                          <thead class="table-light">
                            <tr>
                              <th>Código</th>
                              <th>Nombre</th>
                              <th>Tiempo</th>
                              <th class="text-end">Precio</th>
                            </tr>
                          </thead>
                          <tbody>
                  `;
                        data.anestesia.forEach(a => {
                            total += Number(a.precio) || 0;
                            html += `
                      <tr>
                        <td>${a.codigo}</td>
                        <td>${a.nombre}</td>
                        <td>${a.tiempo}</td>
                        <td class="text-end">$${parseFloat(a.precio).toFixed(2)}</td>
                      </tr>
                    `;
                        });
                        html += `
                          </tbody>
                        </table>
                      </div>
                    </div>
                  `;
                    }

                    // Total summary
                    html += `
                  <div class="d-flex justify-content-end align-items-center mt-3">
                    <span class="fw-bold me-2">Total estimado: </span>
                    <span class="badge bg-primary fs-5">$${total.toFixed(2)}</span>
                  </div>
                `;

                    previewContent.innerHTML = html;
                } catch (e) {
                    previewContent.innerHTML = "<p class='text-danger'>❌ Error al cargar preview</p>";
                    console.error(e);
                }
            });
        });
    </script>

    </body>
    </html>
    <!-- Modal global (uno solo, fuera del foreach) -->
<?php include __DIR__ . '/components/preview_modal.php'; ?>
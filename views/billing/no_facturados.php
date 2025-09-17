<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\BillingController;
use Controllers\PacienteController;
use Controllers\DashboardController;

$billingController = new BillingController($pdo);
$pacienteController = new PacienteController($pdo);
$dashboardController = new DashboardController($pdo);
// Paso 1: Obtener todas las facturas disponibles
$username = $dashboardController->getAuthenticatedUser();
$clasificados = $billingController->procedimientosNoFacturadosClasificados();
$quirurgicos = $clasificados['quirurgicos'];
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
                    <h4>🟢 Procedimientos Quirúrgicos no facturados</h4>
                    <div class="table-responsive"
                         style="overflow-x: auto; max-width: 100%; font-size: 0.85rem;">
                        <table id="example"
                               class="table table-striped table-hover table-sm invoice-archive">
                            <thead class="bg-primary">
                            <tr>
                                <th>Form ID</th>
                                <th>HC</th>
                                <th>Paciente</th>
                                <th>Afiliación</th>
                                <th>Fecha</th>
                                <th>Procedimiento</th>
                                <th>¿Facturar?</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($quirurgicos as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['form_id']) ?></td>
                                    <td><?= htmlspecialchars($r['hc_number']) ?></td>
                                    <td><?= htmlspecialchars(
                                            trim(($r['fname'] ?? '') . ' ' . ($r['mname'] ?? '') . ' ' . ($r['lname'] ?? '') . ' ' . ($r['lname2'] ?? ''))
                                        ) ?></td>
                                    <td><?= htmlspecialchars($r['afiliacion']) ?></td>
                                    <td><?= htmlspecialchars($r['fecha']) ?></td>
                                    <td><?= htmlspecialchars($r['nombre_procedimiento']) ?></td>
                                    <td>
                                        <?php
                                        $status = isset($r['status']) ? (int)$r['status'] : 0;
                                        $badge = $status === 1
                                            ? "<span class='badge bg-success'><i class='fa fa-check'></i></span>"
                                            : "<span class='badge bg-warning'><i class='fa fa-exclamation-triangle'></i></span>";

                                        $formId = htmlspecialchars($r['form_id']);
                                        $hcNumber = htmlspecialchars($r['hc_number']);
                                        // Botón para abrir modal global (solo uno en la página)
                                        echo "<button 
                                                class='btn btn-app btn-info btn-preview' 
                                                data-form-id='$formId'
                                                data-hc-number='$hcNumber'
                                                data-bs-toggle='modal'
                                                data-bs-target='#previewModal'>
                                                $badge
                                                <i class='mdi mdi-file-document'></i> Protocolo
                                            </button>";
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <h4>🔵 Procedimientos NO quirúrgicos no facturados</h4>
                    <div class="table-responsive"
                         style="overflow-x: auto; max-width: 100%; font-size: 0.85rem;">
                        <table id="exampleX"
                               class="table table-striped table-hover table-sm invoice-archive">
                            <thead class="bg-primary">
                            <tr>
                                <th>Form ID</th>
                                <th>HC</th>
                                <th>Paciente</th>
                                <th>Afiliación</th>
                                <th>Fecha</th>
                                <th>Procedimiento</th>
                                <th>¿Facturar?</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($noQuirurgicos as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['form_id']) ?></td>
                                    <td><?= htmlspecialchars($r['hc_number']) ?></td>
                                    <td><?= htmlspecialchars(
                                            trim(($r['fname'] ?? '') . ' ' . ($r['mname'] ?? '') . ' ' . ($r['lname'] ?? '') . ' ' . ($r['lname2'] ?? ''))
                                        ) ?></td>
                                    <td><?= htmlspecialchars($r['afiliacion']) ?></td>
                                    <td><?= htmlspecialchars($r['fecha']) ?></td>
                                    <td><?= htmlspecialchars($r['nombre_procedimiento']) ?></td>
                                    <td>
                                        <a href="/billing/facturar.php?form_id=<?= urlencode($r['form_id']) ?>"
                                           class="btn btn-sm btn-secondary mt-1">
                                            Facturar ahora
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
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

        previewModal.addEventListener("show.bs.modal", async (event) => {
            const button = event.relatedTarget;
            const formId = button.getAttribute("data-form-id");
            const hcNumber = button.getAttribute("data-hc-number");

            facturarFormId.value = formId;
            facturarHcNumber.value = hcNumber;
            previewContent.innerHTML = "<p class='text-muted'>🔄 Cargando datos...</p>";

            try {
                const res = await fetch(`/api/billing/billing_preview.php?form_id=${formId}&hc_number=${hcNumber}`);
                const data = await res.json();

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
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="previewModalLabel">Confirmar Facturación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="previewContent">
                    <p class="text-muted">Cargando datos...</p>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
                <div>
                    <form id="facturarForm" method="POST"
                          action="/views/billing/components/crear_desde_no_facturado.php"
                          class="mb-0">
                        <input type="hidden" name="form_id" id="facturarFormId">
                        <input type="hidden" name="hc_number" id="facturarHcNumber">
                        <button type="submit" class="btn btn-success">Facturar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
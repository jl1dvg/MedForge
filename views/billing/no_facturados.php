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
                                        $modalId = "modal_facturar_$formId";

                                        echo "<button class='btn btn-app btn-info' data-bs-toggle='modal' data-bs-target='#$modalId'>
                                                $badge
                                                <i class='mdi mdi-file-document'></i> Protocolo
                                              </button>";

                                        // Modal
                                        echo "
                                        <div class='modal fade' id='$modalId' tabindex='-1' aria-labelledby='{$modalId}Label' aria-hidden='true'>
                                          <div class='modal-dialog'>
                                            <div class='modal-content'>
                                              <div class='modal-header'>
                                                <h5 class='modal-title' id='{$modalId}Label'>Confirmar Facturación</h5>
                                                <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                                              </div>
                                              <div class='modal-body'>
                                                <p>¿Estás seguro de que deseas facturar este procedimiento quirúrgico?</p>
                                                <ul>
                                                  <li><strong>Paciente:</strong> " . htmlspecialchars(trim(($r['fname'] ?? '') . ' ' . ($r['mname'] ?? '') . ' ' . ($r['lname'] ?? '') . ' ' . ($r['lname2'] ?? ''))) . "</li>
                                                  <li><strong>HC:</strong> {$r['hc_number']}</li>
                                                  <li><strong>Procedimiento:</strong> " . htmlspecialchars($r['nombre_procedimiento']) . "</li>
                                                  <li><strong>Fecha:</strong> " . htmlspecialchars($r['fecha']) . "</li>
                                                </ul>
                                              </div>
                                              <div class='modal-footer'>
                                                <form method='POST' action='/views/billing/components/crear_desde_no_facturado.php'>
                                                  <input type='hidden' name='form_id' value='$formId'>
                                                  <input type='hidden' name='hc_number' value='$hcNumber'>
                                                  <button type='submit' class='btn btn-success'>Facturar</button>
                                                </form>
                                                <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Cancelar</button>
                                              </div>
                                            </div>
                                          </div>
                                        </div>";
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

</body>
</html>
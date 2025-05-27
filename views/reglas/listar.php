<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\DashboardController;
use Controllers\ReglaController;

$controller = new ReglaController($pdo);
$dashboardController = new DashboardController($pdo);
$reglas = $controller->obtenerReglasActivas();
$username = $dashboardController->getAuthenticatedUser();
?>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="/public/images/favicon.ico">

    <title>Listado de Reglas - Asistente CIVE</title>

    <!-- Vendors Style-->
    <link rel="stylesheet" href="/public/css/vendors_css.css">

    <!-- Style-->
    <link rel="stylesheet" href="/public/css/horizontal-menu.css">
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="/public/css/skin_color.css">

</head>
<body class="layout-top-nav light-skin theme-primary fixed">

<div class="wrapper">
    <div id="loader"></div>

    <?php include __DIR__ . '/../components/header.php'; ?>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <div class="container-full">
            <!-- Content Header (Page header) -->
            <div class="content-header">
                <div class="d-flex align-items-center">
                    <div class="me-auto">
                        <h3 class="page-title">Reglas Clínicas y de Facturación</h3>
                        <div class="d-inline-block align-items-center">
                            <nav>
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="#"><i class="mdi mdi-home-outline"></i></a>
                                    </li>
                                    <li class="breadcrumb-item active" aria-current="page">Reglas del Sistema</li>
                                </ol>
                            </nav>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Main content -->
            <section class="content">
                <div class="row">
                    <div class="col-12">
                        <div class="box">
                            <div class="box-body">

                                <h2>Listado de Reglas</h2>
                                <a href="crear.php" class="btn btn-primary mb-3">➕ Nueva Regla</a>
                                <table class="table table-bordered table-striped">
                                    <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Tipo</th>
                                        <th>Descripción</th>
                                        <th>Acciones</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($reglas as $r): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($r['nombre']) ?></td>
                                            <td><?= htmlspecialchars($r['tipo']) ?></td>
                                            <td><?= htmlspecialchars($r['descripcion']) ?></td>
                                            <td>
                                                <a href="editar.php?id=<?= $r['id'] ?>">✏️ Editar</a> |
                                                <a href="#">🗑️ Eliminar</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <!-- /.box-body -->
                        </div>
                    </div>
                </div>
            </section>
            <!-- /.content -->

        </div>
    </div>
    <!-- /.content-wrapper -->

</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

</div>
<script>
    let contadorCond = 1;
    let contadorAcc = 1;

    function agregarCondicion() {
        const cont = document.getElementById('condiciones-container');
        cont.innerHTML += `
        <div>
            <input name="condiciones[${contadorCond}][campo]" placeholder="Campo">
            <input name="condiciones[${contadorCond}][operador]" placeholder="Operador">
            <input name="condiciones[${contadorCond}][valor]" placeholder="Valor">
        </div>`;
        contadorCond++;
    }

    function agregarAccion() {
        const cont = document.getElementById('acciones-container');
        cont.innerHTML += `
        <div>
            <input name="acciones[${contadorAcc}][tipo]" placeholder="Tipo">
            <input name="acciones[${contadorAcc}][parametro]" placeholder="Parámetro">
        </div>`;
        contadorAcc++;
    }
</script>

<!-- Vendor JS -->
<script src="/public/js/vendors.min.js"></script>
<script src="/public/js/pages/chat-popup.js"></script>
<script src="/public/assets/icons/feather-icons/feather.min.js"></script>
<script src="/public/assets/vendor_components/datatable/datatables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


<!-- Doclinic App -->
<script src="/public/js/jquery.smartmenus.js"></script>
<script src="/public/js/menus.js"></script>
<script src="/public/js/template.js"></script>
<script src="/public/js/pages/appointments.js"></script>
</body>
</html>

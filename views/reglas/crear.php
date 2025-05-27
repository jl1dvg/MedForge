<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\DashboardController;

$dashboardController = new DashboardController($pdo);

$username = $dashboardController->getAuthenticatedUser();
?>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="/public/images/favicon.ico">

    <title>Crear Regla - Asistente CIVE</title>

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
                        <h3 class="page-title">Crear Nueva Regla</h3>
                        <div class="d-inline-block align-items-center">
                            <nav>
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="#"><i class="mdi mdi-home-outline"></i></a>
                                    </li>
                                    <li class="breadcrumb-item active" aria-current="page">Nueva Regla</li>
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
                                <!-- views/reglas/crear.php -->
                                <h2>Crear nueva regla</h2>
                                <form method="POST" action="guardar_regla.php">
                                    <div class="mb-3">
                                        <label class="form-label">Nombre:</label>
                                        <input type="text" class="form-control" name="nombre" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Tipo:</label>
                                        <select name="tipo" class="form-control">
                                            <option value="clinica">Clínica</option>
                                            <option value="facturacion">Facturación</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Descripción:</label>
                                        <textarea name="descripcion" rows="4" class="form-control"></textarea>
                                    </div>

                                    <h3>Condiciones</h3>
                                    <div id="condiciones-container">
                                        <div class="row mb-2">
                                            <div class="col">
                                                <select class="form-control" name="condiciones[0][campo]">
                                                    <option value="afiliacion">afiliacion</option>
                                                    <option value="diagnostico">diagnostico</option>
                                                    <option value="procedimiento">procedimiento</option>
                                                    <option value="edad">edad</option>
                                                </select>
                                            </div>
                                            <div class="col">
                                                <select class="form-control" name="condiciones[0][operador]">
                                                    <option value="=">=</option>
                                                    <option value="!=">!=</option>
                                                    <option value="LIKE">LIKE</option>
                                                    <option value="IN">IN</option>
                                                    <option value=">">&gt;</option>
                                                    <option value="<">&lt;</option>
                                                </select>
                                            </div>
                                            <div class="col">
                                                <input class="form-control" name="condiciones[0][valor]"
                                                       placeholder="Valor">
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-secondary mb-3" onclick="agregarCondicion()">+
                                        Añadir condición
                                    </button>

                                    <h3>Acciones</h3>
                                    <div id="acciones-container">
                                        <div class="row mb-2">
                                            <div class="col">
                                                <select class="form-control" name="acciones[0][tipo]">
                                                    <option value="asignar_tarifa_anestesia">asignar_tarifa_anestesia
                                                    </option>
                                                    <option value="asignar_porcentaje_pago">asignar_porcentaje_pago
                                                    </option>
                                                    <option value="excluir_insumo">excluir_insumo</option>
                                                    <option value="mostrar_alerta">mostrar_alerta</option>
                                                </select>
                                            </div>
                                            <div class="col">
                                                <input class="form-control" name="acciones[0][parametro]"
                                                       placeholder="Parámetro">
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-secondary mb-3" onclick="agregarAccion()">+
                                        Añadir acción
                                    </button>

                                    <div class="mt-3">
                                        <button type="submit" class="btn btn-success">Guardar regla</button>
                                    </div>
                                </form>
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
        <div class="row mb-2">
            <div class="col">
                <select class="form-control" name="condiciones[${contadorCond}][campo]">
                    <option value="afiliacion">afiliacion</option>
                    <option value="diagnostico">diagnostico</option>
                    <option value="procedimiento">procedimiento</option>
                    <option value="edad">edad</option>
                </select>
            </div>
            <div class="col">
                <select class="form-control" name="condiciones[${contadorCond}][operador]">
                    <option value="=">=</option>
                    <option value="!=">!=</option>
                    <option value="LIKE">LIKE</option>
                    <option value="IN">IN</option>
                    <option value=">">&gt;</option>
                    <option value="<">&lt;</option>
                </select>
            </div>
            <div class="col">
                <input class="form-control" name="condiciones[${contadorCond}][valor]" placeholder="Valor">
            </div>
        </div>`;
        contadorCond++;
    }

    function agregarAccion() {
        const cont = document.getElementById('acciones-container');
        cont.innerHTML += `
        <div class="row mb-2">
            <div class="col">
                <select class="form-control" name="acciones[${contadorAcc}][tipo]">
                    <option value="asignar_tarifa_anestesia">asignar_tarifa_anestesia</option>
                    <option value="asignar_porcentaje_pago">asignar_porcentaje_pago</option>
                    <option value="excluir_insumo">excluir_insumo</option>
                    <option value="mostrar_alerta">mostrar_alerta</option>
                </select>
            </div>
            <div class="col">
                <input class="form-control" name="acciones[${contadorAcc}][parametro]" placeholder="Parámetro">
            </div>
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

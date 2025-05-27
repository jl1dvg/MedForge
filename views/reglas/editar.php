<?php

require_once '../../bootstrap.php';

use Controllers\ReglaController;
use Controllers\DashboardController;

if (!isset($_GET['id'])) {
    echo "ID no proporcionado";
    exit;
}

$controller = new ReglaController($pdo);
$dashboardController = new DashboardController($pdo);

$regla = $controller->obtenerReglaPorId($_GET['id']); // Asegúrate de que este método existe

if (!$regla) {
    echo "Regla no encontrada";
    exit;
}
$username = $dashboardController->getAuthenticatedUser();
$condiciones = $controller->obtenerCondiciones($regla['id']);
$acciones = $controller->obtenerAcciones($regla['id']);
?>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="/public/images/favicon.ico">

    <title>Editar Regla - Asistente CIVE</title>

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
                        <h3 class="page-title">Editar Regla</h3>
                        <div class="d-inline-block align-items-center">
                            <nav>
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="#"><i class="mdi mdi-home-outline"></i></a>
                                    </li>
                                    <li class="breadcrumb-item active" aria-current="page">Editar Regla</li>
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
                                <h2>Editar regla</h2>
                                <form method="POST" action="actualizar_regla.php">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($regla['id']) ?>">

                                    <div class="mb-3">
                                        <label class="form-label">Nombre:</label>
                                        <input type="text" class="form-control" name="nombre"
                                               value="<?= htmlspecialchars($regla['nombre']) ?>"
                                               required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Tipo:</label>
                                        <select name="tipo" class="form-control">
                                            <option value="clinica" <?= $regla['tipo'] === 'clinica' ? 'selected' : '' ?>>
                                                Clínica
                                            </option>
                                            <option value="facturacion" <?= $regla['tipo'] === 'facturacion' ? 'selected' : '' ?>>
                                                Facturación
                                            </option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Descripción:</label>
                                        <textarea name="descripcion"
                                                  class="form-control"><?= htmlspecialchars($regla['descripcion']) ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Condiciones:</label>
                                        <div id="condiciones-container">
                                            <?php foreach ($condiciones as $index => $condicion): ?>
                                                <div class="condicion-item mb-2">
                                                    <div class="row">
                                                        <div class="col">
                                                            <input type="text" name="condiciones[<?= $index ?>][campo]"
                                                                   class="form-control mb-1"
                                                                   placeholder="Campo"
                                                                   value="<?= htmlspecialchars($condicion['campo'] ?? '') ?>"
                                                                   required>
                                                        </div>
                                                        <div class="col">
                                                            <input type="text"
                                                                   name="condiciones[<?= $index ?>][operador]"
                                                                   class="form-control mb-1"
                                                                   placeholder="Operador"
                                                                   value="<?= htmlspecialchars($condicion['operador'] ?? '') ?>"
                                                                   required>
                                                        </div>
                                                        <div class="col">
                                                            <input type="text" name="condiciones[<?= $index ?>][valor]"
                                                                   class="form-control"
                                                                   placeholder="Valor"
                                                                   value="<?= htmlspecialchars($condicion['valor'] ?? '') ?>"
                                                                   required>
                                                        </div>
                                                        <div class="col-auto d-flex align-items-start">
                                                            <button type="button"
                                                                    class="btn btn-danger btn-sm eliminar-condicion">
                                                                &times;
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <button type="button" id="add-condicion" class="btn btn-secondary mt-2">Añadir
                                            condición
                                        </button>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Acciones:</label>
                                        <div id="acciones-container">
                                            <?php foreach ($acciones as $index => $accion): ?>
                                                <div class="accion-item mb-2">
                                                    <div class="row">
                                                        <div class="col">
                                                            <input type="text" name="acciones[<?= $index ?>][tipo]"
                                                                   class="form-control mb-1"
                                                                   placeholder="Tipo"
                                                                   value="<?= htmlspecialchars($accion['tipo'] ?? '') ?>"
                                                                   required>
                                                        </div>
                                                        <div class="col">
                                                            <input type="text" name="acciones[<?= $index ?>][parametro]"
                                                                   class="form-control"
                                                                   placeholder="Detalle"
                                                                   value="<?= htmlspecialchars($accion['parametro'] ?? '') ?>"
                                                                   required>
                                                        </div>
                                                        <div class="col-auto d-flex align-items-start">
                                                            <button type="button"
                                                                    class="btn btn-danger btn-sm eliminar-accion">
                                                                &times;
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <button type="button" id="add-accion" class="btn btn-secondary mt-2">Añadir
                                            acción
                                        </button>
                                    </div>

                                    <button type="submit" class="btn btn-success">Guardar Cambios</button>
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
    let contadorCond = <?= count($condiciones) ?>;
    let contadorAcc = <?= count($acciones) ?>;

    function agregarCondicion() {
        const cont = document.getElementById('condiciones-container');
        cont.insertAdjacentHTML('beforeend', `
        <div class="condicion-item mb-2">
            <div class="row">
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
                <div class="col-auto d-flex align-items-start">
                    <button type="button" class="btn btn-danger btn-sm eliminar-condicion">&times;</button>
                </div>
            </div>
        </div>`);
        contadorCond++;
    }

    function agregarAccion() {
        const cont = document.getElementById('acciones-container');
        cont.insertAdjacentHTML('beforeend', `
        <div class="accion-item mb-2">
            <div class="row">
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
                <div class="col-auto d-flex align-items-start">
                    <button type="button" class="btn btn-danger btn-sm eliminar-accion">&times;</button>
                </div>
            </div>
        </div>`);
        contadorAcc++;
    }

    document.getElementById('add-condicion').addEventListener('click', agregarCondicion);
    document.getElementById('add-accion').addEventListener('click', agregarAccion);

    document.addEventListener('click', function (event) {
        if (event.target.matches('.eliminar-condicion')) {
            const item = event.target.closest('.condicion-item');
            if (item) {
                item.remove();
            }
        }
        if (event.target.matches('.eliminar-accion')) {
            const item = event.target.closest('.accion-item');
            if (item) {
                item.remove();
            }
        }
    });
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
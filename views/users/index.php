<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\DashboardController;
use Controllers\UserController;

$controller = new UserController($pdo);
$dashboardController = new DashboardController($pdo);

$users = $controller->index();
$username = $dashboardController->getAuthenticatedUser();
?>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="/public/images/favicon.ico">

    <title>Asistente CIVE - Dashboard</title>

    <!-- Vendors Style-->
    <link rel="stylesheet" href="/public/css/vendors_css.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Style-->
    <link rel="stylesheet" href="/public/css/horizontal-menu.css">
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="/public/css/skin_color.css">
    <!-- SweetAlert2 -->
</head>
<body class="layout-top-nav light-skin theme-primary fixed">

<div class="wrapper">

    <?php include __DIR__ . '/../components/header.php'; ?>
    <div class="content-wrapper">
        <div class="container-full">
            <!-- Content Header (Page header) -->
            <div class="content-header">
                <div class="d-flex align-items-center">
                    <div class="me-auto">
                        <h3 class="page-title">Gestión de Usuarios</h3>
                        <div class="d-inline-block align-items-center">
                            <nav>
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="#"><i class="mdi mdi-home-outline"></i></a>
                                    </li>
                                    <li class="breadcrumb-item" aria-current="page">Usuarios</li>
                                    <li class="breadcrumb-item active" aria-current="page">Lista de Usuarios</li>
                                </ol>
                            </nav>
                        </div>
                    </div>

                </div>
            </div>

            <div class="content">
                <div class="row">
                    <div class="col-12">
                        <div class="box">
                            <div class="box-header d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="box-title">📋 <strong>Listado de Usuarios</strong></h4>
                                    <h6 class="subtitle">Administra los usuarios registrados en el sistema.</h6>
                                </div>
                                <button id="agregarUsuarioBtn" class="waves-effect waves-light btn btn-primary mb-5">
                                    <i class="mdi mdi-account-plus"></i> Agregar Usuario
                                </button>
                            </div>
                            <div class="box-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover table-sm align-middle">
                                        <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Usuario</th>
                                            <th>Email</th>
                                            <th>Nombre</th>
                                            <th>Especialidad</th>
                                            <th>Acciones</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($user['id']) ?></td>
                                                <td><?= htmlspecialchars($user['username']) ?></td>
                                                <td><?= htmlspecialchars($user['email']) ?></td>
                                                <td><?= htmlspecialchars($user['nombre']) ?></td>
                                                <td><?= htmlspecialchars($user['especialidad']) ?></td>
                                                <td>
                                                    <button class="btn btn-info btn-sm btn-editar-usuario"
                                                            data-id="<?= $user['id'] ?>">
                                                        Editar
                                                    </button>
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
        </div>
    </div>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>
<!-- Vendor JS -->
<script src="/public/js/vendors.min.js"></script> <!-- contiene jQuery -->
<script src="/public/js/pages/chat-popup.js"></script>
<script src="/public/assets/icons/feather-icons/feather.min.js"></script>
<script src="/public/assets/vendor_components/datatable/datatables.min.js"></script>
<script src="/public/assets/vendor_components/tiny-editable/mindmup-editabletable.js"></script>
<script src="/public/assets/vendor_components/tiny-editable/numeric-input-example.js"></script>


<!-- Doclinic App -->
<script src="/public/js/jquery.smartmenus.js"></script>
<script src="/public/js/menus.js"></script>
<script src="/public/js/template.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).on('click', '.btn-editar-usuario', function () {
        let userId = $(this).data('id');
        $("#modalEditarUsuario .modal-body").load('/views/users/edit.php?id=' + userId, function () {
            $("#modalEditarUsuario").modal('show');
        });
    });
</script>

<!-- Modal de edición de usuario -->
<div class="modal fade" id="modalEditarUsuario" tabindex="-1" aria-labelledby="modalEditarUsuarioLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <!-- Contenido del modal se cargará dinámicamente aquí -->
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).on('click', '.btn-editar-usuario', function () {
        let userId = $(this).data('id');
        $('#modalEditarUsuario .modal-content').load('/views/users/edit.php?id=' + userId, function () {
            const modal = new bootstrap.Modal(document.getElementById('modalEditarUsuario'));
            modal.show();
        });
    });
</script>
<script>
    $(document).on('submit', '#modalEditarUsuario form', function (e) {
        e.preventDefault();
        const form = $(this);
        const userId = $('#modalEditarUsuario').data('id');
        const action = '/views/users/edit.php?id=' + userId;

        $.post(action, form.serialize(), function (response) {
            if (response.trim() === 'ok') {
                Swal.fire({
                    icon: 'success',
                    title: 'Actualizado',
                    text: 'El usuario ha sido actualizado correctamente.',
                    confirmButtonText: 'Aceptar'
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo actualizar el usuario. ' + response
                });
            }
        });
    });

    $(document).on('click', '.btn-editar-usuario', function () {
        let userId = $(this).data('id');
        $('#modalEditarUsuario')
            .data('id', userId)
            .find('.modal-content')
            .load('/views/users/edit.php?id=' + userId, function () {
                const modal = new bootstrap.Modal(document.getElementById('modalEditarUsuario'));
                modal.show();
            });
    });
</script>
</body>
</html>

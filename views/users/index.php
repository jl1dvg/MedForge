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
                <div class="row d-flex flex-column flex-md-row">
                    <div class="col-12">
                        <div class="box shadow-sm rounded">
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
                                    <table id="example"
                                           class="table table-striped table-hover table-sm invoice-archive">
                                        <thead class="bg-primary">
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
                                            <tr data-row-id="<?= $user['id'] ?>">
                                                <td><?= htmlspecialchars($user['id']) ?></td>
                                                <td><?= htmlspecialchars($user['username']) ?></td>
                                                <td><?= htmlspecialchars($user['email']) ?></td>
                                                <td>
                                                    <?= htmlspecialchars($user['nombre']) ?><br>
                                                    <span class="badge <?= $user['is_approved'] ? 'bg-success' : 'bg-warning' ?>">
                                                        <?= $user['is_approved'] ? 'Aprobado' : 'Pendiente' ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($user['especialidad']) ?></td>
                                                <td>
                                                    <button class="btn btn-info btn-sm btn-editar-usuario"
                                                            data-id="<?= $user['id'] ?>"
                                                            title="Editar usuario"
                                                            aria-label="Editar usuario">
                                                        <i class="fas fa-user-edit"></i> Editar
                                                    </button>
                                                    <a href="/views/users/profile.php?id=<?= $user['id'] ?>"
                                                       class="btn btn-outline-secondary btn-sm"
                                                       title="Ver perfil del usuario"
                                                       aria-label="Ver perfil del usuario">
                                                        <i class="fas fa-id-badge"></i> Perfil
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
<script src="/public/js/pages/data-table.js"></script>


<!-- Modal de edición de usuario -->
<div class="modal fade" id="modalEditarUsuario" tabindex="-1" aria-labelledby="modalEditarUsuarioLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content card shadow-sm">
            <!-- Contenido del modal se cargará dinámicamente aquí -->
        </div>
    </div>
</div>

<!-- Modal para visualizar el perfil del usuario -->
<div class="modal fade" id="modalPerfilUsuario" tabindex="-1" aria-labelledby="modalPerfilUsuarioLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content card shadow-sm">
            <!-- Contenido del perfil se cargará dinámicamente aquí -->
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).on('click', '.btn-editar-usuario', function () {
        let userId = $(this).data('id');
        $('#modalEditarUsuario')
            .data('id', userId)
            .find('.modal-content')
            .load('/views/users/edit.php?id=' + userId, function () {
                $('#modalEditarUsuarioLabel').text('Editar Usuario');
                $('#modalEditarUsuario button[type="submit"]').text('Actualizar Usuario');
                const modal = new bootstrap.Modal(document.getElementById('modalEditarUsuario'));
                modal.show();
            });
    });

    // Manejador para el botón "Agregar Usuario"
    $(document).on('click', '#agregarUsuarioBtn', function () {
        $('#modalEditarUsuario')
            .removeData('id') // para asegurarnos que no tenga un ID previo
            .find('.modal-content')
            .load('/views/users/create.php', function () {
                $('#modalEditarUsuarioLabel').text('Crear Usuario');
                $('#modalEditarUsuario button[type="submit"]').text('Crear Usuario');
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
        const action = userId ? '/views/users/edit.php?id=' + userId : '/views/users/create.php';

        const submitButton = form.find('button[type="submit"]');
        submitButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...');

        $.post(action, form.serialize(), function (response) {
            submitButton.prop('disabled', false).text(userId ? 'Actualizar Usuario' : 'Crear Usuario');
            if (response.trim() === 'ok') {
                Swal.fire({
                    icon: 'success',
                    title: 'Actualizado',
                    text: 'El usuario ha sido actualizado correctamente.',
                    confirmButtonText: 'Aceptar'
                }).then(() => {
                    const row = $('tr[data-row-id="' + userId + '"]');
                    row.find('td:nth-child(2)').text(form.find('[name="username"]').val());
                    row.find('td:nth-child(3)').text(form.find('[name="email"]').val());
                    row.find('td:nth-child(4)').text(form.find('[name="nombre"]').val());
                    row.find('td:nth-child(5)').text(form.find('[name="especialidad"]').val());
                    row.addClass('table-success');
                    setTimeout(() => row.removeClass('table-success'), 2000);
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
                $('#modalEditarUsuarioLabel').text('Editar Usuario');
                $('#modalEditarUsuario button[type="submit"]').text('Actualizar Usuario');
                const modal = new bootstrap.Modal(document.getElementById('modalEditarUsuario'));
                modal.show();
            });
    });
</script>
<script>
    $(document).on('click', '.btn-ver-perfil', function () {
        let userId = $(this).data('id');
        $('#modalPerfilUsuario')
            .find('.modal-content')
            .load('/views/users/profile.php?id=' + userId, function () {
                const modal = new bootstrap.Modal(document.getElementById('modalPerfilUsuario'));
                modal.show();
            });
    });
</script>
<script>
    // Limpieza del backdrop y restauración de scroll cuando se cierra el modal
    $('#modalEditarUsuario, #modalPerfilUsuario').on('hidden.bs.modal', function () {
        $('body').removeClass('modal-open');
        $('.modal-backdrop').remove();
        $('body').css('overflow', 'auto'); // <-- restaura scroll
    });

    // Si el modal tiene aria-hidden mal configurado, lo corregimos al mostrarlo
    $('#modalEditarUsuario, #modalPerfilUsuario').on('shown.bs.modal', function () {
        $(this).attr('aria-hidden', 'false');
        $('body').css('overflow', 'auto');
    });
</script>
</body>
</html>

<?php // views/layout.php ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="icon" href="<?= asset('images/favicon.ico') ?>">

    <?php
    $titleSuffix = isset($pageTitle) && $pageTitle ? ' - ' . htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') : '';
    ?>
    <title>MedForge<?= $titleSuffix ?></title>

    <!-- Vendors Style-->
    <link rel="stylesheet" href="<?= asset('/css/vendors_css.css') ?>">

    <!-- Style-->
    <link rel="stylesheet" href="<?= asset('css/horizontal-menu.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/skin_color.css') ?>">

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
</head>

<body class="hold-transition light-skin sidebar-mini theme-primary fixed">
<div class="wrapper">

    <?php
    // Detectar si estamos en el login o cualquier página pública
    $isAuthView = isset($viewPath) && str_contains($viewPath, '/modules/Auth/views/login.php');
    ?>

    <?php if (!$isAuthView): ?>

        <!-- Encabezado -->
        <?php include __DIR__ . '/partials/header.php'; ?>

        <!-- Sidebar -->
        <?php include __DIR__ . '/partials/navbar.php'; ?>

        <!-- Contenido dinámico -->
        <div class="content-wrapper">
            <div class="container-full">
                <!-- Main content -->
                <?php if (isset($viewPath) && is_file($viewPath)): ?>
                    <?php include $viewPath; ?>
                <?php endif; ?>
                <!-- /.content -->
            </div>
        </div>

        <!-- Pie -->
        <?php include __DIR__ . '/partials/footer.php'; ?>
    <?php else: ?>
        <!-- Vista de login sin layout completo -->
        <?php if (isset($viewPath) && is_file($viewPath)): ?>
            <?php include $viewPath; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>

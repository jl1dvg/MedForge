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
    $defaultBodyClass = 'hold-transition light-skin sidebar-mini theme-primary fixed';
    $bodyClassAttr = htmlspecialchars($bodyClass ?? $defaultBodyClass, ENT_QUOTES, 'UTF-8');
    ?>
    <title>MedForge<?= $titleSuffix ?></title>

    <!-- Vendors Style-->
    <link rel="stylesheet" href="<?= asset('/css/vendors_css.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/icons/font-awesome/css/font-awesome.min.css') ?>">

    <!-- Style-->
    <link rel="stylesheet" href="<?= asset('css/horizontal-menu.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/skin_color.css') ?>">

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
</head>

<body class="<?= $bodyClassAttr ?>">
<div class="wrapper">

    <?php
    // Detectar si estamos en el login o cualquier página pública
    $isAuthView = isset($viewPath) && stripos($viewPath, '/modules/auth/views/login.php') !== false;
    ?>

    <?php if (!$isAuthView): ?>

        <!-- Encabezado -->
        <?php include __DIR__ . '/partials/header.php'; ?>

        <!-- Panel global de notificaciones -->
        <?php include __DIR__ . '/partials/notification_panel.php'; ?>

        <!-- Sidebar -->
        <?php include __DIR__ . '/partials/navbar.php'; ?>

        <?php
        if (!isset($scripts) || !is_array($scripts)) {
            $scripts = [];
        }
        if (!isset($inlineScripts) || !is_array($inlineScripts)) {
            $inlineScripts = [];
        }
        ?>

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

<?php
$defaultScripts = [
    'js/vendors.min.js',
    'js/pages/chat-popup.js',
    'assets/icons/feather-icons/feather.min.js',
    'js/jquery.smartmenus.js',
    'js/menus.js',
    'js/template.js',
];

$scriptStack = [];
foreach (array_merge($defaultScripts, $scripts) as $script) {
    if (!is_string($script) || $script === '') {
        continue;
    }
    $scriptStack[] = $script;
}
$scriptStack = array_values(array_unique($scriptStack));
?>

<?php foreach ($scriptStack as $script): ?>
    <?php
    $isAbsolute = preg_match('#^(?:https?:)?//#', $script) === 1;
    $src = $isAbsolute ? $script : asset($script);
    ?>
    <script src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endforeach; ?>

<?php foreach ($inlineScripts as $inlineScript): ?>
    <?php if (is_string($inlineScript) && $inlineScript !== ''): ?>
        <script><?= $inlineScript ?></script>
    <?php endif; ?>
<?php endforeach; ?>

<?php if (!$isAuthView): ?>
    <?php
    $hasSettingsHelper = function_exists('get_option');
    $notificationChannels = [
        'email' => $hasSettingsHelper ? (bool) get_option('notifications_email_enabled') : false,
        'sms' => $hasSettingsHelper ? (bool) get_option('notifications_sms_enabled') : false,
        'daily_summary' => $hasSettingsHelper ? (bool) get_option('notifications_daily_summary') : false,
    ];
    $pusherEnabled = $hasSettingsHelper ? (bool) get_option('pusher_realtime_notifications') : false;
    $pusherKey = $hasSettingsHelper ? (string) get_option('pusher_app_key') : '';
    $panelModuleAsset = asset('js/pages/solicitudes/notifications/panel.js');
    ?>
    <script type="module">
        import { createNotificationPanel } from '<?= $panelModuleAsset ?>';

        window.MEDF = window.MEDF || {};
        const panel = window.MEDF.notificationPanel || createNotificationPanel({
            panelId: 'kanbanNotificationPanel',
            backdropId: 'notificationPanelBackdrop',
            toggleSelector: '[data-notification-panel-toggle]'
        });
        window.MEDF.notificationPanel = panel;

        window.MEDF.defaultNotificationChannels = <?= json_encode($notificationChannels) ?>;
        panel.setChannelPreferences(window.MEDF.defaultNotificationChannels);

        window.MEDF.pusherIntegration = {
            enabled: <?= $pusherEnabled ? 'true' : 'false' ?>,
            hasKey: <?= $pusherKey !== '' ? 'true' : 'false' ?>
        };

        if (!window.MEDF.pusherIntegration.enabled) {
            panel.setIntegrationWarning('Las notificaciones en tiempo real están desactivadas en Configuración → Notificaciones.');
        } else if (!window.MEDF.pusherIntegration.hasKey) {
            panel.setIntegrationWarning('No se configuró la APP Key de Pusher en los ajustes.');
        } else {
            panel.setIntegrationWarning('');
        }
    </script>
<?php endif; ?>

</body>
</html>

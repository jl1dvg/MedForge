<?php
// views/layout-turnero.php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <?php
    $titleSuffix = isset($pageTitle) && $pageTitle
        ? ' - ' . htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8')
        : '';

    $defaultBodyClass = 'turnero-body';
    $bodyClassAttr = htmlspecialchars($bodyClass ?? $defaultBodyClass, ENT_QUOTES, 'UTF-8');

    if (!isset($styles) || !is_array($styles)) {
        $styles = [];
    }
    ?>
    <title>MedForge<?= $titleSuffix ?></title>
    <link rel="icon" href="<?= asset('images/favicon.ico') ?>">
    <link rel="stylesheet" href="<?= asset('/css/vendors_css.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/skin_color.css') ?>">
    <?php foreach (array_unique(array_filter($styles, 'is_string')) as $style): ?>
        <?php
        $isAbsolute = preg_match('#^(?:https?:)?//#', $style) === 1;
        $href = $isAbsolute ? $style : asset($style);
        ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>">
    <?php endforeach; ?>
</head>
<body class="<?= $bodyClassAttr ?>">
<main class="turnero-main" role="main">
    <?php if (isset($viewPath) && is_file($viewPath)): ?>
        <?php include $viewPath; ?>
    <?php endif; ?>
</main>

<?php
$defaultScripts = [
    'js/vendors.min.js',
];

if (!isset($scripts) || !is_array($scripts)) {
    $scripts = [];
}

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

<?php if (isset($inlineScripts) && is_array($inlineScripts)): ?>
    <?php foreach ($inlineScripts as $inlineScript): ?>
        <?php if (is_string($inlineScript) && $inlineScript !== ''): ?>
            <script><?= $inlineScript ?></script>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>

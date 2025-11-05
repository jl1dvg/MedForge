<?php
/**
 * Base layout for PDF templates.
 *
 * Expects the following variables to be defined by the including template:
 * - string|null $title
 * - string|null $bodyClass
 * - string|null $header
 * - string|null $content
 * - array<int, string>|null $stylesheets Additional absolute paths to CSS files.
 */

use Modules\Reporting\Support\RenderContext;

$stylesheets = $stylesheets ?? [];
$inlineStyles = [];

if (!RenderContext::isFragment()) {
    $baseCssPath = __DIR__ . '/../assets/pdf.css';
    if (is_file($baseCssPath)) {
        $baseCss = file_get_contents($baseCssPath);
        if ($baseCss !== false && $baseCss !== '') {
            $inlineStyles[] = $baseCss;
        }
    }

    foreach ($stylesheets as $stylesheet) {
        if (!is_string($stylesheet) || $stylesheet === '') {
            continue;
        }

        if (!is_file($stylesheet)) {
            continue;
        }

        $cssContent = file_get_contents($stylesheet);
        if ($cssContent === false || $cssContent === '') {
            continue;
        }

        $inlineStyles[] = $cssContent;
    }
}

$title = $title ?? 'Reporte PDF';
$bodyClassAttribute = isset($bodyClass) && $bodyClass !== ''
    ? ' class="' . htmlspecialchars((string) $bodyClass, ENT_QUOTES, 'UTF-8') . '"'
    : '';

if (RenderContext::isFragment()) {
    if (!empty($header)) {
        echo $header;
    }

    echo $content ?? '';

    return;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars((string) $title, ENT_QUOTES, 'UTF-8') ?></title>
    <?php foreach ($inlineStyles as $style): ?>
        <style>
            <?= $style ?>
        </style>
    <?php endforeach; ?>
</head>
<body<?= $bodyClassAttribute ?>>
<?php if (!empty($header)): ?>
    <?= $header ?>
<?php endif; ?>
<?= $content ?? '' ?>
</body>
</html>

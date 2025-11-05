<?php
/**
 * Base layout for PDF templates.
 *
 * Expects the following variables to be defined by the including template:
 * - string|null $title
 * - string|null $bodyClass
 * - string|null $header
 * - string|null $content
 */

$title = $title ?? 'Reporte PDF';
$bodyClassAttribute = isset($bodyClass) && $bodyClass !== ''
    ? ' class="' . htmlspecialchars((string) $bodyClass, ENT_QUOTES, 'UTF-8') . '"'
    : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <!--<title><?= htmlspecialchars((string) $title, ENT_QUOTES, 'UTF-8') ?></title>
     Los estilos se cargan en PdfGenerator::generarDesdeHtml() -->
</head>
<body<?= $bodyClassAttribute ?>>
<?php if (!empty($header)): ?>
    <?= $header ?>
<?php endif; ?>
<?= $content ?? '' ?>
</body>
</html>

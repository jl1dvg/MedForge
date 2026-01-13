<?php
/**
 * Simple layout for lightweight PDF reports.
 *
 * Expects:
 * - string|null $title
 * - string|null $content
 */

$title = $title ?? 'Reporte PDF';
$styles = $styles ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars((string) $title, ENT_QUOTES, 'UTF-8') ?></title>
    <?php if (!empty($styles)): ?>
        <style><?= $styles ?></style>
    <?php endif; ?>
</head>
<body>
<?= $content ?? '' ?>
</body>
</html>

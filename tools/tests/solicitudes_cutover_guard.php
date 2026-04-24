#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = realpath(__DIR__ . '/../../');
if (!is_string($root) || $root === '') {
    fwrite(STDERR, "No se pudo resolver la raíz del repositorio.\n");
    exit(2);
}

$legacyPath = 'modules/solicitudes/';
$legacyRetirementAllowlist = [
    'modules/solicitudes/routes.php',
];
$solicitudesScopes = [
    'laravel-app/app/Modules/Solicitudes/',
    'laravel-app/resources/views/solicitudes/',
    'laravel-app/routes/v2/solicitudes.php',
    'public/js/pages/solicitudes/',
    'public/js/pages/shared/crmPanelFactory.js',
];
$legacyAuthPatterns = [
    'LegacySessionAuth',
    'legacy.auth',
    'legacy.permission',
];

$errors = [];

$statusOutput = [];
$statusCode = 0;
exec('git -C ' . escapeshellarg($root) . ' status --porcelain', $statusOutput, $statusCode);
if ($statusCode !== 0) {
    fwrite(STDERR, "No se pudo leer git status.\n");
    exit(2);
}

$changedFiles = [];
foreach ($statusOutput as $line) {
    $line = rtrim($line, "\r\n");
    if ($line === '' || strlen($line) < 4) {
        continue;
    }

    $path = trim(substr($line, 3));
    if ($path === '') {
        continue;
    }

    if (str_contains($path, ' -> ')) {
        [, $path] = explode(' -> ', $path, 2);
        $path = trim($path);
    }

    if ($path !== '') {
        $changedFiles[] = $path;
    }
}

$legacyChanges = array_values(array_filter(
    $changedFiles,
    static fn(string $path): bool => str_starts_with($path, $legacyPath)
        && !in_array($path, $legacyRetirementAllowlist, true)
));
if ($legacyChanges !== []) {
    $errors[] = "Se detectaron cambios en Solicitudes legacy:";
    foreach ($legacyChanges as $path) {
        $errors[] = "  - {$path}";
    }
}

foreach ($legacyRetirementAllowlist as $path) {
    if (!in_array($path, $changedFiles, true)) {
        continue;
    }

    $content = @file_get_contents($root . '/' . $path);
    if (!is_string($content)) {
        $errors[] = "No se pudo leer {$path} para validar el retiro legacy.";
        continue;
    }

    if (str_contains($content, 'SolicitudController')) {
        $errors[] = "{$path} no debe ejecutar SolicitudController durante Fase 6.";
    }
}

$diffOutput = [];
$diffCode = 0;
exec('git -C ' . escapeshellarg($root) . ' diff --unified=0 --no-color HEAD', $diffOutput, $diffCode);
if ($diffCode !== 0) {
    fwrite(STDERR, "No se pudo leer git diff.\n");
    exit(2);
}

$matches = [];
$currentFile = null;
foreach ($diffOutput as $line) {
    if (str_starts_with($line, '+++ b/')) {
        $currentFile = substr($line, 6);
        continue;
    }

    if ($currentFile === null || !str_starts_with($line, '+') || str_starts_with($line, '+++')) {
        continue;
    }

    $isSolicitudesScope = false;
    foreach ($solicitudesScopes as $scope) {
        if (str_starts_with($currentFile, $scope) || $currentFile === rtrim($scope, '/')) {
            $isSolicitudesScope = true;
            break;
        }
    }

    if (!$isSolicitudesScope) {
        continue;
    }

    foreach ($legacyAuthPatterns as $pattern) {
        if (str_contains($line, $pattern)) {
            $matches[] = $currentFile . ': ' . trim(substr($line, 1));
            break;
        }
    }
}

if ($matches !== []) {
    $errors[] = 'Se detectaron referencias legacy dentro del alcance Laravel de Solicitudes:';
    foreach ($matches as $match) {
        $errors[] = "  - {$match}";
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Solicitudes cutover guard: FAIL\n\n");
    fwrite(STDERR, implode("\n", $errors) . "\n\n");
    fwrite(STDERR, "Regla activa: Fase 1 congela legacy; Fase 6 solo permite retirar rutas legacy sin ejecutar SolicitudController.\n");
    exit(1);
}

fwrite(STDOUT, "Solicitudes cutover guard: OK\n");
exit(0);

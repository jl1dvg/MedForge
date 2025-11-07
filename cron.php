#!/usr/bin/env php
<?php

declare(strict_types=1);

use Modules\CronManager\Services\CronRunner;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script debe ejecutarse desde la línea de comandos." . PHP_EOL);
    exit(1);
}

require __DIR__ . '/bootstrap.php';

$pdo = $GLOBALS['pdo'] ?? null;
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "No hay conexión a la base de datos disponible." . PHP_EOL);
    exit(1);
}

$options = getopt('', ['force', 'task:']);
$force = array_key_exists('force', $options);
$taskSlug = $options['task'] ?? null;

$runner = new CronRunner($pdo);

try {
    if ($taskSlug !== null) {
        $result = $runner->runBySlug((string) $taskSlug, $force);
        if ($result === null) {
            fwrite(STDERR, "La tarea especificada no existe." . PHP_EOL);
            exit(1);
        }

        cron_cli_print_result($result);
        exit(strtolower((string) ($result['status'] ?? '')) === 'failed' ? 1 : 0);
    }

    $results = $runner->runAll($force);
    $exitCode = 0;

    foreach ($results as $result) {
        cron_cli_print_result($result);
        if (strtolower((string) ($result['status'] ?? '')) === 'failed') {
            $exitCode = 1;
        }
    }

    exit($exitCode);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Error ejecutando tareas de cron: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

/**
 * @param array<string, mixed> $result
 */
function cron_cli_print_result(array $result): void
{
    $status = strtoupper((string) ($result['status'] ?? 'DESCONOCIDO'));
    $name = $result['name'] ?? ($result['slug'] ?? 'Tarea');
    $message = $result['message'] ?? '';

    $line = sprintf('[%s] %s', $status, (string) $name);
    if ($message !== '') {
        $line .= ': ' . $message;
    }

    echo $line . PHP_EOL;

    if (!empty($result['details']) && is_array($result['details'])) {
        $json = json_encode($result['details'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json !== false) {
            echo $json . PHP_EOL;
        }
    }
}

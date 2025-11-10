#!/usr/bin/env php
<?php

declare(strict_types=1);

use Modules\KPI\Services\KpiCalculationService;
use Modules\KPI\Support\KpiRegistry;

$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../config/database.php';

$options = getopt('', ['start::', 'end::', 'kpi::', 'help::']);

if (isset($options['help'])) {
    echo "Uso: php tools/kpi_recalculate.php [--start=YYYY-MM-DD] [--end=YYYY-MM-DD] [--kpi=clave1,clave2]\n";
    exit(0);
}

$startRaw = $options['start'] ?? 'yesterday';
$endRaw = $options['end'] ?? 'today';

try {
    $start = new DateTimeImmutable($startRaw);
} catch (Throwable $e) {
    fwrite(STDERR, "[error] Fecha de inicio inválida: {$startRaw}\n");
    exit(1);
}

try {
    $end = new DateTimeImmutable($endRaw);
} catch (Throwable $e) {
    fwrite(STDERR, "[error] Fecha final inválida: {$endRaw}\n");
    exit(1);
}

$start = $start->setTime(0, 0, 0);
$end = $end->setTime(0, 0, 0);

if ($start > $end) {
    [$start, $end] = [$end, $start];
}

$keys = [];
if (!empty($options['kpi'])) {
    $keys = array_values(array_filter(array_map('trim', explode(',', (string) $options['kpi']))));
}

$available = array_keys(KpiRegistry::all());
if ($keys !== []) {
    $unknown = array_diff($keys, $available);
    if ($unknown !== []) {
        fwrite(STDERR, '[error] KPI desconocidos: ' . implode(', ', $unknown) . PHP_EOL);
        exit(1);
    }
}

$period = new DatePeriod($start, new DateInterval('P1D'), $end->add(new DateInterval('P1D')));

$service = new KpiCalculationService($pdo);
$results = $service->recalculateRange($period, $keys);

$grouped = [];
foreach ($results as $kpiKey => $result) {
    $grouped[$kpiKey] = $result;
}

echo "Recalculo completado desde {$start->format('Y-m-d')} hasta {$end->format('Y-m-d')}." . PHP_EOL;
foreach ($grouped as $kpiKey => $result) {
    $value = $result['value'] ?? 'n/a';
    echo sprintf(" - %s: %s\n", $kpiKey, (string) $value);
}

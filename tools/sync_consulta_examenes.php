#!/usr/bin/env php
<?php

require_once __DIR__ . '/../bootstrap.php';

use Modules\Examenes\Services\ConsultaExamenSyncService;

$pdo = $GLOBALS['pdo'] ?? null;

if (!$pdo instanceof PDO) {
    fwrite(STDERR, "No se pudo establecer conexión a la base de datos.\n");
    exit(1);
}

$service = new ConsultaExamenSyncService($pdo);

$procesadas = $service->backfillFromConsultaData();

echo sprintf("Se sincronizaron %d consultas con exámenes normalizados.\n", $procesadas);

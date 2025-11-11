<?php

$checks = [
    'controllers/BillingController.php' => [
        'LOWER(pa.afiliacion)' => 'Utilizar colaciones en BillingController',
    ],
    'controllers/Services/ExportService.php' => [
        "DATE_FORMAT(pd.fecha_inicio, '%Y-%m')" => 'Reemplazar DATE_FORMAT en ExportService',
        'UPPER(pa.afiliacion)' => 'Utilizar colaciones en ExportService',
    ],
    'modules/Pacientes/Controllers/Pacientes.php' => [
        'LOWER(p.afiliacion)' => 'Evitar LOWER en filtros de afiliación (Pacientes controller)',
    ],
    'modules/Pacientes/Services/PacienteService.php' => [
        'LOWER(p.afiliacion)' => 'Evitar LOWER en filtros de afiliación (PacienteService)',
    ],
    'models/IplPlanificadorModel.php' => [
        'LOWER(p.afiliacion)' => 'Evitar LOWER en filtros de afiliación (IPL planificador)',
    ],
    'models/ExamenesModel.php' => [
        'LOWER(pd.afiliacion)' => 'Usar colaciones en filtros de afiliación (ExamenesModel)',
        'LOWER(sp.doctor)' => 'Usar colaciones en filtros de doctor (ExamenesModel)',
        'LOWER(sp.prioridad)' => 'Usar colaciones en filtros de prioridad (ExamenesModel)',
        'TRIM(sp.procedimiento)' => 'Evitar TRIM en columnas indexadas (ExamenesModel)',
    ],
    'modules/solicitudes/models/ExamenesModel.php' => [
        'LOWER(pd.afiliacion)' => 'Usar colaciones en filtros de afiliación (mod ExamenesModel)',
        'LOWER(sp.doctor)' => 'Usar colaciones en filtros de doctor (mod ExamenesModel)',
        'LOWER(sp.prioridad)' => 'Usar colaciones en filtros de prioridad (mod ExamenesModel)',
        'TRIM(sp.procedimiento)' => 'Evitar TRIM en columnas indexadas (mod ExamenesModel)',
    ],
];

$root = realpath(__DIR__ . '/../../');
$failures = [];

foreach ($checks as $relative => $patterns) {
    $path = $root . DIRECTORY_SEPARATOR . $relative;
    if (!is_file($path)) {
        $failures[] = "Archivo no encontrado: {$relative}";
        continue;
    }

    $contents = file_get_contents($path);
    foreach ($patterns as $pattern => $message) {
        if (strpos($contents, $pattern) !== false) {
            $failures[] = "{$relative}: {$message}";
        }
    }
}

if ($failures) {
    fwrite(STDERR, "Se encontraron patrones prohibidos:\n" . implode("\n", $failures) . "\n");
    exit(1);
}

echo "SQL filter guard: OK\n";

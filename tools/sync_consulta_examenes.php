#!/usr/bin/env php
<?php

require_once __DIR__ . '/../bootstrap.php';

use Modules\Examenes\Services\ConsultaExamenSyncService;

function usage(): void
{
    $script = basename(__FILE__);
    $help = <<<TXT
Uso: php tools/{$script} [--hc=<HC_NUMBER>] [--form=<FORM_ID>] [--limit=<N>] [--dry-run] [--verbose]

Opciones:
  --hc         Limita la sincronización a un número de historia clínica específico.
  --form       Limita la sincronización a un form_id concreto.
  --limit      Procesa como máximo N registros (útil para pruebas).
  --dry-run    No escribe en la base de datos; muestra estadísticas de lo que se importaría.
  --verbose    Muestra el detalle de cada consulta procesada.
TXT;
    fwrite(STDOUT, $help . "\n");
}

$pdo = $GLOBALS['pdo'] ?? null;

if (!$pdo instanceof PDO) {
    fwrite(STDERR, "No se pudo establecer conexión a la base de datos.\n");
    exit(1);
}

$options = getopt('', ['hc::', 'form::', 'limit::', 'dry-run', 'verbose', 'help']);

if (isset($options['help'])) {
    usage();
    exit(0);
}

$hc = isset($options['hc']) ? trim((string) $options['hc']) : null;
$form = isset($options['form']) ? trim((string) $options['form']) : null;
$limit = null;

if (isset($options['limit']) && $options['limit'] !== '') {
    if (!ctype_digit((string) $options['limit'])) {
        fwrite(STDERR, "--limit debe ser un entero positivo.\n");
        exit(1);
    }
    $limit = (int) $options['limit'];
}

$dryRun = array_key_exists('dry-run', $options);
$verbose = array_key_exists('verbose', $options);

$service = new ConsultaExamenSyncService($pdo);

$callback = null;
if ($verbose) {
    $callback = static function (array $row, array $normalizados, bool $persisted, bool $skipped) use ($dryRun): void {
        $hcNumber = $row['hc_number'] ?? 'N/A';
        $formId = $row['form_id'] ?? 'N/A';

        if ($skipped) {
            printf("[SKIP] %s/%s sin datos normalizables.\n", $hcNumber, $formId);
            return;
        }

        $count = count($normalizados);
        $status = $dryRun ? 'SIMULADO' : ($persisted ? 'IMPORTADO' : 'SIN CAMBIOS');
        printf("[%s] %s/%s → %d examen(es) %s\n", $status, $hcNumber, $formId, $count, $dryRun ? '(dry-run)' : '');
    };
}

$stats = $service->backfillFromConsultaData($hc, $form, $limit, $dryRun, $callback);

echo "Resumen de sincronización:\n";
echo sprintf("  Consultas procesadas: %d\n", $stats['processed']);
echo sprintf("  Exámenes normalizados: %d\n", $stats['with_exams']);

if ($dryRun) {
    echo "  ⚠️ Modo simulación: no se escribieron cambios.\n";
} else {
    echo sprintf("  Registros insertados: %d\n", $stats['persisted']);
}

if ($stats['skipped'] > 0) {
    echo sprintf("  Consultas sin datos útiles: %d\n", $stats['skipped']);
}

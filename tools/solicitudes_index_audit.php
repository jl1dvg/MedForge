#!/usr/bin/env php
<?php

declare(strict_types=1);

$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

$options = getopt('', ['strict', 'table::', 'help']);
if (isset($options['help'])) {
    echo "Usage:\n";
    echo "  php tools/solicitudes_index_audit.php [--strict] [--table=table1,table2]\n";
    echo "\n";
    echo "Options:\n";
    echo "  --strict          Exit with code 2 when there are missing index coverages.\n";
    echo "  --table=...       Comma-separated table filter.\n";
    echo "  --help            Show this help.\n";
    exit(0);
}

$tableFilter = [];
if (isset($options['table'])) {
    $tableFilter = array_values(array_filter(array_map(
        static fn(string $item): string => trim($item),
        explode(',', (string) $options['table'])
    )));
}

$strict = isset($options['strict']);

/**
 * @return array<string,array<int,array<string,mixed>>>
 */
function getSolicitudesIndexChecks(): array
{
    return [
        'solicitud_procedimiento' => [
            ['label' => 'Lookup por form_id', 'columns' => ['form_id']],
            ['label' => 'Lectura por hc_number + created_at', 'columns' => ['hc_number', 'created_at']],
            ['label' => 'Turnero por turno', 'columns' => ['turno']],
            ['label' => 'Filtros por fecha', 'columns' => ['fecha']],
        ],
        'consulta_data' => [
            ['label' => 'Join por form_id + hc_number', 'columns' => ['form_id', 'hc_number']],
            ['label' => 'Timeline por hc_number + fecha', 'columns' => ['hc_number', 'fecha']],
        ],
        'solicitud_checklist' => [
            ['label' => 'Unique solicitud + etapa', 'columns' => ['solicitud_id', 'etapa_slug']],
            ['label' => 'Listado por solicitud_id', 'columns' => ['solicitud_id']],
        ],
        'solicitud_crm_detalles' => [
            ['label' => 'Detalle por solicitud_id', 'columns' => ['solicitud_id']],
            ['label' => 'Filtro por responsable_id', 'columns' => ['responsable_id']],
        ],
        'solicitud_crm_notas' => [
            ['label' => 'Notas por solicitud_id', 'columns' => ['solicitud_id']],
        ],
        'solicitud_crm_adjuntos' => [
            ['label' => 'Adjuntos por solicitud_id', 'columns' => ['solicitud_id']],
        ],
        'crm_tasks' => [
            ['label' => 'Lookup tareas solicitudes', 'columns' => ['company_id', 'source_module', 'source_ref_id']],
            ['label' => 'Cola por asignado/estado/due', 'columns' => ['company_id', 'assigned_to', 'status', 'due_at']],
        ],
        'crm_calendar_blocks' => [
            ['label' => 'Bloqueos por solicitud_id', 'columns' => ['solicitud_id']],
            ['label' => 'Rango por fecha_inicio + fecha_fin', 'columns' => ['fecha_inicio', 'fecha_fin']],
        ],
        'solicitud_mail_log' => [
            ['label' => 'Historial por solicitud_id', 'columns' => ['solicitud_id']],
            ['label' => 'Series por sent_at', 'columns' => ['sent_at']],
            ['label' => 'Series por status', 'columns' => ['status']],
        ],
    ];
}

function connect(): PDO
{
    try {
        /** @var PDO $pdo */
        $pdo = require __DIR__ . '/../config/database.php';
        return $pdo;
    } catch (Throwable $e) {
        fwrite(STDERR, "[error] No fue posible conectar a la base de datos: {$e->getMessage()}" . PHP_EOL);
        fwrite(STDERR, "        Verifica DB_HOST, DB_NAME, DB_USER y DB_PASS." . PHP_EOL);
        exit(1);
    }
}

/**
 * @return array<int,array<string,mixed>>
 */
function fetchIndexRows(PDO $pdo, string $table): array
{
    $sql = <<<SQL
        SELECT
            index_name,
            seq_in_index,
            column_name,
            non_unique
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = :table
        ORDER BY index_name, seq_in_index
    SQL;

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':table' => $table]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @param array<int,array<string,mixed>> $rows
 * @return array<string,array{columns:array<int,string>,unique:bool}>
 */
function buildIndexMap(array $rows): array
{
    $map = [];
    foreach ($rows as $row) {
        $name = (string) ($row['index_name'] ?? '');
        $column = (string) ($row['column_name'] ?? '');
        if ($name === '' || $column === '') {
            continue;
        }
        if (!isset($map[$name])) {
            $map[$name] = [
                'columns' => [],
                'unique' => ((int) ($row['non_unique'] ?? 1)) === 0,
            ];
        }
        $map[$name]['columns'][] = $column;
    }
    return $map;
}

/**
 * @param array<int,string> $indexColumns
 * @param array<int,string> $expectedColumns
 */
function hasLeadingCoverage(array $indexColumns, array $expectedColumns): bool
{
    if (count($indexColumns) < count($expectedColumns)) {
        return false;
    }

    foreach ($expectedColumns as $position => $column) {
        if (!isset($indexColumns[$position]) || strcasecmp($indexColumns[$position], $column) !== 0) {
            return false;
        }
    }

    return true;
}

/**
 * @param array<string,array{columns:array<int,string>,unique:bool}> $indexMap
 * @param array<int,string> $expectedColumns
 * @return array<int,string>
 */
function resolveCoverage(array $indexMap, array $expectedColumns): array
{
    $coveredBy = [];
    foreach ($indexMap as $name => $meta) {
        if (hasLeadingCoverage($meta['columns'], $expectedColumns)) {
            $coveredBy[] = $name . ' (' . implode(',', $meta['columns']) . ')';
        }
    }
    return $coveredBy;
}

$checks = getSolicitudesIndexChecks();
if ($tableFilter !== []) {
    $checks = array_filter(
        $checks,
        static fn(string $table): bool => in_array($table, $tableFilter, true),
        ARRAY_FILTER_USE_KEY
    );
}

if ($checks === []) {
    fwrite(STDERR, "[error] No hay tablas para auditar con el filtro actual.\n");
    exit(1);
}

$pdo = connect();
$missing = 0;

echo "Solicitudes Index Audit\n";
echo "=======================\n";

foreach ($checks as $table => $tableChecks) {
    echo PHP_EOL . "Table: {$table}\n";
    $rows = fetchIndexRows($pdo, $table);
    if ($rows === []) {
        echo "  [MISS] tabla sin índices visibles o no existe.\n";
        $missing += count($tableChecks);
        continue;
    }

    $indexMap = buildIndexMap($rows);
    $indexNames = array_keys($indexMap);
    sort($indexNames);
    echo "  Indexes: " . implode(', ', $indexNames) . PHP_EOL;

    foreach ($tableChecks as $check) {
        $label = (string) ($check['label'] ?? '');
        $expected = array_values(array_map('strval', (array) ($check['columns'] ?? [])));
        if ($expected === []) {
            continue;
        }

        $coveredBy = resolveCoverage($indexMap, $expected);
        if ($coveredBy !== []) {
            echo "  [OK]   {$label}: " . implode(' | ', $coveredBy) . PHP_EOL;
            continue;
        }

        $missing++;
        echo "  [MISS] {$label}: expected prefix (" . implode(', ', $expected) . ")\n";
    }
}

echo PHP_EOL . "Summary: missing_checks={$missing}\n";

if ($strict && $missing > 0) {
    exit(2);
}

exit(0);
